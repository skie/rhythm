<?php
declare(strict_types=1);

namespace Rhythm\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Rhythm\Rhythm;

/**
 * Command to clear Rhythm data.
 *
 * Purge Rhythm data from the database and redis queue.
 */
class ClearCommand extends Command
{
    /**
     * Rhythm instance.
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

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
    }

    /**
     * Configure the command.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to update
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Delete all Rhythm data from storage')
            ->addOption('type', [
                'short' => 't',
                'help' => 'Only clear the specified type(s)',
                'multiple' => true,
            ])
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force the operation to run when in production',
                'boolean' => true,
            ]);

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments
     * @param \Cake\Console\ConsoleIo $io The console I/O
     * @return int|null|void The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        if (Configure::read('debug') === false && !$args->getOption('force')) {
            $io->error('This command cannot be run in production without the --force option.');

            return Command::CODE_ERROR;
        }

        $types = $args->getOption('type');

        if (!empty($types) && is_string($types)) {
            $io->info('Purging Rhythm data for [' . $types . ']');
            $types = explode(',', $types);
            $this->rhythm->purge($types);
        } else {
            $io->info('Purging all Rhythm data');
            $this->rhythm->purge();
        }

        $io->success('Rhythm data cleared successfully.');

        return Command::CODE_SUCCESS;
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'rhythm clear';
    }

    /**
     * Get the command description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Delete all Rhythm data from storage';
    }
}
