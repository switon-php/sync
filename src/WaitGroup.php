<?php

declare(strict_types=1);

namespace Switon\Sync;

use Switon\Core\Exception\InvalidArgumentException;
use Switon\Core\Exception\MisuseException;
use Switon\Core\Runtime;
use Switon\Sync\Exception\WaitGroupConcurrentException;
use Switon\Sync\Exception\WaitTimeoutException;
use Swoole\Coroutine\Channel as SwooleChannel;

/**
 * Default wait-group implementation for coordinating task completion.
 *
 * Use when one caller must block until all registered tasks call done().
 *
 * Guidance: only one coroutine may wait on the same instance at a time; non-coroutine mode cannot block with pending tasks; call <code>add()</code> with <code>delta >= 1</code> (including <code>WaitGroup::of()</code>).
 *
 * @see \Switon\Sync\WaitGroupInterface
 * @see \Switon\Sync\Exception\WaitTimeoutException
 * @see \Switon\Sync\Exception\WaitGroupConcurrentException
 */
class WaitGroup implements WaitGroupInterface
{
    protected int $counter = 0;
    protected ?SwooleChannel $signal = null;
    protected bool $waiting = false;

    protected function __construct()
    {
    }

    /**
     * Creates a new WaitGroup with an initial task count.
     *
     * <code>
     * $wg = WaitGroup::of(3);
     * </code>
     *
     * @param int $count Number of tasks to wait for (must be >= 1)
     *
     * @return static
     *
     * @throws \Switon\Core\Exception\InvalidArgumentException If count is less than 1
     */
    public static function of(int $count): static
    {
        $wg = new static();
        $wg->add($count);

        return $wg;
    }

    /**
     * Increments the task counter.
     *
     * @param int $delta Number of tasks to add (must be >= 1)
     */
    public function add(int $delta = 1): void
    {
        if ($delta < 1) {
            InvalidArgumentException::raise('Delta must be a positive integer, got {delta}', ['delta' => $delta]);
        }

        $this->counter += $delta;
    }

    public function done(): void
    {
        if ($this->counter <= 0) {
            MisuseException::raise('Cannot call done() when WaitGroup counter is already zero');
        }

        $this->counter--;

        if ($this->counter === 0 && $this->signal !== null) {
            $this->signal->push(true);
        }
    }

    public function wait(?float $timeout = null): void
    {
        if ($this->counter === 0) {
            return;
        }

        if (!Runtime::isCoroutineEnabled()) {
            WaitTimeoutException::raise(
                'WaitGroup counter is ' . $this->counter . ', cannot block in non-coroutine mode'
            );
        }

        // Prevent concurrent wait() calls
        if ($this->waiting) {
            WaitGroupConcurrentException::raise(
                'WaitGroup already has an active waiter. Only one coroutine may call wait() at a time.'
            );
        }

        // Coroutine mode: block until counter reaches zero or timeout
        $this->waiting = true;
        $this->signal = new SwooleChannel(1);

        try {
            $result = $timeout === null ? $this->signal->pop() : $this->signal->pop($timeout);

            if ($result === false) {
                WaitTimeoutException::raise(
                    'WaitGroup wait timed out, counter is still ' . $this->counter
                );
            }
        } finally {
            $this->signal = null;
            $this->waiting = false;
        }
    }

    public function count(): int
    {
        return $this->counter;
    }
}
