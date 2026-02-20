# Contributing Guide

## Installation

```bash
git clone https://github.com/adiachenko/4lmanac.git
cd 4lmanac

# Initialize the application
composer setup

# Install pre-commit and pre-push git hooks for formatting and static analysis
sh install-git-hooks.sh
```

### Environment Configuration

Follow the instructions in [README.md](README.md#environment-configuration) to set up the environment.

You'll need a valid HTTPS domain to go through the bootstrap process locally. The easiest options for local HTTPS are:

- Herd/Valet users can use [fwd.host service](https://herd.laravel.com/docs/macos/advanced-usage/social-auth)
- Docker users on MacOS can use [Orbstack](https://docs.orbstack.dev/).

You should also setup external test calendar for testing Google API interactions:

1. Create a dedicated calendar used only for external tests.
2. Copy its calendar ID into `GOOGLE_EXTERNAL_TEST_CALENDAR_ID`.
3. Keep real meetings out of that calendar. Tests create temporary events with `MCP_TMP_` prefix and clean them up.

### Git Hooks

Installed Git hooks:

- `pre-commit` runs `composer format`
- `pre-push` runs `composer analyse`

If you use [Fork](https://git-fork.com/) and hooks misbehave, see [this issue](https://github.com/fork-dev/Tracker/issues/996).

## Development Commands

| Command                  | Purpose                                                         |
| ------------------------ | --------------------------------------------------------------- |
| `composer test`          | Run Feature and Unit test suites (`pest --compact --parallel`). |
| `composer test:external` | Run the External test suite (`--testsuite=External`).           |
| `composer format`        | Run Laravel Pint and Prettier formatting.                       |
| `composer analyse`       | Run static analysis (`phpstan`).                                |
| `composer refactor`      | Apply Rector refactors.                                         |
| `composer coverage`      | Run tests with local coverage (`pest --coverage`).              |
| `composer coverage:herd` | Run coverage via Laravel Herd tooling.                          |

## Tests Structure and Conventions

The tests are organized into three test suites:

- `tests/Feature`: default starting point for validating application behavior. Test **from the outside in** by calling endpoints directly. Organize feature tests in subfolders by interface type: `Web`, `Api`, or `Mcp` for HTTP endpoints, `Console` for Artisan commands, etc.
- `tests/Unit`: tests for individual classes aligned with `app/` namespaces; strict isolation is not required (using database or involving related classes is acceptable).
- `tests/External`: tests real interactions with external services (no mocking), organized by provider or domain.

If unsure, always start with `Feature` tests and work inward toward `Unit` tests as complexity grows.

Do not place unmocked external integration checks in `Feature` or `Unit`; keep them in `tests/External`.

Test descriptions should follow the pattern: `<verb> <observable outcome> [when <condition>] [for <actor>]`.

## Additional Folders

Not strictly Laravel-official, but adopted as common practices in the community:

- `app/Actions`: invokable classes for encapsulating business logic.
- `app/Data`: data transfer objects (DTOs).
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
