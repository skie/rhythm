<?php
declare(strict_types=1);

namespace Rhythm\Model\Table;

use Cake\Chronos\Chronos;
use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Database\Expression\IdentifierExpression;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use InvalidArgumentException;

/**
 * Rhythm Aggregates Table
 *
 *
 * Handles storage and retrieval of aggregated metrics.
 */
class RhythmAggregatesTable extends Table
{
    use LocatorAwareTrait;

    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rhythm_aggregates');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('bucket')
            ->notEmptyString('bucket');

        $validator
            ->integer('period')
            ->notEmptyString('period');

        $validator
            ->scalar('type')
            ->maxLength('type', 255)
            ->notEmptyString('type');

        $validator
            ->scalar('key_hash')
            ->maxLength('key_hash', 32)
            ->notEmptyString('key_hash');

        $validator
            ->scalar('key')
            ->maxLength('key', 10000)
            ->notEmptyString('key');

        $validator
            ->scalar('aggregate')
            ->maxLength('aggregate', 50)
            ->notEmptyString('aggregate');

        $validator
            ->numeric('value')
            ->notEmptyString('value');

        $validator
            ->integer('count')
            ->notEmptyString('count');

        return $validator;
    }

    /**
     * Find aggregates by type and period.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Rhythm\Model\Entity\MetricAggregate> $query Query object
     * @param array<string, mixed> $options Options array
     * @return \Cake\ORM\Query\SelectQuery<\Rhythm\Model\Entity\MetricAggregate>
     */
    public function findByTypeAndPeriod(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'type' => $options['type'],
            'period' => $options['period'],
        ]);
    }

    /**
     * Find aggregates by bucket.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Rhythm\Model\Entity\MetricAggregate> $query Query object
     * @param array<string, mixed> $options Options array
     * @return \Cake\ORM\Query\SelectQuery<\Rhythm\Model\Entity\MetricAggregate>
     */
    public function findByBucket(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'bucket' => $options['bucket'],
        ]);
    }

    /**
     * Retrieve aggregate values for multiple types.
     *
     * @param array<string>|string $types The metric types
     * @param string $aggregate Single aggregate type
     * @param int $intervalMinutes Interval in minutes
     * @param string|null $orderBy Order by column
     * @param string $direction Order direction
     * @param int $limit Result limit
     * @return \Cake\Collection\Collection
     */
    public function aggregateTypes(
        string|array $types,
        string $aggregate,
        int $intervalMinutes,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $this->_validateAggregates($aggregate);

        /** @var \Rhythm\Model\Table\RhythmEntriesTable $RhythmEntries */
        $RhythmEntries = $this->fetchTable('Rhythm.RhythmEntries');

        $types = is_array($types) ? $types : [$types];
        $orderBy = $orderBy ?: $types[0];
        [
            'windowStart' => $windowStart,
            'period' => $period,
            'oldestBucket' => $oldestBucket,
        ] = $this->_getTimeScope($intervalMinutes);

        $aggregatedResults = [];

        $query = $this->find()
            ->select([
                'key',
                'key_hash',
                'type',
                'total' => 'SUM(value)',
            ])
            ->where([
                'type IN' => $types,
                'aggregate' => $aggregate,
                'period' => $period,
                'bucket >=' => $oldestBucket,
            ])
            ->groupBy(['key_hash', 'key', 'type'])
            ->limit($limit * count($types));

        foreach ($query->all() as $row) {
            $keyHash = $row->key_hash;
            if (!isset($aggregatedResults[$keyHash])) {
                $aggregatedResults[$keyHash] = [
                    'key' => $row->key,
                ];

                foreach ($types as $type) {
                    $aggregatedResults[$keyHash][$type] = 0;
                }
            }
            $aggregatedResults[$keyHash][$row->type] = (float)$row->total;
        }

        $tailQuery = $RhythmEntries->find()
            ->select(['key', 'key_hash', 'type', 'value'])
            ->where([
                'type IN' => $types,
                'timestamp >=' => $windowStart,
            ]);

        $tailData = [];
        foreach ($tailQuery->all() as $entry) {
            $keyHash = $entry->key_hash;
            $type = $entry->type;

            if (!isset($tailData[$keyHash])) {
                $tailData[$keyHash] = [
                    'key' => $entry->key,
                    'values' => [],
                ];
                foreach ($types as $t) {
                    $tailData[$keyHash]['values'][$t] = [];
                }
            }
            $tailData[$keyHash]['values'][$type][] = $entry->value;
        }

        foreach ($tailData as $keyHash => $data) {
            if (!isset($aggregatedResults[$keyHash])) {
                $aggregatedResults[$keyHash] = ['key' => $data['key']];
                foreach ($types as $type) {
                    $aggregatedResults[$keyHash][$type] = 0;
                }
            }

            foreach ($types as $type) {
                $values = $data['values'][$type] ?? [];
                $tailValue = match ($aggregate) {
                    'count' => count($values),
                    'min' => $values === [] ? 0 : min($values),
                    'max' => $values === [] ? 0 : max($values),
                    'sum' => array_sum($values),
                    'avg' => $values === [] ? 0 : array_sum($values) / count($values),
                    default => throw new InvalidArgumentException("Invalid aggregate: {$aggregate}"),
                };

                $aggregatedResults[$keyHash][$type] += $tailValue;
            }
        }

        $results = array_values($aggregatedResults);
        if ($orderBy && isset($results[0][$orderBy])) {
            usort($results, function (array $a, array $b) use ($orderBy, $direction) {
                $aVal = $a[$orderBy] ?? 0;
                $bVal = $b[$orderBy] ?? 0;

                return $direction === 'desc' ? $bVal <=> $aVal : $aVal <=> $bVal;
            });
        }

        return new Collection(array_slice($results, 0, $limit));
    }

    /**
     * Retrieve aggregate total for given types.
     *
     * @param array<string>|string $types The metric types
     * @param string $aggregate Aggregate type
     * @param int $intervalMinutes Interval in minutes
     * @return \Cake\Collection\Collection|float Total value or collection by type
     */
    public function aggregateTotal(
        array|string $types,
        string $aggregate,
        int $intervalMinutes,
    ): float|Collection {
        $this->_validateAggregates($aggregate);

        /** @var \Rhythm\Model\Table\RhythmEntriesTable $RhythmEntries */
        $RhythmEntries = $this->fetchTable('Rhythm.RhythmEntries');

        $isArray = is_array($types);
        $types = $isArray ? $types : [$types];
        [
            'windowStart' => $windowStart,
            'period' => $period,
            'oldestBucket' => $oldestBucket,
        ] = $this->_getTimeScope($intervalMinutes);

        $results = [];
        foreach ($types as $type) {
            $results[$type] = 0;
        }

        $aggregateQuery = $this->find()
            ->select([
                'type',
                'total' => match ($aggregate) {
                    'count' => 'SUM(value)',
                    'min' => 'MIN(value)',
                    'max' => 'MAX(value)',
                    'sum' => 'SUM(value)',
                    'avg' => 'AVG(value)',
                    default => throw new InvalidArgumentException("Invalid aggregate: {$aggregate}"),
                },
            ])
            ->where([
                'type IN' => $types,
                'aggregate' => $aggregate,
                'period' => $period,
                'bucket >=' => $oldestBucket,
            ])
            ->groupBy(['type']);

        foreach ($aggregateQuery->all() as $row) {
            $results[$row->type] += (float)$row->total;
        }

        $tailQuery = $RhythmEntries->find()
            ->select([
                'type',
                'total' => match ($aggregate) {
                    'count' => 'COUNT(*)',
                    'min' => 'MIN(value)',
                    'max' => 'MAX(value)',
                    'sum' => 'SUM(value)',
                    'avg' => 'AVG(value)',
                    default => throw new InvalidArgumentException("Invalid aggregate: {$aggregate}"),
                },
            ])
            ->where([
                'type IN' => $types,
                'timestamp >=' => $windowStart,
            ])
            ->groupBy(['type']);

        foreach ($tailQuery->all() as $row) {
            if ($aggregate === 'min') {
                $results[$row->type] = $results[$row->type] === 0 ?
                    (float)$row->total :
                    min($results[$row->type], (float)$row->total);
            } elseif ($aggregate === 'max') {
                $results[$row->type] = max($results[$row->type], (float)$row->total);
            } else {
                $results[$row->type] += (float)$row->total;
            }
        }

        if ($isArray) {
            return new Collection($results);
        }

        return $results[$types[0]] ?? 0.0;
    }

    /**
     * Retrieve aggregate values for plotting on a graph.
     *
     * @param array<string> $types List of metric types
     * @param string $aggregate Aggregate type ('count', 'min', 'max', 'sum', 'avg')
     * @param int $intervalMinutes Interval in minutes
     * @return \Cake\Collection\Collection
     */
    public function graph(array $types, string $aggregate, int $intervalMinutes): Collection
    {
        $this->_validateAggregates($aggregate);

        if ($intervalMinutes <= 0) {
            return new Collection([]);
        }

        [
            'period' => $period,
        ] = $this->_getTimeScope($intervalMinutes);

        $maxDataPoints = 60;
        $intervalSeconds = $intervalMinutes * 60;
        $secondsPerDataPoint = $intervalSeconds / $maxDataPoints;

        $now = Chronos::now();
        $currentBucket = (int)(floor($now->getTimestamp() / $secondsPerDataPoint) * $secondsPerDataPoint);
        $firstBucket = $currentBucket - (($maxDataPoints - 1) * $secondsPerDataPoint);

        $padding = [];
        for ($i = 0; $i < $maxDataPoints; $i++) {
            $padding[Chronos::createFromTimestamp($firstBucket + $i * $secondsPerDataPoint)->toDateTimeString()] = null;
        }

        $structure = [];
        foreach ($types as $type) {
            $structure[$type] = $padding;
        }

        $queryResults = $this->find()
            ->select(['bucket', 'type', 'key', 'value'])
            ->where([
                'type IN' => $types,
                'aggregate' => $aggregate,
                'period' => $period,
                'bucket >=' => $firstBucket,
                'bucket <=' => $currentBucket,
            ])
            ->orderBy(['bucket'])
            ->all();

        $groupedByKey = (new Collection($queryResults))->groupBy('key')->toArray();

        $finalResult = [];
        foreach ($groupedByKey as $key => $readings) {
            $readingsByType = (new Collection($readings))->groupBy('type')->toArray();

            $mapped = [];
            foreach ($readingsByType as $type => $typeReadings) {
                $valuesByBucket = [];
                foreach ($typeReadings as $reading) {
                    $time = Chronos::createFromTimestamp($reading->bucket)->toDateTimeString();
                    $valuesByBucket[$time] = $reading->value;
                }
                $merged = $valuesByBucket + $padding;
                $mapped[$type] = array_slice($merged, 0, $maxDataPoints, true);
            }

            $finalResult[$key] = new Collection($mapped + $structure);
        }

        ksort($finalResult, SORT_NATURAL);

        return new Collection($finalResult);
    }

    /**
     * Find the best period for the given interval.
     *
     * @param int $intervalMinutes Interval in minutes
     * @param array<int> $periods Available periods in minutes
     * @return int Best period in minutes
     */
    public function getBestPeriod(int $intervalMinutes, array $periods): int
    {
        foreach ($periods as $period) {
            if ($intervalMinutes <= $period) {
                return $period;
            }
        }

        $result = end($periods);

        return $result !== false ? $result : 0;
    }

    /**
     * Retrieve aggregate values for the given type using CakePHP ORM.
     *
     * @param string $type The metric type
     * @param array<string>|string $aggregates List of aggregates ('count', 'min', 'max', 'sum', 'avg')
     * @param int $intervalMinutes Interval in minutes
     * @param string|null $orderBy Order by column
     * @param string $direction Order direction
     * @param int $limit Result limit
     * @return \Cake\Collection\Collection Collection of aggregate results
     */
    public function aggregate(
        string $type,
        array|string $aggregates,
        int $intervalMinutes,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $aggregates = $this->_validateAggregates($aggregates);

        $orderBy = $orderBy ?: $aggregates[0];
        [
            'windowStart' => $windowStart,
            'period' => $period,
            'oldestBucket' => $oldestBucket,
        ] = $this->_getTimeScope($intervalMinutes);

        $entriesTable = TableRegistry::getTableLocator()->get('Rhythm.RhythmEntries');
        $tailQuery = $entriesTable->find();

        $tailSelectFields = ['key_hash' => $entriesTable->aliasField('key_hash')];
        foreach ($aggregates as $aggregate) {
            $tailSelectFields[$aggregate] = match ($aggregate) {
                'count' => $tailQuery->func()->count('*'),
                'min' => $tailQuery->func()->min('value'),
                'max' => $tailQuery->func()->max('value'),
                'sum' => $tailQuery->func()->sum('value'),
                'avg' => $tailQuery->func()->avg('value'),
                default => throw new InvalidArgumentException("Invalid aggregate: {$aggregate}"),
            };
        }

        $tailQuery = $tailQuery
            ->select($tailSelectFields)
            ->where([
                'type' => $type,
                'timestamp >=' => $windowStart,
                'timestamp <=' => $oldestBucket - 1,
            ])
            ->groupBy(['key_hash']);

        $bucketQueries = [];
        foreach ($aggregates as $currentAggregate) {
            $bucketQuery = $this->find();

            $bucketSelectFields = ['key_hash' => $this->aliasField('key_hash')];
            foreach ($aggregates as $aggregate) {
                if ($aggregate === $currentAggregate) {
                    $bucketSelectFields[$aggregate] = match ($aggregate) {
                        'count' => $bucketQuery->func()->sum('value'),
                        'min' => $bucketQuery->func()->min('value'),
                        'max' => $bucketQuery->func()->max('value'),
                        'sum' => $bucketQuery->func()->sum('value'),
                        'avg' => $bucketQuery->func()->avg('value'),
                        default => throw new InvalidArgumentException("Invalid aggregate: {$aggregate}"),
                    };
                } else {
                    $bucketSelectFields[$aggregate] = $bucketQuery->expr('NULL');
                }
            }

            $bucketQuery = $bucketQuery
                ->select($bucketSelectFields)
                ->where([
                    'period' => $period,
                    'type' => $type,
                    'aggregate' => $currentAggregate,
                    'bucket >=' => $oldestBucket,
                ])
                ->groupBy(['key_hash']);

            $bucketQueries[] = $bucketQuery;
        }

        $unionQuery = $tailQuery;
        foreach ($bucketQueries as $bucketQuery) {
            $unionQuery = $unionQuery->unionAll($bucketQuery);
        }

        $middleQuery = $this->find();
        $middleSelectFields = ['key_hash' => 'key_hash'];
        foreach ($aggregates as $aggregate) {
            $middleSelectFields[$aggregate] = match ($aggregate) {
                'count' => $middleQuery->func()->sum(new IdentifierExpression($aggregate)),
                'min' => $middleQuery->func()->min(new IdentifierExpression($aggregate)),
                'max' => $middleQuery->func()->max(new IdentifierExpression($aggregate)),
                'sum' => $middleQuery->func()->sum(new IdentifierExpression($aggregate)),
                'avg' => $middleQuery->func()->avg(new IdentifierExpression($aggregate)),
                default => throw new InvalidArgumentException("Invalid aggregate: {$aggregate}"),
            };
        }

        $middleQuery = $middleQuery
            ->select($middleSelectFields)
            ->from(['results' => $unionQuery])
            ->groupBy(['key_hash'])
            ->orderBy([$orderBy => $direction])
            ->limit($limit);

        $finalQuery = $this->find();
        $outerSelectFields = [];

        $keySubquery = $this->find()
            ->select(['key'])
            ->where(function ($exp, $q) {
                return $exp->equalFields('key_hash', 'aggregated.key_hash');
            })
            ->limit(1);

        $outerSelectFields['key'] = $keySubquery;
        foreach ($aggregates as $aggregate) {
            $outerSelectFields[$aggregate] = $finalQuery->expr($aggregate);
        }

        $finalQuery = $finalQuery
            ->select($outerSelectFields)
            ->from(['aggregated' => $middleQuery])
            ->enableHydration(false);

        $results = $finalQuery->toArray();

        if (
            in_array('avg', $aggregates) && in_array('count', $aggregates) &&
            in_array('sum', $aggregates)
        ) {
            foreach ($results as &$result) {
                if (isset($result['count']) && isset($result['sum']) && $result['count'] > 0) {
                    $result['avg'] = $result['sum'] / $result['count'];
                }
            }
        }

        return new Collection($results);
    }

    /**
     * Calculate time scope for aggregation.
     *
     * @param int $intervalMinutes Interval in minutes.
     * @return array{windowStart: int, period: int, oldestBucket: int}
     */
    private function _getTimeScope(int $intervalMinutes): array
    {
        $now = (new DateTime())->getTimestamp();
        $intervalSeconds = $intervalMinutes * 60;
        $windowStart = $now - $intervalSeconds + 1;

        $periods = Configure::read('Rhythm.aggregation.periods', [60, 360, 1440, 10080]);
        $period = $this->getBestPeriod($intervalMinutes, $periods);
        $periodSeconds = $period * 60;

        $currentBucket = (int)(floor($now / $periodSeconds) * $periodSeconds);
        $oldestBucket = $currentBucket - $intervalSeconds + $periodSeconds;

        return [
            'windowStart' => $windowStart,
            'period' => $period,
            'oldestBucket' => $oldestBucket,
        ];
    }

    /**
     * Validate aggregate types.
     *
     * @param array<string>|string $aggregates Aggregate types to validate.
     * @return array<string> Validated aggregate types as array.
     * @throws \InvalidArgumentException When invalid aggregate types are provided.
     */
    private function _validateAggregates(array|string $aggregates): array
    {
        $aggregates = is_array($aggregates) ? $aggregates : [$aggregates];
        $allowed = ['count', 'min', 'max', 'sum', 'avg'];

        $invalid = array_diff($aggregates, $allowed);
        if ($invalid) {
            throw new InvalidArgumentException(
                'Invalid aggregate type(s) [' . implode(', ', $invalid) . '], ' .
                'allowed types: [' . implode(', ', $allowed) . '].',
            );
        }

        return $aggregates;
    }
}
