<?php
declare(strict_types=1);

namespace Rhythm\Recorder;

use Cake\Cache\Event\CacheAfterGetEvent;
use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Rhythm\Recorder\Trait\GroupsTrait;
use Rhythm\Recorder\Trait\IgnoresTrait;
use Rhythm\Recorder\Trait\SamplingTrait;
use Rhythm\Rhythm;

/**
 * Cache Recorder
 *
 * Records cache hits and misses from CakePHP 5.3 cache events.
 * Matches Reverb (Laravel Pulse) approach - simple and focused on hit/miss tracking only.
 */
class CacheRecorder extends BaseRecorder implements EventListenerInterface
{
    use GroupsTrait;
    use SamplingTrait;
    use IgnoresTrait;

    /**
     * Constructor.
     *
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $config = $config ?: Configure::read('Rhythm.recorders.cache', []);
        parent::__construct($rhythm, $config);
    }

    /**
     * Implemented events.
     *
     * @return array<string, string>
     */
    public function implementedEvents(): array
    {
        return [
            'Cache.afterGet' => 'record',
        ];
    }

    /**
     * Record metric data.
     *
     * @param mixed $data Data to record
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        if (!$data instanceof CacheAfterGetEvent) {
            return;
        }

        $key = (string)$data->getKey();
        $decodedKey = rawurldecode($key);

        if ($this->shouldIgnore($decodedKey) || $this->shouldIgnore($key)) {
            return;
        }

        $groupedKey = $this->group($decodedKey);

        $result = $data->getResult();
        $type = $result === true ? 'cache_hit' : 'cache_miss';

        $this->rhythm->record($type, $groupedKey)->count()->onlyBuckets();
    }
}
