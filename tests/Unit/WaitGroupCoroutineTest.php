<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Core\Runtime;
use Switon\Sync\Exception\WaitGroupConcurrentException;
use Switon\Sync\Exception\WaitTimeoutException;
use Switon\Sync\Tests\TestCase;
use Switon\Sync\WaitGroup;
use Throwable;

/**
 * Coroutine-mode tests for WaitGroup.
 *
 * Tests WaitGroup behavior when using Swoole coroutines for concurrent task coordination.
 * All tests run inside Swoole\Coroutine\run() to enable coroutine context.
 */
class WaitGroupCoroutineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for coroutine tests');
        }
    }

    /**
     * Test wait blocks until all coroutines call done.
     */
    public function testCoroutineWaitBlocksUntilAllDone(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $wg = WaitGroup::of(3);
            $completed = [];

            // Act - three coroutines each doing work and calling done
            \Swoole\Coroutine::create(function () use ($wg, &$completed) {
                \Swoole\Coroutine::sleep(0.01);
                $completed[] = 'a';
                $wg->done();
            });

            \Swoole\Coroutine::create(function () use ($wg, &$completed) {
                \Swoole\Coroutine::sleep(0.02);
                $completed[] = 'b';
                $wg->done();
            });

            \Swoole\Coroutine::create(function () use ($wg, &$completed) {
                \Swoole\Coroutine::sleep(0.03);
                $completed[] = 'c';
                $wg->done();
            });

            $wg->wait();

            // Assert - all tasks completed before wait returns
            $this->assertCount(3, $completed);
            $this->assertSame(0, $wg->count());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test wait with timeout throws on expiration.
     */
    public function testCoroutineWaitTimeoutThrows(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $wg = WaitGroup::of(1);
            // Never call done - simulate stuck task

            // Act & Assert
            $thrown = false;
            try {
                $wg->wait(0.01);
            } catch (WaitTimeoutException) {
                $thrown = true;
            }
            $this->assertTrue($thrown);
            $this->assertSame(1, $wg->count());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test wait without timeout blocks indefinitely until done.
     */
    public function testCoroutineWaitWithoutTimeoutBlocksUntilDone(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $wg = WaitGroup::of(1);
            $waitReturned = false;

            // Act - done after delay
            \Swoole\Coroutine::create(function () use ($wg) {
                \Swoole\Coroutine::sleep(0.05);
                $wg->done();
            });

            $wg->wait(); // no timeout, blocks until done
            $waitReturned = true;

            // Assert
            $this->assertTrue($waitReturned);
            $this->assertSame(0, $wg->count());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test wait returns immediately when counter is already zero in coroutine mode.
     */
    public function testCoroutineWaitReturnsImmediatelyWhenZero(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $wg = WaitGroup::of(1);
            $wg->done();

            // Act & Assert - should not block
            $wg->wait();
            $this->assertSame(0, $wg->count());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test concurrent wait() throws WaitGroupConcurrentException.
     */
    public function testCoroutineConcurrentWaitThrows(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $wg = WaitGroup::of(2);
            $exceptionClass = null;

            // Act - first coroutine calls wait()
            \Swoole\Coroutine::create(function () use ($wg) {
                $wg->wait(1.0);
            });

            // Second coroutine also calls wait() — should throw
            \Swoole\Coroutine::create(function () use ($wg, &$exceptionClass) {
                \Swoole\Coroutine::sleep(0.01);
                try {
                    $wg->wait(1.0);
                } catch (Throwable $e) {
                    $exceptionClass = $e::class;
                }
            });

            // Complete the tasks so first waiter unblocks
            \Swoole\Coroutine::create(function () use ($wg) {
                \Swoole\Coroutine::sleep(0.05);
                $wg->done();
                $wg->done();
            });

            \Swoole\Coroutine::sleep(0.1);

            // Assert
            $this->assertSame(WaitGroupConcurrentException::class, $exceptionClass);

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test reusable across multiple wait cycles in coroutine mode.
     */
    public function testCoroutineReusableAcrossCycles(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $wg = WaitGroup::of(2);
            \Swoole\Coroutine::create(function () use ($wg) {
                \Swoole\Coroutine::sleep(0.01);
                $wg->done();
            });
            \Swoole\Coroutine::create(function () use ($wg) {
                \Swoole\Coroutine::sleep(0.01);
                $wg->done();
            });
            $wg->wait();
            $this->assertSame(0, $wg->count());

            // Second cycle - same WaitGroup
            $wg->add(1);
            \Swoole\Coroutine::create(function () use ($wg) {
                \Swoole\Coroutine::sleep(0.01);
                $wg->done();
            });
            $wg->wait();

            // Assert
            $this->assertSame(0, $wg->count());

            Runtime::setCoroutineEnabled(false);
        });
    }
}
