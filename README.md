# 📆 4lmanac

Google Calendar MCP server with full calendar management, allowing you to create, update, and delete events, going beyond the read-only integrations officially offered by ChatGPT and Claude.

## Client Compatibility 🤝

At the time of writing **Claude is the recommended choice for custom MCPs**. While both use the same API with the same feature set, ChatGPT's connector is situationally limited, notably slower, and constrained by restrictive security policies — these are ChatGPT's own limitations, not issues with this project.

## Deployment Requirements 🚀

- Google Cloud project with the Google Calendar API enabled (no billing required, setup is described below).
- Valid HTTPS domain.
- Web server with PHP 8.5+.
    - If you're familiar with Docker, you can use `compose.yml` supplied in this repository (based on [frankenstack](https://github.com/adiachenko/frankenstack) image) by tweaking configuration to your needs.

## Setup 🛠️

### 1. Google Cloud Prerequisites

1. Create a Google Cloud project and enable the **Google Calendar API**.
2. Configure the OAuth consent screen under **Google Auth Platform** (Branding / Audience / Data Access):
    - **Audience**: set to `External` (`Internal` requires Google Workspace but also works).
    - Add your Google account as a test user and leave the app in testing mode.
    - Add your domain (e.g. `your-domain.com`) under Authorized domains.
    - Add the following scopes:
        - `openid`
        - `https://www.googleapis.com/auth/userinfo.email`
        - `https://www.googleapis.com/auth/userinfo.profile`
        - `https://www.googleapis.com/auth/calendar`

### 2. Create OAuth Client for Bootstrap

This client is used for the one-time shared token bootstrap flow.

1. Go to **Google Auth Platform > Clients > Create client**.
2. Application type: **Web application**.
3. Name: e.g. `mcp-calendar-bootstrap`.
4. Authorized redirect URI: `https://your-domain.com/oauth/google/bootstrap/callback`
5. Save and copy the `client_id` and `client_secret`.

Add these to your `.env`:

```env
GOOGLE_OAUTH_CLIENT_ID=your-client-id
GOOGLE_OAUTH_CLIENT_SECRET=your-client-secret
GOOGLE_OAUTH_REDIRECT_URI=https://your-domain.com/oauth/google/bootstrap/callback
```

Optionally, override the calendar used by the integration (defaults to `primary`):

```env
GOOGLE_CALENDAR_DEFAULT_ID=primary
```

### 3. Create OAuth Client(s) for MCP Clients

Create a separate OAuth client for each MCP client (Claude, ChatGPT, or both).

1. Go to **Google Auth Platform > Clients > Create client**.
2. Application type: **Web application**.
3. Set Authorized redirect URIs per client:

    **Claude** (e.g. `mcp-claude-auth`):
    - `https://claude.ai/api/mcp/auth_callback`
    - `https://claude.com/api/mcp/auth_callback`

    **ChatGPT** (e.g. `mcp-chatgpt-auth`):
    - `https://chatgpt.com/connector_platform_oauth_redirect`
    - `https://platform.openai.com/apps-manage/oauth`

4. Save and copy each client's `client_id` and `client_secret` — you'll need them when connecting the MCP client.

Whitelist the client IDs in `.env` (comma-separated):

```env
GOOGLE_OAUTH_ALLOWED_AUDIENCES=your-claude-client-id,your-chatgpt-client-id
```

### 4. Bootstrap Shared Token (One-Time)

1. Run the bootstrap command:

    ```bash
    php artisan app:google-calendar:bootstrap
    ```

2. Open the printed URL, sign in with the shared Google account, and grant access.
3. On success, the app stores token data in `storage/app/mcp/google-calendar-tokens.json`.

Check token status at any time:

```bash
php artisan app:google-calendar:token:status
```

## Secret Rotation 🔄

1. Rotate the OAuth client secret in Google Cloud Console.
2. Update the corresponding `.env` values.
3. Delete `storage/app/mcp/google-calendar-tokens.json`.
4. Re-run the bootstrap flow to obtain a new refresh token.
