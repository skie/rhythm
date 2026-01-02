<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Psr\Http\Server\RequestHandlerInterface;
use Rhythm\Middleware\RhythmMiddleware;

/**
 * Integration Flow Test
 *
 * Tests integration scenarios and real-world usage patterns:
 * 1. Middleware integration
 * 2. HTTP request monitoring
 * 3. Complex metric collection patterns
 * 4. Real-world data flows
 */
class IntegrationFlowTest extends RhythmTestCase
{
    /**
     * Test middleware integration flow.
     *
     * @return void
     */
    public function testMiddlewareIntegrationFlow(): void
    {
        $this->redisIngest->clear();

        $middleware = new RhythmMiddleware($this->rhythm);

        $request = new ServerRequest([
            'url' => '/test/users',
            'method' => 'GET',
            'params' => [
                'controller' => 'Users',
                'action' => 'index',
            ],
        ]);

        /** @var \Psr\Http\Server\RequestHandlerInterface&\PHPUnit\Framework\MockObject\MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () {
                usleep(10000); // 10ms

                return new Response();
            });
        $this->assertInstanceOf(RequestHandlerInterface::class, $handler);

        $result = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $result);

        $this->rhythm->digest();
    }

    /**
     * Test complex metric collection pattern.
     *
     * @return void
     */
    public function testComplexMetricCollectionPattern(): void
    {
        $this->simulateUserSession();
        $this->simulateDatabaseOperations();
        $this->simulateExternalApiCalls();
        $this->simulateErrorConditions();

        $count = $this->rhythm->ingest();

        $this->assertGreaterThan(0, $count, 'No metrics collected from complex scenario');

        $this->rhythm->digest();

        $this->verifyUserSessionMetrics();
        $this->verifyDatabaseMetrics();
        $this->verifyApiMetrics();
        $this->verifyErrorMetrics();
    }

    /**
     * Test real-world data flow with multiple recorders.
     *
     * @return void
     */
    public function testRealWorldDataFlow(): void
    {
        $startTime = microtime(true);

        $this->rhythm->record('auth', 'login_attempt', 1);
        $this->rhythm->set('auth', 'user_id', 'user_123');

        $this->rhythm->record('db_queries', 'SELECT users', 50);
        $this->rhythm->record('db_queries', 'SELECT posts', 75);
        $this->rhythm->record('db_queries', 'UPDATE user_last_seen', 25);

        $this->rhythm->record('api_calls', 'GET /api/weather', 200);
        $this->rhythm->record('api_calls', 'POST /api/analytics', 150);

        $this->rhythm->record('cache', 'hits', 5);
        $this->rhythm->record('cache', 'misses', 2);

        $this->rhythm->record('file_ops', 'uploads', 1);
        $this->rhythm->record('file_ops', 'downloads', 3);

        $totalTime = (microtime(true) - $startTime) * 1000;
        $this->rhythm->record('performance', 'request_total_time', (int)$totalTime);

        $count = $this->rhythm->ingest();

        $this->assertGreaterThan(0, $count, 'No metrics ingested from real-world scenario');

        $this->rhythm->digest();

        $this->assertMetricEntryExists('auth', 'login_attempt', 1);
        $this->assertMetricValueExists('auth', 'user_id', 'user_123');
        $this->assertMetricEntryExists('db_queries', 'SELECT users', 50);
        $this->assertMetricEntryExists('api_calls', 'GET /api/weather', 200);
        $this->assertMetricEntryExists('cache', 'hits', 5);
        $this->assertMetricEntryExists('file_ops', 'uploads', 1);
        $this->assertMetricEntryExists('performance', 'request_total_time');
    }

    /**
     * Test metric aggregation in real-world scenario.
     *
     * @return void
     */
    public function testRealWorldAggregation(): void
    {
        $baseTime = (new DateTime())->getTimestamp() - 3600;
        $metrics = [
            'user_requests' => [100, 150, 200, 120, 180],
            'db_queries' => [50, 75, 100, 60, 90],
            'api_calls' => [20, 30, 40, 25, 35],
        ];

        foreach ($metrics as $type => $values) {
            foreach ($values as $index => $value) {
                $timestamp = $baseTime + ($index * 900);
                $this->rhythm->record($type, "key_{$index}", $value, $timestamp);
            }
        }

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $aggregationTypes = ['sum', 'avg', 'min', 'max', 'count'];

        foreach ($metrics as $type => $values) {
            foreach ($aggregationTypes as $aggType) {
                $aggregates = $this->rhythm->aggregate($type, $aggType, 3600);

                $this->assertGreaterThan(
                    0,
                    $aggregates->count(),
                    "No aggregates for {$type} with {$aggType}",
                );

                foreach ($aggregates as $aggregate) {
                    $this->assertArrayHasKey('key', $aggregate);
                    $this->assertArrayHasKey($aggType, $aggregate);
                    $this->assertIsNumeric($aggregate[$aggType]);
                    if ($aggType === 'count') {
                        $this->assertGreaterThan(0, $aggregate['count']);
                    }
                }
            }
        }
    }

    /**
     * Simulate user session metrics.
     *
     * @return void
     */
    protected function simulateUserSession(): void
    {
        $this->rhythm->record('sessions', 'active_users', 1);
        $this->rhythm->set('sessions', 'user_agent', 'Mozilla/5.0');
        $this->rhythm->record('sessions', 'session_duration', 1800);
    }

    /**
     * Simulate database operations.
     *
     * @return void
     */
    protected function simulateDatabaseOperations(): void
    {
        $this->rhythm->record('db_queries', 'SELECT * FROM users', 50);
        $this->rhythm->record('db_queries', 'INSERT INTO logs', 25);
        $this->rhythm->record('db_queries', 'UPDATE user_profile', 75);
        $this->rhythm->record('db_queries', 'DELETE FROM temp_data', 10);
    }

    /**
     * Simulate external API calls.
     *
     * @return void
     */
    protected function simulateExternalApiCalls(): void
    {
        $this->rhythm->record('api_calls', 'GET /api/weather', 200);
        $this->rhythm->record('api_calls', 'POST /api/analytics', 150);
        $this->rhythm->record('api_calls', 'PUT /api/user', 300);
    }

    /**
     * Simulate error conditions.
     *
     * @return void
     */
    protected function simulateErrorConditions(): void
    {
        $this->rhythm->record('errors', 'database_connection', 1);
        $this->rhythm->record('errors', 'api_timeout', 1);
        $this->rhythm->record('errors', 'validation_failed', 1);
    }

    /**
     * Verify user session metrics.
     *
     * @return void
     */
    protected function verifyUserSessionMetrics(): void
    {
        $this->assertMetricEntryExists('sessions', 'active_users', 1);
        $this->assertMetricValueExists('sessions', 'user_agent', 'Mozilla/5.0');
        $this->assertMetricEntryExists('sessions', 'session_duration', 1800);
    }

    /**
     * Verify database metrics.
     *
     * @return void
     */
    protected function verifyDatabaseMetrics(): void
    {
        $this->assertMetricEntryExists('db_queries', 'SELECT * FROM users', 50);
        $this->assertMetricEntryExists('db_queries', 'INSERT INTO logs', 25);
        $this->assertMetricEntryExists('db_queries', 'UPDATE user_profile', 75);
        $this->assertMetricEntryExists('db_queries', 'DELETE FROM temp_data', 10);
    }

    /**
     * Verify API metrics.
     *
     * @return void
     */
    protected function verifyApiMetrics(): void
    {
        $this->assertMetricEntryExists('api_calls', 'GET /api/weather', 200);
        $this->assertMetricEntryExists('api_calls', 'POST /api/analytics', 150);
        $this->assertMetricEntryExists('api_calls', 'PUT /api/user', 300);
    }

    /**
     * Verify error metrics.
     *
     * @return void
     */
    protected function verifyErrorMetrics(): void
    {
        $this->assertMetricEntryExists('errors', 'database_connection', 1);
        $this->assertMetricEntryExists('errors', 'api_timeout', 1);
        $this->assertMetricEntryExists('errors', 'validation_failed', 1);
    }

    /**
     * Simulate event-driven scenario.
     *
     * @return void
     */
    protected function simulateEventDrivenScenario(): void
    {
        $this->rhythm->record('events', 'user_registered', 1);
        $this->rhythm->record('events', 'order_placed', 1);
        $this->rhythm->record('events', 'payment_processed', 1);
        $this->rhythm->record('events', 'email_sent', 1);

        $this->rhythm->ingest();
    }
}
