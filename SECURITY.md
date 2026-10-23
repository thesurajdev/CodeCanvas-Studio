# Security Policy — CodeCanvas

## Reporting

For security-sensitive reports, contact Suraj Dev through the contact method published at https://surajdev.com/. Do not publish a working exploit before maintainers have had a chance to investigate.

## Trust model

CodeCanvas's Page Editor is limited to users with `edit_pages` and `unfiltered_html`. It saves trusted administrator-authored HTML, CSS and JavaScript. These languages are not inert text: JavaScript runs in visitors' browsers and raw HTML can affect the document. Only grant code-editing capabilities to trusted people.

## API key rules

1. Never include private keys in theme source, a ZIP distribution, public Git repository, page HTML, CSS, browser JS, hidden fields, WordPress custom fields, front-end JSON, query strings or logs.
2. Prefer environment variables or server-side constants configured through `wp-config.php`.
3. If a key is leaked, revoke/rotate it at the provider, inspect logs and remove it from the repository history where applicable.
4. Public client-side keys are not secrets. Restrict them to required origins, API permissions and quotas.
5. Any server-side integration endpoint must implement authorization appropriate to the action, input validation, service/URL allowlisting and usage controls. Never proxy arbitrary client-supplied URLs with privileged credentials.

## Built-in defenses

- Admin mutation handlers verify capabilities and WordPress nonces.
- Coded pages use a limited token replacement system and never evaluate PHP from the database; other pages render from theme files only.
- Private keys are not collected or stored by this theme's settings screens.
- The page save handler blocks several recognizable private-token formats server-side. This is defense in depth, not complete secret detection; formats change and some credentials do not match any pattern.

## Limitations

A site owner may intentionally save unsafe code as an administrator. No theme can guarantee complete protection from compromised hosting, stolen admin credentials, vulnerable core/plugin code, or unsafe custom integrations. Keep backups and use a staging environment.


## Page Editor preview

The live preview uses a sandboxed iframe with a restrictive Content Security Policy. It disables network/API connections, form submissions, nested frames, and external scripts. This is a preview safeguard, not a complete security boundary for production code. Published custom JavaScript runs for site visitors and must be reviewed by the site owner. Never store private API credentials in page HTML, CSS, JavaScript, or other browser-delivered assets.

## CodeCanvas Studio source editing

The 1.5.0 Studio can write eligible website-theme/plugin files, so it should be available only to trusted administrators on a backed-up local or staging site until thoroughly validated. Each AJAX action checks a nonce, `manage_options`, root-specific file editing capabilities, the WordPress file-modification policy, and source-root path validation. Path traversal and symlinks are rejected; source extensions and file size are restricted. The previous file content is recorded as a bounded, non-autoloaded database snapshot before destructive writes. This snapshot is not a substitute for a full site/database backup.

No dashboard code editor can make editing PHP risk-free. A compromised administrator account can use the same capabilities to alter executable code. Do not enable source writes on production solely for convenience. The editor does not run PHP at save-time and intentionally does not expose `wp-config.php`, `.env`, `.htaccess`, `.user.ini`, SSH keys, WordPress core, arbitrary binary files, or large files.

API keys and private credentials must not be placed in browser-delivered assets. In this release there is no dedicated secrets vault; keep credentials in server-side environment/configuration managed outside public theme/plugin source, and implement provider calls in reviewed server-side code.

Studio rejects obvious PHP parse errors before saving source files, but static parsing cannot guarantee runtime safety. Do not treat browser-based editing as a replacement for code review, tests, Git or deployment controls.


## Plugin and website-theme separation (2.0.0)

The CodeCanvas plugin folder contains the developer UI, MCP server and editor implementation. Code Studio permits read access but rejects create/save/rename/delete/restore writes under the plugin folder. Normal website work belongs in the generated `CodeCanvas Site` theme at `wp-content/themes/codecanvas-site`. The website-theme setup action is nonce-protected, requires administrator/theme permissions, validates the standalone theme header, rejects symlink/conflicting theme folders, and never overwrites existing files. It activates the theme only after an explicit administrator action. This separation reduces accidental self-breakage; it is not a substitute for Git, backups, review or server security.

## MCP server (v1.7.0, OAuth sign-in in v1.7.1)

MCP is an optional remote interface that can read and write executable source files. A valid token therefore carries the same file-write authority as the administrator who generated it. Treat it like a deploy key, not a read-only analytics key.

### Controls

- Disabled by default; requires an explicit administrator opt-in.
- A dedicated bearer token, stored only as an HMAC-SHA-256 hash keyed with `wp_salt('auth')` and compared with `hash_equals()`.
- WordPress login cookies are never accepted for MCP authentication.
- The token is bound to one administrator account; `manage_options` is re-checked on every request.
- Per-project and per-tool allow-lists, plus independent file-write and content-write switches.
- The CodeCanvas framework theme is excluded from project discovery and rejected even if requested.
- Paths are canonicalised and confined to `wp-content/themes` or `wp-content/plugins`; traversal, absolute paths, null bytes and symlinks are rejected.
- `wp-config.php`, `.env*`, `.htaccess`, `.user.ini`, SSH keys, browser-incompatible extensions and over-size files are blocked.
- PHP is parsed with `TOKEN_PARSE` before writing; invalid PHP writes nothing.
- Well-known private-credential patterns are rejected in browser-delivered files.
- Every write stores a bounded pre-change snapshot; `expected_sha256` prevents lost updates.
- `delete_file` requires `confirm: true` and refuses to delete theme bootstrap files.
- Requests are limited to 1 MB, writes to 256 KB, and calls to 1–600 per minute per token.
- The last 50 tool calls are audited without file contents or tokens.
- All responses are `Cache-Control: no-store`.

### OAuth sign-in (v1.7.1)

The optional OAuth 2.1 flow lets browser clients connect without a fixed header. It adds surface area, so review before enabling it on a public site.

- Every grant is created only after a signed-in administrator with `manage_options` approves a consent screen; the approved tools, projects and write modes are shown first.
- Authorization codes are single use, valid for five minutes, stored only as SHA-256 hashes, and bound to the client, redirect URI and PKCE `S256` challenge.
- Access tokens last one hour and refresh tokens 30 days; both are stored as HMAC hashes. Refresh tokens rotate on every use.
- Redirect URIs must be absolute HTTPS URLs with no fragment; HTTP is accepted only for `localhost`/`127.0.0.1`.
- Dynamic registration is public by design, rate limited to 20 clients per IP per hour and capped at 100 clients; grants are capped at 200.
- Client ID Metadata Documents are fetched with `wp_safe_remote_get()` (private networks rejected), a five-second timeout and a 100 KB cap.
- Revoking a token deletes the whole grant. **Revoke all OAuth connections** clears every grant and client.
- The discovery documents require a root installation and a publicly reachable HTTPS host. OAuth does not widen the project/tool allow-lists; it is the same authority as the static token.

### Token handling

- The plaintext token is shown once and never stored by CodeCanvas.
- Generating a replacement invalidates the previous token immediately.
- Revoking deletes the hash and disables the server.
- Rotating WordPress salts invalidates all existing tokens.
- Prefer `CODECANVAS_MCP_TOKEN` in `wp-config.php` for permanent deployments; keep that file out of version control.
- Never log tokens, paste them into tickets or commit client configuration containing them.

### Recovery guard (v1.7.4)

The MCP server writes executable PHP. Syntax validation cannot catch a call to a missing function, so a valid-looking write can still produce a fatal error. CodeCanvas installs `wp-content/mu-plugins/codecanvas-recovery.php` (a must-use plugin, so it loads before the theme and ordinary plugins) to make that recoverable without host File Manager access.

- On a fatal error from a file under `wp-content/themes` or `wp-content/plugins` that has a CodeCanvas snapshot, the newest snapshot is written back, opcache is invalidated for that file, and the event is logged (last 20 shown in Settings).
- Files with no snapshot are never touched; a snapshot older than 24 hours is never restored, so an old version cannot silently overwrite newer work; a repeated restore of the same file within 30 seconds is skipped; an unrelated `codecanvas-recovery.php` is never overwritten; installation is blocked by `DISALLOW_FILE_MODS`.
- It restores the previous contents, not a known-good build: if the previous version was also broken, the next request fails again and the log records it.
- It is a safety net, not a sandbox. It cannot recover database damage, a fatal raised before mu-plugins load, or a compromise that persists in another file.
- Removing the theme leaves the guard in place; remove it from **MCP & Settings → Recovery guard** before deleting CodeCanvas.

### MCP limitations

- No SSE streaming, resources, prompts or sampling.
- No per-token scopes narrower than the global project/tool allow-lists.
- No sandbox: a syntactically valid PHP change can still break or compromise the site.
- Audit records are stored in the WordPress database and are not tamper-proof against database-level attackers.
- Multi-site networks require per-site configuration.

## Risk register

| ID | Risk | Severity | Mitigation | Residual risk |
|---|---|---|---|---|
| R1 | Administrator writes unsafe PHP that breaks or compromises the site | High | Capability checks, PHP syntax validation, snapshots, staging guidance | A determined administrator can still write malicious code; PHP edits are inherently privileged |
| R2 | MCP token leaks and an attacker changes source files | High | Hashed storage, revocation, one-time display, audit, scoped projects/tools, writes off by default | Token theft grants write access until revoked; HTTPS and client-side hygiene are essential |
| R3 | Path traversal or symlink escape reaches `wp-config.php` or core | High | Canonicalisation, root confinement, symlink rejection, blocked names; tested | None found in the 1.7.0 suite; host-level filesystem attacks remain out of scope |
| R4 | Concurrent edits overwrite each other | Medium | SHA-256 conflict detection in Studio and MCP writes; the server tracks the hash last returned per file; snapshots | A first write with no prior read has no baseline to compare against |
| R5 | Stored XSS through preview or custom HTML | Medium | Administrator-only code fields, escaping in standard templates, CSP sandbox in the legacy preview | Administrators can intentionally store executable HTML/JS; only trusted users should hold those capabilities |
| R6 | Secret committed to a theme file | High | Credential-pattern blocking on browser-delivered files, documentation, server-side guidance | Pattern matching is incomplete; review and secret scanners are still required |
| R7 | MCP brute force or request flooding | Medium | 1 MB body cap, rate limit, 401/429 responses, audit | Distributed attacks are not fully mitigated at the application layer |
| R8 | Broken website theme after an edit or update | Medium | Website theme separate from the plugin; plugin updates replace only the plugin; recovery guide | Customizations made in the plugin folder are lost by design |
| R9 | Audit log tampering | Low | Non-autoloaded option, bounded entries | Database-level attackers can modify or delete it |
| R10 | Plugin/theme source editing exposes third-party code | Medium | Explicit project selection, read/write capability checks, snapshots | Editing vendor code can be overwritten by vendor updates |
| R11 | A syntactically valid PHP write fatals the site and locks out wp-admin | High | Recovery guard restores the newest (at most 24-hour-old) snapshot on fatal errors; `render_url`/`get_php_error_log` checks; snapshots | If the broken file has no snapshot, the snapshot is stale, or the fatal occurs before mu-plugins, manual recovery is still required |

## Reporting a vulnerability

Do not include tokens, credentials or database exports in a report. Provide reproduction steps, affected version, and whether the issue affects the frontend, wp-admin, Code Studio or MCP.
