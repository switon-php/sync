<?php

declare(strict_types=1);

namespace Switon\Sync;

use Switon\Sync\Exception\ChannelClosedException;
use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;

/**
 * Defines the bounded FIFO channel contract for producer-consumer coordination.
 *
 * Use when tasks need a fixed-capacity handoff queue with consistent behavior in coroutine and non-coroutine runtimes.
 *
 * Road-signs:
 * - push()/pop() for handoff operations
 * - close() for shutdown semantics
 * - length()/capacity() for queue state
 *
 * @see \Switon\Sync\Channel
 * @see \Switon\Sync\Exception\ChannelFullException
 * @see \Switon\Sync\Exception\ChannelEmptyException
 * @see \Switon\Sync\Exception\ChannelClosedException
 */
interface ChannelInterface
{
    /**
     * Pushes data into the channel.
     *
     * @param mixed $data Data to push
     *
     * @throws ChannelClosedException If the channel is closed
     * @throws ChannelFullException If the channel is at capacity
     */
    public function push(mixed $data): void;

    /**
     * Pops data from the channel.
     *
     * In coroutine mode, supports blocking with an optional timeout.
     * In non-coroutine mode, throws immediately if the channel is empty.
     *
     * @param float|null $timeout Timeout in seconds (coroutine mode only)
     *
     * @return mixed The popped data, or false on timeout in coroutine mode
     *
     * @throws ChannelClosedException If the channel is closed and empty
     * @throws ChannelEmptyException If the channel is empty (non-coroutine mode)
     */
    public function pop(?float $timeout = null): mixed;

    /**
     * Closes the channel.
     *
     * After closing, push() throws ChannelClosedException. Pop() continues
     * returning remaining items; once empty, throws ChannelClosedException.
     * In coroutine mode, blocked pop() calls are woken and receive the exception.
     * Idempotent: calling close() on an already closed channel is a no-op.
     */
    public function close(): void;

    /**
     * Whether the channel has been closed.
     */
    public function isClosed(): bool;

    /**
     * Whether the channel has no items.
     */
    public function isEmpty(): bool;

    /**
     * Whether the channel has reached its capacity.
     */
    public function isFull(): bool;

    /**
     * Returns the current number of items in the channel.
     */
    public function length(): int;

    /**
     * Returns the maximum capacity of the channel.
     */
    public function capacity(): int;
}
