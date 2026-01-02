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

/**
 * Restart Command
 *
 * Broadcasts restart signals to all running Rhythm commands.
 */
class RestartCommand extends Command
{
    /**
     * Constructor.
     *
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory instance
     */
    public function __construct(?CommandFactoryInterface $factory = null)
    {
        parent::__construct($factory);
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
            ->setDescription('Restart any running "check" and "digest" commands');

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
        Cache::write('rhythm:restart', (new DateTime())->getTimestamp(), 'default');

        $io->success('Broadcasting Rhythm restart signal to all running commands.');
        $io->info('Running check and digest commands will restart gracefully.');

        return self::CODE_SUCCESS;
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'rhythm restart';
    }

    /**
     * Get the command description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Restart any running "check" and "digest" commands';
    }
}
