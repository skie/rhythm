<?php
declare(strict_types=1);

namespace Rhythm\Command;

use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Rhythm\Rhythm;
use SignalHandler\Command\Trait\SignalHandlerTrait;

/**
 * Digest Command
 *
 * Process incoming Rhythm data from the ingest stream in a continuous loop.
 * This command runs continuously, digesting metrics and periodically trimming old data.
 */
class DigestCommand extends Command
{
    use SignalHandlerTrait;

    /**
     * Rhythm instance.
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Whether the command should continue running.
     *
     * @var bool
     */
    protected bool $isRunning = true;

    /**
     * Constructor
     *
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     */
    public function __construct(Rhythm $rhythm, ?CommandFactoryInterface $factory = null)
    {
        parent::__construct($factory);
        $this->rhythm = $rhythm;
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
            ->setDescription('Process incoming Rhythm data from the ingest stream')
            ->addOption('stop-when-empty', [
                'short' => 's',
                'help' => 'Stop when the stream is empty',
                'boolean' => true,
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
        $io->info('Starting Rhythm digest worker...');

        $this->bindGracefulTermination(function (int $signal) use ($io): void {
            $io->info('Received termination signal, finishing current digest cycle...');
            $this->isRunning = false;
        });

        $lastRestart = Cache::read('rhythm:restart', 'default');
        $lastTrimmedStorageAt = (new DateTime())->getTimestamp();

        while ($this->isRunning) {
            $now = (new DateTime())->getTimestamp();

            if ($lastRestart !== Cache::read('rhythm:restart', 'default')) {
                $io->info('Restart signal detected, stopping worker...');

                return self::CODE_SUCCESS;
            }

            $count = $this->rhythm->digest();

            if ($count > 0) {
                $io->success("Digested {$count} metrics successfully.");
            }

            if ($now - $lastTrimmedStorageAt >= 600) {
                $io->info('Trimming old data...');
                $this->rhythm->trim();
                $lastTrimmedStorageAt = $now;
                $io->success('Trim completed.');
            }

            if ($args->getOption('stop-when-empty')) {
                $io->info('Stop when empty option enabled, exiting...');

                return self::CODE_SUCCESS;
            }

            sleep(1);
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
        return 'rhythm digest';
    }

    /**
     * Get the command description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Process incoming Rhythm data from the ingest stream';
    }
}
