<?php

declare(strict_types=1);

namespace Switon\Sync;

use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;

/**
 * Default bounded LIFO stack implementation.
 *
 * Use when you need fixed-capacity last-in-first-out reuse with optional blocking pop in coroutine mode.
 *
 * Guidance: `pop(timeout)` returns `false` only for timeout-style waits; immediate empty pops still raise `ChannelEmptyException`.
 *
 * @see \Switon\Sync\StackInterface
 * @see \Switon\Sync\Channel
 * @see \Switon\Sync\Exception\ChannelFullException
 * @see \Switon\Sync\Exception\ChannelEmptyException
 */
class Stack implements StackInterface
{
    protected int $capacity;
    /** @var array<int, mixed> Stored objects */
    protected array $objects;
    /** @var array<int, int> Available index stack */
    protected array $indexStack;
    protected Channel $channel;

    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
        $this->objects = [];
        $this->indexStack = [];
        $this->channel = new Channel($capacity);
    }

    public function push(mixed $data): void
    {
        if ($this->channel->isFull()) {
            ChannelFullException::raise('Stack is full, cannot push more items');
        }

        $this->objects[] = $data;
        $index = array_key_last($this->objects);
        $this->indexStack[] = $index;
        $this->channel->push($index);
    }

    public function pop(?float $timeout = null): mixed
    {
        if (!empty($this->indexStack)) {
            $index = array_pop($this->indexStack);
            if (!$this->channel->isEmpty()) {
                $this->channel->pop();
            }
        } elseif ($timeout !== null) {
            try {
                $index = $this->channel->pop($timeout);
                if ($index === false) {
                    // Coroutine mode: Swoole Channel returns false on timeout
                    return false;
                }
            } catch (ChannelEmptyException $e) {
                // Non-coroutine mode: Channel throws when empty
                return false;
            }

            // Remove consumed index from indexStack to prevent stale access
            // (blocking pop bypasses indexStack, leaving orphaned entries)
            $key = array_search($index, $this->indexStack, true);
            if ($key !== false) {
                unset($this->indexStack[$key]);
            }
        } else {
            ChannelEmptyException::raise('Stack is empty, no items to pop');
        }

        $object = $this->objects[$index];
        unset($this->objects[$index]);
        return $object;
    }

    public function isEmpty(): bool
    {
        return $this->channel->isEmpty();
    }

    public function isFull(): bool
    {
        return $this->channel->isFull();
    }

    public function length(): int
    {
        return $this->channel->length();
    }

    public function capacity(): int
    {
        return $this->channel->capacity();
    }
}
