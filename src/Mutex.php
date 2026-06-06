<?php

declare(strict_types=1);

namespace Switon\Sync;

use Switon\Core\Runtime;
use Swoole\Coroutine;

/**
 * Default mutex implementation backed by a capacity-1 channel.
 *
 * Use when you need a lightweight lock that works in coroutine and non-coroutine runtimes.
 * Guidance: In coroutine mode the mutex is reentrant for the same coroutine id; in non-coroutine mode repeated `lock()` still fails fast.
 *
 * Road-signs:
 * - lock()/unlock() for explicit ownership
 * - guard() for RAII-style release
 * - capacity-1 Channel as the token source
 *
 * @see \Switon\Sync\MutexInterface
 * @see \Switon\Sync\Lock
 * @see \Switon\Sync\Channel
 */
class Mutex implements MutexInterface
{
    protected Channel $channel;
    protected ?int $ownerId = null;
    protected int $depth = 0;

    public function __construct()
    {
        $this->channel = new Channel(1);
        // Push a token to initialize the mutex as unlocked
        // The actual value doesn't matter; only its presence/absence is checked
        $this->channel->push(true);
    }

    public function lock(): void
    {
        $ownerId = $this->getCurrentOwnerId();
        if ($ownerId !== null && $this->ownerId === $ownerId) {
            $this->depth++;
            return;
        }

        $this->channel->pop();
        if ($ownerId !== null) {
            $this->ownerId = $ownerId;
            $this->depth = 1;
        }
    }

    public function unlock(): void
    {
        $ownerId = $this->getCurrentOwnerId();
        if ($ownerId !== null && $this->ownerId === $ownerId) {
            $this->depth--;
            if ($this->depth > 0) {
                return;
            }

            $this->ownerId = null;
        }

        $this->channel->push(true);
    }

    public function guard(): Lock
    {
        $this->lock();

        return new Lock($this);
    }

    public function isLocked(): bool
    {
        return $this->channel->isEmpty();
    }

    protected function getCurrentOwnerId(): ?int
    {
        if (!Runtime::isCoroutineEnabled() || !class_exists(Coroutine::class)) {
            return null;
        }

        $cid = Coroutine::getCid();
        return $cid >= 0 ? $cid : null;
    }
}
