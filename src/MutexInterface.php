<?php

declare(strict_types=1);

namespace Switon\Sync;

use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;

/**
 * Defines the mutex contract for protecting critical sections.
 *
 * Use when shared state must be accessed by one execution path at a time.
 *
 * Road-signs:
 * - lock()/unlock() for explicit critical sections
 * - guard() for scoped release via Lock
 * - isLocked() for state inspection
 *
 * @see \Switon\Sync\Mutex
 * @see \Switon\Sync\Lock
 * @see \Switon\Sync\ChannelInterface
 * @see \Switon\Id\Snowflake Typical consumer
 * @see \Switon\Pooling\PoolManager Typical consumer
 * @see \Switon\Rendering\Renderer Typical consumer
 */
interface MutexInterface
{
    /**
     * Acquires the lock.
     *
     * Blocks in coroutine mode if already locked; throws in non-coroutine mode.
     *
     * @throws ChannelEmptyException If already locked (non-coroutine mode)
     */
    public function lock(): void;

    /**
     * Releases the lock.
     *
     * @throws ChannelFullException If not currently locked
     */
    public function unlock(): void;

    /**
     * Locks the mutex and returns a Lock token (RAII pattern).
     *
     * The returned Lock releases the mutex on {@see Lock::release()} or
     * automatically when destroyed (RAII safety net).
     *
     * @return Lock Lock token for releasing the mutex
     *
     * @throws ChannelEmptyException If already locked (non-coroutine mode)
     */
    public function guard(): Lock;

    /**
     * Whether the mutex is currently locked.
     */
    public function isLocked(): bool;
}
