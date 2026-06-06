<?php

declare(strict_types=1);

namespace Switon\Sync\Exception;

use Switon\Sync\Exception as BaseException;

/**
 * Exception for WaitGroup wait operations that cannot complete in time.
 *
 * Raised on wait timeout, or when wait is requested in non-coroutine mode with pending tasks.
 * Fix: make sure all tasks call done(), extend the timeout, or avoid blocking in non-coroutine mode.
 *
 * @see \Switon\Sync\Exception
 * @see \Switon\Sync\WaitGroup
 */
class WaitTimeoutException extends BaseException
{
}
