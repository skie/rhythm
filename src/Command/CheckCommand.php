<?php
declare(strict_types=1);

namespace Rhythm\Command;

use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Rhythm\Event\SharedBeat;
use Rhythm\Rhythm;
use SignalHandler\Command\Trait\SignalHandlerTrait;

/**
 * Check Command for server tracking
 *
 * Continuously monitors server metrics and ingests them into the Rhythm system.
 */
class CheckCommand extends Command
{
    use SignalHandlerTrait;

    /**
     * Rhythm instance.
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Event manager instance.
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * Whether the command should continue running.
     *
     * @var bool
     */
    protected bool $isRunning = true;

    /**
     * Constructor.
     *
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     * @param \Cake\Console\CommandFactoryInterface $factory Command factory instance
     */
    public function __construct(Rhythm $rhythm, ?CommandFactoryInterface $factory = null)
    {
        parent::__construct($factory);
        $this->rhythm = $rhythm;
        $this->eventManager = EventManager::instance();
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription("Take a snapshot of the current server's rhythm")
            ->addOption('once', [
                'short' => 'o',
                'help' => 'Take a single snapshot',
                'boolean' => true,
            ])
            ->addOption('interval', [
                'short' => 'i',
                'help' => 'Interval between checks in seconds (default: 1)',
                'default' => 1,
            ]);

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $once = $args->getOption('once');
        $interval = (int)$args->getOption('interval');

        $this->rhythm->loadRecordersFromConfig();

        $io->info('Starting Rhythm server tracking...');
        $io->info('Press Ctrl+C to stop');

        $this->bindGracefulTermination(function (int $signal) use ($io): void {
            $io->info('Rhythm Check received termination signal, shutting down gracefully...');
            $this->isRunning = false;
        });

        $lastRestart = Cache::read('rhythm:restart', 'default');
        while ($this->isRunning) {
            usleep(100000);
            if ($lastRestart !== Cache::read('rhythm:restart', 'default')) {
                $io->info('Restart signal detected, exiting...');

                return self::CODE_SUCCESS;
            }

            $now = new DateTime();
            $instance = gethostname() ?: 'default';

            $this->eventManager->dispatch(new SharedBeat($now, $instance));

            $ingested = $this->rhythm->ingest();
            if ($ingested > 0) {
                $io->verbose("Ingested {$ingested} metrics");
            }

            if ($once) {
                $io->info('Single snapshot completed');

                return self::CODE_SUCCESS;
            }

            sleep($interval);
        }

        return self::CODE_SUCCESS;
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'rhythm check';
    }

    /**
     * Get the command description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Take a snapshot of the current server\'s rhythm';
    }
}
