<?php

declare(strict_types=1);

namespace Switon\Sync;

/**
 * Base exception for the Sync component.
 *
 * Use when synchronization-specific failures need one package-level exception root.
 *
 * @see \Switon\Sync\Channel
 * @see \Switon\Sync\Stack
 * @see \Switon\Sync\WaitGroup
 * @see \Switon\Core\Exception
 */
class Exception extends \Switon\Core\Exception
{
}
