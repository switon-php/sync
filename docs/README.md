# Sync Package {#sync-package}

`switon/sync` provides four in-process coordination primitives for FIFO handoff, mutual exclusion, completion waiting, and LIFO reuse.

**Key features:**

- **`Channel` for FIFO handoff** — bounded push/pop with explicit close semantics
- **`Mutex` for critical sections** — `guard()` gives a short, exception-safe lock path
- **`WaitGroup` for completion** — wait until a known set of tasks finishes
- **`Stack` for LIFO reuse** — fixed-capacity reuse when newest-first is the right fit
- **Same API across runtimes** — coroutine and non-coroutine code call the same methods, but blocking behavior still differs

---

## Installation {#installation}

**Requirements:** PHP 8.3+

```bash
composer require switon/sync
```

---

## Quick Start {#quick-start}

Pick the primitive that matches the coordination pattern.

```php
use Switon\Sync\Channel;
use Switon\Sync\Mutex;
use Switon\Sync\WaitGroup;

$channel = new Channel(2);
$channel->push('job-1');
$job = $channel->pop();

$mutex = new Mutex();
$lock = $mutex->guard();
try {
    // critical section
} finally {
    $lock->release();
}

$waitGroup = WaitGroup::of(2);
// task A eventually calls done()
// task B eventually calls done()
$waitGroup->wait();
```

---

## Core Concepts {#core-concepts}

### Choosing A Primitive {#choosing-a-primitive}

- Use `Channel` when one flow hands off work to another in order.
- Use `Mutex` when one flow must own a critical section at a time.
- Use `WaitGroup` when one flow must wait for a known count of completions.
- Use `Stack` when reuse should prefer the newest returned item.

### `Channel` {#channel}

`Channel` is a bounded FIFO queue.

- `push()` adds one item.
- `pop()` removes the oldest item.
- `close()` stops future pushes.
- queued items stay readable after `close()`.
- `capacity` must be at least `1`; `0` makes the first `push()` fail with `ChannelFullException`.

Runtime behavior differs:

- In coroutine mode, empty `pop()` can wait.
- In non-coroutine mode, empty `pop()` fails fast.
- `pop($timeout)` returns `false` on timeout in coroutine mode.

Useful checks: `isEmpty()`, `isFull()`, `length()`, `capacity()`, `isClosed()`.

### `Mutex` {#mutex}

`Mutex` protects a critical section. `guard()` is the normal path.

- `guard()` returns `Switon\Sync\Lock`.
- `release()` ends the lock.
- the lock also releases on destruction.
- in coroutine mode, the same coroutine can re-enter the mutex.
- in non-coroutine mode, a second `lock()` / `guard()` attempt fails immediately.

Use `unlock()` only when you need the direct lock API. For most code, keep the `Lock` in a local variable and release it in `finally`.

### `WaitGroup` {#waitgroup}

`WaitGroup` waits until a counter returns to zero.

- `WaitGroup::of($count)` starts with a positive count.
- `add()` raises the count and requires a delta of at least `1`.
- `done()` lowers the count.
- `wait()` blocks until the count reaches zero.
- only one coroutine may wait on one instance at a time.
- calling `done()` when the counter is already zero raises `MisuseException`.

Useful check: `count()`.

### `Stack` {#stack}

`Stack` is a bounded LIFO container.

- `push()` stores the newest item at the top.
- `pop()` returns the newest item first.
- `pop($timeout)` can wait in coroutine mode and returns `false` on timeout.

Use `Stack` when newest-first reuse is the right fit. If you need borrow/return lifecycle, use [Pool](https://github.com/switon-php/pool/blob/main/docs/README.md) instead.

---

## Keep in Mind {#keep-in-mind}

- `Channel` is for handoff, not for ownership.
- `Mutex` is for short critical sections, not for long-running work.
- `WaitGroup` coordinates completion only; it does not carry results.
- `Stack` is not a resource pool.
- `Sync` has no `switon.yml` surface.
- `Sync` still needs coroutine runtime support for blocking behavior.

---

## Next {#next}

- **Need setup questions answered quickly:** [FAQ](faq.md#sync-faq)
- **Need design trade-offs:** [Design Rationale](design.md#design-rationale)
- **Need borrow/return pooling:** [Pool Package](https://github.com/switon-php/pool/blob/main/docs/README.md)
