<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\Runtime;
use Switon\Sync\Channel;
use Switon\Sync\Termination;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel as SwooleChannel;

/**
 * Termination broadcast tests.
 *
 * Verifies that terminate() wakes coroutines blocked on registered channels,
 * stays idempotent, and drops garbage-collected registrants.
 */
class TerminationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for coroutine tests');
        }
    }

    public function testTerminateWakesBlockedSyncChannelPop(): void
    {
        Runtime::setCoroutineEnabled(true);
        try {
            Coroutine\run(function (): void {
                $channel = new Channel(1);
                $termination = new Termination();
                $termination->register($channel);

                $woken = false;
                Coroutine::create(function () use ($channel, &$woken): void {
                    try {
                        $channel->pop();
                    } catch (\Switon\Sync\Exception\ChannelClosedException) {
                        $woken = true;
                    }
                });

                // Let the pop coroutine block, then broadcast termination.
                usleep(50_000);
                $termination->terminate();
                usleep(50_000);

                $this->assertTrue($woken, 'pop should be released by terminate()');
                $this->assertTrue($termination->isTerminated());
            });
        } finally {
            Runtime::setCoroutineEnabled(false);
        }
    }

    public function testTerminateWakesBlockedSwooleChannelPop(): void
    {
        Runtime::setCoroutineEnabled(true);
        try {
            Coroutine\run(function (): void {
                $channel = new SwooleChannel(1);
                $termination = new Termination();
                $termination->register($channel);

                $woken = false;
                Coroutine::create(function () use ($channel, &$woken): void {
                    $result = $channel->pop();
                    $woken = $result === false; // Swoole pop returns false after close
                });

                usleep(50_000);
                $termination->terminate();
                usleep(50_000);

                $this->assertTrue($woken, 'native Swoole channel pop should be released');
            });
        } finally {
            Runtime::setCoroutineEnabled(false);
        }
    }

    public function testTerminateIsIdempotent(): void
    {
        $channel = new Channel(1);
        $termination = new Termination();
        $termination->register($channel);

        $termination->terminate();
        $termination->terminate();

        $this->assertTrue($termination->isTerminated());
    }

    public function testRegistrantIsGarbageCollectedWithoutUnregister(): void
    {
        $termination = new Termination();
        $channel = new Channel(1);
        $termination->register($channel);

        $this->assertFalse($termination->isTerminated());

        unset($channel);
        gc_collect_cycles();

        // No exception and no retained registrant: terminate on an empty weak set.
        $termination->terminate();
        $this->assertTrue($termination->isTerminated());
    }
}
