# Switon Sync Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/sync/ci.yml?branch=main&label=CI)](https://github.com/switon-php/sync/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's synchronization primitives for bounded handoff, mutual exclusion, task joining, and LIFO reuse in coroutine and
non-coroutine runtimes.

## Highlights

- **Coroutine-friendly primitives:** the same contracts work in coroutine and non-coroutine runtimes.
- **Bounded channels:** `Channel` provides fixed-capacity FIFO handoff.
- **Scoped mutexes:** `Mutex::guard()` returns a lock for deterministic release.
- **Task joining:** `WaitGroup` blocks until registered work finishes.
- **LIFO reuse:** `Stack` provides fixed-capacity last-in-first-out reuse.

## Installation

```bash
composer require switon/sync
```

## Quick Start

```php
use Switon\Sync\Channel;
use Switon\Sync\Mutex;
use Switon\Sync\WaitGroup;

$channel = new Channel(2);
$channel->push('task-1');
$task = $channel->pop();

$mutex = new Mutex();
$lock = $mutex->guard();
try {
    // critical section
} finally {
    $lock->release();
}

$wg = WaitGroup::of(2);
$wg->done();
$wg->done();
$wg->wait();
```

Docs: https://github.com/switon-php/sync/blob/main/docs/README.md

## License

MIT.
