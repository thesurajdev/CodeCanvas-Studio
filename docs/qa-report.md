# CodeCanvas 1.7.4 QA report

Date: 22 October 2026
Environment: WordPress 7.1.3, PHP 8.0.30, Apache 2.4.58, XAMPP on Windows, CodeCanvas 1.7.4 parent with `codecanvas-site` child active.

This report distinguishes automated checks, HTTP checks, browser checks and **untested** areas. A file existing or looking correct is not counted as a pass.

---

## 0.1 Addendum — 2.0.0 plugin release

Date: 22 October 2026. Environment: WordPress 7.1.3, PHP 8.0.30, Apache 2.4.58, XAMPP on Windows. The 1.7.x entries below are the historical theme-era record; this section covers the plugin conversion.

| Check | Result |
|---|---|
| Official WordPress Plugin Check (`wp plugin check codecanvas`) | **Pass — 0 errors, 0 warnings** |
| PHP lint across every plugin and generated-theme file | Pass |
| Full automated suite (`php tests/codecanvas/run-tests.php`, suite moved outside the plugin) | **301 passed, 0 failed, 1 skipped** |
| Front page over HTTP | Pass — `200`, generated theme header/footer and assets present |
| Admin menu registration (Welcome, Code Studio, MCP & Settings, Pages → CodeCanvas Editor) | Pass — every slug registered with its capability; the three removed builder screens leave no menu entry |
| Welcome, Code Studio, MCP & Settings and CodeCanvas Editor page callbacks render | Pass — HTTP 200 with an administrator session, no PHP notices in the output |
| Coded-page editor reachable from **Pages → CodeCanvas Editor** | Pass — previously linked from Welcome but unregistered |
| Deactivate/reactivate the plugin with the generated theme active | Pass — front end stays `200` on theme fallbacks |
| Release ZIP (`php build-release.php`) | Pass — 65 entries, tests excluded, required files present, no forbidden paths |

---

## 0.2 Addendum — removal of the Views, Content Types and Global Code screens

Date: 23 October 2026. Same environment. The three builder-era admin screens were removed so the front end renders from theme files plus coded pages only.

| Check | Result |
|---|---|
| Menu registration (`codecanvas_admin_menu`, three `admin-post` handlers) and the screens' callbacks | Pass — removed; suite asserts the functions, hooks and the `assets/js/admin-code-editor.js` file are gone |
| `includes/content-types.php`, `codecanvas_site_options()`, the global-code option reads in `assets.php` and the page-editor preview | Pass — removed with no remaining references |
| Front end with the generated theme | Pass — front page, single, category, date archive, search, 404 and a coded page all return the expected status |
| Coded page round trip over HTTP | Pass — body rendered from post content, `{{post.title}}` resolved, page CSS and JS printed inline, site header/footer kept, no `<main>` wrapper |
| Plain page over HTTP | Pass — theme template, `<main>` wrapper and `cc-prose` present, no coded markup |
| Shipped scaffold activated as a theme and routed (front, single, search, category archive, 404, coded page) | Pass — no fatals; listing pages render the standard card |
| Listing-page fatal found in the same pass | Fixed — `archive.php`, `index.php`, `home.php` and `search.php` called an undefined `codecanvas_site_default_card()`; templates now call the defined name and the plugin keeps an alias for previously generated themes |
| Official WordPress Plugin Check after the removal (`wp plugin check codecanvas`) | **Pass — 0 errors, 0 warnings** |
| Administrator-session render of Welcome, Code Studio, MCP & Settings and the coded-page editor | Pass — HTTP `200`, no PHP notices, no removed screen in the sidebar |
| MCP & Settings rebuild (capability groups, collapsed snippets and log) | Pass — page height `10,052 px` → `3,462 px` at 1920 px wide; saving with every group collapsed still stored all 25 tools; all three groups, the three setup snippets and the 50-row log render when expanded |

---

## 0.3 Addendum — 1.7.4 recovery guard and editable-content guidance

1.7.4 adds `wp-content/mu-plugins/codecanvas-recovery.php`, which loads before the theme and restores the newest CodeCanvas snapshot when a PHP fatal error comes from a theme or plugin file. It also changes the MCP `initialize` instructions to keep page copy in WordPress records instead of hardcoded templates.

| Check | Result |
|---|---|
| Guard installs as a mu-plugin and is recognised by its marker | Pass |
| Guard source hooks `register_shutdown_function` and reads `codecanvas_studio_bk_` snapshots | Pass |
| Restores the newest snapshot for a fatally broken file | Pass — exact contents restored |
| Logs the restored path | Pass |
| Ignores a file with no snapshot | Pass |
| Live fatal test: broken child `front-page.php` | Pass — first request `500`, guard restored the file, second request `200`, bytes identical to the backup |
| Settings panel renders guard status, path, recoveries and install/remove actions | Pass (code path exercised with admin includes) |
| Full automated suite | **243 passed, 0 failed, 1 skipped** |

### Performance and SEO

- The guard adds one shutdown callback and does no work (and no queries) on a request that does not fatal.
- MCP code registers hooks only; the server, OAuth endpoints and guard add no front-end output.
- No `<head>` output, redirect, canonical, sitemap or structured-data behaviour is changed by 1.7.4.
- Local check: home page returns `200` with the guard installed. A query-count/TTFB benchmark against the host was not run, so no performance percentage is claimed.

**Not verified for 1.7.4:** a host that blocks writes to `wp-content/mu-plugins`, multi-site, and PHP 7.4 / WordPress 6.6.

---

## 0.2 Addendum — 1.7.3 plugin scaffolding

1.7.3 adds `create_plugin`, which creates a new plugin project instead of only editing installed ones. It writes `wp-content/plugins/<slug>/<slug>.php` with a valid plugin header, optional nested files, PHP syntax checks, and then enables `plugin:<slug>` for MCP. It never activates the plugin.

| Check | Result |
|---|---|
| Invalid slug rejected | Pass — `INVALID Slug!` refused |
| Existing folder refused | Pass |
| Header file created with plugin metadata | Pass — `Plugin Name: CC Test Plugin` present |
| Nested extra files scaffolded | Pass — `includes/helper.php`, `assets/plugin.css` |
| Project added to the allow-list and persisted | Pass — `plugin:<slug>` in options, `mcp_enabled: true` |
| Visible in `list_projects` | Pass |
| Further MCP writes in the new project | Pass — `create_file` wrote `includes/extra.php` immediately |
| Plugin left inactive | Pass — response reports `active: false` |
| Scaffolded folder removed by the test | Pass |
| Full automated suite | **236 passed, 0 failed, 1 skipped** |

**Not verified for 1.7.3:** a host where `wp-content/plugins` is not writable, a client creating and then activating a plugin (activation is deliberately not exposed), and PHP 7.4 / WordPress 6.6.

---

## 0.1 Addendum — 1.7.2 MCP tool completion

1.7.2 adds the tools a client needs to finish a job, not just start one: `patch_file`, `create_files`, `list_snapshots`, `restore_snapshot`, `render_url`, `get_php_error_log`, `get_settings`, `update_settings`, `list_menus`, `update_menu`, `upload_media` and `delete_content`, plus full content fields and paging. The catalogue is now 24 tools (12 read, 6 file-write, 6 content-write) in `inc/mcp.php` and the new `inc/mcp-tools.php`.

| Check | Result |
|---|---|
| `tools/list` returns one registered catalogue of 24 tools with `annotations.codecanvasEnabled` | Pass |
| Disabled tools state which Settings switch enables them and are refused when called | Pass |
| `patch_file` unique replacement, `replace_all`, ambiguous and missing matches | Pass — exact file contents asserted |
| `patch_file` rejects a stale `expected_sha256` and reports the current hash plus modification time | Pass |
| Tracked hashes: a stale edit is refused even without `expected_sha256`, and a fresh `read_file` clears the conflict | Pass |
| `list_snapshots` metadata and preview; `restore_snapshot` restores index 0 | Pass |
| `restore_snapshot` recreates a file deleted with `delete_file` | Pass — HTTP `created: true` |
| `create_files` creates several files in a new subfolder | Pass |
| `list_files` `depth=2`, `max_items`, `offset`/`next_offset`; `search_files` and `list_content` paging with `total` | Pass |
| `delete_content` requires `confirm`, trashes by default and deletes permanently with `force` | Pass — `trash` then `deleted`, post gone |
| `update_content` accepts `status: "trash"` | Pass |
| Content slug, custom fields, tags and returned permalink | Pass |
| `get_settings`, `update_settings` (tagline set and clear), `list_menus`, `update_menu` (create/add/assign/delete) | Pass — settings and menus restored after the test |
| `upload_media` 1×1 PNG, MIME detection and alt text; `.php` upload refused | Pass |
| `render_url` on the local home page | Pass — HTTP `200`, title `Demo`, `php_error: null`, ~700 ms |
| `render_url` on another host | Pass — refused |
| `get_php_error_log` with no log configured | Pass — actionable message instead of an empty result |
| `list_projects` explains disabled projects | Pass — `note` names the Settings screen |
| Full automated suite | **223 passed, 0 failed, 1 skipped** |

### Defects found and fixed during 1.7.2 verification

| # | Defect | Fix | Verification |
|---|---|---|---|
| 1 | `create_file`/`create_files` could not write into a folder that did not exist yet: path resolution requires an existing parent. | Added `codecanvas_mcp_prepare_parent_dirs()`, which creates only validated, in-project folders before resolution. | Automated test creates two files in a new subfolder; HTTP `create_files` equivalent. |
| 2 | `update_settings` ignored an empty `tagline`, so an existing tagline could never be cleared. | The argument is now applied whenever the key is present, including an empty string. | Automated and HTTP set/clear check. |

**Not verified for 1.7.2:** a real AI client driving every new tool end to end, a host that blocks the server from calling its own public URL (render_url), the error-log path on a host with a non-standard `error_log`, media decoding on a server without GD, and PHP 7.4 / WordPress 6.6.

---

## 0. Addendum — 1.7.1 OAuth sign-in

1.7.1 adds an OAuth 2.1 authorization server (`inc/mcp-oauth.php`) so browser-based MCP clients that cannot send a fixed header — claude.ai's custom connector in particular — can use **Sign in now** instead of a static token.

| Check | Result |
|---|---|
| Discovery documents (`/.well-known/oauth-protected-resource`, `/.well-known/oauth-authorization-server`) | Pass — `200` JSON, issuer, endpoints and `S256` advertised |
| `401` challenge advertises `resource_metadata` | Pass |
| Dynamic client registration (RFC 7591) | Pass — `201`, public client only, HTTP/fragment/confidential-client/empty-list rejected |
| Anonymous authorize redirects to WordPress login with parameters preserved | Pass — `302` |
| Consent screen (client, redirect host, tools/projects, nonce) | Pass — `200`, standalone page, `X-Frame-Options: DENY`, `no-store` |
| Approve / deny | Pass — `302` with `code`+`state`, or `error=access_denied` |
| PKCE `S256` | Pass — wrong verifier `invalid_grant`, malformed verifier rejected |
| Code binding and single use | Pass — client, redirect URI and verifier mismatch rejected; replay rejected |
| Token exchange and response shape | Pass — `access_token`, `refresh_token`, `token_type: Bearer`, `expires_in: 3600`, `scope: mcp` |
| OAuth access token authenticating MCP | Pass — `initialize` and `tools/list` `200` with the enabled tools; forged token `401` |
| Refresh rotation | Pass — new pair issued, old refresh rejected, client binding enforced |
| Expiry and revocation | Pass — expired token `401`; revocation `200` then MCP `401` |
| Unregistered redirect URI / `GET` on token endpoint | Pass — `400` page without redirect / `405` |
| Settings panel and revoke-all admin action | Pass — panel renders without notices; action `302`s with the `oauth-revoked` notice and clears grants and clients |
| Static bearer token still works | Pass |

OAuth grants inherit the global project/tool allow-lists; they add no per-token scope. An approved client is equivalent to the static token for the enabled tools, so keep write switches off until needed.

**Not verified for 1.7.1:** a real claude.ai connector against a public HTTPS host, the Client ID Metadata Document path against Anthropic's live document, hosts that intercept `/.well-known/` before WordPress, subdirectory installs, and PHP 7.4 / WordPress 6.6.

---

## 1. Summary

| Area | Result |
|---|---|
| PHP syntax (33 files, both themes) | Pass |
| JSON (`theme.json`) | Pass |
| JavaScript syntax | Pass via Chromium load (no parser CLI installed) — see limitations |
| CSS validation | Not run (no linter installed) |
| Automated integration/security suite | **243 passed, 0 failed, 1 skipped** |
| Live MCP HTTP checks | Pass |
| Admin/MCP settings UI | Pass after two defects were found and fixed |
| Studio UI | Pass |
| Frontend routes and asset isolation | Pass |
| Fresh-install and cross-host matrix | Not run |

**Release statement:** 1.7.0 is a tested **release candidate** for independent distribution and staging use. It is not claimed as universally production-ready until the untested matrix in section 8 is completed on the target host.

## 2. Defects found and fixed during this QA cycle

| # | Defect | Impact | Fix | Verification |
|---|---|---|---|---|
| 1 | `register_setting()` attaches the option sanitizer to **every** `update_option()` call; the sanitizer rebuilt the option from existing values and dropped `token_hash`, `token_hint`, `token_created` and `user_id`. | Generating a token appeared to succeed but the server rejected the new token with `401`. | Sanitizer now validates and preserves token fields from the input (or existing values when the settings form omits them). | UI-generated token prefix matched the stored hint; an HTTP `tools/list` call with the displayed token returned `200` and 10 tools. Automated tests now register the setting so this path is covered. |
| 2 | Token action `<form>` elements were nested inside the settings `<form>`. | Invalid HTML; the browser closed the outer form, detaching project/tool fields and the Save button from their form. | Token actions moved to hidden forms outside the settings form; buttons use the HTML `form` attribute. | DOM check: settings form contains all 12 tool inputs, project inputs and the save button; saving persisted `allow_writes` and showed a success notice. |
| 3 | WordPress's “Settings saved.” notice is registered under `general`, so `settings_errors( 'codecanvas_mcp_options' )` hid it. | No save confirmation. | Added an explicit notice when `settings-updated=true`. | Browser wait for “MCP settings saved.” succeeded. |
| 4 | “Exposed tools” counted configured tools even while the server was disabled. | Misleading security status. | Counter shows 0 unless the server is enabled. | Disabled state shows `0`. |
| 5 | WordPress rejected malformed JSON with its own `rest_invalid_json` before the MCP handler ran. | MCP clients received HTTP 400 instead of JSON-RPC `-32700`. | `rest_pre_dispatch` presents the body as opaque text for this route; the handler parses and validates it. | Live malformed request returned `-32700` with HTTP 200. |
| 6 | Inline view JavaScript had no script handle on blank canvases after skipping the menu script. | Custom JS could silently disappear on blank canvases. | Enqueue `frontend.js` when inline JS exists, even on a blank layout. | Code path reviewed; blank layout with no inline JS loads only `site.js`. |

Earlier 1.6.3 fixes (case-insensitive framework guard, menu redirect, child starters, theme-mod inheritance) remain in place and were re-exercised.

## 3. Automated suite

Command:

```bash
php tests/codecanvas/run-tests.php
```

(Recorded at the time as `php wp-content/themes/CodeCanvas/tests/run-tests.php`; the suite moved outside the plugin in 2.0.0.)

Result:

```
Passed: 129   Failed: 0   Skipped: 1
```

The suite registers the settings sanitizer (as an admin request does), uses the live active theme, and restores every option and temporary file it touches.

### Covered

**Environment/release**
- Version constant, parent/child relationship, child `Template` header, MCP files loaded.

**Static source checks**
- 11 Studio AJAX actions registered; shared nonce guard; each write handler uses `codecanvas_studio_request()`.
- MCP route namespace; no `$wpdb`, `eval()`, `shell_exec()`; `hash_equals()` and `hash_hmac()` present.
- Settings screen requires `manage_options`; token actions verify nonces.

**Path and file policy**
- Rejects `../`, absolute Unix/Windows paths, null bytes, encoded traversal and `./` segments.
- Resolves a valid child file.
- Framework guard catches `CodeCanvas`, `codecanvas`, `CODECANVAS` and resolved framework targets.
- Blocks `.env`, `wp-config.php`, `private-key.php`, `credentials.json`, images and archives.
- Symlink escape test: **skipped** because this Windows session cannot create symlinks. The resolver code rejects links; this specific runtime case is unverified here.

**Authentication**
- Disabled server → 503 even with a valid token.
- Missing/invalid token → 401 with `WWW-Authenticate`.
- Cookie-authenticated administrator without a token → 401.
- GET → 405 with `Allow: POST`.
- Token stored only as a 64-character hash; bound to the generating user.

**Protocol**
- `initialize` negotiation, server info, capabilities.
- `tools/list` counts: 12 with all switches, 7 read-only with writes off; write/content tools hidden.
- Malformed JSON → `-32700`; unknown method → `-32601`; batch of two → two responses; notification → 202; >1 MB body → `-32600`.

**Read tools**
- `site_info`, `list_projects`, child-theme file listing, exact `read_file` content/hash, search hit, framework exclusion, disabled-project rejection, traversal rejection.

**Write tools**
- Writes rejected when disabled; no file created.
- Create file and folder, reserved-name rejection (`.env`), read, overwrite with correct hash, stale-hash conflict, pre-change snapshot, traversal rejection.
- Invalid PHP rejected without writing; valid PHP accepted.
- Delete requires `confirm: true`; bootstrap files protected; deletion snapshotted.

**Content tools**
- Draft creation, read-back, update title/status to publish, list, content-write switch enforcement.

**Settings**
- Unknown tools/projects stripped; rate clamped; enable without token refused; enable without project refused; admin error recorded.

**Rate limit and audit**
- First request 200, second 429 with `Retry-After`.
- Audit records `write_file`; does not contain file content marker or token; bounded to 50.

**Constant token**
- `CODECANVAS_MCP_TOKEN` authenticates a constant-only deployment (maps to a current administrator).
- The constant takes precedence over a stored token hash; the old stored token is rejected.

**Cleanup**
- Temporary files/posts removed; options restored.

## 4. Live HTTP checks (Apache, no caching)

| Check | Result |
|---|---|
| No token on `POST /wp-json/codecanvas/v1/mcp` | `401`, `WWW-Authenticate: Bearer realm="CodeCanvas MCP"` |
| Valid token, `initialize` | `200`, `application/json`, protocol `2025-06-18`, server `codecanvas` 1.7.0 |
| `tools/list` with writes off | 7 tools |
| `tools/list` with writes on | 10 tools |
| `read_file` child `style.css` | `isError: false`, exact content and hash |
| `write_file` with writes off | `isError: true`, explanatory message, file unchanged |
| `create_file` / `delete_file` | Created and removed a temp file; delete snapshotted it |
| Traversal write `../../wp-config.php` | `isError: true`, “unsupported segment” |
| Malformed JSON | `-32700` |
| `GET` | `405`, `Allow: POST` |
| Response headers | `Cache-Control: no-store`, `X-CodeCanvas-MCP: 1`, `Content-Type: application/json` |

## 5. Browser checks (Chromium via the preview panel)

### MCP & Settings

- Page loads with no PHP notices or console errors.
- Sidebar: **CodeCanvas → Code Studio, MCP & Settings**.
- Status shows Enabled/Disabled, token state, exposed count, last request and rate limit.
- Saving with `allow_writes` changed persisted the value and displayed “MCP settings saved.”
- Generate token displayed the one-time panel; the token prefix matched the stored hint and worked over HTTP.
- Revoke displayed a warning notice, set the server to Disabled, token None, exposed tools 0, and left the Enable checkbox disabled until a new token exists.
- Final delivered state: MCP disabled, no token, writes off, 0 tools exposed.
- Layout checked at 1440 px and a narrow viewport; no horizontal overflow observed.

### Code Studio

- Opens directly in `codecanvas-site` (WEBSITE CHILD THEME).
- File list shows the child templates, `functions.php`, `style.css`, `assets/`.
- Parent is labelled read-only.
- New **MCP** toolbar button links to settings.
- Console: only the jQuery Migrate informational log; no errors.

### Frontend

- Homepage console: no messages.
- No Studio/MCP/CodeMirror assets in public HTML.
- Chrome screenshot captured for visual review.

## 6. Frontend routes and assets

| URL | Status | Notes |
|---|---|---|
| `/` | 200 | 4 CSS, 2 JS, 26,416 B HTML |
| `/sample-page/` | 200 | Standard page template |
| `/category/uncategorized/` | 200 | Archive template |
| `/?s=wordpress` | 200 | Search template |
| `/no-such-page-xyz/` | 404 | 404 template |
| Temporary blank-canvas page | 200 | 4 CSS, 1 JS; no shared menu script; page deleted afterwards |

No route loaded `studio.js`, `studio.css`, `mcp-settings.css` or CodeMirror.

## 7. Static checks

- `php -l` across **31** PHP files in `CodeCanvas` + `codecanvas-site`: 0 failures.
- `theme.json`: valid JSON.
- JavaScript: `node` is not installed in this environment, so a standalone parser check was not possible. `studio.js`, `frontend.js` and `site.js` were loaded by Chromium in the Studio/frontend pages with no syntax or runtime errors. An external parser/linter remains recommended in CI.
- CSS: 8 files; no CSS validator installed, so no formal CSS lint was run. Pages rendered without missing/blocked styles.

## 8. Not tested / limitations

These are **not** passes and must be completed before a broad production claim:

1. Fresh WordPress installation and ZIP upload/activation (the site already had CodeCanvas installed and the website theme active).
2. Activating the packaged ZIP on a clean site and clicking the website-theme setup button end to end.
3. Linux hosting and case-sensitive filesystems.
4. Multisite/network activation.
5. `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` / FTP/SSH-credential hosts.
6. Non-administrator accounts attempting Studio/MCP operations (automated capability checks exist, but no two-account browser flow was run).
7. A real Claude Code, Cursor or VS Code MCP client handshake. The protocol was exercised with curl and `WP_REST_Request`, not a third-party client.
8. Symlink escape at runtime (skipped on Windows; code path exists).
9. OAuth-protected MCP, SSE streaming, resources/prompts (not implemented).
10. PageSpeed/Lighthouse, field Core Web Vitals, gzip/CDN/HTTP2, and high-concurrency load.
11. A controlled baseline comparison against Twenty Twenty-Five.
12. PHP 7.4 and WordPress 6.6 runtime testing (lint targets 7.4 syntax, but the runtime used PHP 8.0/WP 7.1.3 only).
13. Automated accessibility audit (keyboard navigation was spot-checked; no axe/WAVE run).
14. Media upload, plugin activation and rollback through MCP (intentionally unsupported).

## 9. Performance

See `PERFORMANCE.md`. Summary: homepage 4 CSS / 2 JS / 26,416 B HTML; blank canvas 4 CSS / 1 JS / 24,664 B; no development tooling on public pages. Local TTFB 0.50–0.70 s with no caching; not a production score.

## 10. Release artifact verification

- ZIP built with `build-release.php` from the theme folder only.
- 57 entries, single `CodeCanvas/` root; `CodeCanvas/style.css`, `functions.php`, `inc/mcp.php`, `inc/mcp-settings.php`, docs, tests and release metadata present.
- No `.git`, `node_modules`, screenshots, nested duplicate theme folder, `.env`, `wp-config.php`, key files or `.cc-*` temp files.
- ZIP consistency check (`ZipArchive::CHECKCONS`) passed.
- Extracted copy: all PHP files pass `php -l`; `style.css` header reports `Version: 1.7.0`, `Requires at least: 6.6`, `Requires PHP: 7.4`, `Text Domain: codecanvas`.
- SHA-256 is printed by `php build-release.php` when the package is built; rebuild after any source change and use that value for the published artifact.

A live upload/activation of the ZIP on a **fresh** WordPress installation was not performed (see section 8). The ZIP was extracted and linted instead; static structure does not prove activation behavior.

## 11. Release recommendation

Ship 1.7.0 as an independent, GPL theme release with the limitations above documented. Before enabling MCP or browser source writes on a production site:

- take a full backup,
- use HTTPS,
- keep writes disabled until needed,
- select only the required project/tools,
- revoke tokens after use,
- test the exact host and PHP version.

Do not describe the release as WordPress.org-compliant, sandboxed, or free of all risk.
