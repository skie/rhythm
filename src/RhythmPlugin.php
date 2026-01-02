<?php
declare(strict_types=1);

namespace Rhythm;

use Cake\Console\CommandCollection;
use Cake\Console\CommandFactoryInterface;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerApplicationInterface;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\MiddlewareQueue;
use Rhythm\Command\CheckCommand;
use Rhythm\Command\ClearCommand;
use Rhythm\Command\DigestCommand;
use Rhythm\Command\RestartCommand;
use Rhythm\Database\Log\RhythmQueryLogger;
use Rhythm\Ingest\IngestInterface;
use Rhythm\Ingest\NullIngest;
use Rhythm\Ingest\RedisIngest;
use Rhythm\Middleware\RhythmMiddleware;
use Rhythm\Storage\DigestStorage;
use Rhythm\Storage\StorageInterface;

/**
 * Plugin for Rhythm performance monitoring
 */
class RhythmPlugin extends BasePlugin
{
    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
        foreach (ConnectionManager::configured() as $name) {
            if ($name === 'debug_kit') {
                continue;
            }
            $connection = ConnectionManager::get($name);
            $driver = $connection->getDriver();
            if (method_exists($driver, 'getLogger') && method_exists($driver, 'setLogger')) {
                $existingLogger = $driver->getLogger();
                $rhythmLogger = new RhythmQueryLogger(
                    $existingLogger,
                    $name,
                    1,
                );
                $driver->setLogger($rhythmLogger);
            }
        }

        $app->getEventManager()->on('Command.beforeExecute', function ($event) use ($app): void {
            if ($app instanceof ContainerApplicationInterface) {
                $container = $app->getContainer();
            } else {
                $container = Configure::read('app.container');
            }
            if ($container && $container->has(Rhythm::class)) {
                $container->get(Rhythm::class);
            }
        });

        $app->getEventManager()->on('Command.afterExecute', function ($event) use ($app): void {
            if ($app instanceof ContainerApplicationInterface) {
                $container = $app->getContainer();
            } else {
                $container = Configure::read('app.container');
            }
            if ($container && $container->has(Rhythm::class)) {
                $rhythm = $container->get(Rhythm::class);
                $rhythm->ingest();
            }
        });
    }

    /**
     * Register container services.
     *
     * @param \Cake\Core\ContainerInterface $container The container to register services with
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        Configure::write('app.container', $container);

        $container->addShared(StorageInterface::class, DigestStorage::class);

        $container->addShared('ingest.config.redis', function () {
            return Configure::read('Rhythm.ingest.redis');
        });

        $ingestDriver = Configure::read('Rhythm.ingest.driver', 'redis');
        if ($ingestDriver === 'redis') {
            $container->addShared(IngestInterface::class, RedisIngest::class)
                ->addArgument(StorageInterface::class)
                ->addArgument('ingest.config.redis');
        } else {
            $container->addShared(IngestInterface::class, NullIngest::class);
        }
        $container->addShared(ContainerInterface::class, $container);

        $container->addShared(Rhythm::class)
            ->addArgument(StorageInterface::class)
            ->addArgument(IngestInterface::class)
            ->addArgument(ContainerInterface::class);

        $container->addShared(Rhythm::class, function () use ($container) {
            $storage = $container->get(StorageInterface::class);
            $ingest = $container->get(IngestInterface::class);
            $rhythm = new Rhythm($storage, $ingest, $container);
            $rhythm->loadRecordersFromConfig();

            return $rhythm;
        });

        $container
            ->add(CheckCommand::class)
            ->addArgument(Rhythm::class)
            ->addArgument(CommandFactoryInterface::class);

        $container->add(DigestCommand::class)
            ->addArgument(Rhythm::class)
            ->addArgument(CommandFactoryInterface::class);

        $container->add(ClearCommand::class)
            ->addArgument(Rhythm::class);

        $container->addShared(RhythmMiddleware::class, function () use ($container) {
            return new RhythmMiddleware($container->get(Rhythm::class));
        });
    }

    /**
     * Add middleware for the plugin.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue->insertBefore(ErrorHandlerMiddleware::class, RhythmMiddleware::class);

        return $middlewareQueue;
    }

    /**
     * Add console commands.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);

        return $commands->addMany([
            'rhythm clear' => ClearCommand::class,
            'rhythm purge' => ClearCommand::class,
            'rhythm digest' => DigestCommand::class,
            'rhythm work' => DigestCommand::class,
            'rhythm check' => CheckCommand::class,
            'rhythm restart' => RestartCommand::class,
        ]);
    }
}
