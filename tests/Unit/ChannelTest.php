<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Sync\Channel;
use Switon\Sync\Exception\ChannelClosedException;
use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;
use PHPUnit\Framework\TestCase;

class ChannelTest extends TestCase
{
    public function testConstructCreatesChannelWithCapacity(): void
    {
        $channel = new Channel(5);

        $this->assertSame(5, $channel->capacity());
        $this->assertSame(0, $channel->length());
        $this->assertTrue($channel->isEmpty());
        $this->assertFalse($channel->isFull());
    }

    public function testPushAddsItemToChannel(): void
    {
        $channel = new Channel(2);

        $channel->push('item1');

        $this->assertSame(1, $channel->length());
        $this->assertFalse($channel->isEmpty());
        $this->assertFalse($channel->isFull());
    }

    public function testPushThrowsExceptionWhenFull(): void
    {
        $channel = new Channel(1);

        $channel->push('item1');

        $this->expectException(ChannelFullException::class);

        $channel->push('item2');
    }

    public function testPushThrowsExceptionWhenCapacityIsZero(): void
    {
        $channel = new Channel(0);

        $this->assertTrue($channel->isFull());
        $this->expectException(ChannelFullException::class);
        $channel->push('item');
    }

    public function testPopReturnsItemFromChannel(): void
    {
        $channel = new Channel(2);

        $channel->push('item1');
        $channel->push('item2');

        // Channel uses FIFO (queue) behavior: first pushed item comes out first
        $this->assertSame('item1', $channel->pop());
        $this->assertSame(1, $channel->length());

        $this->assertSame('item2', $channel->pop());
        $this->assertSame(0, $channel->length());
    }

    public function testPopThrowsExceptionWhenEmpty(): void
    {
        $channel = new Channel(1);

        $this->expectException(ChannelEmptyException::class);

        $channel->pop();
    }

    public function testIsEmptyReturnsTrueWhenEmpty(): void
    {
        $channel = new Channel(1);

        $this->assertTrue($channel->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasItems(): void
    {
        $channel = new Channel(1);

        $channel->push('item');

        $this->assertFalse($channel->isEmpty());
    }

    public function testIsFullReturnsTrueWhenFull(): void
    {
        $channel = new Channel(1);

        $channel->push('item');

        $this->assertTrue($channel->isFull());
    }

    public function testIsFullReturnsFalseWhenNotFull(): void
    {
        $channel = new Channel(2);

        $channel->push('item');

        $this->assertFalse($channel->isFull());
    }

    public function testLengthReturnsCorrectLength(): void
    {
        $channel = new Channel(3);

        $this->assertSame(0, $channel->length());

        $channel->push('item1');
        $this->assertSame(1, $channel->length());

        $channel->push('item2');
        $this->assertSame(2, $channel->length());

        $channel->pop();
        $this->assertSame(1, $channel->length());
    }

    public function testCapacityReturnsCorrectCapacity(): void
    {
        $channel = new Channel(5);

        $this->assertSame(5, $channel->capacity());
    }

    public function testPushPopSequence(): void
    {
        $channel = new Channel(3);

        $channel->push('a');
        $channel->push('b');
        $channel->push('c');

        // Channel uses FIFO (queue) behavior: first pushed item comes out first
        $this->assertSame('a', $channel->pop());
        $this->assertSame('b', $channel->pop());
        $this->assertSame('c', $channel->pop());

        $this->assertTrue($channel->isEmpty());
    }

    public function testPushPopWithDifferentTypes(): void
    {
        $channel = new Channel(1);

        $channel->push('string');
        $this->assertSame('string', $channel->pop());

        $channel->push(123);
        $this->assertSame(123, $channel->pop());

        $channel->push(['array' => 'value']);
        $this->assertSame(['array' => 'value'], $channel->pop());
    }

    public function testFifoOrderIsPreservedAfterInterleavedPushAndPop(): void
    {
        $channel = new Channel(3);

        $channel->push('a');
        $channel->push('b');
        $this->assertSame('a', $channel->pop());
        $channel->push('c');

        $this->assertSame('b', $channel->pop());
        $this->assertSame('c', $channel->pop());
    }

    // ========== Close Tests ==========

    public function testCloseMarksChannelAsClosed(): void
    {
        // Arrange
        $channel = new Channel(3);
        $this->assertFalse($channel->isClosed());

        // Act
        $channel->close();

        // Assert
        $this->assertTrue($channel->isClosed());
    }

    public function testPushThrowsOnClosedChannel(): void
    {
        // Arrange
        $channel = new Channel(3);
        $channel->close();

        // Act & Assert
        $this->expectException(ChannelClosedException::class);
        $channel->push('data');
    }

    public function testPopRemainingItemsAfterClose(): void
    {
        // Arrange
        $channel = new Channel(3);
        $channel->push('a');
        $channel->push('b');

        // Act
        $channel->close();

        // Assert - can still pop remaining items
        $this->assertSame('a', $channel->pop());
        $this->assertSame('b', $channel->pop());
    }

    public function testPopThrowsClosedExceptionOnEmptyClosedChannel(): void
    {
        // Arrange
        $channel = new Channel(3);
        $channel->push('item');
        $channel->pop();
        $channel->close();

        // Act & Assert
        $this->expectException(ChannelClosedException::class);
        $channel->pop();
    }

    public function testDoubleCloseIsNoOp(): void
    {
        // Arrange
        $channel = new Channel(1);
        $channel->close();

        // Act — must be idempotent for termination broadcasts
        $channel->close();

        // Assert
        $this->assertTrue($channel->isClosed());
    }

    public function testPopThrowsClosedBeforeEmptyException(): void
    {
        // Arrange - closed + empty should throw ChannelClosedException, not ChannelEmptyException
        $channel = new Channel(1);
        $channel->close();

        // Act & Assert
        $this->expectException(ChannelClosedException::class);
        $channel->pop();
    }

    public function testChannelNotFullAfterPopAllowsAnotherPush(): void
    {
        $channel = new Channel(1);
        $channel->push('first');
        $this->assertTrue($channel->isFull());

        $this->assertSame('first', $channel->pop());
        $this->assertFalse($channel->isFull());

        $channel->push('second');
        $this->assertSame('second', $channel->pop());
    }

    public function testLengthTracksBufferedItemsUntilFullyDrainedAfterClose(): void
    {
        $channel = new Channel(3);
        $channel->push('a');
        $channel->push('b');
        $channel->close();

        $this->assertSame(2, $channel->length());
        $this->assertFalse($channel->isEmpty());

        $this->assertSame('a', $channel->pop());
        $this->assertSame(1, $channel->length());

        $this->assertSame('b', $channel->pop());
        $this->assertSame(0, $channel->length());
        $this->assertTrue($channel->isEmpty());
    }

    public function testFifoOrderAfterPartialDrainWhenMigratedToInternalQueue(): void
    {
        $channel = new Channel(3);
        $channel->push('a');
        $channel->push('b');
        $channel->push('c');

        $this->assertSame('a', $channel->pop());

        $channel->push('d');

        $this->assertSame('b', $channel->pop());
        $this->assertSame('c', $channel->pop());
        $this->assertSame('d', $channel->pop());
        $this->assertTrue($channel->isEmpty());
    }

    public function testPopAfterCloseStillThrowsWhenQueueHadBeenEmptied(): void
    {
        $channel = new Channel(1);
        $channel->push('only');
        $channel->close();
        $this->assertSame('only', $channel->pop());

        $this->expectException(ChannelClosedException::class);
        $channel->pop();
    }
}
