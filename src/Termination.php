<?php

declare(strict_types=1);

namespace Switon\Sync;

use Swoole\Coroutine\Channel as SwooleChannel;
use WeakMap;

/**
 * Default {@see TerminationInterface} backed by a weak set of registrants.
 *
 * Use when a lifecycle end (worker exit, command finish, test teardown) must
 * release every coroutine blocked on a registered channel. The registrant key
 * is the primitive object itself, held weakly, so no unregister() is needed and
 * garbage-collected primitives never leak.
 *
 * Road-signs:
 * - register() keys the weak map by the primitive object
 * - terminate() closes each live registrant once, then sets terminated=true
 * - isTerminated() is the replayable state check
 *
 * @see \Switon\Sync\TerminationInterface
 * @see \Switon\Sync\CloseableInterface
 * @see \Switon\Sync\Channel
 */
class Termination implements TerminationInterface
{
    /** @var WeakMap<object, true> */
    protected WeakMap $registrants;

    protected bool $terminated = false;

    public function __construct()
    {
        $this->registrants = new WeakMap();
    }

    public function register(CloseableInterface|SwooleChannel $registrant): void
    {
        $this->registrants[$registrant] = true;
    }

    public function terminate(): void
    {
        if ($this->terminated) {
            return;
        }

        $this->terminated = true;

        foreach ($this->registrants as $registrant => $_) {
            $registrant->close();
        }
    }

    public function isTerminated(): bool
    {
        return $this->terminated;
    }
}
