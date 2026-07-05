<?php

declare(strict_types=1);

namespace Switon\Sync;

/**
 * Contract for objects that can be closed to release waiting coroutines.
 *
 * Use when a producer-consumer primitive (for example a channel) must be
 * terminable from outside: closing it wakes every coroutine blocked on it.
 *
 * Road-signs:
 * - close() releases waiters and is idempotent
 * - isClosed() reports the closed state
 *
 * @see \Switon\Sync\Channel
 * @see \Switon\Sync\Termination
 */
interface CloseableInterface
{
    /**
     * Closes the primitive and wakes coroutines blocked on it.
     *
     * Implementations must be idempotent: calling close() on an already
     * closed primitive is a no-op.
     */
    public function close(): void;

    /**
     * Whether the primitive has been closed.
     */
    public function isClosed(): bool;
}
