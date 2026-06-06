<?php

declare(strict_types=1);

namespace Switon\Sync\Exception;

use Switon\Sync\Exception as BaseException;

/**
 * Exception for operations that require an open channel.
 *
 * Raised when pushing to a closed channel, or popping from a closed empty channel.
 * Fix: keep the channel open while producers/consumers are active, or stop using it after close().
 *
 * @see \Switon\Sync\Exception
 * @see \Switon\Sync\Channel
 */
class ChannelClosedException extends BaseException
{
}
