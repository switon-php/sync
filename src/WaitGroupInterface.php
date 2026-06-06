<?php

declare(strict_types=1);

namespace Switon\Sync;

use Switon\Core\Exception\InvalidArgumentException;
use Switon\Core\Exception\MisuseException;
use Switon\Sync\Exception\WaitTimeoutException;

/**
 * Defines task coordination contract that waits until a counter returns to zero.
 *
 * Use when one flow must wait for a dynamic set of tasks to finish.
 * Limitation: only one coroutine may call wait() at a time.
 *
 * Road-signs:
 * - add()/done() for task accounting
 * - wait() for completion barrier
 * - count() for current pending task count
 *
 * @see \Switon\Sync\WaitGroup
 * @see \Switon\Sync\Exception\WaitTimeoutException
 * @see \Switon\Sync\Exception\WaitGroupConcurrentException
 */
interface WaitGroupInterface
{
    /**
     * Increments the task counter.
     *
     * Must be called before wait(). Each add() call should have
     * a corresponding number of done() calls.
     *
     * @param int $delta Number of tasks to add (must be >= 1)
     *
     * @throws InvalidArgumentException If delta is less than 1
     */
    public function add(int $delta = 1): void;

    /**
     * Decrements the task counter by one.
     *
     * When the counter reaches zero and a coroutine is blocked on wait(),
     * it will be woken up.
     *
     * @throws MisuseException If counter is already zero
     */
    public function done(): void;

    /**
     * Blocks until the task counter reaches zero.
     *
     * In coroutine mode, suspends the current coroutine until all tasks
     * call done() or the timeout expires. In non-coroutine mode, returns
     * immediately if counter is zero, or throws if non-zero.
     *
     * @param float|null $timeout Timeout in seconds (coroutine mode only)
     *
     * @throws WaitTimeoutException If timeout expires, or if the counter is non-zero in non-coroutine mode
     */
    public function wait(?float $timeout = null): void;

    /**
     * Returns the current task counter value.
     */
    public function count(): int;
}
