# Testing Guidelines

## Implementation Order

Start implementation with a test. Write a failing test that describes the desired behavior, then implement the code to make it pass.

## Test Suite Selection

Choose the test suite by intent:

- `tests/Feature`: default starting point for validating application behavior through HTTP endpoints (`Web`/`Api`), console commands (`Console`), or message handlers.
- `tests/Unit`: focused tests for individual classes aligned with `app/` namespaces; strict isolation is not required.
- `tests/External`: third-party integration tests, organized by provider or domain (favor existing project conventions).

Do not place unmocked third-party integration checks in `Feature` or `Unit`; keep them in `tests/External`.

## Test File Naming

- Always use the `Test.php` suffix.
- **Unit tests**: Mirror the class under test. Use `{ClassName}Test.php` (e.g. `UserTest.php`, `CreateUserJobTest.php`).
- **Feature tests**: Mirror the controller (controller action) or command name. Use `{ActionOrCommandName}Test.php` (e.g. `StoreUserTest.php`, `HealthcheckCommandTest.php`). Each controller action must have a separate test file.
- **External tests**: Use descriptive names that reflect what is being tested (e.g. `StripeWebhookTest.php`).

## Test Description Naming

Test descriptions should follow:

`<present-tense verb> <observable outcome> [when <condition>] [for <actor>]`

Example:

```php
test('returns validation errors when required registration fields are missing', function () {});
```

## Prefer explicit assertions over chained helpers (debuggability)

Prefer explicit, single-purpose assertions (one per line) over chained convenience helpers in tests.

## Test Execution

Run the smallest test scope that meaningfully proves the change, then expand scope when risk increases.

```bash
# Filter by test description (regex)
php artisan test --filter="hides sensitive attributes"
php artisan test --filter="has home"

# Single file
php artisan test tests/Unit/Models/UserTest.php

# Test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=External
```

## Verification

After making changes, run `composer format` and `composer analyse` before finalizing.
