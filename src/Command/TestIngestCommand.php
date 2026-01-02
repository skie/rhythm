<?php
declare(strict_types=1);

namespace Rhythm\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Rhythm\Rhythm;

/**
 * Test Ingest Command
 *
 * This command demonstrates automatic ingest functionality.
 * It records metrics and relies on the shutdown function to ingest them.
 */
class TestIngestCommand extends Command
{
    /**
     * Rhythm instance.
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

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
            ->setDescription('Test automatic ingest functionality')
            ->addOption('metrics', [
                'short' => 'm',
                'help' => 'Number of metrics to record (default: 5)',
                'default' => 5,
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
        $metricCount = (int)$args->getOption('metrics');

        $io->info('Testing automatic ingest functionality...');
        $io->info("Recording {$metricCount} metrics...");

        /** @var \Rhythm\Rhythm $rhythm */
        $rhythm = $this->rhythm;

        for ($i = 1; $i <= $metricCount; $i++) {
            $rhythm->record('test_metric', "metric_{$i}", $i * 100);
            $io->verbose("Recorded metric_{$i} = " . ($i * 100));
        }

        $io->info('Metrics recorded. Check if they are automatically ingested before command exit.');
        $io->info("You should see 'Rhythm: Ingested X metrics before command exit' in the output.");

        return self::CODE_SUCCESS;
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'rhythm test-ingest';
    }

    /**
     * Get the command description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Test automatic ingest functionality';
    }
}
