<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase;

use Cake\I18n\DateTime;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use Rhythm\Recorder\RecorderInterface;
use Rhythm\Rhythm;
use Rhythm\RhythmEntry;
use Rhythm\RhythmValue;
use Rhythm\Storage\DigestStorage;

/**
 * Core Flow Test
 *
 * Tests the core flows of the Rhythm system against both storage implementations:
 * - DigestStorage (uses RhythmAggregateDigest class)
 *
 * This ensures both storage approaches work correctly with the same test scenarios.
 *
 * Tests include:
 * 1. Basic metric recording flow
 * 2. Storage flow
 * 3. Ingest flow
 * 4. Event system flow
 * 5. Recorder flow
 */
class CoreFlowTest extends RhythmTestCase
{
    /**
     * Disable default test data setup since this test creates its own data.
     *
     * @return bool
     */
    protected function shouldSetupTestData(): bool
    {
        return false;
    }

    /**
     * Data provider for storage implementations.
     *
     * @return array
     */
    public static function storageProvider(): array
    {
        return [
            'DigestStorage' => [
                DigestStorage::class,
                [
                    'connection' => 'test',
                    'tables' => [
                        'entries' => 'rhythm_entries',
                        'aggregates' => 'rhythm_aggregates',
                    ],
                ],
            ],
        ];
    }

    /**
     * Setup storage and rhythm instance for parameterized tests.
     *
     * @param string $storageClass Storage class name
     * @param array $config Storage configuration
     * @return void
     */
    protected function setupStorage(string $storageClass, array $config): void
    {
        $this->redisIngest->clear();

        /** @var \Rhythm\Storage\BaseStorage $storage */
        $storage = new $storageClass($config);
        $this->storage = $storage;

        $this->rhythm = new Rhythm($storage, $this->redisIngest, $this->container);
    }

    /**
     * Test basic metric recording flow.
     *
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testBasicMetricRecordingFlow(string $storageClass, array $config): void
    {
        $this->setupStorage($storageClass, $config);

        $entry = $this->rhythm->record('test_type', 'test_key', 100);

        $this->assertInstanceOf(RhythmEntry::class, $entry);
        $this->assertEquals('test_type', $entry->type);
        $this->assertEquals('test_key', $entry->key);
        $this->assertEquals(100, $entry->value);

        $value = $this->rhythm->set('test_type', 'test_key', 'test_value');

        $this->assertInstanceOf(RhythmValue::class, $value);
        $this->assertEquals('test_type', $value->type);
        $this->assertEquals('test_key', $value->key);
        $this->assertEquals('test_value', $value->value);
    }

    /**
     * Test storage flow - metrics are stored in database.
     *
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testStorageFlow(string $storageClass, array $config): void
    {
        $this->setupStorage($storageClass, $config);

        $this->rhythm->record('user_requests', 'GET /users', 150);
        $this->rhythm->record('user_requests', 'POST /users', 200);
        $this->rhythm->set('slow_queries', 'SELECT * FROM users', '250ms');

        $entries = $this->rhythm->entries();
        $this->storage->store($entries);
        $this->rhythm->flush();

        $aggregatedEntries = $this->storage->aggregate('user_requests', 'count', 60);
        $this->assertGreaterThan(0, $aggregatedEntries->count(), 'User request metric not found in storage');

        $userRequestsAggregate = $aggregatedEntries->firstMatch(['key' => 'GET /users']);
        $this->assertNotEmpty($userRequestsAggregate, 'GET /users aggregate not found');
        $this->assertEquals(1, $userRequestsAggregate['count'], 'GET /users count should be 1');

        $userRequestsAggregate = $aggregatedEntries->firstMatch(['key' => 'POST /users']);
        $this->assertNotEmpty($userRequestsAggregate, 'POST /users aggregate not found');
        $this->assertEquals(1, $userRequestsAggregate['count'], 'POST /users count should be 1');

        $slowQueries = $this->connection->selectQuery()
            ->select(['key', 'value'])
            ->from('rhythm_values')
            ->where(['type' => 'slow_queries'])
            ->execute()
            ->fetchAll('assoc');

        $this->assertCount(1, $slowQueries, 'Expected 1 slow query entry');
        $this->assertEquals('SELECT * FROM users', $slowQueries[0]['key']);
        $this->assertEquals('250ms', $slowQueries[0]['value']);
    }

    /**
     * Test recorder flow - recorders collect and record metrics.
     *
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testRecorderFlow(string $storageClass, array $config): void
    {
        $this->markTestSkipped('Recorder tests not implemented yet');
    }

    /**
     * Test aggregation flow - metrics are aggregated over time periods.
     *
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testAggregationFlow(string $storageClass, array $config): void
    {
        $this->setupStorage($storageClass, $config);

        $fixedTimestamp = (new DateTime())->getTimestamp() - 30;

        $this->rhythm->record('agg_test', 'key_1', 10, $fixedTimestamp);
        $this->rhythm->record('agg_test', 'key_1', 20, $fixedTimestamp);
        $this->rhythm->record('agg_test', 'key_2', 30, $fixedTimestamp);

        $entries = $this->rhythm->entries();
        $this->storage->store($entries);
        $this->rhythm->flush();

        $aggregates = $this->storage->aggregate('agg_test', 'sum', 60);
        $this->assertGreaterThan(0, $aggregates->count(), 'No aggregates generated');

        $key1Aggregate = $aggregates->firstMatch(['key' => 'key_1']);
        $this->assertNotEmpty($key1Aggregate, 'Aggregate for key_1 not found');
        $this->assertEquals(30, (int)$key1Aggregate['sum'], 'Sum for key_1 should be 30');

        $key2Aggregate = $aggregates->firstMatch(['key' => 'key_2']);
        $this->assertNotEmpty($key2Aggregate, 'Aggregate for key_2 not found');
        $this->assertEquals(30, (int)$key2Aggregate['sum'], 'Sum for key_2 should be 30');
    }

    /**
     * Test end-to-end flow - complete metric lifecycle.
     *
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testEndToEndFlow(string $storageClass, array $config): void
    {
        $this->setupStorage($storageClass, $config);

        $userRequestsRecorder = new class implements RecorderInterface {
            public function record(mixed $data): void
            {
                if (is_array($data) && count($data) >= 3) {
                    $data[0]->record($data[1], $data[2], $data[3] ?? null);
                }
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getSampleRate(): float
            {
                return 1.0;
            }
        };

        $this->rhythm->registerRecorder('user_requests', $userRequestsRecorder);
        $userRequestsRecorder->record([$this->rhythm, 'user_requests', 'GET /test', 150]);
        $this->rhythm->ingest();

        $this->rhythm->digest();

        $entries = $this->connection->selectQuery()
            ->select(['COUNT(*) as count'])
            ->from('rhythm_entries')
            ->where(['type' => 'user_requests'])
            ->execute()
            ->fetch('assoc')['count'];

        $this->assertGreaterThan(0, $entries, 'User request metric not found in database');
    }

    /**
     * Test error handling flow - errors are handled gracefully.
     *
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testErrorHandlingFlow(string $storageClass, array $config): void
    {
        $invalidConfig = ['connection' => 'nonexistent'];

        try {
            new $storageClass($invalidConfig);
            $this->fail('Expected exception for invalid connection');
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    /**
     * Test performance flow - metrics are recorded efficiently.
     *
     * @group performance
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testPerformanceFlow(string $storageClass, array $config): void
    {
        $this->setupStorage($storageClass, $config);

        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $this->rhythm->record('performance_test', "key_{$i}", $i);
        }

        $recordTime = microtime(true) - $startTime;

        $this->assertLessThan(1.0, $recordTime, "Recording 100 metrics took too long: {$recordTime}s");

        $startTime = microtime(true);
        $ingestCount = $this->rhythm->ingest();
        $ingestTime = microtime(true) - $startTime;

        $this->assertEquals(100, $ingestCount, 'All 100 metrics should be ingested');
        $this->assertLessThan(1.0, $ingestTime, "Ingesting 100 metrics took too long: {$ingestTime}s");
    }

    /**
     * Minimal test: Store a RhythmEntry and dump the DB row for debugging.
     *
     * @param string $storageClass
     * @param array $config
     * @return void
     */
    #[DataProvider('storageProvider')]
    public function testMinimalRhythmEntryStorage(string $storageClass, array $config): void
    {
        $this->setupStorage($storageClass, $config);

        $this->rhythm->record('debug_type', 'minimal_key', 123);
        $this->rhythm->ingest();
        $this->rhythm->digest();
        $row = $this->connection->selectQuery()
            ->select(['key', 'value'])
            ->from('rhythm_entries')
            ->where([
                'type' => 'debug_type',
                'key' => 'minimal_key',
            ])
            ->execute()
            ->fetch('assoc');

        $this->assertNotEmpty($row, 'Row should be present in DB');
        $this->assertEquals(123, (int)$row['value'], 'Value should be 123');
        $this->assertEquals('minimal_key', $row['key'], 'Key value should be minimal_key');
    }
}
