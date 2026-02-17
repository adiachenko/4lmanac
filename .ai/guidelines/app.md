# App Guide

This file gives a fast, accurate overview of what this repository currently is and how it works.
Use it as the first-stop orientation before changing code.

## What This Application Does

- This is a Laravel 12 backend that exposes a Google Calendar integration through an MCP web endpoint.
- The app is centered on one integration boundary: `Google Calendar`.
- Core capability: list/search/create/update/delete events and compute availability slots.
- Data for Google tokens and idempotency is file-based under `storage/`, not database-backed.

## Current Scope

In scope today:

- MCP server for Google Calendar in `app/Mcp/Servers/GoogleCalendarServer.php`.
- MCP tools in `app/Mcp/Tools/*`.
- OAuth bootstrap + callback flow for shared Google refresh token.
- OAuth metadata endpoints for MCP clients.
- Middleware-based validation of incoming MCP bearer tokens.
- External integration tests for real Google Calendar API calls.

Out of scope today:

- Additional external providers (only Google Calendar is implemented).
- Background jobs/queues for calendar operations (calls are synchronous).
- Persistent DB models for calendar data.

## Architecture Map

### Routing Surface

- `routes/ai.php`
    - `Mcp::web('/mcp', GoogleCalendarServer::class)`
    - OAuth metadata routes:
        - `/.well-known/oauth-protected-resource/{path?}`
        - `/.well-known/oauth-authorization-server/{path?}`
- `routes/web.php`
    - `/` health-like welcome page.
    - `/oauth/google/bootstrap/callback` for shared token bootstrap completion.

### Core Components

- `app/Mcp/Servers/GoogleCalendarServer.php`
    - Registers tool list and global MCP instructions.
- `app/Http/Middleware/AuthenticateGoogleMcpClient.php`
    - Validates incoming bearer token via Google `tokeninfo`.
    - Enforces allowed OAuth audience and required scopes.
- `app/Services/GoogleCalendar/*`
    - `GoogleCalendarService`: domain-level input mapping to Google Calendar API endpoints.
    - `GoogleCalendarClient`: token resolution, refresh-on-401, HTTP transport.
    - `GoogleTokenStore`: file-backed token persistence with file locking.
    - `GoogleTokenRefresher`: refresh-token exchange flow.
    - `IdempotencyStore`: file-backed idempotent replay store with TTL.
    - `GoogleCalendarErrorMapper`: maps Google HTTP errors to stable app error codes.

### Tool Inventory

Registered tools:

- `list_events`
- `search_events`
- `create_event`
- `update_event`
- `delete_event`
- `find_availability`

## Request and Auth Flow

### MCP Request Flow

1. MCP client sends request to `/mcp` with `Authorization: Bearer <token>`.
2. Middleware validates token by calling configured Google token-info endpoint.
3. Middleware checks:
    - token audience in `services.google_mcp.oauth_allowed_audiences`
    - token scopes include all `services.google_mcp.mcp_required_scopes`
4. Tool validates payload, calls `GoogleCalendarService`, and returns structured MCP content.
5. Service uses `GoogleCalendarClient` for Google API requests.
6. If access token is expired/invalid, client refreshes shared token once and retries.

### Shared Token Bootstrap Flow

1. Run `php artisan app:google-calendar:bootstrap`.
2. Command prints Google consent URL and stores anti-CSRF state in:
    - `storage/app/mcp/google-bootstrap-state.json`
3. Google redirects to `/oauth/google/bootstrap/callback`.
4. Callback validates state, exchanges code for tokens, and persists tokens via `GoogleTokenStore`.
5. Check status with `php artisan app:google-calendar:token:status`.

## Contract Invariants (Important)

### Time Handling

- Never assume UTC unless explicitly requested.
- Timed event mode:
    - Requires `start_at`, `end_at`, `timezone`.
    - `start_at`/`end_at` must be RFC3339 with explicit offsets.
    - Datetime offset must match the supplied IANA timezone at that instant.
- All-day event mode:
    - Uses `start_date`, `end_date`.
    - `end_date` is exclusive (single-day event means next day).
    - Do not mix timed and all-day fields.

### Idempotency

- Required for `create_event`, `update_event`, and `delete_event` via `idempotency_key`.
- Idempotency key includes operation + payload hash (not key alone).
- Entries expire after 24 hours.
- Store file: `storage/app/mcp/idempotency.json` (configurable path).

### Error Shape

Google/API failures are normalized to structured errors with:

- `code` (e.g., `RATE_LIMITED`, `NOT_FOUND`, `GOOGLE_REAUTH_REQUIRED`)
- `message`
- `http_status`
- `context` (includes Google reason when available)

## Configuration and Runtime State

Primary config: `config/services.php` (`google_mcp` key).

Relevant env vars:

- `GOOGLE_OAUTH_CLIENT_ID`
- `GOOGLE_OAUTH_CLIENT_SECRET`
- `GOOGLE_OAUTH_REDIRECT_URI`
- `GOOGLE_OAUTH_ALLOWED_AUDIENCES` (comma-separated)
- `GOOGLE_CALENDAR_DEFAULT_ID` (default `primary`)
- `GOOGLE_EXTERNAL_TEST_CALENDAR_ID`

Runtime files (all should remain ignored by git):

- `storage/app/mcp/google-calendar-tokens.json`
- `storage/app/mcp/idempotency.json`
- `storage/app/mcp/google-bootstrap-state.json`

## Testing Map

- `tests/Feature/Mcp/*`
    - Tool validation and behavior with mocked Google API.
    - Auth middleware behavior and OAuth metadata routes.
    - Idempotent replay behavior.
- `tests/Unit/Services/GoogleCalendar/*`
    - Service-level parameter mapping and storage path semantics.
- `tests/External/Google/GoogleCalendarApiTest.php`
    - Real Google API integration checks (guarded by env/token prerequisites).

## Where To Update What

When changing Google MCP behavior, keep these in sync in the same task:

1. Code under `app/Mcp`, `app/Services/GoogleCalendar`, and related routes/middleware/controllers.
2. Setup/runbook in `README.md`.
3. Env keys/defaults in `.env.example` when config changes.
4. Storage ignore rules in `.gitignore` when new runtime artifacts are introduced.
5. Feature/Unit/External tests that prove the contract.
