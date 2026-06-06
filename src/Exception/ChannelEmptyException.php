<?php

declare(strict_types=1);

namespace Switon\Sync\Exception;

use Switon\Sync\Exception as BaseException;

/**
 * Exception for pop attempts on an empty channel or stack.
 *
 * Raised when no item is available for immediate pop.
 * Fix: push data first, or switch to timeout/blocking usage where supported.
 *
 * @see \Switon\Sync\Exception
 * @see \Switon\Sync\Channel
 * @see \Switon\Sync\Stack
 */
class ChannelEmptyException extends BaseException
{
}
