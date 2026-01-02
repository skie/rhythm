<?php
declare(strict_types=1);

/**
 * Rhythm Plugin Configuration
 *
 * Configuration for the Rhythm performance monitoring plugin.
 */

return [
    'Rhythm' => [
        'enabled' => env('RHYTHM_ENABLED', true),

        'storage' => [
            'driver' => env('RHYTHM_STORAGE_DRIVER', 'database'),
            'database' => [
                'connection' => 'default',
                'tables' => [
                    'entries' => 'rhythm_entries',
                    'aggregates' => 'rhythm_aggregates',
                ],
            ],
            'cache' => [
                'config' => 'default',
                'prefix' => 'rhythm:',
            ],
        ],

        'recorders' => [
            'servers' => [
                'className' => \Rhythm\Recorder\ServersRecorder::class,
                'enabled' => env('RHYTHM_SERVER_ENABLED', true),
                'server_name' => env('RHYTHM_SERVER_NAME', gethostname()),
                'directories' => explode(':', env('RHYTHM_SERVER_DIRECTORIES', '/')),
                'ignore' => [
                    '/^\/tmp/',
                    '/^\/var\/log/',
                    '/^\/proc/',
                    '/^\/sys/',
                ],
            ],
            'user_requests' => [
                'className' => \Rhythm\Recorder\UserRequestsRecorder::class,
                'enabled' => env('RHYTHM_USER_REQUESTS_ENABLED', true),
                'sample_rate' => env('RHYTHM_USER_REQUESTS_SAMPLE_RATE', 1.0),
                'ignore' => [
                    '#^/rhythm#',
                    '#^/health#',
                ],
            ],
            'cache' => [
                'className' => \Rhythm\Recorder\CacheRecorder::class,
                'enabled' => env('RHYTHM_CACHE_ENABLED', true),
                'sample_rate' => env('RHYTHM_CACHE_SAMPLE_RATE', 1.0),
                'groups' => [
                    // Optional: Normalize cache keys (like Reverb)
                    // '/users:\d+:profile/' => 'users:{user}:profile',
                ],
                'ignore' => [
                    // '/cake_model_/',
                    '/cake_core_/',
                    '/^rhythm-widget/',
                    '/^cake_rhythm/',
                    '/^rhythm/',
                ],
            ],
            'slow_queries' => [
                'className' => \Rhythm\Recorder\SlowQueriesRecorder::class,
                'enabled' => env('RHYTHM_SLOW_QUERIES_ENABLED', true),
                'threshold' => [
                    'default' => env('RHYTHM_SLOW_QUERIES_THRESHOLD', 10),
                    '/SELECT.*FROM users/' => 500,
                    '/UPDATE.*SET/' => 20,
                    '/DELETE FROM/' => 15,
                    '/INSERT INTO/' => 12,
                ],
                'sample_rate' => env('RHYTHM_SLOW_QUERIES_SAMPLE_RATE', 0.5),
                'max_query_length' => env('RHYTHM_SLOW_QUERIES_MAX_LENGTH', 1000),
                'location' => env('RHYTHM_SLOW_QUERIES_LOCATION', true),
                'ignore' => [
                    '/(["`])rhythm_[\w]+?\1/',
                    '/(["`])sessions[\w]*?\1/',
                    '/(["`])cache_[\w]+?\1/',
                ],
            ],
            'slow_requests' => [
                'className' => \Rhythm\Recorder\SlowRequestsRecorder::class,
                'enabled' => env('RHYTHM_SLOW_REQUESTS_ENABLED', true),
                'threshold' => [
                    'default' => env('RHYTHM_SLOW_REQUESTS_THRESHOLD', 10),
                    '/^\/api\//' => 500,
                    '/^\/admin\//' => 2000,
                    '/^\/rhythm\//' => 2000,
                ],
                'sample_rate' => env('RHYTHM_SLOW_REQUESTS_SAMPLE_RATE', 0.1),
                'ignore' => [
                    '#^/rhythm#',
                    '#^/health#',
                    '#^/api/docs#',
                ],
            ],
            'slow_outgoing_requests' => [
                'className' => \Rhythm\Recorder\OutgoingRequestRecorder::class,
                'enabled' => env('RHYTHM_SLOW_OUTGOING_REQUESTS_ENABLED', true),
                'threshold' => [
                    'default' => 1000,
                    '/^https?:\/\/api\./' => 500,
                    // '/^https?:\/\/external\./' => 2000,
                    // '/^https?:\/\/database\./' => 1500,
                ],
                'ignore' => [
                    '#^http://127\.0\.0\.1#',
                    '#^http://localhost#',
                    '#^https?://([^/]*\.)?internal\.#',
                ],
                'groups' => [
                    '#^(https?://)api\.([^/]+)\.com/([^/]+)/(\d+)#' => '\1api.\2.com/\3/\4', // Group API endpoints with IDs
                    '#^(https?://)([^/]+)\.api\.([^/]+)\.com/([^/]+)#' => '\1\2.api.\3.com/\4', // Group subdomain APIs
                    '#^(https?://)([^/]+)\.com/api/v(\d+)/([^/]+)#' => '\1\2.com/api/v\3/\4', // Group versioned APIs
                    '#^(https?://)([^/]+)\.com/([^/]+)/(\d+)#' => '\1\2.com/\3/\4', // Group endpoints with IDs
                ],
            ],
            'exceptions' => [
                'className' => \Rhythm\Recorder\ExceptionsRecorder::class,
                'enabled' => env('RHYTHM_EXCEPTIONS_ENABLED', true),
                'sample_rate' => env('RHYTHM_EXCEPTIONS_SAMPLE_RATE', 0.1),
                'ignore' => [
                    // '/^Package\\\\Exceptions\\\\/',
                    // '/^Cake\\\\/',
                ],
            ],

            'queues' => [
                'className' => \Rhythm\Recorder\QueuesRecorder::class,
                'enabled' => env('RHYTHM_QUEUE_JOBS_ENABLED', true),
                'sample_rate' => env('RHYTHM_QUEUE_JOBS_SAMPLE_RATE', 0.1),
                'ignore' => [
                    '/^Package\\\\Jobs\\\\/',
                    '/^Cake\\\\/',
                ],
            ],
            // 'queue_stats' => [
                // 'className' => \Rhythm\Recorder\QueueStatsRecorder::class,
                // 'enabled' => env('RHYTHM_QUEUE_STATS_ENABLED', true),
                // 'sample_rate' => env('RHYTHM_QUEUE_STATS_SAMPLE_RATE', 0.99),
                // 'ignore' => [
                    // '/^system:/',
                    // '/^internal:/',
                    // '/^test:/',
                // ],
                // 'redis' => [
                    // 'host' => env('REDIS_HOST', '127.0.0.1'),
                    // 'port' => env('REDIS_PORT', 6379),
                    // 'password' => env('REDIS_PASSWORD', null),
                    // 'database' => env('REDIS_DATABASE', 0),
                    // 'prefix' => env('REDIS_PREFIX', ''),
                // ],
            // ],
            'slow_jobs' => [
                'className' => \Rhythm\Recorder\SlowJobsRecorder::class,
                'enabled' => env('RHYTHM_SLOW_JOBS_ENABLED', true),
                'threshold' => [
                    'default' => env('RHYTHM_SLOW_JOBS_THRESHOLD', 10), // 5 seconds default
                    // '/^TestJob/' => 1000,  // Test jobs have lower threshold
                    // '/^EmailJob/' => 3000, // Email jobs have medium threshold
                    // '/^ReportJob/' => 10000, // Report jobs have higher threshold
                ],
                'sample_rate' => env('RHYTHM_SLOW_JOBS_SAMPLE_RATE', 0.8),
                'ignore' => [
                    // '/^system:/',
                    // '/^internal:/',
                    // '/^test:/',
                ],
            ],
            'redis_monitor' => [
                'className' => \Rhythm\Recorder\RedisMonitorRecorder::class,
                'enabled' => env('RHYTHM_REDIS_MONITOR_ENABLED', true),
                'interval' => env('RHYTHM_REDIS_MONITOR_INTERVAL', 5), // minutes
                'connections' => ['default_db0'],
                'metrics' => [
                    'memory_usage' => env('RHYTHM_REDIS_MEMORY_ENABLED', true),
                    'key_statistics' => env('RHYTHM_REDIS_KEYS_ENABLED', true),
                    'removed_keys' => env('RHYTHM_REDIS_REMOVED_ENABLED', true),
                    'network_usage' => env('RHYTHM_REDIS_NETWORK_ENABLED', true),
                ],
                'redis' => [
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', 6379),
                    'password' => env('REDIS_PASSWORD', null),
                    'database' => env('REDIS_DATABASE', 0),
                    'prefix' => env('REDIS_PREFIX', ''),
                ],
            ],
            'mysql_monitor' => [
                'className' => \Rhythm\Recorder\MySqlMonitorRecorder::class,
                'enabled' => env('RHYTHM_MYSQL_MONITOR_ENABLED', true),
                'interval' => env('RHYTHM_MYSQL_MONITOR_INTERVAL', 5), // minutes
                'connections' => ['default'],
                'metrics' => [
                    'connections' => env('RHYTHM_MYSQL_CONNECTIONS_ENABLED', true),
                    'threads' => env('RHYTHM_MYSQL_THREADS_ENABLED', true),
                    'innodb' => env('RHYTHM_MYSQL_INNODB_ENABLED', true),
                    'performance' => env('RHYTHM_MYSQL_PERFORMANCE_ENABLED', true),
                ],
                'status_variables' => [
                    // Connection metrics
                    'Connections',
                    'Max_used_connections',

                    // Thread metrics
                    'Threads_connected',
                    'Threads_running',
                    'Threads_created',
                    'Threads_cached',

                    // InnoDB metrics
                    'Innodb_buffer_pool_reads',
                    'Innodb_buffer_pool_read_requests',
                    'Innodb_buffer_pool_pages_total',
                    'Innodb_buffer_pool_pages_free',
                    'Innodb_buffer_pool_pages_data',
                    'Innodb_buffer_pool_pages_dirty',
                    'Innodb_buffer_pool_pages_flushed',
                    'Innodb_buffer_pool_pages_misc',
                    'Innodb_buffer_pool_read_ahead_rnd',
                    'Innodb_buffer_pool_read_ahead',
                    'Innodb_buffer_pool_read_ahead_evicted',
                    'Innodb_buffer_pool_wait_free',
                    'Innodb_log_waits',
                    'Innodb_log_write_requests',
                    'Innodb_log_writes',
                    'Innodb_os_log_fsyncs',
                    'Innodb_os_log_pending_fsyncs',
                    'Innodb_os_log_pending_writes',
                    'Innodb_os_log_written',
                    'Innodb_row_lock_current_waits',
                    'Innodb_row_lock_time',
                    'Innodb_row_lock_time_avg',
                    'Innodb_row_lock_time_max',
                    'Innodb_row_lock_waits',
                    'Innodb_rows_deleted',
                    'Innodb_rows_inserted',
                    'Innodb_rows_read',
                    'Innodb_rows_updated',

                    // Performance metrics
                    'Questions',
                    'Slow_queries',
                    'Com_select',
                    'Com_insert',
                    'Com_update',
                    'Com_delete',
                ],
                'aggregates' => [
                    'max' => [
                        'Connections',
                        'Max_used_connections',
                        'Threads_connected',
                        'Threads_running',
                        'Threads_created',
                        'Threads_cached',
                    ],
                    'avg' => [
                        'Innodb_buffer_pool_reads',
                        'Innodb_buffer_pool_read_requests',
                        'Innodb_buffer_pool_pages_total',
                        'Innodb_buffer_pool_pages_free',
                        'Innodb_buffer_pool_pages_data',
                        'Innodb_buffer_pool_pages_dirty',
                        'Innodb_buffer_pool_pages_flushed',
                        'Innodb_buffer_pool_pages_misc',
                        'Innodb_row_lock_time_avg',
                        'Innodb_row_lock_time_max',
                    ],
                    'count' => [
                        'Questions',
                        'Slow_queries',
                        'Com_select',
                        'Com_insert',
                        'Com_update',
                        'Com_delete',
                        'Innodb_rows_deleted',
                        'Innodb_rows_inserted',
                        'Innodb_rows_read',
                        'Innodb_rows_updated',
                    ],
                ],
            ],
            'git' => [
                'className' => \Rhythm\Recorder\GitRecorder::class,
                'enabled' => env('RHYTHM_GIT_ENABLED', true),
            ],
            'postgresql_monitor' => [
                'className' => \Rhythm\Recorder\PostgreSqlMonitorRecorder::class,
                'enabled' => env('RHYTHM_POSTGRESQL_MONITOR_ENABLED', true),
                'interval' => env('RHYTHM_POSTGRESQL_MONITOR_INTERVAL', 5), // minutes
                'connections' => ['default'],
                'metrics' => [
                    'connections' => env('RHYTHM_POSTGRESQL_CONNECTIONS_ENABLED', true),
                    'transactions' => env('RHYTHM_POSTGRESQL_TRANSACTIONS_ENABLED', true),
                    'performance' => env('RHYTHM_POSTGRESQL_PERFORMANCE_ENABLED', true),
                    'background_writer' => env('RHYTHM_POSTGRESQL_BACKGROUND_WRITER_ENABLED', true),
                ],
                'aggregates' => [
                    'max' => [
                        'active_connections',
                        'total_connections',
                        'idle_connections',
                        'idle_in_transaction_connections',
                        'temp_files',
                        'deadlocks',
                    ],
                    'avg' => [
                        'block_read_time',
                        'block_write_time',
                        'checkpoint_write_time',
                        'checkpoint_sync_time',
                    ],
                    'count' => [
                        'transactions_committed',
                        'transactions_rollback',
                        'blocks_read',
                        'blocks_hit',
                        'tuples_returned',
                        'tuples_fetched',
                        'tuples_inserted',
                        'tuples_updated',
                        'tuples_deleted',
                        'temp_bytes',
                        'checkpoints_timed',
                        'checkpoints_req',
                        'buffers_checkpoint',
                        'buffers_clean',
                        'maxwritten_clean',
                        'buffers_backend',
                        'buffers_backend_fsync',
                        'buffers_alloc',
                    ],
                ],
            ],
        ],

        'aggregation' => [
            'periods' => [60, 360, 1440, 10080], // 1h, 6h, 24h, 7d in minutes
            'trim' => [
                'keep' => '7 days',
            ],
        ],

        'buffer' => env('RHYTHM_BUFFER', 10), // Number of entries before auto-ingest

        'ingest' => [
            'driver' => env('RHYTHM_INGEST_DRIVER', 'redis'),
            'redis' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', null),
                'database' => env('REDIS_DATABASE', 0),
                'queue_key' => 'rhythm:metrics:queue:' . env('APP_NAME', 'default'),
                'processing_key' => 'rhythm:metrics:processing:' . env('APP_NAME', 'default'),
                'batch_size' => env('RHYTHM_INGEST_BATCH_SIZE', 500),
                'trim' => [
                    'keep' => '1 hour',
                ],
            ],
        ],

        'check' => [
            'interval' => env('RHYTHM_CHECK_INTERVAL', 1), // seconds
        ],

        'widgets' => [
            'cache' => [
                'className' => \Rhythm\Widget\CacheWidget::class,
                'name' => 'Cache',
                'cols' => ['default' => 12, 'lg' => 4],
                'refreshInterval' => 60,
            ],
            'server-state' => [
                'className' => \Rhythm\Widget\ServerStateWidget::class,
                'name' => 'Server State',
                'cols' => ['default' => 12, 'lg' => 12],
                'rows' => 1,
                'refreshInterval' => 30,
            ],
            'usage' => [
                'className' => \Rhythm\Widget\UsageWidget::class,
                'name' => 'Usage',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 30,
            ],
            'queues' => [
                'className' => \Rhythm\Widget\QueuesWidget::class,
                'name' => 'Queues',
                'cols' => ['default' => 12, 'lg' => 4],
                'refreshInterval' => 30,
                'colors' => [
                    'activity' => [
                        'queues.queued' => 'rgba(107,114,128,0.5)',
                        'queues.processing' => 'rgba(147,51,234,0.5)',
                        'queues.processed' => '#9333ea',
                        'queues.released' => '#eab308',
                        'queues.failed' => '#e11d48',
                    ],
                    'statistics' => [
                        'queue_depth' => '#3b82f6',
                        'queue_health' => '#10b981',
                        'queue_maximum_wait_time' => '#ef4444',
                    ],
                ],
                'labels' => [
                    'activity' => [
                        'queues.queued' => 'Queued',
                        'queues.processing' => 'Processing',
                        'queues.processed' => 'Processed',
                        'queues.released' => 'Released',
                        'queues.failed' => 'Failed',
                    ],
                    'statistics' => [
                        'queue_depth' => 'Depth',
                        'queue_health' => 'Health',
                        'queue_maximum_wait_time' => 'Max Wait',
                    ],
                ],
            ],
            'slow-queries' => [
                'className' => \Rhythm\Widget\SlowQueriesWidget::class,
                'name' => 'Slow Queries',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 60,
            ],
            'slow-requests' => [
                'className' => \Rhythm\Widget\SlowRequestsWidget::class,
                'name' => 'Slow Requests',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 5,
            ],
            'exceptions' => [
                'className' => \Rhythm\Widget\ExceptionsWidget::class,
                'name' => 'Exceptions',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 5,
            ],
            'slow-jobs' => [
                'className' => \Rhythm\Widget\SlowJobsWidget::class,
                'name' => 'Slow Jobs',
                'cols' => ['default' => 12, 'lg' => 12],
                'refreshInterval' => 5,
            ],
            'git' => [
                'className' => \Rhythm\Widget\GitWidget::class,
                'name' => 'Git Status',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 60,
                'commit_count' => 5,
            ],
            'app_info' => [
                'className' => \Rhythm\Widget\AppInfoWidget::class,
                'name' => 'Application Info',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 300,
            ],
            'redis_monitor' => [
                'className' => \Rhythm\Widget\RedisWidget::class,
                'name' => 'Redis Status',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 60,
                'period' => 60,
                'connections' => ['default'],
                'metrics' => [
                    'memory_usage' => true,
                    'key_statistics' => true,
                    'removed_keys' => true,
                    'network_usage' => true,
                ],
                'colors' => [
                    'memory' => [
                        'redis_used_memory' => '#10b981',
                        'redis_max_memory' => '#9333ea',
                    ],
                    'active_keys' => [
                        'redis_keys_total' => '#3b82f6',
                        'redis_keys_with_expiration' => '#f59e0b',
                    ],
                    'removed_keys' => [
                        'redis_expired_keys' => '#ef4444',
                        'redis_evicted_keys' => '#8b5cf6',
                    ],
                    'ttl' => [
                        'redis_avg_ttl' => '#06b6d4',
                    ],
                    'network' => [
                        'redis_network_usage' => '#84cc16',
                    ],
                ],
                'labels' => [
                    'memory' => [
                        'redis_used_memory' => 'Used Memory',
                        'redis_max_memory' => 'Max Memory',
                    ],
                    'active_keys' => [
                        'redis_keys_total' => 'Total Keys',
                        'redis_keys_with_expiration' => 'Keys with TTL',
                    ],
                    'removed_keys' => [
                        'redis_expired_keys' => 'Expired Keys',
                        'redis_evicted_keys' => 'Evicted Keys',
                    ],
                    'ttl' => [
                        'redis_avg_ttl' => 'Average TTL',
                    ],
                    'network' => [
                        'redis_network_usage' => 'Network I/O',
                    ],
                ],
            ],
            'database_monitor' => [
                'className' => \Rhythm\Widget\DatabaseWidget::class,
                'name' => 'Database Status',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 60,
                'period' => 60,
                'connections' => ['default'],
            ],
            'slow-outgoing-requests' => [
                'className' => \Rhythm\Widget\SlowOutgoingRequestsWidget::class,
                'name' => 'Slow Outgoing Requests',
                'cols' => ['default' => 12, 'lg' => 6],
                'refreshInterval' => 60,
            ],
        ],

        'layouts' => [
            'default' => [
                'server-state' => ['cols' => ['default' => 12, 'lg' => 12]],
                'slow-queries' => ['cols' => ['default' => 12, 'lg' => 6]],
                'exceptions' => ['cols' => ['default' => 12, 'lg' => 6]],
                'slow-requests' => ['cols' => ['default' => 12, 'lg' => 6]],
                'slow-outgoing-requests' => ['cols' => ['default' => 12, 'lg' => 6]],
                'queues' => ['cols' => ['default' => 12, 'lg' => 12]],
                'slow-jobs' => ['cols' => ['default' => 12, 'lg' => 12]],
                'cache' => ['cols' => ['default' => 12, 'lg' => 6]],
            ],
            'status' => [
                'app_info' => ['cols' => ['default' => 12, 'lg' => 12]],
                'git' => ['cols' => ['default' => 12, 'lg' => 12]],
                'redis_monitor' => ['cols' => ['default' => 12, 'lg' => 12]],
                'mysql_threads' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 6],
                    'name' => 'Active threads',
                    'values' => ['Threads_connected', 'Threads_running'],
                    'graphs' => [
                        'max' => ['Threads_connected' => '#00a1ff', 'Threads_running' => '#0cdd01'],
                    ],
                ],
                'mysql_connections' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 6],
                    'name' => 'Connections',
                    'values' => ['Connections', 'Max_used_connections'],
                    'graphs' => [
                        'max' => ['Connections' => '#0031ff', 'Max_used_connections' => '#0cdd01'],
                    ],
                ],
                'mysql_innodb' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 12],
                    'name' => 'InnoDB',
                    'values' => ['Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests', 'Innodb_buffer_pool_pages_total'],
                    'graphs' => [
                        'avg' => ['Innodb_buffer_pool_reads' => '#0031ff', 'Innodb_buffer_pool_read_requests' => '#3c5d0f'],
                    ],
                ],
                'mysql_com' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 12],
                    'name' => 'Commands',
                    'values' => ['Questions', 'Slow_queries', 'Com_select', 'Com_insert', 'Com_update', 'Com_delete'],
                    'graphs' => [
                        'count' => ['Questions' => '#A3230f', 'Slow_queries' => '#1c5dff', 'Com_select' => '#3c5d4f', 'Com_insert' => '#3c0dff', 'Com_update' => '#3c5dff', 'Com_delete' => '#FF0d0f'],
                    ],
                ],
                'postgresql_connections' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 6],
                    'name' => 'PostgreSQL Connections',
                    'values' => ['total_connections', 'active_connections', 'idle_connections'],
                    'graphs' => [
                        'max' => ['total_connections' => '#0031ff', 'active_connections' => '#0cdd01', 'idle_connections' => '#f59e0b'],
                    ],
                ],
                'postgresql_transactions' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 6],
                    'name' => 'PostgreSQL Transactions',
                    'values' => ['transactions_committed', 'transactions_rollback', 'deadlocks'],
                    'graphs' => [
                        'count' => ['transactions_committed' => '#10b981', 'transactions_rollback' => '#ef4444', 'deadlocks' => '#8b5cf6'],
                    ],
                ],
                'postgresql_performance' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 12],
                    'name' => 'PostgreSQL Performance',
                    'values' => ['blocks_read', 'blocks_hit', 'tuples_returned', 'tuples_fetched', 'tuples_inserted', 'tuples_updated', 'tuples_deleted'],
                    'graphs' => [
                        'count' => ['blocks_read' => '#3b82f6', 'blocks_hit' => '#10b981', 'tuples_returned' => '#f59e0b', 'tuples_fetched' => '#ef4444', 'tuples_inserted' => '#8b5cf6', 'tuples_updated' => '#06b6d4', 'tuples_deleted' => '#84cc16'],
                    ],
                ],
                'postgresql_background_writer' => [
                    'widget' => 'database_monitor',
                    'cols' => ['default' => 12, 'lg' => 12],
                    'name' => 'PostgreSQL Background Writer',
                    'values' => ['checkpoints_timed', 'checkpoints_req', 'buffers_checkpoint', 'buffers_clean', 'buffers_backend'],
                    'graphs' => [
                        'count' => ['checkpoints_timed' => '#3b82f6', 'checkpoints_req' => '#ef4444', 'buffers_checkpoint' => '#10b981', 'buffers_clean' => '#f59e0b', 'buffers_backend' => '#8b5cf6'],
                    ],
                ],
            ],
            'compact' => [
                'server-state' => ['cols' => ['default' => 12, 'lg' => 12]],
                'usage' => ['cols' => ['default' => 12, 'lg' => 4]],
                'slow-queries' => ['cols' => ['default' => 12, 'lg' => 4]],
                'slow-requests' => ['cols' => ['default' => 12, 'lg' => 4]],
            ],
            'queues' => [
                'queues' => ['cols' => ['default' => 12, 'lg' => 12]],
            ],
        ],
    ],
];
