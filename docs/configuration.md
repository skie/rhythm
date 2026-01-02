# Rhythm Configuration

- [General Configuration](#general-configuration)
- [Storage Configuration](#storage-configuration)
- [Aggregation Configuration](#aggregation-configuration)
- [Ingest Configuration](#ingest-configuration)
- [Common Recorder Settings](#common-recorder-settings)
    - [Groups Settings](#groups-settings)
    - [Ignore Settings](#ignore-settings)
    - [Sampling Settings](#sampling-settings)
    - [Thresholds Settings](#thresholds-settings)
- [Recorders Configuration](#recorders-configuration)
    - [Servers Recorder](#servers-recorder)
    - [User Requests Recorder](#user-requests-recorder)
    - [Slow Queries Recorder](#slow-queries-recorder)
    - [Slow Requests Recorder](#slow-requests-recorder)
    - [Slow Outgoing Requests Recorder](#slow-outgoing-requests-recorder)
    - [Exceptions Recorder](#exceptions-recorder)
    - [Queues Recorder](#queues-recorder)
    - [Queue Stats Recorder](#queue-stats-recorder)
    - [Slow Jobs Recorder](#slow-jobs-recorder)
    - [Redis Monitor Recorder](#redis-monitor-recorder)
    - [MySQL Monitor Recorder](#mysql-monitor-recorder)
    - [PostgreSQL Monitor Recorder](#postgresql-monitor-recorder)
- [Widgets Configuration](#widgets-configuration)
    - [Server State Widget](#server-state-widget)
    - [Usage Widget](#usage-widget)
    - [Queues Widget](#queues-widget)
    - [Slow Queries Widget](#slow-queries-widget)
    - [Slow Requests Widget](#slow-requests-widget)
    - [Exceptions Widget](#exceptions-widget)
    - [Slow Jobs Widget](#slow-jobs-widget)
    - [Git Widget](#git-widget)
    - [App Info Widget](#app-info-widget)
    - [Redis Monitor Widget](#redis-monitor-widget)
    - [Database Monitor Widget](#database-monitor-widget)
    - [Slow Outgoing Requests Widget](#slow-outgoing-requests-widget)
- [Layouts Configuration](#layouts-configuration)

## General Configuration

The main Rhythm configuration is located in `config/rhythm.php` and contains all settings for the Rhythm performance monitoring plugin.

### Basic Settings

```php
'Rhythm' => [
    'enabled' => env('RHYTHM_ENABLED', true),
    'buffer' => env('RHYTHM_BUFFER', 1000),
]
```

#### `enabled`
- **Environment Variable**: `RHYTHM_ENABLED`
- **Description**: Enables or disables the entire Rhythm plugin. When disabled, no metrics will be captured or displayed.

#### `buffer`
- **Environment Variable**: `RHYTHM_BUFFER`
- **Description**: Number of entries to buffer before automatically ingesting them into storage. Lower values provide more real-time data but may impact performance.

### Check Settings

Controls the frequency of the Rhythm check command that collects system metrics and processes buffered data.

```php
'check' => [
    'interval' => env('RHYTHM_CHECK_INTERVAL', 1), // seconds
],
```

#### `interval`
- **Environment Variable**: `RHYTHM_CHECK_INTERVAL`
- **Description**: How often the Rhythm check command should run in seconds. This controls the frequency of system metric collection and data processing. Lower values provide more real-time data but increase system load.

## Storage Configuration

Rhythm stores all collected metrics in a database for analysis and visualization. The storage configuration controls how data is organized and retained.

### Storage Settings

```php
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
```

#### `driver`
- **Environment Variable**: `RHYTHM_STORAGE_DRIVER`
- **Description**: The storage driver to use for storing Rhythm metrics. Currently supports 'database' for storing in database tables.

#### `database.connection`
- **Description**: The database connection to use for storing Rhythm metrics. Uses the same connection configuration as your main application.

#### `database.tables`
- **Description**: Configuration for Rhythm database tables. Defines table names for raw entries and aggregated data.

#### `cache.config`
- **Description**: The cache configuration to use for temporary storage and caching of Rhythm data.

#### `cache.prefix`
- **Description**: Prefix for all Rhythm cache keys. This helps separate Rhythm cache data from your application cache.

## Aggregation Configuration

Aggregation settings control how Rhythm processes and summarizes raw metrics data for efficient storage and fast dashboard queries.

### Aggregation Settings

```php
'aggregation' => [
    'periods' => [60, 360, 1440, 10080], // 1h, 6h, 24h, 7d in minutes
    'trim' => [
        'keep' => '7 days',
    ],
],
```

#### `periods`
- **Description**: Time periods in minutes for aggregating metrics. These define the granularity of aggregated data:
  - `60` minutes (1 hour)
  - `360` minutes (6 hours)
  - `1440` minutes (24 hours)
  - `10080` minutes (7 days)

#### `trim.keep`
- **Description**: How long to keep aggregated data before automatic cleanup. Older aggregated data will be permanently deleted.

## Ingest Configuration

The ingest system processes buffered metrics and stores them in the database. Ingest settings control how data flows from recorders to storage.

### Ingest Settings

```php
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
```

#### `driver`
- **Environment Variable**: `RHYTHM_INGEST_DRIVER`
- **Description**: The ingest driver to use for processing metrics. Currently supports 'redis' for using Redis as a queue.

#### `redis.host`
- **Environment Variable**: `REDIS_HOST`
- **Description**: Redis server hostname for the ingest queue.

#### `redis.port`
- **Environment Variable**: `REDIS_PORT`
- **Description**: Redis server port for the ingest queue.

#### `redis.password`
- **Environment Variable**: `REDIS_PASSWORD`
- **Description**: Redis server password for authentication.

#### `redis.database`
- **Environment Variable**: `REDIS_DATABASE`
- **Description**: Redis database number to use for the ingest queue.

#### `redis.queue_key`
- **Description**: Redis key for the metrics queue. Uses the application name to separate queues between different applications.

#### `redis.processing_key`
- **Description**: Redis key for tracking processing status. Uses the application name to separate processing data between different applications.

#### `redis.batch_size`
- **Environment Variable**: `RHYTHM_INGEST_BATCH_SIZE`
- **Description**: Number of metrics to process in each ingest batch. Larger batches are more efficient but may cause longer processing delays.

#### `redis.trim.keep`
- **Description**: How long to keep data in Redis before automatic cleanup. This prevents Redis from accumulating too much data.

## Common Recorder Settings

Many recorders share common configuration options for grouping, ignoring, and sampling data. These settings help control what data is captured and how it's organized.

### Groups Settings

The `groups` setting allows you to normalize or group similar values using regular expressions. This is useful for reducing noise in your metrics by grouping similar entries together.

```php
'groups' => [
    '#^/api/v1/users/(\d+)#' => '/api/v1/users/*', // Group user IDs
    '#^/admin/(.+)#' => '/admin/*', // Group admin routes
    '#^https://api\.github\.com/repos/.*$#' => 'api.github.com/repos/*', // Group GitHub API calls
    '#^https?://([^/]*).*$#' => '\1', // Group by domain only
    '#/\d+#' => '/*', // Remove numeric IDs from paths
],
```

**Usage**: When a value matches a pattern, it will be replaced with the corresponding replacement. The first pattern that matches will be used. If no patterns match, the original value is preserved.

**Common Use Cases**:
- **URL Normalization**: Group similar API endpoints by removing unique IDs
- **Domain Grouping**: Group all requests to the same domain
- **Path Simplification**: Remove dynamic parts from URLs to group similar routes

### Ignore Settings

The `ignore` setting allows you to exclude specific values from being recorded based on regular expression patterns.

```php
'ignore' => [
    '#^/admin#', // Ignore admin routes
    '/^system:/', // Ignore system messages
    '#^/rhythm#', // Ignore Rhythm's own routes
    '#^/health#', // Ignore health check endpoints
    '/(["`])rhythm_[\w]+?\1/', // Ignore Rhythm database tables
],
```

**Usage**: If any pattern matches the value, the entire entry will be ignored and not recorded. This is useful for excluding noise, internal routes, or system-generated data.

**Common Use Cases**:
- **Internal Routes**: Exclude admin panels, health checks, or monitoring endpoints
- **System Data**: Ignore system-generated messages or internal operations
- **Noise Reduction**: Filter out high-frequency, low-value events

### Sampling Settings

The `sample_rate` setting controls what percentage of events should be recorded. This is useful for high-traffic applications where recording every event would be too expensive.

```php
'sample_rate' => env('RHYTHM_SAMPLE_RATE', 1.0), // Record 100% of events
```

**Values**:
- `1.0` (100%): Record all events (default)
- `0.5` (50%): Record approximately half of events
- `0.1` (10%): Record approximately 10% of events
- `0.01` (1%): Record approximately 1% of events

**Usage**: Rhythm uses a lottery system to determine which events to record. In the dashboard, values will be scaled up and prefixed with a `~` to indicate they are approximations.

**When to Use Sampling**:
- **High-Traffic Applications**: When you have millions of events per day
- **Performance Critical**: When recording overhead impacts application performance
- **Cost Optimization**: When storage costs are a concern

**Note**: The more entries you have for a particular metric, the lower you can safely set the sample rate without sacrificing too much accuracy.

### Thresholds Settings

The `threshold` setting allows you to set performance thresholds for different types of events. Events that exceed these thresholds will be recorded as "slow" events.

```php
'threshold' => [
    'default' => env('RHYTHM_THRESHOLD_DEFAULT', 10), // Default threshold in milliseconds
    '/^\/api\//' => 500,  // API routes have higher threshold
    '/^\/admin\//' => 2000, // Admin routes have even higher threshold
    '/SELECT.*FROM users/' => 500,  // User queries have higher threshold
    '/^TestJob/' => 1000,  // Test jobs have lower threshold
],
```

**Usage**: Thresholds are specified in milliseconds (for requests and queries) or seconds (for jobs). The first pattern that matches will be used. If no patterns match, the `default` value will be used.

**Common Threshold Types**:
- **Request Thresholds**: HTTP request duration in milliseconds
- **Query Thresholds**: Database query duration in milliseconds
- **Job Thresholds**: Queue job duration in seconds
- **Outgoing Request Thresholds**: External API call duration in milliseconds

**Pattern Matching**: Uses regular expressions to match against:
- **Requests**: URL paths (e.g., `/^\/api\//`)
- **Queries**: SQL statements (e.g., `/SELECT.*FROM users/`)
- **Jobs**: Job class names (e.g., `/^TestJob/`)

**Examples**:
- Set higher thresholds for admin routes that are expected to be slower
- Set lower thresholds for critical API endpoints that should be fast
- Set different thresholds for different types of database queries
- Set job-specific thresholds based on expected processing time

## Recorders Configuration

Recorders capture different types of metrics from your application. Each recorder can be individually enabled or disabled and supports various configuration options.

### Servers Recorder

Monitors server system resources including CPU, memory, and storage usage. This recorder supports [sampling](#sampling-settings), [ignoring](#ignore-settings), and throttling to reduce system impact.

**Environment Variables**:
- `RHYTHM_SERVER_ENABLED` - Enable/disable server monitoring (default: `true`)
- `RHYTHM_SERVER_NAME` - Custom server name (default: `gethostname()`)
- `RHYTHM_SERVER_DIRECTORIES` - Colon-separated directories to monitor (default: `/`)

**Key Settings**:
- **Server Name**: Customize the server name (defaults to hostname)
- **Directories**: Specify which directories to monitor for disk usage
- **Ignore Patterns**: Exclude system directories like `/tmp`, `/var/log`, `/proc`, `/sys`

### User Requests Recorder

Records metrics for authenticated user requests to track user activity patterns. Supports [sampling](#sampling-settings) and [ignoring](#ignore-settings) specific routes.

**Environment Variables**:
- `RHYTHM_USER_REQUESTS_ENABLED` - Enable/disable user request tracking (default: `true`)
- `RHYTHM_USER_REQUESTS_SAMPLE_RATE` - Sample rate for user requests (default: `1.0`)

**Key Settings**:
- **Sample Rate**: Control what percentage of user requests to record
- **Ignore Patterns**: Exclude Rhythm routes (`/rhythm*`) and health check endpoints (`/health*`)

### Slow Queries Recorder

Captures database queries that exceed performance thresholds. Supports [sampling](#sampling-settings), [ignoring](#ignore-settings), and [thresholds](#thresholds-settings) for different query types.

**Environment Variables**:
- `RHYTHM_SLOW_QUERIES_ENABLED` - Enable/disable slow query tracking (default: `true`)
- `RHYTHM_SLOW_QUERIES_THRESHOLD` - Default threshold in milliseconds (default: `10`)
- `RHYTHM_SLOW_QUERIES_SAMPLE_RATE` - Sample rate for slow queries (default: `0.5`)
- `RHYTHM_SLOW_QUERIES_MAX_LENGTH` - Maximum SQL length to store (default: `1000`)
- `RHYTHM_SLOW_QUERIES_LOCATION` - Capture query location (default: `true`)

**Key Settings**:
- **Thresholds**: Set different thresholds for SELECT, UPDATE, DELETE, and INSERT queries
- **Query Length**: Limit stored SQL length to prevent storage issues
- **Location Capture**: Optionally capture where queries originate
- **Ignore Patterns**: Exclude Rhythm tables, session tables, and cache tables

### Slow Requests Recorder

Records HTTP requests that take longer than expected. Supports [sampling](#sampling-settings), [ignoring](#ignore-settings), and [thresholds](#thresholds-settings) for different route types.

**Environment Variables**:
- `RHYTHM_SLOW_REQUESTS_ENABLED` - Enable/disable slow request tracking (default: `true`)
- `RHYTHM_SLOW_REQUESTS_THRESHOLD` - Default threshold in milliseconds (default: `10`)
- `RHYTHM_SLOW_REQUESTS_SAMPLE_RATE` - Sample rate for slow requests (default: `0.1`)

**Key Settings**:
- **Thresholds**: Set different thresholds for API routes, admin routes, and Rhythm routes
- **Ignore Patterns**: Exclude Rhythm routes, health checks, and API documentation

### Slow Outgoing Requests Recorder

Monitors outgoing HTTP requests to external services. Supports [sampling](#sampling-settings), [ignoring](#ignore-settings), [thresholds](#thresholds-settings), and [grouping](#groups-settings) for URL normalization.

**Environment Variables**:
- `RHYTHM_SLOW_OUTGOING_REQUESTS_ENABLED` - Enable/disable outgoing request tracking (default: `true`)

**Key Settings**:
- **Thresholds**: Set different thresholds for API calls, external services, and database calls
- **Ignore Patterns**: Exclude localhost requests and internal domains
- **Group Patterns**: Normalize similar URLs by removing IDs and grouping by domain

### Exceptions Recorder

Records application exceptions with location information for debugging. Supports [sampling](#sampling-settings) and [ignoring](#ignore-settings) specific exception types.

**Environment Variables**:
- `RHYTHM_EXCEPTIONS_ENABLED` - Enable/disable exception tracking (default: `true`)
- `RHYTHM_EXCEPTIONS_SAMPLE_RATE` - Sample rate for exceptions (default: `0.1`)

**Key Settings**:
- **Sample Rate**: Control exception recording frequency
- **Ignore Patterns**: Exclude package exceptions and CakePHP framework exceptions

### Queues Recorder

Tracks queue job lifecycle events including queued, processing, processed, and failed jobs. Supports [sampling](#sampling-settings) and [ignoring](#ignore-settings) specific job types.

**Environment Variables**:
- `RHYTHM_QUEUE_JOBS_ENABLED` - Enable/disable queue job tracking (default: `true`)
- `RHYTHM_QUEUE_JOBS_SAMPLE_RATE` - Sample rate for queue jobs (default: `0.1`)

**Key Settings**:
- **Sample Rate**: Control job event recording frequency
- **Ignore Patterns**: Exclude package jobs and CakePHP framework jobs

### Queue Stats Recorder

Collects real-time queue statistics from Redis including queue depth, wait times, and health scores. Supports [sampling](#sampling-settings), [ignoring](#ignore-settings), and throttling.

**Environment Variables**:
- `RHYTHM_QUEUE_STATS_ENABLED` - Enable/disable queue statistics (default: `true`)
- `RHYTHM_QUEUE_STATS_SAMPLE_RATE` - Sample rate for queue statistics (default: `0.99`)
- `REDIS_HOST` - Redis host (default: `127.0.0.1`)
- `REDIS_PORT` - Redis port (default: `6379`)
- `REDIS_PASSWORD` - Redis password (default: `null`)
- `REDIS_DATABASE` - Redis database (default: `0`)
- `REDIS_PREFIX` - Redis key prefix (default: `''`)

**Key Settings**:
- **Sample Rate**: Control statistics collection frequency
- **Ignore Patterns**: Exclude system queues, internal queues, and test queues
- **Redis Configuration**: Connection settings for Redis monitoring

### Slow Jobs Recorder

Records queue jobs that exceed performance thresholds. Supports [sampling](#sampling-settings), [ignoring](#ignore-settings), and [thresholds](#thresholds-settings) for different job types.

**Environment Variables**:
- `RHYTHM_SLOW_JOBS_ENABLED` - Enable/disable slow job tracking (default: `true`)
- `RHYTHM_SLOW_JOBS_THRESHOLD` - Default threshold in seconds (default: `10`)
- `RHYTHM_SLOW_JOBS_SAMPLE_RATE` - Sample rate for slow jobs (default: `0.8`)

**Key Settings**:
- **Thresholds**: Set different thresholds for various job classes
- **Sample Rate**: Control slow job recording frequency
- **Ignore Patterns**: Exclude system jobs, internal jobs, and test jobs

### Redis Monitor Recorder

Monitors Redis server statistics including memory usage, key statistics, and network usage. Uses throttling to reduce monitoring overhead.

**Environment Variables**:
- `RHYTHM_REDIS_MONITOR_ENABLED` - Enable/disable Redis monitoring (default: `true`)
- `RHYTHM_REDIS_MONITOR_INTERVAL` - Monitoring interval in minutes (default: `5`)
- `RHYTHM_REDIS_MEMORY_ENABLED` - Enable memory usage monitoring (default: `true`)
- `RHYTHM_REDIS_KEYS_ENABLED` - Enable key statistics monitoring (default: `true`)
- `RHYTHM_REDIS_REMOVED_ENABLED` - Enable removed keys monitoring (default: `true`)
- `RHYTHM_REDIS_NETWORK_ENABLED` - Enable network usage monitoring (default: `true`)
- `REDIS_HOST` - Redis host (default: `127.0.0.1`)
- `REDIS_PORT` - Redis port (default: `6379`)
- `REDIS_PASSWORD` - Redis password (default: `null`)
- `REDIS_DATABASE` - Redis database (default: `0`)
- `REDIS_PREFIX` - Redis key prefix (default: `''`)

**Key Settings**:
- **Interval**: Set monitoring frequency in minutes
- **Connections**: Specify which Redis connections to monitor
- **Metrics**: Choose which Redis metrics to collect (memory, keys, network, etc.)
- **Redis Configuration**: Connection settings for Redis monitoring

### MySQL Monitor Recorder

Monitors MySQL server status variables and performance metrics. Uses throttling to minimize database impact.

**Environment Variables**:
- `RHYTHM_MYSQL_MONITOR_ENABLED` - Enable/disable MySQL monitoring (default: `true`)
- `RHYTHM_MYSQL_MONITOR_INTERVAL` - Monitoring interval in minutes (default: `5`)
- `RHYTHM_MYSQL_CONNECTIONS_ENABLED` - Enable connection monitoring (default: `true`)
- `RHYTHM_MYSQL_THREADS_ENABLED` - Enable thread monitoring (default: `true`)
- `RHYTHM_MYSQL_INNODB_ENABLED` - Enable InnoDB monitoring (default: `true`)
- `RHYTHM_MYSQL_PERFORMANCE_ENABLED` - Enable performance monitoring (default: `true`)

**Key Settings**:
- **Interval**: Set monitoring frequency in minutes
- **Connections**: Specify which database connections to monitor
- **Metrics**: Choose which MySQL metrics to collect (connections, threads, InnoDB, performance)
- **Status Variables**: Configure which MySQL status variables to monitor
- **Aggregates**: Define how to aggregate each metric (max, avg, count)

### PostgreSQL Monitor Recorder

Monitors PostgreSQL server statistics from system catalogs. Uses throttling to minimize database impact.

**Environment Variables**:
- `RHYTHM_POSTGRESQL_MONITOR_ENABLED` - Enable/disable PostgreSQL monitoring (default: `true`)
- `RHYTHM_POSTGRESQL_MONITOR_INTERVAL` - Monitoring interval in minutes (default: `5`)
- `RHYTHM_POSTGRESQL_CONNECTIONS_ENABLED` - Enable connection monitoring (default: `true`)
- `RHYTHM_POSTGRESQL_TRANSACTIONS_ENABLED` - Enable transaction monitoring (default: `true`)
- `RHYTHM_POSTGRESQL_PERFORMANCE_ENABLED` - Enable performance monitoring (default: `true`)
- `RHYTHM_POSTGRESQL_BACKGROUND_WRITER_ENABLED` - Enable background writer monitoring (default: `true`)

**Key Settings**:
- **Interval**: Set monitoring frequency in minutes
- **Connections**: Specify which database connections to monitor
- **Metrics**: Choose which PostgreSQL metrics to collect (connections, transactions, performance, background writer)
- **Aggregates**: Define how to aggregate each metric (max, avg, count)

## Common Widget Settings

Widgets are the building blocks of the Rhythm dashboard. Each widget displays specific metrics and can be customized with various settings.

### Widget Structure

```php
'widgets' => [
    'widget_name' => [
        'className' => \Rhythm\Widget\WidgetClass::class,
        'name' => 'Widget Display Name',
        'cols' => ['default' => 12, 'lg' => 6],
        'rows' => 1,
        'refreshInterval' => 30,
        'period' => 60,
        'connections' => ['default'],
        'metrics' => [
            'metric_name' => true,
        ],
        'colors' => [
            'category' => [
                'metric_name' => '#color',
            ],
        ],
        'labels' => [
            'category' => [
                'metric_name' => 'Display Label',
            ],
        ],
    ],
],
```

### Widgets Configuration

#### `className`
- **Description**: The PHP class that implements the widget functionality
- **Required**: Yes
- **Example**: `\Rhythm\Widget\ServerStateWidget::class`

#### `name`
- **Description**: Human-readable name displayed in the dashboard
- **Required**: Yes
- **Example**: `'Server State'`

#### `cols`
- **Description**: Column width configuration for responsive layout
- **Required**: Yes
- **Values**: Array with a column counts (1-12)
- **Example**: `['default' => 12, 'lg' => 6]`

#### `refreshInterval`
- **Description**: How often the widget refreshes its data in seconds
- **Default**: 30
- **Usage**: Controls real-time updates frequency

#### `colors`
- **Description**: Color configuration for different metric categories
- **Type**: Nested associative array with category and metric color mappings
- **Usage**: Defines visual styling for graphs of different metric types

#### `labels`
- **Description**: Custom display labels for metrics
- **Type**: Nested associative array with category and metric label mappings
- **Usage**: Provides human-readable names for technical metric names

## Layouts Configuration

Layouts define how widgets are arranged and displayed on the Rhythm dashboard. Each layout specifies which widgets to show and their positioning. Key names are used to reference the keys defined in the `widgets` section. When multiple widgets are defined with the same key name, they can specify `widget` key for reference.

### Layout Structure

```php
'layouts' => [
    'layout_name' => [
        'widget_name' => [
            'cols' => ['default' => 12, 'lg' => 6],
            'name' => 'Custom Widget Name',
            'widget' => 'widget_key',
        ],
    ],
],
```

### Common Layout Settings

#### `cols`
- **Description**: Defines the column width for the widget at different screen sizes
- **Values**: Array with a column counts (1-12)
- **Example**: `['default' => 12, 'lg' => 6]` - Full width on mobile, half width on large screens

#### `name`
- **Description**: Custom display name for the widget in this layout
- **Usage**: Overrides the default widget name for this specific layout instance

#### `widget`
- **Description**: References a base widget to use as a template
- **Usage**: Allows reusing widget configurations with different parameters
