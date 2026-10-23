# CodeCanvas MCP integration

CodeCanvas 1.7.4 includes an optional **Model Context Protocol (MCP)** server. It lets an AI assistant or MCP-capable editor read and change the real source files of an enabled theme/plugin project, and optionally manage WordPress content, through a scoped and revocable HTTPS endpoint.

MCP is **off by default**. Nothing is exposed until an administrator generates a token, chooses the projects and tools, and enables the server.

- Developer: [Suraj Dev](https://surajdev.com/)
- Credit: [Webot](https://webot.surajdev.com/)
- Settings screen: **WordPress Admin → CodeCanvas → MCP & Settings**
- Endpoint: `https://example.com/wp-json/codecanvas/v1/mcp`

---

## 1. What MCP is (and is not)

MCP is a JSON-RPC protocol used by AI clients to discover and call tools. CodeCanvas implements the **Streamable HTTP** transport in its simplest form:

- One stateless HTTPS endpoint.
- JSON-RPC over `POST`.
- No server-sent events (SSE) stream.
- Bearer tokens for scripts and editors, plus an OAuth 2.1 sign-in flow for browser clients such as claude.ai (section 4.1).
- No direct terminal, SQL console, database access, plugin activation, media upload or arbitrary URL fetching.

The server exposes a deliberately small tool set. Everything else is out of scope.

## 2. Requirements

| Requirement | Value |
|---|---|
| WordPress | 6.6 or later (tested on 7.1.3) |
| PHP | 7.4 or later (tested on 8.0.30) |
| CodeCanvas | 1.7.0 or later |
| Capability | `manage_options` |
| HTTPS | Strongly recommended; required for any non-local deployment |
| Companion plugin | None |

The REST API must be reachable. Sites that block `/wp-json/` at the web-server level, or that force authentication on every request through a host-level gate, will also block MCP clients. Configure the host to allow the endpoint with the CodeCanvas bearer token, or use a local/staging site.

## 3. Enable MCP

1. Open **CodeCanvas → MCP & Settings**.
2. Click **Generate token**.
3. Copy the token from the one-time panel. It is displayed once and stored only as an HMAC hash.
4. Choose one or more **projects** (the CodeCanvas Site theme is selected by default).
5. Choose the **tools** the client may use. Read-only tools are selected by default.
6. Leave **Allow file writes** and **Allow content writes** off until you actually need them.
7. Tick **Enable the MCP server** and click **Save MCP settings**.
8. Add the endpoint and token to your MCP client (section 7).

If you enable the server without a token or without a project, the sanitizer refuses and explains why.

### Token lifecycle

- **Generate** creates a new random token, immediately invalidating the previous one.
- **Revoke** deletes the token and switches the server off. Connected clients fail on their next request.
- Tokens are bound to the administrator who generated them. If that account loses `manage_options` or is deleted, the token stops working.
- The token hash is keyed with WordPress salts (`wp_salt('auth')`). Rotating salts invalidates existing tokens; generate a new one afterwards.
- The HMAC hash is stored in `wp_options` (not autoloaded); the plaintext token is never stored.
- A permanent deployment can instead define `CODECANVAS_MCP_TOKEN` in `wp-config.php`. When that constant is non-empty it takes precedence over the stored hash; a constant-only deployment maps to a current administrator for capability checks. Generate a UI token if you need the credential bound to a specific account.

```php
// wp-config.php — optional; keep this file out of version control.
define( 'CODECANVAS_MCP_TOKEN', 'ccmcp_replace_with_a_long_random_value' );
```

## 4. Authentication and rate limiting

Every request must include one of:

```http
Authorization: Bearer ccmcp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

```http
X-CodeCanvas-Token: ccmcp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

- A WordPress login cookie is **not** accepted as MCP authentication.
- Missing/invalid token: HTTP `401` with `WWW-Authenticate: Bearer realm="CodeCanvas MCP"`.
- Server disabled: HTTP `503`.
- Too many requests: HTTP `429` with `Retry-After: 60`.
- Requests are limited to **60 per minute** by default (configurable 1–600). The limit is per token.
- Requests are capped at **1 MB** of JSON; MCP writes are capped at **256 KB per file**.

## 4.1 OAuth sign-in for browser clients

Some MCP clients cannot send a fixed header. The claude.ai and Claude Desktop custom-connector screens, for example, only offer OAuth sign-in or an unauthenticated connection. CodeCanvas therefore also acts as a small OAuth 2.1 authorization server for the same endpoint, so the default **Sign in now** option works and no credential ever appears in a URL.

### Endpoints

| Endpoint | URL |
|---|---|
| Protected-resource metadata (RFC 9728) | `https://example.com/.well-known/oauth-protected-resource` |
| Authorization-server metadata (RFC 8414) | `https://example.com/.well-known/oauth-authorization-server` |
| Authorization (consent) | `https://example.com/codecanvas-oauth/authorize` |
| Token | `https://example.com/codecanvas-oauth/token` |
| Dynamic client registration (RFC 7591) | `https://example.com/codecanvas-oauth/register` |
| Revocation (RFC 7009) | `https://example.com/codecanvas-oauth/revoke` |

### How a connection works

1. The client sends `POST /wp-json/codecanvas/v1/mcp` without a token and receives `401` with `WWW-Authenticate: Bearer realm="CodeCanvas MCP", resource_metadata="…"`.
2. It reads the protected-resource metadata, then the authorization-server metadata.
3. It identifies itself either by registering at `/codecanvas-oauth/register` or by using a Client ID Metadata Document (an `https://` client ID whose JSON document lists its redirect URIs).
4. The browser opens `/codecanvas-oauth/authorize` with `response_type=code`, `code_challenge` and `code_challenge_method=S256`. A signed-out administrator is sent to the normal WordPress login first.
5. A consent screen shows the client, the redirect host and exactly which tools, projects and write modes are enabled. Approving issues a single-use code valid for five minutes.
6. The client exchanges the code at `/codecanvas-oauth/token` with its PKCE verifier. It receives an access token (1 hour) and a rotating refresh token (30 days).

### Guarantees

- Only an administrator with `manage_options` can approve, and the grant stops working if that account loses the capability or is deleted.
- Access tokens are stored as HMAC hashes keyed with `wp_salt('auth')`; plaintext tokens exist only in the client.
- PKCE `S256` is mandatory. `plain` and missing challenges are rejected.
- Redirect URIs must be absolute HTTPS URLs without fragments; HTTP is accepted only for `localhost` and `127.0.0.1`.
- Codes are single-use, short lived and bound to client, redirect URI and challenge. Refresh tokens rotate on every use, and revoking either token deletes the whole grant.
- Client ID Metadata Documents are fetched with the safe HTTP API, which refuses private network addresses, with a five-second timeout and a 100 KB cap.
- Registration is rate limited to 20 clients per IP per hour; at most 100 clients and 200 grants are retained.
- **Revoke all OAuth connections** on the settings screen deletes every grant without touching the static bearer token.

### Requirements and limits

- The theme must be installed at the domain root for the two `/.well-known/` documents to resolve. In a subdirectory install they are served under the install path instead, which most AI clients will not probe.
- The site must be publicly reachable over HTTPS: the connector's servers call the endpoint and the discovery documents directly.
- WordPress Playground is not suitable — it runs in the browser and has no public MCP URL.
- OAuth grants inherit the global project/tool allow-lists; there are no per-token scopes beyond the single `mcp` scope.

### Adding the claude.ai connector

1. **CodeCanvas → MCP & Settings**: select a project, enable the read tools and enable the server.
2. In claude.ai open **Settings → Connectors → Add custom connector** and paste `https://example.com/wp-json/codecanvas/v1/mcp`.
3. Keep **Authentication → Sign in now**. Under **OAuth client**, either keep *Use Claude's published identity* (Client ID Metadata Document) or choose *Register automatically* (dynamic registration) — both are supported here.
4. Click **Add**, then **Connect**. Sign in to WordPress if asked and approve the request on the consent screen.

If the connector reports that it could not register, open `/.well-known/oauth-authorization-server` in a browser: a JSON document means the server is ready and the client should use *Register automatically*; a 404 means the rewrite rules or the install path are the problem (re-save permalinks, then reload the settings screen).

## 5. Protocol details

### Supported revisions

- `2025-06-18` (advertised)
- `2025-03-26`
- `2024-11-05`

The server echoes the client's requested revision when it is supported; otherwise it returns its latest revision.

### Methods

| Method | Behaviour |
|---|---|
| `initialize` | Returns protocol revision, `tools` capability, server info and usage instructions. |
| `notifications/initialized` | Accepted with HTTP `202` and an empty body. |
| `ping` | Returns an empty result. |
| `tools/list` | Returns the tools currently enabled by the administrator. |
| `tools/call` | Executes one tool. |
| `logging/setLevel` | Accepted as a no-op. |
| `resources/list`, `prompts/list` | Return an empty result (not implemented). |
| Anything else | JSON-RPC error `-32601`. |

### Error codes

| Code | Meaning |
|---|---|
| `-32700` | Invalid JSON body |
| `-32600` | Invalid request / body over 1 MB / batch over 20 messages |
| `-32601` | Unknown method |
| `-32602` | Missing or invalid parameter |
| `-32001` | Authentication, authorization, rate-limit or server-state error (the HTTP status carries the detail) |

JSON-RPC errors are returned with HTTP `200`; transport/auth failures use `401`, `405`, `429` or `503`. Tool-level denials (scope, syntax, conflict) return HTTP `200` with `isError: true`.

### Example: initialize

```bash
curl -s https://example.com/wp-json/codecanvas/v1/mcp \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer ccmcp_…' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}'
```

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2025-06-18",
    "capabilities": { "tools": { "listChanged": false } },
    "serverInfo": { "name": "codecanvas", "title": "CodeCanvas Studio", "version": "1.7.0" },
    "instructions": "CodeCanvas exposes real WordPress theme/plugin source files…"
  }
}
```

`GET` on the endpoint returns HTTP `405` with `Allow: POST`.

## 6. Tools

Tools are grouped by mode. Every tool the server implements is listed in `tools/list` so a client can discover the whole surface in one place; each tool also carries `annotations.codecanvasEnabled`. A tool is callable only when it is selected in Settings **and** its mode switch is on. A disabled tool says so in its description and the call is refused.

- **Read**: available when selected.
- **File write**: also requires **Allow file writes**.
- **Content write**: also requires **Allow content writes**.

### Read tools

| Tool | Purpose | Key arguments |
|---|---|---|
| `site_info` | WordPress/PHP versions, URL, active themes, permalink structure | — |
| `list_projects` | Installed projects with `mcp_enabled`, `writable` and a `note` explaining how to enable each one | — |
| `list_files` | Files and folders, optionally recursive, with paging | `project`, `path`, `depth`, `max_items`, `offset` |
| `read_file` | Exact file content, SHA-256 and mtime (up to 256 KB) | `project`, `path`, `max_bytes` |
| `search_files` | Case-insensitive text search with line numbers and paging | `project`, `query`, `path`, `max_results`, `offset` |
| `list_content` | Records with ID, title, status, slug, URL and `total`/`next_offset` | `post_type`, `status`, `limit`, `offset`, `search` |
| `get_content` | One record with content, slug, parent, template, featured image, terms and custom fields | `id` |
| `get_settings` | Title, tagline, permalinks, front page, reading settings, templates, uploads, menus | — |
| `list_menus` | Menus, locations and items with parent/order | — |
| `list_snapshots` | Stored pre-change snapshots for one file | `project`, `path`, `include_content` |
| `render_url` | Fetch a page on this site and report status, title, body and PHP error notices | `url`, `include_body`, `max_bytes` |
| `get_php_error_log` | Tail of `debug.log` or the configured `error_log`; when no log exists it returns `available: false` and a setup hint instead of an error | `lines` |

`list_content` accepts `post_type: "any"` to span every public type. Project keys use the form `theme:<folder>` or `plugin:<folder>`, for example `theme:codecanvas-site`.

### File-write tools

| Tool | Purpose | Key arguments |
|---|---|---|
| `patch_file` | Replace an exact `old_string` with `new_string`; fails on zero or ambiguous matches | `project`, `path`, `old_string`, `new_string`, `replace_all`, `expected_sha256` |
| `write_file` | Replace a file after snapshotting it | `project`, `path`, `content`, `expected_sha256` |
| `create_file` | Create a new file or folder; never overwrites | `project`, `path`, `kind`, `content` |
| `create_files` | Create up to 20 files/folders in one call; per-file results | `project`, `files[]` |
| `delete_file` | Delete one file after snapshotting it, or delete an **empty** folder (no snapshot; project root protected) | `project`, `path`, `confirm: true` |
| `restore_snapshot` | Restore a stored snapshot, recreating a deleted file | `project`, `path`, `index` |
| `create_plugin` | Scaffold a new plugin folder with a valid header and optional files; **not** added to the MCP allow-list unless `enable_mcp: true` is passed or the owner enables it in Settings (never activates it) | `slug`, `name`, `description`, `version`, `author`, `license`, `files[]`, `enable_mcp` |

Write guarantees:

- The target must be inside an enabled project.
- Path traversal, absolute paths, null bytes and symlinks are rejected.
- The CodeCanvas framework theme is never exposed over MCP.
- Only supported text/source extensions are writable.
- PHP is parsed with `TOKEN_PARSE` before writing; a syntax error writes nothing.
- Browser-delivered files are checked for well-known private-credential patterns.
- The previous contents are stored as a bounded Studio snapshot before every write, patch, restore or delete.
- The server remembers the hash it last returned for each file. `write_file` and `patch_file` automatically refuse a stale edit, and passing `expected_sha256` still overrides that memory. The conflict error carries the current hash and modification time so the client can recover without another round trip.
- `restore_snapshot` snapshots the current contents first, so a restore can itself be undone.
- `style.css`, `functions.php` and `index.php` cannot be deleted over MCP.
- Empty folders can be deleted (for scaffolding cleanup), but a folder with any contents is refused and folders are not snapshotted.
- Creating a plugin does not widen the MCP write scope by itself: `enable_mcp: true` is an explicit opt-in, otherwise the owner must enable the project in **MCP & Settings**.

### Content-write tools

| Tool | Purpose | Key arguments |
|---|---|---|
| `create_content` | Create a page/post with slug, parent, template, image, terms and meta; returns the permalink | `title`, `content`, `post_type`, `status`, `excerpt`, `slug`, `parent`, `template`, `featured_media`, `categories`, `tags`, `meta` |
| `update_content` | Update any of those fields; `status: "trash"` moves it to the trash | `id`, … , `status` |
| `delete_content` | Trash by default; `force: true` deletes permanently | `id`, `confirm: true`, `force` |
| `update_settings` | Site title, tagline, static front page, posts page, posts per page, permalink structure | those fields |
| `update_menu` | Create a menu, add/edit/remove items, assign a location, delete a menu. `create` returns `menu_id`; every other action needs it, and `update_item`/`remove_item` also need `item_id` | `action`, `menu_id`, `item_id`, … |
| `upload_media` | Store a base64 file in the media library (≤ 700 KB) | `filename`, `data_base64`, `title`, `alt_text`, `post_parent` |

Content writes require the mapped administrator to hold the normal WordPress capabilities (`edit_post`, `publish_posts`, `delete_post`, `edit_theme_options`, `upload_files`). MCP cannot change a record's post type or read private options. `update_settings` and `update_menu` flush rewrite rules after a change so new permalinks and menus resolve immediately.

### Example: read a file

```bash
curl -s https://example.com/wp-json/codecanvas/v1/mcp \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer ccmcp_…' \
  -d '{
    "jsonrpc": "2.0", "id": 2, "method": "tools/call",
    "params": {
      "name": "read_file",
      "arguments": { "project": "theme:codecanvas-site", "path": "front-page.php" }
    }
  }'
```

### Example: safe write with conflict detection

1. `read_file` and keep `sha256`.
2. `patch_file` with the exact `old_string`/`new_string`, or `write_file` with the full `content`.
3. If the file changed in the meantime, the call returns `isError: true` with the current hash and modification time, and writes nothing.

### Example: undo a change

1. `list_snapshots` with the project and path.
2. `restore_snapshot` with `index` 0 for the newest snapshot. A deleted file is recreated.

### Example: confirm a page still works

1. `render_url` with `/` (or the page path) — check `status`, `php_error` and the body.
2. If an error is reported, `get_php_error_log` returns the tail of the log; `restore_snapshot` or `patch_file` fixes the code.

## 7. Client configuration

Replace the URL and token with your own values. Keep tokens in local, uncommitted client configuration.

### Claude Code

```bash
claude mcp add --transport http codecanvas https://example.com/wp-json/codecanvas/v1/mcp \
  --header "Authorization: Bearer ccmcp_…"
```

### Cursor — `~/.cursor/mcp.json`

```json
{
  "mcpServers": {
    "codecanvas": {
      "url": "https://example.com/wp-json/codecanvas/v1/mcp",
      "headers": { "Authorization": "Bearer ccmcp_…" }
    }
  }
}
```

### VS Code — `.vscode/mcp.json`

```json
{
  "servers": {
    "codecanvas": {
      "type": "http",
      "url": "https://example.com/wp-json/codecanvas/v1/mcp",
      "headers": { "Authorization": "Bearer ccmcp_…" }
    }
  }
}
```

### claude.ai custom connector

Add `https://example.com/wp-json/codecanvas/v1/mcp` as a custom connector, choose **Sign in now**, then **Connect** and approve on the consent screen. See section 4.1.

### Generic HTTP client

Send `POST` with `Content-Type: application/json` and the `Authorization` header. A `HEAD`/`GET` request is not supported.

## 8. Recommended workflow for an AI client

1. `site_info`, `get_settings` and `list_projects` — confirm the site, the settings and the enabled project.
2. `list_files` with `depth` and `read_file` — inspect before changing.
3. For small changes: `patch_file` with `old_string`/`new_string`; keep `write_file` for whole-file rewrites. Both refuse stale edits using the hash the server tracked.
4. For new files: `create_file`, or `create_files` when scaffolding several at once. Never guess a path that already exists.
5. After a PHP or CSS change: `render_url` and, on an error, `get_php_error_log`. `restore_snapshot` is the undo.
6. For content: `create_content` as a draft, review in WordPress, then `update_content` to publish. Use `delete_content` to trash (default) or permanently delete.
7. For site structure: `get_settings`/`list_menus`, then `update_settings` (front page, title, tagline) or `update_menu`.
8. If a write is rejected, read the error: it names the policy (PHP syntax, scope, conflicts, size) rather than silently succeeding.

The MCP instructions sent at `initialize` repeat the key rule: **work in the CodeCanvas Site theme; the CodeCanvas framework is read-only.**

## 8.1 Prefer content that WordPress can edit

A page built by writing `front-page.php` (or any other template) is invisible in **Pages**, because it lives in code, not in the database. The `initialize` instructions tell clients to put page copy, headings and images into WordPress records:

1. `create_content` with the page blocks and `status: "draft"` (the response includes the ID, permalink and edit URL).
2. `update_settings` with `show_on_front: "page"` and `page_on_front: <id>`.
3. Replace a hardcoded website-theme `front-page.php` with a delegating template, or delete it, so the Page renders at `/`.

Use `front-page.php` only when the user explicitly asks for a code-level layout. If a website theme already hardcodes content there, ask before replacing it.

### Converting a code-built homepage into an editable page

1. Create a new Page whose blocks reproduce the current copy and images (`create_content` with `status: "draft"`, then review the `edit_url`).
2. Move any design tokens the page needs into the website theme's `assets/css/site.css` or a registered block style.
3. Set **Settings → Reading → Your homepage displays → A static page** (or `update_settings` with `show_on_front: "page"` and `page_on_front`).
4. Replace the website theme's hardcoded `front-page.php` with the bundled starter, which renders the assigned page unless that page has its own coded content:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) {
    while ( have_posts() ) : the_post();
        the_content();
    endwhile;
}
get_footer();
```

The starter renders the assigned page's content, so from then on the homepage is edited in **Pages**, not in code. Take a snapshot first (`list_snapshots`) so the old template can be restored.

## 9. Security model

MCP is a privileged remote-code interface. Treat the token like an SSH key.

### Controls

- Explicit administrator opt-in; off by default.
- Bearer token, HMAC-hashed at rest, never logged.
- Token mapped to one administrator account, re-checked on every request.
- Per-tool and per-project allow-lists.
- Separate switches for file writes and content writes.
- Framework theme excluded entirely.
- Canonical path validation, traversal/symlink rejection, extension block-list.
- PHP syntax validation and credential-pattern checks before writing.
- Bounded snapshots before destructive operations.
- 1 MB request cap, 256 KB write cap, 1–600 req/min rate limit.
- Audit log of the last 50 tool calls without file contents or tokens.
- `Cache-Control: no-store` on all responses.

### What it cannot do

- A token grants the same file-write ability as an administrator using Code Studio. It is not a sandbox.
- Syntax validation does not prove runtime correctness. A syntactically valid PHP change can still break the site.
- Audit logs are stored in the WordPress database; an attacker with database access can alter them.
- The token is sent with every request. If the client stores it insecurely, it can leak.

### Recovery guard (1.7.4)

MCP writes executable PHP. Syntax validation catches parse errors but not a call to a function that does not exist, so a write can still fatal the site. CodeCanvas installs `wp-content/mu-plugins/codecanvas-recovery.php` (once per release, and via **MCP & Settings → Recovery guard**) to make that recoverable without File Manager access:

- It loads before the theme and plugins, so it is present when a theme fatal occurs.
- On `E_ERROR`, `E_PARSE`, `E_COMPILE_ERROR` or `E_USER_ERROR`, it maps the failing file to its CodeCanvas snapshot, restores the newest one, invalidates opcache, and logs the event.
- Files without a snapshot are never touched, and a snapshot older than 24 hours is refused: the guard only undoes a recent CodeCanvas change, never an old version that would overwrite newer work. A repeated restore of the same file within 30 seconds is skipped. The last 20 recoveries appear in Settings.
- It does not overwrite an unrelated `codecanvas-recovery.php`, respects `DISALLOW_FILE_MODS`, and reports install failures instead of failing silently.
- The current request still shows an error; the next one loads the restored file.

This is a safety net, not a sandbox: it cannot help with whitespace-only output, database damage, or a fatal thrown before mu-plugins load. Keep backups and use `render_url`/`get_php_error_log` after PHP changes.

### If a token leaks

1. Open **MCP & Settings** and click **Revoke token** immediately.
2. Review **Recent MCP activity** for unexpected tool calls.
3. Restore any changed files from Studio history, Git or backups.
4. Generate a new token only after the client configuration is cleaned.

### Secrets

MCP never returns environment variables, `wp-config.php`, `.env` files, private keys or option values. It also blocks well-known credential patterns in browser-delivered files. This is defense in depth, not complete secret detection; keep credentials server-side.

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `401 Missing bearer token` | Client not sending the header | Add `Authorization: Bearer …` |
| `401 Invalid MCP token` | Token replaced, revoked, or salts rotated | Generate a new token |
| `503 The CodeCanvas MCP server is disabled` | Enable switch off | Enable it in Settings |
| `503 administrator account unavailable` | Token owner deleted/lost capability | Generate a token from a current administrator |
| `429` | Rate limit | Wait 60 seconds or raise the limit |
| `405` on GET | Endpoint is POST-only | Use an HTTP/Streamable client, not an SSE URL |
| `Tool … is not enabled` | Tool checkbox off or write switch off | Enable the tool **and** its mode switch |
| `project is not enabled` | Project unchecked | Select it in Settings |
| `framework … not exposed` | Tried to use `theme:CodeCanvas` | Work in the website theme |
| `PHP syntax error` | Invalid PHP | Fix the syntax; nothing was written |
| `file changed since it was read` | Concurrent edit | Re-read and reapply with the new hash |
| 404 on `/wp-json/...` | REST API blocked or permalinks disabled | Enable REST/pretty permalinks; check host rules |

### Verify the endpoint from the command line

```bash
curl -i -X POST https://example.com/wp-json/codecanvas/v1/mcp \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer ccmcp_…' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

Expect `HTTP/1.1 200`, `Content-Type: application/json`, and the tools you enabled.

## 11. Limitations

- OAuth is limited to the authorization-code + PKCE grant with public clients; there is no client-secret or device-code flow.
- OAuth discovery requires a root installation and a publicly reachable HTTPS host.
- No SSE streaming, resources, prompts, sampling or elicitation.
- No multi-site network-wide endpoint; each site has its own settings.
- No plugin/theme activation, SQL, WP-CLI, Git or deployment.
- No per-token scopes beyond the global project/tool allow-lists.
- Snapshots are bounded (three per file) and stored in the database; they are not a replacement for Git or backups.
- `render_url` fetches this site only, and a small number of hosts block the server from calling its own public URL. `get_php_error_log` needs a readable log file; without one it reports the empty state and how to enable `WP_DEBUG_LOG` temporarily.
- Media uploads are limited to 700 KB because the JSON-RPC body is capped at 1 MB.
- `theme:CodeCanvas` is intentionally unreachable.

## 12. Verification performed for 1.7.4

The release test suite (`tests/run-tests.php`) covers:

- Missing, invalid and revoked tokens; cookie-only rejection; disabled server.
- `initialize`, `tools/list`, `tools/call`, batch, notifications, malformed JSON, oversized bodies, unknown methods.
- Tool scoping, framework exclusion, traversal rejection, PHP validation, write conflicts, snapshots, delete confirmation/protection.
- Content create/update/read and capability switches.
- Settings sanitization, rate limiting and audit redaction.
- Framework read-only guard casing and resolution.
- OAuth: metadata documents, client registration validation, PKCE verification, code single-use and binding, token exchange, refresh rotation, revocation, and OAuth access tokens authenticating MCP requests.
- 1.7.2 additions: `patch_file` exact/ambiguous/missing matches, snapshot listing and restore (including recreating a deleted file), tracked-hash conflicts, recursive and paged listings, `delete_content` trash/permanent paths, content fields and permalink, `update_settings`, `update_menu`, `upload_media`, `get_settings`, project notes and the single-catalogue `tools/list` behaviour.
- 1.7.3 additions: `create_plugin` rejects an invalid slug and an existing folder, writes a header file plus nested extra files, and accepts further MCP writes once the project is approved; the test removes the scaffolded folder afterwards. (1.7.9 changed the automatic allow-list behaviour described below.)
- 1.7.9 additions: `create_plugin` is scope-neutral by default and only adds `plugin:<slug>` when `enable_mcp: true` is passed; an unapproved plugin rejects further writes and `list_projects` explains why. `delete_file` removes empty folders and refuses non-empty ones. `get_php_error_log` returns an empty tail with a `hint` when no log exists. `update_menu` documents its `menu_id`/`item_id` requirements.
- 1.7.4 additions: the recovery guard installs as a mu-plugin, is recognised by its marker, hooks the shutdown handler, restores a snapshot for a fatally broken file, logs the restored path and ignores files without a snapshot. A live test deliberately corrupted the website theme's `front-page.php`: the first request returned `500`, the guard restored the file, and the next request returned `200` with identical contents.

The live endpoint was also exercised over HTTP on WordPress 7.1.3 / PHP 8.0.30 for `initialize`, `tools/list`, `read_file`, `create_file`, `write_file` (blocked and allowed), `delete_file`, traversal attempts, `401`, `405`, `-32700` and `429` responses, plus the 1.7.2 tools in section 12 of the QA report.

See `docs/qa-report.md` for the full record and `SECURITY.md` for the risk register.
