<?php

declare(strict_types=1);

namespace Switon\Sync;

use Swoole\Coroutine\Channel as SwooleChannel;

/**
 * Broadcasts process termination to every registered closeable primitive.
 *
 * Use when long-lived coroutines block on a channel and must be released when
 * the process (or a lifecycle phase) stops. Termination is the counterpart of a
 * coordinator: instead of calling shutdown on each worker, the shared primitive
 * is closed and every blocked pop() wakes on its own.
 *
 * Road-signs:
 * - register() adds a closeable primitive (sync Channel or Swoole Channel)
 * - terminate() closes all registered primitives, then stays terminated (idempotent)
 * - isTerminated() reports whether terminate() has been called
 *
 * @see \Switon\Sync\Termination
 * @see \Switon\Sync\CloseableInterface
 */
interface TerminationInterface
{
    /**
     * Registers a closeable primitive whose blocked waiters terminate() must wake.
     *
     * The registrant is held weakly: it needs no unregister call and is removed
     * automatically when garbage collected.
     *
     * @param CloseableInterface|SwooleChannel $registrant
     */
    public function register(CloseableInterface|SwooleChannel $registrant): void;

    /**
     * Broadcasts termination: closes every registered primitive.
     *
     * Safe to call when nothing is registered; idempotent after the first call.
     */
    public function terminate(): void;

    /**
     * Whether terminate() has been called.
     */
    public function isTerminated(): bool;
}
