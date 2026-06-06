<?php

declare(strict_types=1);

namespace Switon\Sync\Exception;

use Switon\Sync\Exception as BaseException;

/**
 * Exception for push attempts on a full channel or stack.
 *
 * Raised when capacity is reached.
 * Fix: pop/reuse existing items first, or increase capacity if that fits the design.
 *
 * @see \Switon\Sync\Exception
 * @see \Switon\Sync\Channel
 * @see \Switon\Sync\Stack
 */
class ChannelFullException extends BaseException
{
}
