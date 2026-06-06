<?php

declare(strict_types=1);

namespace Switon\Sync\Exception;

use Switon\Sync\Exception as BaseException;

/**
 * Exception for concurrent wait() calls on one WaitGroup instance.
 *
 * Raised when more than one coroutine waits on the same wait group at the same time.
 * Fix: ensure one coordinator coroutine owns wait(), or use separate wait groups.
 *
 * @see \Switon\Sync\Exception
 * @see \Switon\Sync\WaitGroup
 */
class WaitGroupConcurrentException extends BaseException
{
}
