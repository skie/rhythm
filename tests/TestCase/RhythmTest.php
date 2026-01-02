<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase;

use Cake\Core\ContainerInterface;
use Cake\TestSuite\TestCase;
use Exception;
use Rhythm\Ingest\TransparentIngest;
use Rhythm\Rhythm;
use Rhythm\RhythmEntry;
use Rhythm\RhythmValue;
use Rhythm\Storage\DigestStorage;

/**
 * Rhythm Test Case
 */
class RhythmTest extends TestCase
{
    /**
     * Rhythm instance.
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Mock container for testing.
     *
     * @var mixed
     */
    protected mixed $container;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createStub(ContainerInterface::class);

        $storage = new DigestStorage();
        $ingest = new TransparentIngest($storage);

        // if (extension_loaded('redis')) {
        //     try {
        //         $ingest = new RedisIngest($storage, [
        //             'host' => '127.0.0.1',
        //             'port' => 6379,
        //             'queue_key' => 'rhythm:test:queue',
        //             'processing_key' => 'rhythm:test:processing',
        //         ]);
        //     } catch (Exception $e) {
        //         $this->markTestSkipped('Redis not available. ' . $e->getMessage());
        //     }
        // } else {
        //     $this->markTestSkipped('Redis not available');
        // }

        $this->rhythm = new Rhythm($storage, $ingest, $this->container);
    }

    /**
     * Test recording a metric
     *
     * @return void
     */
    public function testRecord(): void
    {
        $entry = $this->rhythm->record('request', 'user:123', 100);

        $this->assertInstanceOf(RhythmEntry::class, $entry);
        $this->assertEquals('request', $entry->type);
        $this->assertEquals('user:123', $entry->key);
        $this->assertEquals(100, $entry->value);
    }

    /**
     * Test setting a metric value
     *
     * @return void
     */
    public function testSet(): void
    {
        $value = $this->rhythm->set('user', 'active:123', 'John Doe');

        $this->assertInstanceOf(RhythmValue::class, $value);
        $this->assertEquals('user', $value->type);
        $this->assertEquals('active:123', $value->key);
        $this->assertEquals('John Doe', $value->value);
    }

    /**
     * Test flushing entries
     *
     * @return void
     */
    public function testFlush(): void
    {
        $this->rhythm->record('request', 'user:123', 100);
        $this->rhythm->record('request', 'user:456', 200);

        $result = $this->rhythm->flush();

        $this->assertEquals(2, $result);
    }

    /**
     * Test configuration
     *
     * @return void
     */
    public function testConfig(): void
    {
        $buffer = $this->rhythm->getConfig('buffer', 5000);

        $this->assertEquals(5000, $buffer);
    }

    /**
     * Test start and stop recording
     *
     * @return void
     */
    public function testStartStopRecording(): void
    {
        $this->rhythm->stopRecording();

        $this->rhythm->record('test', 'key1', 100);
        $this->rhythm->set('test', 'key2', 'value');

        $this->assertEquals(0, $this->rhythm->entries()->count());

        $this->rhythm->startRecording();

        $this->rhythm->record('test', 'key3', 200);
        $this->rhythm->set('test', 'key4', 'value2');

        $this->assertEquals(2, $this->rhythm->entries()->count());
    }

    /**
     * Test ignore method for excluding operations
     *
     * @return void
     */
    public function testIgnore(): void
    {
        $this->rhythm->record('before', 'key1', 100);

        $result = $this->rhythm->ignore(function () {
            $this->rhythm->record('ignored', 'key2', 200);
            $this->rhythm->set('ignored', 'key3', 'value');

            return 'test_result';
        });

        $this->rhythm->record('after', 'key4', 300);

        $this->assertEquals('test_result', $result);

        $this->assertEquals(2, $this->rhythm->entries()->count());

        $entries = $this->rhythm->entries()->toArray();
        $this->assertCount(2, $entries);
        $this->assertEquals('before', $entries[0]->type);
        $this->assertEquals('after', $entries[1]->type);
    }

    /**
     * Test lazy method for deferred metric collection
     *
     * @return void
     */
    public function testLazy(): void
    {
        $this->rhythm->lazy(function (): void {
            $this->rhythm->record('lazy_test', 'key1', 100);
        });

        $this->assertEquals(0, $this->rhythm->entries()->count());

        $count = $this->rhythm->ingest();

        $this->assertEquals(1, $count, 'Lazy callback should have been executed during ingest');
    }

    /**
     * Test filter method for entry filtering
     *
     * @return void
     */
    public function testFilter(): void
    {
        $this->rhythm->filter(function ($entry) {
            return $entry->value === null || $entry->value > 150;
        });

        $this->rhythm->record('test', 'low', 100);
        $this->rhythm->record('test', 'high', 200);
        $this->rhythm->set('test', 'value', 'text');

        $count = $this->rhythm->ingest();

        $this->assertEquals(2, $count);
    }

    /**
     * Test rescue method for exception handling
     *
     * @return void
     */
    public function testRescue(): void
    {
        $result = $this->rhythm->rescue(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);

        $result = $this->rhythm->rescue(function (): void {
            throw new Exception('Test exception');
        });

        $this->assertNull($result);
    }

    /**
     * Test rescue with custom exception handler
     *
     * @return void
     */
    public function testRescueWithCustomHandler(): void
    {
        $handledException = null;

        $this->rhythm->handleExceptionsUsing(function ($exception) use (&$handledException): void {
            $handledException = $exception;
        });

        $result = $this->rhythm->rescue(function (): void {
            throw new Exception('Custom handler test');
        });

        $this->assertNull($result);
        $this->assertNotNull($handledException);
        $this->assertInstanceOf(Exception::class, $handledException);
        /** @var Exception $handledException */
        $this->assertEquals('Custom handler test', $handledException->getMessage());
    }

    /**
     * Test multiple filters working together
     *
     * @return void
     */
    public function testMultipleFilters(): void
    {
        $this->rhythm->filter(function ($entry) {
            return $entry->type === 'test';
        });

        $this->rhythm->filter(function ($entry) {
            return $entry->value === null || $entry->value > 100;
        });

        $this->rhythm->record('test', 'key1', 150);
        $this->rhythm->record('test', 'key2', 50);
        $this->rhythm->record('other', 'key3', 200);
        $this->rhythm->set('test', 'key4', 'value');

        $count = $this->rhythm->ingest();

        $this->assertEquals(2, $count);
    }

    /**
     * Test that fluent API doesn't immediately add to buffer
     *
     * @return void
     */
    public function testFluentApiBufferManagement(): void
    {
        $entry = $this->rhythm->record('test', 'key1', 100);

        $this->assertEquals(1, $this->rhythm->entries()->count());

        $entry->onlyBuckets()->count();

        $this->assertEquals(1, $this->rhythm->entries()->count());
    }

    /**
     * Test that lazy entries work with buffer size management
     *
     * @return void
     */
    public function testLazyWithBufferManagement(): void
    {
        $executed = 0;

        for ($i = 0; $i < 3; $i++) {
            $this->rhythm->lazy(function () use (&$executed, $i): void {
                $executed++;
                $this->rhythm->record('lazy_test', "key_{$i}", $i * 100);
            });
        }

        $this->assertEquals(0, $executed);

        $count = $this->rhythm->ingest();

        $this->assertEquals(3, $executed);
        $this->assertEquals(3, $count);
    }
}
