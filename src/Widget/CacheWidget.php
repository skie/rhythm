<?php
declare(strict_types=1);

namespace Rhythm\Widget;

use Cake\Collection\CollectionInterface;
use Exception;

/**
 * Cache Widget
 *
 * Displays cache metrics from Rhythm data.
 */
class CacheWidget extends BaseWidget
{
    /**
     * Get widget data
     *
     * @param array $options Widget options (period, sort, etc.)
     * @return array
     */
    public function getData(array $options = []): array
    {
        $period = $options['period'] ?? 60;

        return $this->remember(function () use ($period) {
            try {
                $cacheTotalsResult = $this->rhythm->getStorage()
                    ->aggregateTotal(['cache_hit', 'cache_miss'], 'count', $period);

                /** @var array<string, float> $cacheTotals */
                $cacheTotals = $cacheTotalsResult instanceof CollectionInterface
                    ? $cacheTotalsResult->toArray()
                    : [];

                $hits = (float)($cacheTotals['cache_hit'] ?? 0);
                $misses = (float)($cacheTotals['cache_miss'] ?? 0);

                $total = $hits + $misses;
                $hitRate = $total > 0 ? round($hits / $total * 100, 2) : 0;

                $ignorePatterns = (array)($this->getRecorderSettingForRecorder('cache', 'ignore') ?? []);

                $cacheKeyInteractions = $this->rhythm->getStorage()
                    ->aggregateTypes(['cache_hit', 'cache_miss'], 'count', $period, 'cache_hit', 'desc', 100)
                    ->map(function (array $row) {
                        return (object)[
                            'key' => $row['key'] ?? '',
                            'hits' => (int)($row['cache_hit'] ?? 0),
                            'misses' => (int)($row['cache_miss'] ?? 0),
                        ];
                    })
                    ->filter(function (object $item) use ($ignorePatterns): bool {
                        /** @var object{key: string, hits: int, misses: int} $item */
                        if (empty($item->key) || ($item->hits === 0 && $item->misses === 0)) {
                            return false;
                        }

                        if (!empty($ignorePatterns)) {
                            $decodedKey = rawurldecode($item->key);
                            foreach ($ignorePatterns as $pattern) {
                                if (preg_match($pattern, $decodedKey) > 0 || preg_match($pattern, $item->key) > 0) {
                                    return false;
                                }
                            }
                        }

                        return true;
                    })
                    ->toArray();

                return [
                    'hits' => (int)$hits,
                    'misses' => (int)$misses,
                    'total' => (int)$total,
                    'hit_rate' => $hitRate,
                    'status' => $this->getCacheStatus($hitRate),
                    'cacheKeyInteractions' => $cacheKeyInteractions,
                ];
            } catch (Exception $e) {
                return [
                    'hits' => 0,
                    'misses' => 0,
                    'total' => 0,
                    'hit_rate' => 0,
                    'status' => 'unknown',
                    'cacheKeyInteractions' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }, 'cache_' . $period, $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/cache';
    }

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return $this->getConfigValue('refreshInterval', 60);
    }

    /**
     * Get default icon for this widget
     *
     * @return string|null
     */
    protected function getDefaultIcon(): ?string
    {
        return 'fas fa-bolt';
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'cache';
    }

    /**
     * Get cache status based on hit rate
     *
     * @param float $hitRate Cache hit rate percentage
     * @return string
     */
    protected function getCacheStatus(float $hitRate): string
    {
        if ($hitRate >= 90) {
            return 'excellent';
        }

        if ($hitRate >= 70) {
            return 'good';
        }

        if ($hitRate >= 50) {
            return 'fair';
        }

        return 'poor';
    }
}
