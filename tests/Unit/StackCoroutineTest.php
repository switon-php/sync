<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Core\Runtime;
use Switon\Sync\Stack;
use Switon\Sync\Tests\TestCase;

/**
 * Coroutine-mode tests for Stack.
 *
 * Tests Stack behavior when using Swoole coroutine runtime.
 * All tests run inside Swoole\Coroutine\run() to enable coroutine context.
 */
class StackCoroutineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for coroutine tests');
        }
    }

    /**
     * Test pop with timeout on empty stack returns false in coroutine mode.
     */
    public function testCoroutinePopWithTimeoutReturnsFalseOnEmpty(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $stack = new Stack(3);

            // Act
            $result = $stack->pop(0.01);

            // Assert
            $this->assertFalse($result);
            $this->assertTrue($stack->isEmpty());
            $this->assertSame(0, $stack->length());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test pop with timeout after consuming all items returns false.
     */
    public function testCoroutinePopWithTimeoutAfterConsumingAll(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $stack = new Stack(3);
            $stack->push('a');
            $stack->push('b');

            // Act - consume all
            $this->assertSame('b', $stack->pop());
            $this->assertSame('a', $stack->pop());

            // Pop again with timeout
            $result = $stack->pop(0.01);

            // Assert
            $this->assertFalse($result);
            $this->assertTrue($stack->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test basic LIFO push/pop in coroutine mode.
     */
    public function testCoroutineLifoBehavior(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $stack = new Stack(5);

            // Act
            $stack->push('first');
            $stack->push('second');
            $stack->push('third');

            // Assert - LIFO order
            $this->assertSame('third', $stack->pop());
            $this->assertSame('second', $stack->pop());
            $this->assertSame('first', $stack->pop());
            $this->assertTrue($stack->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test blocking pop receives data pushed from another coroutine.
     */
    public function testCoroutinePopBlocksUntilPush(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $stack = new Stack(1);
            $received = null;

            // Act - consumer blocks, producer pushes after delay
            \Swoole\Coroutine::create(function () use ($stack, &$received) {
                $received = $stack->pop(1.0);
            });

            \Swoole\Coroutine::create(function () use ($stack) {
                \Swoole\Coroutine::sleep(0.01);
                $stack->push('from-producer');
            });

            // Wait for child coroutines
            \Swoole\Coroutine::sleep(0.05);

            // Assert
            $this->assertSame('from-producer', $received);
            $this->assertTrue($stack->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }

    /**
     * Test blocking pop cleans indexStack to prevent stale access.
     *
     * Regression: blocking pop consumed from channel but left indexStack stale,
     * causing subsequent non-blocking pop to access unset objects.
     */
    public function testCoroutineBlockingPopCleansIndexStack(): void
    {
        \Swoole\Coroutine\run(function () {
            // Arrange
            Runtime::setCoroutineEnabled(true);
            $stack = new Stack(3);
            $received = null;

            // Act - C1 blocks waiting for data
            \Swoole\Coroutine::create(function () use ($stack, &$received) {
                $received = $stack->pop(1.0); // blocking pop
            });

            // C2 pushes after delay - wakes C1 via channel
            \Swoole\Coroutine::create(function () use ($stack) {
                \Swoole\Coroutine::sleep(0.01);
                $stack->push('item-A');
            });

            \Swoole\Coroutine::sleep(0.05);

            // C1 consumed 'item-A' via blocking pop
            $this->assertSame('item-A', $received);

            // Now push another item and pop non-blocking
            // Before fix: indexStack had stale entry, pop would access unset object
            $stack->push('item-B');
            $result = $stack->pop();

            // Assert
            $this->assertSame('item-B', $result);
            $this->assertTrue($stack->isEmpty());

            Runtime::setCoroutineEnabled(false);
        });
    }
}
