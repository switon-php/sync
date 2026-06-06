<?php

declare(strict_types=1);

namespace Switon\Sync;

/**
 * RAII lock token returned by MutexInterface::guard().
 *
 * Use when you want deterministic unlock in finally and automatic unlock on object destruction.
 *
 * @see \Switon\Sync\MutexInterface::guard()
 * @see \Switon\Sync\MutexInterface
 * @see \Switon\Sync\Mutex
 */
class Lock
{
    protected bool $released = false;

    public function __construct(
        protected MutexInterface $mutex
    ) {
    }

    /**
     * Releases the lock. Safe to call multiple times.
     */
    public function release(): void
    {
        if (!$this->released) {
            $this->released = true;
            $this->mutex->unlock();
        }
    }

    /**
     * Whether the lock has been released.
     */
    public function isReleased(): bool
    {
        return $this->released;
    }

    /**
     * Auto-release safety net when destroyed.
     */
    public function __destruct()
    {
        $this->release();
    }
}
