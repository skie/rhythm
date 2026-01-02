<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Recorder;

use Cake\Core\Container;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Rhythm\Ingest\IngestInterface;
use Rhythm\Recorder\RecorderInterface;
use Rhythm\Recorder\RecorderResolver;
use Rhythm\Rhythm;
use Rhythm\Storage\StorageInterface;
use stdClass;

/**
 * RecorderResolver Test Case
 */
class RecorderResolverTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Rhythm\Recorder\RecorderResolver
     */
    protected RecorderResolver $resolver;

    /**
     * Real container
     *
     * @var \Cake\Core\Container
     */
    protected Container $container;

    /**
     * Real rhythm instance
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();

        $this->container->addShared(StorageInterface::class, function () {
            return $this->createStub(StorageInterface::class);
        });

        $mockIngest = $this->createStub(IngestInterface::class);
        $this->rhythm = new Rhythm(
            $this->container->get(StorageInterface::class),
            $mockIngest,
            $this->container,
        );

        $this->resolver = new RecorderResolver($this->container, $this->rhythm);
    }

    /**
     * Test resolve with container registration
     *
     * @return void
     */
    public function testResolveWithContainerRegistration(): void
    {
        $mockRecorder = $this->createStub(RecorderInterface::class);

        $this->container->addShared('Rhythm\Test\TestCase\Recorder\TestRecorder', function () use ($mockRecorder) {
            return $mockRecorder;
        });

        $result = $this->resolver->resolve('Rhythm\Test\TestCase\Recorder\TestRecorder');

        $this->assertSame($mockRecorder, $result);
    }

    /**
     * Test resolve with auto-injection
     *
     * @return void
     */
    public function testResolveWithAutoInjection(): void
    {
        $result = $this->resolver->resolve('Rhythm\Test\TestCase\Recorder\TestRecorder');

        $this->assertInstanceOf('Rhythm\Test\TestCase\Recorder\TestRecorder', $result);
    }

    /**
     * Test resolve with non-existent class
     *
     * @return void
     */
    public function testResolveWithNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recorder class `NonExistentRecorder` does not exist.');

        $this->resolver->resolve('NonExistentRecorder');
    }

    /**
     * Test resolve with invalid interface
     *
     * @return void
     */
    public function testResolveWithInvalidInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recorder class `Rhythm\Test\TestCase\Recorder\InvalidRecorder` must implement RecorderInterface.');

        $this->resolver->resolve('Rhythm\Test\TestCase\Recorder\InvalidRecorder');
    }

    /**
     * Test resolve with container returning invalid type
     *
     * @return void
     */
    public function testResolveWithContainerReturningInvalidType(): void
    {
        $invalidRecorder = new stdClass();

        $this->container->addShared('TestRecorder', function () use ($invalidRecorder) {
            return $invalidRecorder;
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recorder `TestRecorder` from container does not implement RecorderInterface.');

        $this->resolver->resolve('TestRecorder');
    }
}
