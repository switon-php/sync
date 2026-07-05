<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Core\Runtime;
use Switon\Sync\Channel;
use Switon\Sync\Exception\ChannelClosedException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Coroutine-mode tests for Channel.
 *
 * Tests Channel behavior when using Swoole Coroutine Channel as the backend.
 * All tests run inside Swoole\Coroutine\run() to enable coroutine context.
 */
class ChannelCoroutineTest extends TestCase
{
    protected function setUp(): void
    {

        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for coroutine tests');
        }
    }

    /**
     * Test basic push and pop in coroutine mode (FIFO order).
     */
    public function testCoroutinePushAndPop(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(3);

            // Act
            $channel->push('a');
            $channel->push('b');
            $channel->push('c');

            // Assert - FIFO order
            $this->assertSame('a', $channel->pop());
            $this->assertSame('b', $channel->pop());
            $this->assertSame('c', $channel->pop());
            $this->assertTrue($channel->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test push after migration to queue mode (items === null path).
     */
    public function testCoroutinePushAfterMigration(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(3);

            // Act - push then pop triggers migration, then push again in queue mode
            $channel->push('first');
            $this->assertSame('first', $channel->pop());

            // Push after migration (items === null, queue already exists)
            $channel->push('second');
            $channel->push('third');

            // Assert
            $this->assertSame('second', $channel->pop());
            $this->assertSame('third', $channel->pop());
            $this->assertTrue($channel->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test pop with timeout returns false on empty channel without corrupting length.
     */
    public function testCoroutinePopWithTimeoutReturnsFalseOnEmpty(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(3);

            // Act - pop with short timeout on empty channel
            $result = $channel->pop(0.01);

            // Assert
            $this->assertFalse($result);
            $this->assertSame(0, $channel->length());
            $this->assertTrue($channel->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test pop with timeout returns false after items were consumed.
     */
    public function testCoroutinePopWithTimeoutAfterConsumingAllItems(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(2);
            $channel->push('item1');
            $channel->push('item2');

            // Act - consume all items
            $this->assertSame('item1', $channel->pop());
            $this->assertSame('item2', $channel->pop());

            // Pop again with timeout - should return false
            $result = $channel->pop(0.01);

            // Assert
            $this->assertFalse($result);
            $this->assertSame(0, $channel->length());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test blocking pop receives data from another coroutine.
     */
    public function testCoroutinePopBlocksUntilDataAvailable(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(1);
            $received = null;

            // Act - consumer blocks, producer pushes after delay
            \Swoole\Coroutine::create(function () use ($channel, &$received) {
                $received = $channel->pop(1.0);
            });

            \Swoole\Coroutine::create(function () use ($channel) {
                \Swoole\Coroutine::sleep(0.01);
                $channel->push('from-producer');
            });

            // Assert - Co\run waits for all child coroutines
            // Need brief sleep to let child coroutines complete
            \Swoole\Coroutine::sleep(0.05);
            $this->assertSame('from-producer', $received);

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test state consistency after mixed coroutine operations.
     */
    public function testCoroutineStateConsistency(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(5);

            // Act - multiple push/pop cycles
            $channel->push(1);
            $channel->push(2);
            $channel->push(3);
            $this->assertSame(3, $channel->length());
            $this->assertFalse($channel->isFull());

            $this->assertSame(1, $channel->pop());
            $this->assertSame(2, $channel->length());

            $channel->push(4);
            $channel->push(5);
            $this->assertSame(4, $channel->length());

            // Assert - remaining items in FIFO order
            $this->assertSame(2, $channel->pop());
            $this->assertSame(3, $channel->pop());
            $this->assertSame(4, $channel->pop());
            $this->assertSame(5, $channel->pop());
            $this->assertTrue($channel->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }

    // ========== Close Tests (Coroutine) ==========

    /**
     * Test pop remaining items after close in coroutine mode.
     */
    public function testCoroutinePopRemainingItemsAfterClose(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(3);
            $channel->push('a');
            $channel->push('b');

            // Act
            $channel->close();

            // Assert - remaining items still poppable
            $this->assertSame('a', $channel->pop());
            $this->assertSame('b', $channel->pop());

            // Pop on empty closed → exception
            $thrown = false;
            try {
                $channel->pop(0.01);
            } catch (ChannelClosedException) {
                $thrown = true;
            }
            $this->assertTrue($thrown);

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test close wakes blocked coroutine with ChannelClosedException.
     */
    public function testCoroutineCloseWakesBlockedPop(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(1);
            $exceptionClass = null;

            // Act - consumer blocks, producer closes after delay
            \Swoole\Coroutine::create(function () use ($channel, &$exceptionClass) {
                try {
                    $channel->pop(1.0);
                } catch (Throwable $e) {
                    $exceptionClass = $e::class;
                }
            });

            \Swoole\Coroutine::create(function () use ($channel) {
                \Swoole\Coroutine::sleep(0.01);
                $channel->close();
            });

            \Swoole\Coroutine::sleep(0.05);

            // Assert
            $this->assertSame(ChannelClosedException::class, $exceptionClass);

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test close during array phase then pop without timeout throws immediately.
     *
     * Regression: close() during array phase creates unclosed Swoole Channel,
     * pop without timeout would block forever without the pre-check.
     */
    public function testCoroutineCloseDuringArrayPhaseThenPopThrows(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange - close during array phase (before any pop triggers migration)
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(3);
            $channel->push('a');
            $channel->close();

            // Act - pop remaining item
            $this->assertSame('a', $channel->pop());

            // Assert - pop on empty closed channel throws immediately (no timeout)
            $thrown = false;
            try {
                $channel->pop(); // must NOT block forever
            } catch (ChannelClosedException) {
                $thrown = true;
            }
            $this->assertTrue($thrown);

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test close during array phase with empty channel then pop throws.
     */
    public function testCoroutineCloseDuringArrayPhaseEmptyThenPopThrows(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange - close empty channel during array phase
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(3);
            $channel->close();

            // Act & Assert - pop must throw immediately, not block
            $thrown = false;
            try {
                $channel->pop();
            } catch (ChannelClosedException) {
                $thrown = true;
            }
            $this->assertTrue($thrown);

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test push throws on closed channel in coroutine mode.
     */
    public function testCoroutinePushThrowsOnClosedChannel(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $channel = new Channel(3);
            $channel->close();

            // Act & Assert
            $thrown = false;
            try {
                $channel->push('data');
            } catch (ChannelClosedException) {
                $thrown = true;
            }
            $this->assertTrue($thrown);

            Runtime::setCoroutineEnabled(false);
        });
    }
}
