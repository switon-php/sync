<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Core\Runtime;
use Switon\Sync\Mutex;
use Switon\Sync\Tests\TestCase;

/**
 * Coroutine-mode tests for Mutex.
 */
class MutexCoroutineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for coroutine tests');
        }
    }

    public function testCoroutineLockIsReentrantForSameCoroutine(): void
    {
        \Swoole\Coroutine\run(function () {
            Runtime::setCoroutineEnabled(true);

            try {
                $mutex = new Mutex();

                $mutex->lock();
                $this->assertTrue($mutex->isLocked());

                $mutex->lock();
                $this->assertTrue($mutex->isLocked());

                $mutex->unlock();
                $this->assertTrue($mutex->isLocked());

                $mutex->unlock();
                $this->assertFalse($mutex->isLocked());
            } finally {
                Runtime::setCoroutineEnabled(false);
            }
        });
    }
}
