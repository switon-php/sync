# Design Rationale {#design-rationale}

`switon/sync` keeps coordination small on purpose: separate primitives, explicit runtime differences, and no hidden pooling layer.

## See also {#see-also}

- [Sync Package](README.md) — usage, primitive selection, and common boundaries
- [FAQ](faq.md#sync-faq) — short answers for runtime behavior and edge cases
- [Pool Package](../../pool/docs/README.md) — borrow/return lifecycle when `Stack` is not enough

---

## Core Problem {#core-problem}

Applications usually need a few different coordination patterns:

- ordered handoff
- exclusive access
- wait-until-complete
- newest-first reuse

Those patterns are easy to blur into one helper. When that happens, shutdown behavior, ownership, and fairness become hard to see in review.

## Why These Four Primitives {#primitives}

Switon keeps the patterns separate.

- `Channel` owns ordered handoff.
- `Mutex` owns exclusive access.
- `WaitGroup` owns completion waiting.
- `Stack` owns LIFO reuse.

That split makes intent visible at the call site and keeps each primitive narrow.

## Runtime Differences Stay Visible {#runtime-differences-visible}

Coroutine runtimes can suspend. Non-coroutine runtimes cannot.

`sync` does not hide that difference behind one fake behavior. Empty reads, waits, and timeouts still surface different outcomes when the runtime cannot block.

## Why `Stack` Is Not `Pool` {#stack-pool}

`Stack` is only a bounded container with LIFO reuse.

Borrow/return lifecycle, owner scoping, and resource management belong to [Pool](../../pool/docs/README.md). Keeping that boundary separate avoids turning a simple reuse primitive into a pool manager.

## Deliberate Simplicity {#deliberate-simplicity}

`sync` is meant to be predictable, not clever.

- no single umbrella abstraction
- no hidden queue-to-lock conversion
- no built-in config surface
- no broadcast-style waiting

The trade-off is less convenience. The gain is that coordination stays explicit where it matters most.
