# 4lmanac

A Google Calendar MCP server that provides full calendar management — creating, updating, and deleting events — unlike the read-only integrations offered by ChatGPT and Claude.

## Installation

```bash
git clone https://github.com/adiachenko/4lmanac.git

cd example-app

# Initialize the application
composer install

# Optionally, install git hooks for development
sh install-git-hooks.sh
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
- `tests/Feature`: Validates broader application behavior through HTTP endpoints (`Web`/`Api`/`Mcp`, etc.), console commands (`Console`), or message handlers (`Message` if applicable). Feature tests should reflect your application's APIs.
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

## Google Calendar MCP Setup

### Prerequisites

1. Create a Google Cloud project.
2. Enable Google Calendar API in that project.
3. Configure OAuth consent screen (Google Auth Platform):
    - Audience: `External` (or `Internal` if your Workspace policy requires it).
    - Add test users while app is in testing mode.
    - Add scopes:
        - `openid`
        - `https://www.googleapis.com/auth/userinfo.email`
        - `https://www.googleapis.com/auth/userinfo.profile`
        - `https://www.googleapis.com/auth/calendar`
4. Create OAuth clients:
    - MCP client auth client(s) for ChatGPT and Claude callback URIs.
    - Bootstrap client for the shared calendar token flow.

### Environment Configuration

Set these variables in `.env`:

```env
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
GOOGLE_OAUTH_REDIRECT_URI=http://127.0.0.1:8000/oauth/google/bootstrap/callback
GOOGLE_OAUTH_ALLOWED_AUDIENCES=

GOOGLE_CALENDAR_DEFAULT_ID=primary

GOOGLE_EXTERNAL_TEST_CALENDAR_ID=
```

Field notes:

- `GOOGLE_OAUTH_CLIENT_ID` / `GOOGLE_OAUTH_CLIENT_SECRET` / `GOOGLE_OAUTH_REDIRECT_URI` are for the shared token bootstrap flow.
- `GOOGLE_OAUTH_ALLOWED_AUDIENCES` must include the OAuth client IDs used by MCP clients (ChatGPT/Claude), comma-separated.
- Required incoming OAuth scopes are fixed in app config: `openid` and `https://www.googleapis.com/auth/userinfo.email`.

### Shared Token Bootstrap (one-time)

1. Run:

```bash
php artisan app:google-calendar:bootstrap
```

2. Open the printed URL, sign in with the shared Google account, and grant access.
3. Google redirects to:
    - `GET /oauth/google/bootstrap/callback`
4. On success, the app stores token data in:
    - `storage/app/mcp/google-calendar-tokens.json`

Check token state:

```bash
php artisan app:google-calendar:token:status
```

### External Test Calendar Setup

1. Create a dedicated calendar used only for integration tests.
2. Copy its calendar ID into `GOOGLE_EXTERNAL_TEST_CALENDAR_ID`.
3. Keep real meetings out of that calendar.
4. Tests create temporary events with `MCP_TMP_` prefix and clean them up.

### Runbook

Local feature tests (mocked Google API):

```bash
php artisan test --compact --testsuite=Feature
```

External integration tests (real Google API):

```bash
php artisan test --compact --testsuite=External
```

### MCP Event Payload Contract

- Timed mode requires `start_at`, `end_at`, and `timezone`.
- Timed datetimes must be RFC3339 with explicit offsets.
- All-day mode requires `start_date` and `end_date` (Google-exclusive `end_date`).
- Do not mix timed fields and all-day fields in the same request.

### Common Errors

- `redirect_uri_mismatch`: OAuth client redirect URI does not exactly match `GOOGLE_OAUTH_REDIRECT_URI`.
- `invalid audience`: neither incoming token `aud` nor `azp` is listed in `GOOGLE_OAUTH_ALLOWED_AUDIENCES`.
- `insufficient_scope`: incoming client token is missing one of the required scopes (`openid`, `https://www.googleapis.com/auth/userinfo.email`).
- Missing refresh token during bootstrap: ensure auth URL requests `access_type=offline` and `prompt=consent`.

### Secret Rotation and Revocation

1. Rotate OAuth client secrets in Google Cloud.
2. Update `.env` values.
3. Delete `storage/app/mcp/google-calendar-tokens.json`.
4. Run bootstrap flow again to mint new shared refresh token.
