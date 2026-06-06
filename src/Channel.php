<?php

declare(strict_types=1);

namespace Switon\Sync;

use SplQueue;
use Switon\Core\Runtime;
use Switon\Sync\Exception\ChannelClosedException;
use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;
use Swoole\Coroutine\Channel as SwooleChannel;

/**
 * Default bounded FIFO channel implementation.
 *
 * Use when producers and consumers need fixed-capacity queueing with backpressure and close semantics.
 *
 * Guidance: in coroutine mode `pop(timeout)` may block and return `false` on timeout; in non-coroutine mode empty pops fail fast.
 *
 * @see \Switon\Sync\ChannelInterface
 * @see \Switon\Sync\Mutex
 * @see \Switon\Sync\Stack
 * @see \Switon\Sync\Exception\ChannelFullException
 * @see \Switon\Sync\Exception\ChannelEmptyException
 * @see \Switon\Sync\Exception\ChannelClosedException
 */
class Channel implements ChannelInterface
{
    protected int $capacity;
    protected int $length;
    protected bool $closed = false;
    /** @var list<mixed>|null */
    protected ?array $items = [];
    /** @var SwooleChannel|SplQueue<mixed>|null */
    protected null|SwooleChannel|SplQueue $queue = null;

    /**
     * Creates a channel with fixed capacity.
     */
    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
        $this->length = 0;
    }

    public function push(mixed $data): void
    {
        if ($this->closed) {
            ChannelClosedException::raise('Cannot push to a closed channel');
        }

        if ($this->length + 1 > $this->capacity) {
            ChannelFullException::raise('Channel is full, cannot push more items');
        }

        $this->length++;

        if ($this->items === null) {
            // Queue mode: items have been migrated to queue
            if ($this->queue === null) {
                // Queue not initialized yet, initialize it
                $this->queue = Runtime::isCoroutineEnabled() ? new SwooleChannel($this->capacity) : new SplQueue();
            }
            if (Runtime::isCoroutineEnabled()) {
                $this->queue->push($data);
            } else {
                $this->queue->enqueue($data);
            }
        } else {
            // Array mode: using items array
            $this->items[] = $data;
        }
    }

    public function pop(?float $timeout = null): mixed
    {
        if ($this->queue === null) {
            $this->queue = Runtime::isCoroutineEnabled() ? new SwooleChannel($this->capacity) : new SplQueue();
            foreach ($this->items as $item) {
                if (Runtime::isCoroutineEnabled()) {
                    $this->queue->push($item);
                } else {
                    $this->queue->enqueue($item);
                }
            }
            $this->items = null;

            // Close the newly created Swoole Channel if the Channel was already closed
            if ($this->closed && $this->queue instanceof SwooleChannel) {
                $this->queue->close();
            }
        }

        if (Runtime::isCoroutineEnabled()) {
            // Pre-check: avoid blocking on a closed empty channel
            if ($this->closed && $this->length === 0) {
                ChannelClosedException::raise('Channel is closed and empty');
            }

            $data = $timeout === null ? $this->queue->pop() : $this->queue->pop($timeout);
            if ($data === false && $this->length === 0) {
                if ($this->closed) {
                    ChannelClosedException::raise('Channel is closed and empty');
                }
                if ($timeout !== null) {
                    // Swoole Channel returns false on timeout
                    return false;
                }
            }
        } else {
            if ($this->length === 0) {
                if ($this->closed) {
                    ChannelClosedException::raise('Channel is closed and empty');
                }
                ChannelEmptyException::raise('Channel is empty, no items to pop');
            }

            $data = $this->queue->dequeue();
        }

        $this->length--;

        return $data;
    }

    public function close(): void
    {
        if ($this->closed) {
            ChannelClosedException::raise('Channel is already closed');
        }

        $this->closed = true;

        // Wake blocked coroutines by closing the underlying Swoole Channel
        if ($this->queue instanceof SwooleChannel) {
            $this->queue->close();
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function isEmpty(): bool
    {
        return $this->length === 0;
    }

    public function isFull(): bool
    {
        return $this->length === $this->capacity;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }
}
