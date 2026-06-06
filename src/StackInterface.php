<?php

declare(strict_types=1);

namespace Switon\Sync;

use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;

/**
 * Defines the bounded LIFO stack contract with optional blocking pop behavior.
 *
 * Use when recent items should be reused first, for example in object pools with fixed capacity.
 *
 * Road-signs:
 * - push()/pop() for reuse operations
 * - length()/capacity() for pool state
 * - ChannelEmptyException / ChannelFullException
 *
 * @see \Switon\Sync\Stack
 * @see \Switon\Sync\ChannelInterface
 * @see \Switon\Sync\Exception\ChannelFullException
 * @see \Switon\Sync\Exception\ChannelEmptyException
 */
interface StackInterface
{
    /**
     * Pushes data onto the top of the stack.
     *
     * @param mixed $data Data to push
     *
     * @throws ChannelFullException If the stack is at capacity
     */
    public function push(mixed $data): void;

    /**
     * Pops data from the top of the stack.
     *
     * @param float|null $timeout Timeout in seconds for blocking pop (coroutine mode)
     *
     * @return mixed The popped data, or false on timeout
     *
     * @throws ChannelEmptyException If the stack is empty and no timeout given
     */
    public function pop(?float $timeout = null): mixed;

    /**
     * Whether the stack has no items.
     */
    public function isEmpty(): bool;

    /**
     * Whether the stack has reached its capacity.
     */
    public function isFull(): bool;

    /**
     * Returns the current number of items in the stack.
     */
    public function length(): int;

    /**
     * Returns the maximum capacity of the stack.
     */
    public function capacity(): int;
}
