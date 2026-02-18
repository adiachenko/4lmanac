# Project Preferences

## General

- Prefer guard clauses and straight-line control flow; keep nesting shallow.
- Avoid boolean flags — prefer distinct methods, named option objects, or separate functions.
- Favor functional transformations (map/filter/reduce) using laravel collection methods over loops where they improve clarity. Use loops only when needed for early exits or managing state and only when you can't express the logic easily in a functional way.
- Avoid pass-through `config()` wrappers; read config inline at call sites with explicit defaults/type handling.

## Exceptions: Centralize, Don't Scatter

- Let exceptions bubble to the centralized boundary (global handler / middleware) by default.
- Don't add `try/catch` unless it changes behavior meaningfully: a fallback path, retry, or converting a runtime error to a domain-specific exception.
- Implement fallbacks with simple branching and sensible defaults — **don't use `try/catch/finally` for routine control flow**.
- Use `finally` only for guaranteed cleanup of resources you acquired (locks, temp files, external handles). Keep it minimal.

## Controllers

Use single-action (invokable) controllers named `VerbNounController` (e.g. `StorePostController`, `LoginUserController`). Verb-first, action-descriptive, RESTful when practical. Avoid noun-first names that hide the action (e.g. `PostController`, `UserLoginController`).
