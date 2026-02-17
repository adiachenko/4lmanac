# Laravel Starter Kit

## What's Different from the Official Laravel Starter

- PHP `>=8.4` baseline with `declare(strict_types=1)` enforced by Pint.
- The default boilerplate is lean and backend/API-friendly rather than frontend-scaffold-heavy.
- `AppServiceProvider` comes preconfigured with useful safeguards like immutable dates and stricter Eloquent.
- Models are unguarded by default because we trust our validation more than our patience to keep `$fillable` in sync.
- Tooling is already wired: Pest (parallel tests), PHPStan + Larastan at max level, and Rector for refactors.
- Improved testing setup with clear conventions and a dedicated suite for external dependencies.
- Laravel Boost configuration prompts are included during setup, and sensible `.gitignore` rules for Boost are preconfigured. Comes bundled with .ai directory as a starting point for your own AI overrides.
- SQLite is set as the default database, offering a clean, minimal starting point that you can easily customize for your preferred database setup.
- Formatting is consistent out of the box: Pint for PHP and Prettier for everything else.

## Installation

Create application (replace `example-app` with desired project name):

```bash
composer create-project adiachenko/starter-kit-laravel --prefer-dist example-app
```

> Keep in mind that `composer create-project` already does most of the setup for you. It will install dependencies, create .env file, generate app key, and prompt you to configure Laravel Boost.

Navigate to your project and complete the setup:

```bash
cd example-app

# Initialize git repo
git init
sh install-git-hooks.sh

# Optionally, scaffold API routes with Sanctum or Passport (add --passport flag)
php artisan install:api
```

Installed Git hooks:

- `pre-commit` runs `composer format`
- `pre-push` runs `composer analyse`

If you use [Fork](https://git-fork.com/) and hooks misbehave, see [this issue](https://github.com/fork-dev/Tracker/issues/996).

## Development Commands

| Command                  | Purpose                                               |
| ------------------------ | ----------------------------------------------------- |
| `composer test`          | Run the test suite (`pest --compact --parallel`).     |
| `composer test:external` | Run the external test suite (`--testsuite=External`). |
| `composer format`        | Run Laravel Pint and Prettier formatting.             |
| `composer analyse`       | Run static analysis (`phpstan`).                      |
| `composer refactor`      | Apply Rector refactors.                               |
| `composer coverage`      | Run tests with local coverage (`pest --coverage`).    |
| `composer coverage:herd` | Run coverage via Laravel Herd tooling.                |

## Tests Structure and Conventions

The tests are organized into three test suites:

- `tests/Unit`: Tests individual classes that align with the `app/` namespace structure. These tests focus on a specific class, but do not require strict isolation. Using database or involving related classes is acceptable.
- `tests/Feature`: Validates broader application behavior through HTTP endpoints (`Web`/`Api`), console commands (`Console`), or message handlers. Feature tests should reflect your application's APIs.
- `tests/External`: Tests interactions with external (third-party) services, organized by provider or domain.

In most cases, start with `Feature` tests. Use `Unit` tests when you need to validate complex underlying logic in individual classes. Reserve `External` tests for checks on third-party services that cannot or should not be mocked.

Running `composer test` executes only the `Unit` and `Feature` suites. To run the external tests, use `composer test:external`.

Test descriptions should follow the pattern: `<verb> <observable outcome> [when <condition>] [for <actor>]`.

## Additional Folders

Not strictly Laravel-official, but enforced in the starter kit as common practices in the Laravel community:

- `app/Actions`: invokable classes for encapsulating business logic.
- `app/Data`: data transfer objects (DTOs) for encapsulating data.
- `app/Enums`: self-explanatory.
- `app/Services`: for calling external services.

## PhpStorm Setup

Recommended setup for consistent formatting:

- `Settings | Editor | Code Style`: ensure "Enable EditorConfig support" is checked.
- `Settings | PHP | Quality Tools | Laravel Pint`: use ruleset from `pint.json`
- `Settings | PHP | Quality Tools`: set Laravel Pint as external formatter
- `Settings | Tools | Actions on Save`: enable reformat on save
- `Settings | Languages & Frameworks | JavaScript | Prettier`: use automatic config, enable "Run on save", and prefer Prettier config. Include `md` in Prettier file extensions.

## VSCode/Cursor Setup

VSCode and Cursor will automatically detect formatting settings defined in the `.vscode/` folder – no additional setup is needed beyond installing the suggested extensions.
