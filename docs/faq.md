# Sync FAQ {#sync-faq}

Short answers for the most common `sync` questions.

> **Prerequisites:** [Sync Package](README.md)

## Getting Started {#getting-started}

### Which primitive should I use? {#which-primitive}

Use `Channel` for ordered handoff, `Mutex` for exclusive access, `WaitGroup` for completion waiting, and `Stack` for newest-first reuse.

### Should I use `Stack` for pooling? {#stack-for-pooling}

Only if you want a bare LIFO container. If you need borrow/return lifecycle, use [Pool](https://github.com/switon-php/pool/blob/main/docs/README.md).

## Runtime Behavior {#runtime-behavior}

### Why does `pop()` behave differently in coroutine and non-coroutine mode? {#pop-runtime-difference}

Coroutine mode can suspend. Non-coroutine mode cannot, so empty reads fail fast there.

### Why does `WaitGroup::wait()` fail outside coroutine mode? {#wait-non-coroutine}

Because the runtime cannot block and resume that path the same way. If the count is not zero, `wait()` raises `Switon\Sync\Exception\WaitTimeoutException`.

### Is `Mutex` reentrant? {#mutex-reentrant}

Yes, for the same coroutine in coroutine mode. That is the only reentrant case.
In non-coroutine mode, a second `lock()` / `guard()` attempt fails immediately.

### What should I use after `Channel::close()`? {#channel-close}

Keep draining the remaining items, then stop pushing. After the channel is closed and empty, `pop()` raises `Switon\Sync\Exception\ChannelClosedException`.
Calling `close()` again also raises `ChannelClosedException`.

### What happens if I call `add(0)` or `done()` too many times? {#waitgroup-bounds}

`add(0)` is rejected with `InvalidArgumentException`, and `done()` past zero raises `MisuseException`.

## Troubleshooting {#troubleshooting}

### Why does `pop($timeout)` return `false`? {#pop-false}

It can mean timeout. If `false` is also a real payload in your app, wrap the value before pushing it.

### Why can only one coroutine call `wait()` on one `WaitGroup`? {#single-waiter}

That is a design limit of the current primitive. Use separate `WaitGroup` instances if multiple flows need to wait independently.
When a wait is already in progress, the second caller gets `Switon\Sync\Exception\WaitGroupConcurrentException`.
