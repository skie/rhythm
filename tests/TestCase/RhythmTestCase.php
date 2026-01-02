<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Exception;
use Rhythm\Ingest\RedisIngest;
use Rhythm\Rhythm;
use Rhythm\RhythmEntry;
use Rhythm\RhythmValue;
use Rhythm\Storage\BaseStorage;
use Rhythm\Storage\DigestStorage;

/**
 * Rhythm Test Case
 *
 * Base test case for Rhythm plugin tests with common setup and utilities.
 */
abstract class RhythmTestCase extends TestCase
{
    /**
     * Rhythm instance for testing.
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Database storage for testing.
     *
     * @var \Rhythm\Storage\BaseStorage
     */
    protected BaseStorage $storage;

    /**
     * Redis ingest for testing (if available).
     *
     * @var \Rhythm\Ingest\RedisIngest
     */
    protected RedisIngest $redisIngest;

    /**
     * Test database connection.
     *
     * @var \Cake\Database\Connection
     */
    protected Connection $connection;

    /**
     * Mock container for testing.
     *
     * @var mixed
     */
    protected mixed $container;

    /**
     * Setup method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $conn = ConnectionManager::get('test');
        if (!$conn instanceof Connection) {
            $this->markTestSkipped('Test connection is not a Cake\Database\Connection');
        }
        $this->connection = $conn;
        $this->setupRhythmComponents();

        if ($this->shouldSetupTestData()) {
            $this->setupTestData();
        }
    }

    /**
     * Teardown method.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Setup Rhythm components for testing.
     *
     * @return void
     */
    protected function setupRhythmComponents(): void
    {
        $this->container = $this->createStub(ContainerInterface::class);

        Configure::write('Rhythm.default_aggregations', ['count', 'min', 'max', 'sum', 'avg']);

        $this->storage = new DigestStorage([
            'connection' => 'test',
            'tables' => [
                'entries' => 'rhythm_entries',
                'aggregates' => 'rhythm_aggregates',
            ],
        ]);

        if (extension_loaded('redis')) {
            try {
                $this->redisIngest = new RedisIngest($this->storage, [
                    'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
                    'port' => (int)(getenv('REDIS_PORT') ?: 6379),
                    'queue_key' => 'rhythm:test:queue',
                    'processing_key' => 'rhythm:test:processing',
                ]);

                $this->rhythm = new Rhythm($this->storage, $this->redisIngest, $this->container);
            } catch (Exception $e) {
                $this->markTestSkipped('Redis not available. ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('Redis not available');
        }
    }

    /**
     * Determine if test data should be setup.
     * Override this method in test classes that don't want the default test data.
     *
     * @return bool
     */
    protected function shouldSetupTestData(): bool
    {
        return true;
    }

    /**
     * Setup test data.
     *
     * @return void
     */
    protected function setupTestData(): void
    {
        $entries = [
            new RhythmEntry(
                timestamp: (new DateTime())->getTimestamp() - 3600,
                type: 'user_requests',
                key: 'GET /users',
                value: 150,
            ),
            new RhythmEntry(
                timestamp: (new DateTime())->getTimestamp() - 1800,
                type: 'user_requests',
                key: 'POST /users',
                value: 200,
            ),
            new RhythmEntry(
                timestamp: (new DateTime())->getTimestamp() - 900,
                type: 'slow_queries',
                key: 'SELECT * FROM users',
                value: 250,
            ),
        ];

        $this->storage->store(new Collection($entries));
    }

    /**
     * Cleanup test data.
     *
     * @return void
     */
    protected function cleanupTestData(): void
    {
        $this->connection->execute('DELETE FROM rhythm_entries');
        $this->connection->execute('DELETE FROM rhythm_aggregates');
        $this->connection->execute('DELETE FROM rhythm_values');

        $this->redisIngest->clear();
    }

    /**
     * Create a test metric entry.
     *
     * @param string $type Metric type
     * @param string $key Metric key
     * @param int|null $value Metric value
     * @param int|null $timestamp Timestamp
     * @return \Rhythm\RhythmEntry
     */
    protected function createTestEntry(string $type, string $key, ?int $value = null, ?int $timestamp = null): RhythmEntry
    {
        return new RhythmEntry(
            timestamp: $timestamp ?? (new DateTime())->getTimestamp(),
            type: $type,
            key: $key,
            value: $value,
        );
    }

    /**
     * Create a test metric value.
     *
     * @param string $type Metric type
     * @param string $key Metric key
     * @param string $value Metric value
     * @param int|null $timestamp Timestamp
     * @return \Rhythm\RhythmValue
     */
    protected function createTestValue(string $type, string $key, string $value, ?int $timestamp = null): RhythmValue
    {
        return new RhythmValue(
            timestamp: $timestamp ?? (new DateTime())->getTimestamp(),
            type: $type,
            key: $key,
            value: $value,
        );
    }

    /**
     * Assert that a metric entry exists in storage.
     *
     * @param string $type Expected type
     * @param string $key Expected key
     * @param int|null $value Expected value
     * @return void
     */
    protected function assertMetricEntryExists(string $type, string $key, ?int $value = null): void
    {
        $entriesTable = $this->getTableLocator()->get('Rhythm.RhythmEntries');

        $conditions = [
            'type' => $type,
            'key' => $key,
        ];

        if ($value !== null) {
            $conditions['value'] = $value;
        }

        $count = $entriesTable->find()->where($conditions)->count();
        $this->assertGreaterThan(0, $count, "No metric entries found for type: {$type}, key: {$key}");
    }

    /**
     * Assert that a metric value exists in storage.
     *
     * @param string $type Expected type
     * @param string $key Expected key
     * @param string $value Expected value
     * @return void
     */
    protected function assertMetricValueExists(string $type, string $key, string $value): void
    {
        $valuesTable = $this->getTableLocator()->get('Rhythm.RhythmValues');

        $count = $valuesTable->find()->where([
            'type' => $type,
            'key' => $key,
            'value' => $value,
        ])->count();

        $this->assertGreaterThan(0, $count, "No metric values found for type: {$type}, key: {$key}, value: {$value}");
    }

    /**
     * Assert that a metric aggregate exists in storage.
     *
     * @param string $type Expected type
     * @param string $key Expected key
     * @param string $aggregate Expected aggregate type (count, min, max, sum, avg)
     * @return void
     */
    protected function assertMetricAggregateExists(string $type, string $key, string $aggregate): void
    {
        $aggregatesTable = $this->getTableLocator()->get('Rhythm.RhythmAggregates');

        $count = $aggregatesTable->find()->where([
            'type' => $type,
            'key' => $key,
            'aggregate' => $aggregate,
        ])->count();

        $this->assertGreaterThan(0, $count, "No metric aggregates found for type: {$type}, key: {$key}, aggregate: {$aggregate}");
    }

    /**
     * Get test configuration.
     *
     * @return array<string, mixed>
     */
    protected function getTestConfig(): array
    {
        return Configure::read('Rhythm');
    }
}
