# Document control

| Field | Value |
|---|---|
| Project | CodeCanvas |
| Document edition | 1.8 |
| Document date | 22 October 2026 |
| Documented package | CodeCanvas WordPress Plugin v2.0.0 |
| Developer | [Suraj Dev](https://surajdev.com/) |
| Credit / project home | [Webot](https://webot.surajdev.com/) |
| License | GPL-2.0-or-later |
| Distribution | WordPress.org plugin ZIP; generates a standalone website theme |

**Purpose.** This is the complete product specification, user guide, source architecture, security guide, QA record and roadmap for CodeCanvas. It separates current implementation from planned future capabilities. Version 1.6.2 simplifies the dashboard to one Code Studio entry and formalizes child-theme-first development so site-specific code is kept out of the framework/admin theme.

**Patch 1.6.3 (21 October 2026).** No navigation or workflow changes. Fixes: the framework read-only guard is now case-insensitive and verifies the resolved filesystem path; the child theme inherits parent menu locations and custom logo once on activation; generated child starter templates no longer nest `<main>`, reuse the parent layout classes, and keep the parent homepage until replaced; parent/child stylesheets load in the correct order with mtime cache-busting for the child theme. See `CHANGELOG.md` and `qa-report.md`.

**Release 2.0.0 (22 October 2026).** CodeCanvas is now a plugin, not a theme. The plugin owns Code Studio, the coded-page editor, MCP/OAuth, settings and the recovery guard, and it generates the standalone `CodeCanvas Site` theme for the front end. The CodeCanvas sidebar contains **Welcome**, **Code Studio** and **MCP & Settings**; coded pages are under **Pages → CodeCanvas Editor**.

The 2.0.0 release also **removed the Views & Templates, Content Types and Global Code screens**. Rendering is now theme files plus coded pages only, with nothing stored in the database that can override a template, and custom post types and taxonomies are registered in the website theme's `functions.php`. Sections below that describe a theme package, a single-entry sidebar, database-saved views, a global-code screen or an admin content-type registry are historical and apply to 1.6.x–1.7.x only.

**Release 1.7.1 (21 October 2026).** Adds OAuth 2.1 sign-in for the MCP server on top of the 1.7.0 MCP release. Sections 1–23 retain their 1.6.x wording as the historical specification; these addenda record the current release state.

**Release 1.7.2 (22 October 2026).** Completes the MCP tool surface so a client can finish a build: patching, snapshot restore, render/error-log checks, full content fields, site settings, menus, media upload and content deletion.

**Release 1.7.3 (22 October 2026).** Adds `create_plugin`, so a client can start a brand new plugin project instead of only editing installed ones.

**Release 1.7.4 (22 October 2026).** Adds a recovery guard that restores the last snapshot after a PHP fatal error, and tells MCP clients to keep editable content in WordPress pages rather than hardcoded templates.

**Release 1.7.11 (22 October 2026).** Fixes two follow-up Code Studio layout problems: the project dropdown’s options now render light text on the dark bar (including the unselected row in the native popup), and the desktop workspace is locked to the viewport so the editor no longer creates a multi-thousand-pixel page scrollbar below the workbench.

**Release 1.7.10 (22 October 2026).** Fixes the Code Studio workspace failing to start: an undeclared shared wrapper reference now exists, so the Explorer loads files and every toolbar, search, history and shortcut binding runs. Moves the soft-wrap preference out of `init()` so CodeMirror actually starts instead of silently falling back to a plain textarea. The project selector now always lists both roots and labels one that the site's role or server policy does not allow, rather than rendering a disabled option that can disappear from the native popup; the static project label also shows the real root.

**Release 1.7.9 (22 October 2026).** Tightens MCP contracts and defaults: `get_php_error_log` returns an empty tail plus a hint instead of a tool error when no log exists; `delete_file` removes empty folders (non-empty refused); `create_plugin` is scope-neutral unless `enable_mcp: true` is passed; and `update_menu` documents when `menu_id`/`item_id` are required.

**Release 1.7.8 (22 October 2026).** Makes Code Studio mobile-first: the Explorer becomes an off-canvas drawer below 782px with a backdrop, close button, `Esc` support and auto-close on file selection; the activity rail turns horizontal; controls use 38–44px touch targets, the search field is 16px, editor type is 15px, and the layout uses `100dvh` with safe-area padding. Desktop behaviour is unchanged.

**Release 1.7.7 (22 October 2026).** Refreshes the Code Studio interface: grouped dark top bar with icons and sticky positioning, responsive collapse to icon-only controls and stacked rows, a dismissible policy note, soft wrap on by default with a Wrap toggle and `Alt+Z`, styled dark scrollbars, flexible workbench height, and focus mode that is applied only on demand.

**Release 1.7.6 (22 October 2026).** Adds a **focus mode** to Code Studio: a title-bar control and `Ctrl/Cmd + Shift + M` hide the WordPress admin bar, menu, footer, screen options and notices so the workbench fills the viewport; `Esc` exits and the choice is kept for the tab session.

**Release 1.7.5 (22 October 2026).** Adds a branded **Welcome** page as the first item in the CodeCanvas admin menu: brand hero, parent/child theme state, MCP state with enabled tool count, recovery-guard status, and shortcuts to Code Studio, coded pages, MCP settings, Pages, Themes and Reading settings. Activation and the first-run redirect now land there, and the old onboarding checklist with removed routes is gone.

# Version 1.7.11 addendum

## A. Project selector contrast and viewport lock

- `assets/css/studio.css`: the project `<select>` and its `<option>` rows now set an explicit near-white `color`/`-webkit-text-fill-color` on the dark bar, with `color-scheme: dark` and an explicit option background. Previously the unselected “Plugins” row inherited black text on the dark native popup and was unreadable.
- `assets/css/studio.css`: on desktop (min-width 783px) `.cc-studio-wrap` now has a definite `height: calc(100vh - 32px)` with `overflow: hidden`, and the workbench’s minimum height is released. With only `min-height`, the auto-height flex/grid row sized itself from the editor content and produced an endless page scrollbar; the editor now scrolls inside its own pane and the page height stays at the viewport.
- Mobile keeps its scroll-with-page layout and drawer behaviour; on phones the header remains static and the workspace keeps its minimum height.
- Verification: suite **282 passed, 0 failed, 1 skipped**. A 1920×1080 browser session with a 500-line file kept `document.scrollHeight` at 1080 while CodeMirror scrolled internally (12,386px of editor content), and both selector options computed to light text (`rgb(245, 255, 248)`) with `color-scheme: dark`.

# Version 1.7.10 addendum

## A. Studio start-up and editor fixes

- `assets/js/studio.js`: declares the shared `.cc-studio-wrap` jQuery reference. Its absence threw a `ReferenceError` while `init()` bound the drawer, so everything after that point — the initial file list, project switch, search/history panels and keyboard shortcuts — never ran and the page stayed on “Loading files…”.
- `assets/js/studio.js`: `wrapEnabled` is now shared outside `init()`; previously `activateTab()` hit a block-scope `ReferenceError` that the editor’s `try/catch` swallowed, forcing the plain textarea fallback. CodeMirror failures are now logged to the console.
- `assets/js/studio.js`: initialisation is wrapped so an unexpected error appears in the status bar and toast instead of leaving a silent, half-wired page.
- `inc/dev-studio.php`: the project selector always renders both roots; an unavailable root is labelled “— not available” and explained on selection. The initial explorer label shows the real root instead of “CodeCanvas”.
- Verification: automated suite and a live browser session (open file, edit/save, wrap, focus, search, history, root switch, refresh, notice dismiss).

# Version 1.7.9 addendum

## A. MCP contract fixes

- `get_php_error_log` (`inc/mcp-tools.php`): a missing log is a normal state. Returns `available: false`, `lines: []`, `bytes: 0` and a `hint` describing temporary `WP_DEBUG`/`WP_DEBUG_LOG` use, with no `isError`.
- `delete_file` (`inc/mcp.php`): a directory path is handled separately. The project root, symlinks and unwritable folders are refused; a non-empty folder reports how many entries remain; an empty folder is removed with `data.type: folder`. Folders are not snapshotted, which the summary states.
- `create_plugin` (`inc/mcp-tools.php`): the allow-list update only happens when the caller passes `enable_mcp: true` (new schema field, default false). Otherwise the plugin is created but unapproved, `create_file` against it is rejected, `list_projects` shows the project with an explanatory note, and `mcp_enabled` is false.
- `update_menu`: schema descriptions now state that `create` returns `menu_id`, every other action requires it, and `update_item`/`remove_item` also require `item_id`.
- Verification: automated suite **276 passed, 0 failed, 1 skipped**, covering the no-log response, empty and non-empty folder deletion, default scope neutrality, the opt-in path, and cleanup of both scaffolded plugins.

# Version 1.7.8 addendum

## A. Mobile-first Studio

- Breakpoint: `max-width: 782px`, matching the WordPress mobile admin bar (46px). The title bar is static on phones so it cannot overlap the policy note or editor; focus mode hides the WordPress chrome and uses the full `100dvh` height.
- Drawer: `.cc-studio-sidebar` becomes `position: fixed` with `translateX(-102%)`, and `.cc-mobile-explorer-open` slides it in. `#cc-studio-drawer-backdrop` and `#cc-studio-drawer-close` dismiss it; so do `Esc`, file selection, search results and tab clicks. Choosing Search or History in the activity rail opens that panel in the same drawer.
- The activity rail is horizontal at the top of the workspace with 44px targets; the explorer close control is visible only on mobile; every icon-only button has an `aria-label`, and the explorer button exposes `aria-expanded`/`aria-controls`.
- Typography and touch: 15px/1.75 editor text, 16px search input (avoids iOS zoom), 40px file rows, 38px top-bar buttons, 30px status bar with encoding/cursor dropped on the narrowest screens, and safe-area padding on the drawer.
- Verification: automated suite **270 passed, 0 failed, 1 skipped**, including assertions for the drawer markup, slide-in CSS, `100dvh`, touch-sized rows, breakpoint wiring and `aria-expanded`. Studio renders through its callback with the drawer and backdrop present; the JavaScript parses in Chromium.

# Version 1.7.7 addendum

## A. Studio interface refresh

- Top bar (`inc/dev-studio.php` + `assets/css/studio.css`): four action groups (project, file actions, view actions, save), dashicons on every control, `title` tooltips, dark green palette, sticky positioning, and consistent hover/focus-visible/disabled states.
- Responsive breakpoints: 1240px hides button labels, 960px stacks brand and actions and hides the brand subtitle, 782px widens the activity rail/sidebar split and hides secondary status fields, 640px hides the sidebar so the editor fills the width.
- The policy note is dismissible; the dismissal is stored under `ccStudioNotice` with the theme version as its value, so a new release shows it once again.
- Wrap: `ccStudioWrap` in localStorage, default on, toggle in the editor toolbar and `Alt+Z`; CodeMirror receives `lineWrapping` and `scrollPastEnd`. Fallback textareas switch to `white-space: pre` when wrap is off.
- Scrolling: thin dark scrollbars for the CodeMirror scroller, Explorer, search results and inspector; tabs hide their scrollbar; the workbench is a flex child of the Studio wrapper instead of a fixed `calc()` height.
- Focus mode: the `sessionStorage` restore added in 1.7.6 was removed, so chrome is hidden only by an explicit toggle; `Esc` and `Ctrl/Cmd + Shift + M` still work.
- Verification: automated suite **263 passed, 0 failed, 1 skipped**, including static assertions for the wrap control, notice dismissal, responsive CSS, scrollbar styling and on-demand focus behaviour; JavaScript parsed in Chromium.

# Version 1.7.6 addendum

## A. Studio focus mode

- `#cc-studio-focus` in `inc/dev-studio.php` adds a title-bar button; `assets/js/studio.js` toggles `cc-focus-mode` on `<html>` and `<body>`, updates the button label and `aria-pressed`, and refreshes CodeMirror after the layout change. The `sessionStorage` restore from 1.7.6 was removed in 1.7.7 so the mode is strictly on demand.
- `assets/css/studio.css` hides the admin bar, admin menu, footer, screen options, top-level notices and the Studio footer, removes the toolbar offset, and switches the workbench to a flex layout that fills the viewport.
- Keyboard: `Ctrl/Cmd + Shift + M` toggles; `Esc` exits. The mode is Studio-only and never affects the public site or other admin screens.
- Verified: JavaScript parses (checked with `new Function()` in Chromium); static assertions cover the control, the Chrome-hiding CSS, the footer rule, persistence and the `Esc` handler. Automated suite: **256 passed, 0 failed, 1 skipped**.

# Version 1.7.5 addendum

## A. Branded welcome screen

- `inc/admin.php` renders **CodeCanvas → Welcome** and `inc/dev-studio.php` registers it as the top-level menu callback. The menu reads Welcome, Code Studio, MCP & Settings; `admin.php?page=codecanvas` now renders this page instead of redirecting to Studio.
- The page shows the brand hero, active theme (parent or child, with the parent name), MCP state and number of enabled tools, and recovery-guard state, followed by six build shortcuts (Code Studio, coded pages, MCP setup, Pages, Themes, Reading settings) and short “Start here” and “Safety first” panels.
- Every link points at a screen that exists in 1.7.x; the previous legacy checklist and its removed routes are gone.
- `after_switch_theme` and the first-run redirect now open the welcome page.

## B. Verification status for 1.7.5

- Automated suite: **252 passed, 0 failed, 1 skipped**, including assertions that the welcome page is the first menu item, is labelled “Welcome”, links to the workspace and settings, shows the current version and has no links to removed screens.
- Menu order, page output and admin stylesheet gating verified against the local WordPress install.

# Version 1.7.4 addendum

## A. Recovery guard

- `inc/recovery-guard.php` is installed as `wp-content/mu-plugins/codecanvas-recovery.php` so it loads before the theme and plugins. CodeCanvas installs it automatically and refreshes it when the shipped copy changes, and exposes install/repair/remove plus the recovery log under **CodeCanvas → MCP & Settings → Recovery guard**.
- On a fatal error from a theme or plugin file it restores the newest CodeCanvas snapshot for that exact file, invalidates opcache and logs the event. Files with no snapshot are untouched; repeated restores within 30 seconds are skipped; an unrelated file with the same name is never overwritten; `DISALLOW_FILE_MODS` blocks installation.
- Verified live: corrupting the child theme's `front-page.php` produced `500` on the first request, the guard restored it, and the next request returned `200` with identical bytes.

## B. Editable content guidance

The `initialize` instructions now state that page copy belongs in `create_content`/`update_content` records, and that a static front page is set with `update_settings` (`show_on_front=page`, `page_on_front`) rather than by hardcoding `front-page.php`. This is what makes MCP-created pages editable in the block editor.

## C. Verification status for 1.7.4

- Automated suite: **243 passed, 0 failed, 1 skipped**.
- Live fatal-recovery test on WordPress 7.1.3 / PHP 8.0.30 as described above.
- Not yet verified: recovery on a host that blocks mu-plugin writes, multi-site, and PHP 7.4 / WordPress 6.6.

# Version 1.7.3 addendum

## A. Creating a plugin

- `create_plugin` takes a `slug`, a plugin `name`, and optional `description`, `version`, `author`, `license` and `files[]`.
- It creates `wp-content/plugins/<slug>/` with `<slug>.php` carrying a valid plugin header, validates every PHP file with `TOKEN_PARSE`, and writes nested extra files inside the new folder only.
- Existing folders are never overwritten and unsafe paths are rejected.
- The new `plugin:<slug>` key is added to the MCP project allow-list after a discovery refresh, so the client can immediately call `create_file`/`patch_file` in it without a manual settings trip.
- The plugin is **not** activated. Activation remains an explicit administrator action in **Plugins**, because activating unfinished code can fatal the site.
- Gates: the MCP server enabled, **Allow file writes**, `edit_plugins`, and hosting policy that permits file changes.

## B. Verification status for 1.7.3

- Automated suite: **236 passed, 0 failed, 1 skipped** (symlink creation is unavailable on this Windows host).
- `create_plugin` coverage: invalid slug rejected, existing folder rejected, header file and nested extra files created, `plugin:<slug>` persisted and reported as `mcp_enabled`, further MCP writes accepted, scaffolded folder removed by the test.
- The 1.7.2 tool matrix in the next addendum still applies.
- Not yet verified: `create_plugin` on a host where `wp-content/plugins` is not writable, and activation behaviour (deliberately not exposed).

# Version 1.7.2 addendum

## A. Tool surface (24 tools)

- **Read (12):** `site_info`, `list_projects`, `list_files`, `read_file`, `search_files`, `list_content`, `get_content`, `get_settings`, `list_menus`, `list_snapshots`, `render_url`, `get_php_error_log`.
- **File write (6):** `write_file`, `patch_file`, `create_file`, `create_files`, `delete_file`, `restore_snapshot`.
- **Content write (6):** `create_content`, `update_content`, `delete_content`, `update_settings`, `update_menu`, `upload_media`.

The new tools live in `inc/mcp-tools.php` and register through the `codecanvas_mcp_tool_catalog` filter. `tools/list` now returns the whole catalogue once, with `annotations.codecanvasEnabled`; a disabled tool names the Settings switch that enables it, and the dispatcher still refuses it. This removes the earlier behaviour where a tool could vanish between client sessions.

## B. Behaviour changes worth knowing

- `patch_file` replaces an exact `old_string` (optionally `replace_all`) instead of resending a file, and fails clearly on zero or ambiguous matches.
- Snapshots were previously only visible in Code Studio. `list_snapshots` and `restore_snapshot` expose them to the client, restore snapshots the current contents first, and a restore recreates a deleted file.
- `delete_content` mirrors `delete_file`: trash by default, permanent only with `confirm: true` and `force: true`. `update_content` also accepts `status: "trash"`.
- The server tracks the hash it last returned for each file. A stale `write_file` or `patch_file` is refused even without `expected_sha256`, and the error includes the current hash and modification time.
- `list_files` walks up to ten folder levels in one call; `list_files`, `search_files` and `list_content` expose `offset`/`next_offset`, and `list_content` reports `total` and accepts `post_type: "any"`.
- Content tools cover slug, parent, page template, featured image, categories, tags and public custom fields; `create_content` returns the final permalink.
- Site-level tools: `get_settings`/`update_settings` (title, tagline, static front page, posts page, posts per page, permalink structure) and `list_menus`/`update_menu` (create, add, edit, remove, assign location, delete). `upload_media` stores base64 files up to 700 KB with alt text and metadata.

## C. Verification status for 1.7.2

- Automated suite: **223 passed, 0 failed, 1 skipped** (symlink creation is unavailable on this Windows host).
- Live HTTP on WordPress 7.1.3 / PHP 8.0.30: `initialize` reports 1.7.2, `tools/list` returns 24 tools, `patch_file` ambiguous/unique, snapshot list/restore (including recreating a deleted file), `create_files`, `delete_content` trash then permanent, `get_settings`, `list_menus`, `update_settings` set and clear tagline, `upload_media` (1×1 PNG, MIME and alt text, `.php` refused), `render_url` home page `200` with no PHP error, and `get_php_error_log` guidance when no log exists.
- Not yet verified: a real AI client driving every new tool, a host that blocks loopback rendering, non-standard `error_log` paths, servers without GD, and PHP 7.4 / WordPress 6.6.

# Version 1.7.1 addendum

## A. OAuth sign-in for browser clients

claude.ai and Claude Desktop connectors cannot send a fixed `Authorization` header; their dialog offers OAuth sign-in or an unauthenticated connection. 1.7.1 therefore adds a small OAuth 2.1 authorization server in `inc/mcp-oauth.php`:

- Discovery: `/.well-known/oauth-protected-resource` (RFC 9728) and `/.well-known/oauth-authorization-server` (RFC 8414); the MCP `401` challenge advertises `resource_metadata`.
- Endpoints: `/codecanvas-oauth/authorize`, `/token`, `/register` (RFC 7591 dynamic client registration) and `/revoke` (RFC 7009).
- Grant: authorization code with mandatory PKCE `S256`, five-minute single-use codes, one-hour access tokens and rotating 30-day refresh tokens. Client ID Metadata Documents (an HTTPS client ID whose JSON document lists its redirect URIs) are supported alongside dynamic registration.
- Consent: a standalone admin screen lists the client, redirect host, signed-in account, tools, projects and write modes. Only `manage_options` users can approve, and grants stop working if that account loses the capability.
- Storage: access and refresh tokens are HMAC-SHA-256 hashes in `wp_options` (not autoloaded); plaintext exists only in the client. Registration is capped at 20 clients per IP per hour, 100 clients and 200 grants.
- Controls: **CodeCanvas → MCP & Settings** gains an **OAuth sign-in** panel with active-grant counts, the issuer, the discovery URLs and **Revoke all OAuth connections**.
- The static bearer token and `CODECANVAS_MCP_TOKEN` continue to work unchanged.

## B. Limits

- The two discovery documents require a root installation; a subdirectory install serves them under the install path, which most AI clients do not probe.
- A publicly reachable HTTPS site is required. WordPress Playground cannot host an MCP endpoint.
- Only the authorization-code + PKCE grant with public clients is implemented; there is no client-secret, device-code or implicit flow.

## C. Verification status for 1.7.1

- Automated suite: **165 passed, 0 failed, 1 skipped** (symlink creation is unavailable on this Windows host), including OAuth metadata, registration validation, PKCE, code binding/single use, exchange, refresh rotation, revocation and OAuth-token authentication of MCP requests.
- Live HTTP flow on WordPress 7.1.3 / PHP 8.0.30 verified: dynamic registration `201`, anonymous authorize `302` to the login screen with parameters preserved, consent page `200`, approval `302` with code and state, wrong PKCE verifier `invalid_grant`, token exchange `200`, `initialize` and `tools/list` with the OAuth access token `200`, refresh rotation with reuse rejected, revocation `200` followed by MCP `401`, denial redirect `access_denied`, unregistered redirect URI `400`, `GET` on the token endpoint `405`, and the admin revoke-all action `302` clearing grants and clients.
- Not yet verified: a real claude.ai connector against a public host (requires the deployed 1.7.1 build), Client ID Metadata Document fetching against Anthropic's live document, hosts that rewrite `/.well-known/` outside WordPress, and PHP 7.4 / WP 6.6 runtime.

# Version 1.7.0 addendum

## A. Navigation

`CodeCanvas → Code Studio` remains the single developer workspace entry. `CodeCanvas → MCP & Settings` is a configuration screen, not a second development workspace. No legacy Getting Started, Views, Global Code, Content Types or Security submenu is displayed. The normal WordPress dashboard, block editor, media library, users, plugins and settings remain unchanged.

## B. MCP server

CodeCanvas 1.7.0 implements an optional, off-by-default MCP server:

- Endpoint: `/wp-json/codecanvas/v1/mcp`; JSON-RPC 2.0 over Streamable HTTP `POST`; protocol revisions 2025-06-18, 2025-03-26 and 2024-11-05.
- Authentication: dedicated bearer token, stored as an HMAC-SHA-256 hash keyed with WordPress salts, bound to the generating administrator, compared with `hash_equals()`. WordPress cookies are not accepted. A `CODECANVAS_MCP_TOKEN` constant takes precedence over the stored hash and maps to a current administrator for constant-only deployments.
- Authorization: per-project and per-tool allow-lists; independent file-write and content-write switches; `manage_options` re-checked on each request.
- Tools (12): `site_info`, `list_projects`, `list_files`, `read_file`, `search_files`, `list_content`, `get_content` (read-only); `write_file`, `create_file`, `delete_file` (file write); `create_content`, `update_content` (content write).
- File safety: enabled projects only; framework theme excluded; canonical path checks; traversal/symlink/null-byte rejection; blocked names/extensions; 256 KB write cap; PHP `TOKEN_PARSE` validation; credential-pattern checks; pre-change snapshots; optional `expected_sha256` conflict detection; delete confirmation and bootstrap-file protection.
- Operations: 1 MB request cap, 1–600 requests/minute per token, `401`/`405`/`429`/`503` transport errors, `-32700`/`-32600`/`-32601`/`-32602` JSON-RPC errors, 50-entry audit log without file contents or tokens, `no-store` responses.
- Settings screen: connection status, endpoint, one-time token generation/revocation, project/tool scopes, client snippets, audit table and security notes.

Full reference: `docs/11-mcp-integration.md`. Developer architecture: `docs/12-developer-guide.md`. Install/upgrade/recovery: `docs/13-install-upgrade-recovery.md`.

## C. Fixes in 1.7.0

- The MCP option sanitizer now preserves and validates token fields; previously `register_setting()`'s sanitizer (which runs on every `update_option()`) discarded a newly generated token.
- Invalid nested settings forms were removed; token actions now use external hidden forms and the HTML `form` attribute, so Save persists the settings fields.
- Added an explicit “MCP settings saved.” notice.
- The exposed-tool counter reports 0 while the server is disabled.
- The endpoint answers non-POST methods with `405`/`Allow: POST` and preserves JSON-RPC parse errors by handling the raw body before WordPress REST JSON validation.
- Blank-canvas layouts skip the shared menu script while retaining a handle for inline JavaScript.
- Backups, snapshots and Studio histories were expanded with tests and documentation.

## D. Verification status

- Automated suite: **129 passed, 0 failed, 1 skipped** (symlink creation is unavailable on this Windows host).
- PHP lint: 31 files, 0 failures. `theme.json` valid. JavaScript loaded in Chromium without console errors; no Node parser was installed. CSS was not formally linted.
- Live HTTP MCP tests passed for `401`, `405`, `initialize`, `tools/list`, `read_file`, `create_file`, `write_file`, `delete_file`, traversal rejection, malformed JSON and rate limiting.
- Browser checks passed for the MCP settings screen (save, generate, revoke, default disabled state) and Code Studio.
- Not yet verified: fresh-install ZIP activation, Linux hosting, multisite, `DISALLOW_FILE_*`, FTP/SSH-credential hosts, two-account flows, real third-party MCP clients, OAuth/SSE, PHP 7.4/WP 6.6 runtime, and PageSpeed/field performance.

## E. Release position

1.7.0 is a tested release candidate for independent distribution and staging. It is **not** claimed as sandboxed, WordPress.org-compliant, or free of all host-specific risk. See `qa-report.md` and `SECURITY.md` for the remaining verification matrix and risk register.

# Contents

0. Version 1.7.0 addendum  
1. Executive summary  
2. Product vision and principles  
3. Problem statement and intended users  
4. Scope and feature status  
5. User experience and workflows  
6. Functional requirements  
7. System architecture  
8. Source tree and code responsibilities  
9. Data and storage model  
10. Code Studio: real-file editor  
11. Page editor, shared code and views  
12. Custom post types and taxonomies  
13. Security model and API credential policy  
14. Permissions and capability matrix  
15. Installation, updates and recovery  
16. Development workflow  
17. QA, validation and acceptance checklist  
18. Known limitations and risks  
19. Product roadmap  
20. Definition of done  
21. Support, contribution and ownership  
22. Glossary  
23. Official technical references

# 1. Executive summary

CodeCanvas is a free, standalone WordPress parent theme that preserves the familiar WordPress administration experience and adds optional code-first developer tools. Standard Pages, Posts, Media, Users, Settings, the block editor and normal publishing remain available. Developers can open CodeCanvas tools only when they need to work directly with code.

**Version 1.6.2 makes the developer entry point deliberately simple and formalizes the child-theme-first workflow.** WordPress remains the familiar content-management environment; CodeCanvas adds one optional top-level **Code Studio** menu item, not a stack of extra CodeCanvas configuration menus. The standard WordPress Pages, Posts, Media, Users, Plugins, Appearance and Settings menus remain available as usual.

CodeCanvas is the parent/framework theme. During first-time Code Studio setup, an administrator can create and activate a managed child theme named **CodeCanvas Site** (`wp-content/themes/codecanvas-site`). That child theme is the default website code workspace for frontend templates, CSS, JavaScript and site-specific PHP. The parent CodeCanvas framework is browsable but read-only through Code Studio, so website edits do not accidentally overwrite the developer tool itself. The setup action is explicit because creating files and changing the active frontend theme must not happen silently.

The current package includes a real source-file browser/editor for supported files under `wp-content/themes` and `wp-content/plugins`, guarded file operations, basic theme/plugin scaffolds, search, bounded snapshots, and a child-theme setup flow. Older page-code, view, global-code and content-type screens remain in the source for compatibility with earlier work, but v1.6.2 does not expose them as separate dashboard menu choices. The primary development path is to write website code in the CodeCanvas Site child theme or inspect/edit eligible plugin source files in Code Studio. Standard WordPress content editing remains native.

Features such as an integrated terminal, full Git UI, debugger, dependency manager, database schema studio, dedicated secrets vault and deployment pipeline remain planned. They are not presented as implemented. Static checks and isolated child-theme-generation smoke tests do not replace browser-based testing on a real WordPress/XAMPP or staging site.

## 1.1 Product positioning

**WordPress remains the content-management system. CodeCanvas adds an optional developer workspace.**

- Beginners can continue using the standard WordPress dashboard and editor.
- Developers can edit actual child-theme/plugin source files, create their own templates and build page-specific code.
- CodeCanvas framework/admin files are kept distinct from normal website files.
- WordPress continues to manage users, permissions, content records, media, URLs and publishing.
- The public website should not load Code Studio's IDE assets.
- Private API credentials belong on the server, not in browser-delivered code.

## 1.2 Status vocabulary used here

| Status | Meaning |
|---|---|
| Implemented in v1.6.2 | The source and static tests describe this capability as present; runtime behavior must still be confirmed on the target WordPress site. |
| Partial / bounded | A limited implementation exists, but it does not cover the full product expectation. |
| Planned | Part of the target roadmap; it must not be advertised as already available. |
| Restricted by design | Intentionally blocked to reduce risk, such as editing CodeCanvas framework files through the website workspace, editing WordPress core, or editing secret configuration files. |

# 2. Product vision and principles

## 2.1 Vision

Make WordPress a comfortable, familiar website administration system for everyone and, optionally, a powerful code-development environment for developers who want to control their site without a page builder or a stack of required plugins.

## 2.2 Mission

Provide an integrated workflow for writing, testing, organizing and maintaining website code while preserving WordPress's native content and publishing model and keeping the visitor-facing site lean.

## 2.3 Product principles

1. **WordPress first.** Do not replace the standard dashboard or force all users into CodeCanvas.
2. **Optional developer mode.** The IDE is an additional capability, not the only editing interface.
3. **Own code, not premade design.** Provide editing tools and safe scaffolds, not a required visual template system.
4. **Real files where expected.** Theme/plugin source editing must operate on actual eligible files.
5. **Clear boundaries.** Distinguish real filesystem files, page code stored in WordPress, template overrides and data records.
6. **Lean public output.** Do not enqueue Code Studio editor libraries, IDE CSS or admin scripts on public pages.
7. **Safety before convenience.** Validate paths, check capabilities, verify nonces, snapshot files and support recovery.
8. **Credentials stay server-side.** Never send private API secrets to the browser or commit them to source control.
9. **Recoverable changes.** Prefer Git and backups; the in-product snapshot history is only a convenience recovery layer.
10. **Honest scope.** Document what has passed static tests, what still needs runtime testing and what is not yet implemented.

## 2.4 What CodeCanvas is not

- It is not currently a complete Microsoft VS Code replacement.
- It is not currently a full IDE with terminal, Git, debugger, package manager, database migration tool or deployment pipeline.
- It is not a promise that any code is safe merely because it passes a syntax check.
- It is not a guarantee that the full version can be approved by the WordPress.org theme directory.
- It is not a replacement for a staging environment, source control, code review or a secure hosting setup.

---

# 3. Problem statement and intended users

## 3.1 Problems addressed

Traditional WordPress workflows can make developers choose between accepting a premade design, reconstructing a design in a page builder, editing files outside the dashboard, or combining several plugins to manage custom code. CodeCanvas aims to reduce that friction by placing common development tasks next to WordPress content and publishing workflows.

## 3.2 Primary users

- **Freelance developers:** build custom WordPress websites and client-specific layouts.
- **Small digital agencies:** maintain multiple custom sites and structured content types.
- **Technical marketers:** create landing pages and campaign layouts without a visual builder.
- **Theme/plugin developers:** inspect and modify eligible source files from WordPress Admin.
- **Site owners with coding skills:** make controlled changes without leaving the dashboard.

## 3.3 Secondary users

Beginners can continue using the standard WordPress editor. Code Studio is an advanced, optional developer workspace for users trusted to modify real source files and understand the consequences of executable code.

---

# 4. Scope and feature status

## 4.1 Current v1.6.2 feature inventory

| Capability | Status in v1.6.2 | Notes |
|---|---|---|
| Standalone GPL parent theme | Implemented | No companion plugin is required for the included theme features. |
| Single Code Studio Admin entry | Implemented in v1.6.2 | No separate CodeCanvas submenu list is registered; normal WordPress menus remain unchanged. |
| Normal WordPress Pages/Posts workflow | Preserved by design | Native WordPress content editing and publishing remain available. |
| Legacy page HTML/CSS/JS editor | Compatibility code retained, no separate v1.6 menu | The principal v1.6 workflow is editing real child-theme template files; legacy virtual page metadata should be reviewed during migration. |
| Sandbox live preview | Implemented / bounded | Restrictive preview prevents many external actions; test on the public site as well. |
| Shared header/footer/CSS/JS | Theme files only (2.0.0) | The Global Code screen is gone; edit `header.php`, `footer.php`, `assets/css/site.css` and `assets/js/site.js`. Legacy options are read by nothing. |
| Coded pages and token rendering | Implemented / bounded | Coded HTML in post content, CSS/JS/layout in meta; limited token system; no PHP evaluation from stored templates. |
| Custom post types and taxonomies | Website-theme PHP (2.0.0) | Register them in the website theme's `functions.php`; the admin registry was removed so there is one source of truth. |
| First-run child-theme setup | Implemented | One explicit setup action; no separate onboarding menu item. |
| Real theme/plugin source file browser | Implemented / beta | Supported text files under `wp-content/themes` and `wp-content/plugins`. |
| Create/edit/rename/delete eligible files | Implemented / bounded | Capabilities, WordPress file policy, path safety and filesystem permissions apply. |
| CodeCanvas Site child theme setup | Implemented in v1.6.2 | Explicitly creates missing starter files without overwriting existing files, validates the parent header, then activates on user confirmation. |
| Child theme as default website workspace | Implemented in v1.6.2 | Code Studio starts in `codecanvas-site` after it is active; CodeCanvas framework files are read-only within Studio. |
| Source search and basic theme/plugin scaffolds | Implemented / basic | Scaffolds are not automatically activated. |
| Snapshot history and restore | Implemented / bounded | Up to three snapshots per file; not a complete backup or Git system. |
| PHP parse validation before save | Implemented / partial | Catches many syntax errors, not runtime defects or unsafe behavior. |
| Integrated terminal | Planned | Not included. |
| Full Git UI / branches / commits | Planned | Use external Git tools for now. |
| Debugger / PHP runtime sandbox | Planned | No live PHP execution sandbox. |
| Database schema editor / migrations | Planned | Not included. |
| API secrets vault | Planned | No dedicated credential vault in v1.6.2. |
| Test runner / dependency manager / deployment pipeline | Planned | Use a controlled external development workflow for now. |

## 4.2 Out of scope for v1.6.2

- Editing WordPress core files from Code Studio.
- Editing CodeCanvas's own framework/admin files from the website-workspace editor.
- Editing `wp-config.php`, `.env`, `.htaccess`, `.user.ini`, SSH keys or files detected as secrets.
- Arbitrary binary files and source files above the editor's 1 MB limit.
- Arbitrary shell execution, remote command execution or a browser PHP execution sandbox.
- A guarantee of zero downtime, zero vulnerabilities or complete rollback from every fatal error.
- A promise that the complete package will pass WordPress.org theme-directory review; the full edition is for independent distribution.
- A separate custom Page Editor/Global Code/Views/Content Types submenu in v1.6.2; source-driven website work is done in the child theme.

# 5. User experience and workflows

## 5.1 CodeCanvas → CodeCanvas → Code Studio is the only custom developer menu path

CodeCanvas is the developer parent entry, with Code Studio as its only submenu. CodeCanvas removes the redundant same-name submenu WordPress normally creates for a top-level page, keeping the sidebar focused on coding. All native WordPress menus remain unchanged.

Version 1.6.2 simplifies the Admin sidebar. It registers one **CodeCanvas** parent item with a single **Code Studio** child and does not expose a CodeCanvas submenu list. Getting Started, Views & Templates, Global Code, Content Types, Security & Help and the separate CodeCanvas Page Editor are not shown as custom menu destinations in this version. The native WordPress dashboard remains unchanged.

This is intentional: CodeCanvas is a developer tool, not another settings dashboard. Developers work from one workspace and edit website behavior in the real child-theme files; WordPress continues to provide native content editing, plugin activation, theme selection, media, users and settings. The theme contains legacy view/content registration code for existing installations, but new site-specific logic should be written in the child theme and tested before migrating old database-saved views.

## 5.2 Preserve the standard WordPress experience

WordPress remains the default content-management interface. The normal Pages, Posts, Media, Users, Plugins, Appearance, Tools, Settings, block editor and publishing process remain available. CodeCanvas → CodeCanvas → Code Studio is the only custom developer menu path. Child-theme setup never switches the active frontend theme silently; an administrator must explicitly confirm the **Create and activate CodeCanvas Site child theme** action.

## 5.3 First-time website workspace setup

1. Install and activate CodeCanvas as the parent theme on a local or staging site.
2. Open the single **Code Studio** item in the WordPress Admin sidebar. No Getting Started, Views, Global Code, Content Types or Security submenu list is shown.
3. On first use, the workspace explains that CodeCanvas itself contains the developer tools and that normal website code belongs in a child theme.
4. Click **Create and Activate CodeCanvas Site** to create `wp-content/themes/codecanvas-site` and explicitly activate it.
5. Setup creates only missing files, refuses symlinks and incompatible folders, validates the `Template` header, and never overwrites existing child files.
6. After activation, Code Studio opens `codecanvas-site/` by default. CodeCanvas parent files can be inspected but are read-only through Studio. The project selector still lets a trusted developer switch to eligible plugin source when needed.

If server policy prevents theme writes or switching, create the child theme through VS Code/SFTP/Git and activate it via the standard Appearance → Themes screen. Do not loosen file permissions broadly.

## 5.4 Workflow A — Write an independent page

1. Use the normal WordPress Pages screen to create/edit page content, title, slug, status and publishing settings.
2. To control the layout with code, open Code Studio and edit the `CodeCanvas Site` child theme.
3. Use `page.php` for the shared page template or create a WordPress page template / `page-{slug}.php` file for a specific page.
4. Put global styles and scripts in `assets/css/site.css` and `assets/js/site.js`; put template markup in the appropriate PHP template.
5. Preview the public page on staging at desktop/tablet/mobile widths, then publish content through WordPress.
6. Test behavior, accessibility, forms, URLs and front-end asset loading.

**Navigation rule:** v1.6.2 intentionally removes the separate CodeCanvas Page Editor submenu. The primary frontend workflow is source-code editing in the child theme, while the native WordPress Pages editor remains the standard content editor. Previous virtual page-code metadata may exist on upgraded sites; review/migrate legacy page views before relying exclusively on child-theme templates.

## 5.5 Workflow B — Edit website files

1. Confirm CodeCanvas Site is the active child theme.
2. Open **Code Studio** from the WordPress Admin sidebar; the default theme project should be `codecanvas-site/`.
3. Edit `front-page.php`, `page.php`, `single.php`, `archive.php`, `404.php`, `assets/css/site.css`, `assets/js/site.js` or other site-specific files.
4. Save; inspect the change; open the public site in another tab.
5. Use History for a recent file recovery if the editor is still available; use Git/filesystem backups for real recovery.
6. Test on staging, check logs and commit/deploy through a controlled process.

## 5.6 Workflow C — Edit a plugin or create a scaffold

1. Choose the Plugins root to browse a plugin's eligible source files, or use the starter action to scaffold a new plugin.
2. Review the code and permissions before using it.
3. Test in staging; scaffolds are not activated automatically.
4. Activate through WordPress's normal Plugins UI only after review.

## 5.7 Workflow D — Build structured content

1. Register a type such as `project` using `register_post_type()` inside the child theme's `functions.php` or an included PHP module.
2. Register a taxonomy such as `project_category` with `register_taxonomy()` and attach it to the intended type.
3. Add records through the new WordPress Admin menu created by the registration.
4. Design `single-project.php` for individual items, `archive-project.php` for the listing, and `taxonomy-project_category.php` for category pages.
5. Keep registrations modular in files such as `inc/content-types.php` and require that file from the website theme's `functions.php`.
6. If routes return 404, save Settings → Permalinks again.

This is the v1.6 code-first model: content structures are defined in real child-theme PHP files rather than a separate CodeCanvas configuration menu. When the CodeCanvas parent is removed or switched away, any registrations still housed in the parent may disappear, so put site-specific registrations in the child theme.

# 6. Functional requirements

## 6.1 Code Studio requirements

- Display a clear file tree and current root.
- Browse only allowed roots: themes and plugins.
- Open supported text files in the editor with the best language mode available.
- Create file and folder; rename and delete eligible items.
- Search supported files and display matching paths/lines.
- Save files while preserving quotes, backslashes and newline content.
- Validate PHP/PHTML parse syntax where PHP tokenizer support is available.
- Take a bounded snapshot before writes, renames, deletes and restores.
- Provide restore history for recent snapshots.
- Provide keyboard shortcuts: `Ctrl/Cmd+S` save; `Ctrl/Cmd+P` quick-open by path; `Ctrl/Cmd+Shift+F` search.
- Give clear error messages for permission, file-size, unsupported type, path and filesystem errors.
- Never automatically activate generated plugins or themes.

## 6.2 Page editor and view system requirements

- Separate HTML, CSS and JavaScript editing.
- Page title, slug/status/layout fields work with WordPress.
- Unsaved-change handling and a truthful save state.
- Sandboxed preview with restricted network behavior.
- Support shared header/footer and blank-canvas options.
- Support the existing view/token renderer where retained for compatibility, while treating child-theme files as the primary source for new website development.
- Use only a limited dynamic token/loop syntax for database-stored views; never eval arbitrary PHP from those fields.
- Keep the relationship between database-saved overrides and theme-file templates visible to developers.

## 6.3 Content type requirements

- Validate post type and taxonomy keys against WordPress rules and reserved names.
- Allow labels, public visibility, archive behavior and supported editor features to be configured.
- Attach taxonomies to the intended content types.
- Do not expose taxonomies publicly when their only attached content types are private.
- Document rewrite/permalink behavior and theme-switch portability limits.

## 6.4 Front-end requirements

- Keep editor/Studio assets restricted to admin pages.
- Use WordPress template APIs and escaping conventions.
- Render custom content through known tokens and queries.
- Avoid loading unrelated development tooling on public routes.
- Support responsive layouts, keyboard navigation and readable focus styles.

---

# 7. System architecture

## 7.1 Logical architecture

```text
WordPress Admin
  ├─ Standard WordPress editing and publishing (unchanged)
  ├─ CodeCanvas welcome / settings / page editor
  └─ CodeCanvas Code Studio (optional real-file development workspace)
                  │
                  ├─ Themes root: CodeCanvas parent (read-only in Studio)
                  ├─ Themes root: CodeCanvas Site child (default website workspace)
                  └─ Plugins root: eligible plugin files (permission-controlled)
                  │
                  ▼
             WordPress APIs
  ├─ Capabilities, nonces and file-modification policy
  ├─ Posts, post meta, options and media
  ├─ Template hierarchy, custom queries and taxonomies
  ├─ Filesystem/path/symlink/extension/size checks
  └─ Parent assets + child-theme assets and templates
                  │
                  ▼
             Persistent storage
  ├─ WordPress database (content, settings and bounded snapshots)
  └─ Server filesystem (parent framework, child website files and plugin files)
                  │
                  ▼
               Public frontend
  ├─ WordPress template hierarchy: child override → parent fallback
  ├─ Website-specific template/CSS/JS from CodeCanvas Site
  ├─ Core theme assets and queried WordPress content
  └─ No Code Studio editor assets
```

## 7.2 Parent/child separation

- **CodeCanvas parent (`wp-content/themes/CodeCanvas/`):** developer/admin implementation, settings, page editor, views, custom content registration, Code Studio and shared frontend foundation. Studio can inspect this folder but refuses writes to it.
- **CodeCanvas Site child (`wp-content/themes/codecanvas-site/`):** normal website customizations. It is the default Themes workspace after setup. It contains the site's own templates, CSS, JavaScript and site-specific PHP hooks.
- **Plugins root:** third-party or site-specific plugin source, subject to capability/policy/path controls.
- **WordPress database:** content records, page editor code, settings, view definitions and bounded file snapshots.

The child theme is generated only after an administrator explicitly confirms the action. It uses a fixed folder slug `codecanvas-site`; a conflicting or symlink folder is not overwritten or followed. Existing child files are preserved.

## 7.3 Storage and execution boundaries

- **Content data:** WordPress pages, posts, custom content records, taxonomy terms and metadata.
- **Virtual page source:** HTML in the page content and CSS/JS/layout fields in metadata.
- **Coded pages:** post content plus `_codecanvas_page_*` meta, the only database-stored rendering CodeCanvas reads.
- **Real source:** child-theme and plugin files read/written through guarded filesystem operations.
- **History snapshots:** limited pre-change content stored in a non-autoloaded option.
- **Secrets:** not managed by a dedicated vault in v1.6.2; use server-managed environment/configuration outside public code.
- **Public output:** only frontend templates and required assets, not the Code Studio UI bundle.

# 8. Source tree and code responsibilities

The distributed parent theme contains these main areas:

```text
CodeCanvas/
├── style.css                  # Theme metadata
├── functions.php              # Parent bootstrap / module loader
├── theme.json                 # Editor/design configuration
├── header.php / footer.php    # Parent shared shell
├── front-page.php / home.php  # Parent front/blog templates
├── page.php / single.php      # Parent page and single fallbacks
├── archive.php / search.php   # Parent archive/search templates
├── 404.php / comments.php     # Fallback templates
├── templates/blank-canvas.php # Blank page template
├── inc/
│   ├── setup.php              # Theme supports and setup
│   ├── assets.php             # Parent frontend asset loading
│   ├── security.php           # Security helpers/secret checks
│   ├── admin.php              # Admin menus, settings and onboarding
│   ├── page-editor.php        # Page editor/save/preview workflow
│   ├── views.php              # Coded-page resolution, tokens and template routing
│   └── dev-studio.php         # Child setup and guarded real-file Code Studio
├── assets/css/                # Parent frontend + admin/editor styles
├── assets/js/                 # Parent frontend + admin/editor scripts
├── docs/                      # Getting started, views, security and Studio guides
├── README.md / SECURITY.md / docs/qa-report.md / CHANGELOG.md
├── LICENSE.txt
└── screenshot.png
```

After the administrator creates the website child theme, the separate folder looks like:

```text
wp-content/themes/codecanvas-site/
├── style.css                  # Child-theme metadata; Template: CodeCanvas
├── functions.php              # Website-only hooks and asset enqueueing
├── front-page.php             # Site homepage template
├── page.php                   # Static page template
├── single.php                 # Single post/content fallback
├── archive.php                # Archive/listing fallback
├── 404.php                    # Not-found view
├── assets/css/site.css        # Site-specific CSS
├── assets/js/site.js          # Public browser JavaScript
└── README.md                  # Child-theme file map and guidance
```

## 8.1 Bootstrap model

The parent `functions.php` defines version and parent paths, then loads separated modules under `inc/`. The child theme does not replace parent framework bootstrap; WordPress loads the parent functions as part of the child-theme relationship and resolves matching templates/assets through normal WordPress APIs.

## 8.2 Ownership rule

Routine website/frontend edits belong in `codecanvas-site`. CodeCanvas Admin/editor/security code belongs in the parent source repository and should be developed in a separate local Git checkout with normal test/deploy procedures. The in-dashboard file editor deliberately blocks writes to the parent folder to prevent accidental self-breakage.

# 9. Data and storage model

| Data | Storage in current design | Portability note |
|---|---|---|
| Standard page title/status/slug/excerpt/content | Native WordPress post record | Remains normal WordPress content. |
| Page HTML for CodeCanvas code-first page | WordPress page content | Existing block data may be rendered/converted when saved through the code-first workflow; back up first. |
| Page CSS/JS/layout | WordPress post metadata | Theme-specific meaning; custom styling may not follow another theme. |
| Global header/footer/CSS/JS | Theme files (2.0.0) | Part of the theme, so it moves with the theme and is visible in Git. |
| Coded page definitions | WordPress post content and metadata | Uses CodeCanvas's limited token syntax. |
| CPT/taxonomy definitions | Website-theme `functions.php` | Section menus/routes disappear if the theme providing them is switched off. |
| Theme/plugin source files | Actual server filesystem | Subject to filesystem policy and permissions. |
| Source history snapshots | Bounded non-autoloaded database option | Only three snapshots per file; not a backup replacement. |
| API private credentials | Not stored in a CodeCanvas vault in v1.6.2 | Use server environment/`wp-config.php`/hosting secret manager outside public source. |

## 9.1 Data safety principles

- Validate all keys, labels, paths and user inputs.
- Preserve the exact bytes of source content, including quotes, backslashes and newlines.
- Use WordPress APIs instead of constructing raw SQL where possible.
- Take a snapshot before file mutations.
- Keep snapshots bounded to avoid unbounded database growth.
- Treat source code and saved HTML/CSS/JS as potentially dangerous executable input.
- Do not expose private page code through unauthenticated REST endpoints.

---

# 10. Code Studio: real-file editor

## 10.1 What v1.6.2 does

Code Studio is the **only custom developer menu item** in WordPress Admin. It provides a guarded file explorer/editor for eligible real source files in the themes and plugins roots. It can read and save supported text files, create files/folders, rename/delete eligible files, search, create basic starter scaffolds and restore bounded snapshots, subject to WordPress capabilities, file-modification policy and filesystem permissions.

The important v1.6.2 changes are **child-theme-first workspace separation** and **single-entry Admin navigation**. The previous CodeCanvas submenu collection and separate Page Editor submenu are removed from the visible Admin navigation. Before setup, the Themes root shows an explanation and explicit button to create/activate `CodeCanvas Site`. Once active, the editor opens `codecanvas-site/` by default. Files in the parent `CodeCanvas/` folder are readable but treated as read-only within Studio. This is intended to prevent users from accidentally editing the CodeCanvas admin/editor UI while trying to change their own site.

The editor uses the CodeMirror functionality bundled with WordPress where available. This is not the full VS Code application; support depends on the bundled editor modes.

## 10.2 Roots and default target

- `wp-content/themes/codecanvas-site/`: default website project after child-theme activation.
- `wp-content/themes/CodeCanvas/`: parent/framework; inspectable but read-only in Studio.
- `wp-content/themes/` other theme directories: accessible subject to permission policy, though not recommended as the CodeCanvas website workspace.
- `wp-content/plugins/`: plugin source, where capabilities and filesystem permissions allow.

The CodeCanvas Site child theme is created by an explicit, nonce-protected administrator action. It does not silently switch the public site theme. If WordPress cannot write theme files, use VS Code/SFTP/Git and the normal Theme screen.

## 10.3 Child-theme starter files

The setup action creates missing starter files and never overwrites existing files:

- `style.css` with a `Template` header pointing to the actual CodeCanvas parent folder;
- `functions.php` for site-specific hooks and child CSS/JS enqueueing;
- `assets/css/site.css` and `assets/js/site.js`;
- `front-page.php`, `page.php`, `single.php`, `archive.php`, and `404.php`;
- `README.md`.

It refuses to follow a child directory symlink or take over an incompatible existing `codecanvas-site` folder. A repeat setup fills missing files only; user edits are preserved.

## 10.4 Restricted paths and file types

The editor deliberately excludes WordPress core, common server-secret/config filenames (`wp-config.php`, `.env`, `.htaccess`, `.user.ini`, SSH/private keys), symlinked paths, binary/unsupported extensions, and files over the configured 1 MB limit. It skips dependency/version-control directories such as `node_modules`, `.git`, `.svn` and `.hg` in browsing/search.

These restrictions are security and usability boundaries, not bugs to bypass.

## 10.5 Permissions for file writes

Writing requires the right WordPress capabilities including `manage_options` and root-specific `edit_themes` or `edit_plugins`, compliance with the WordPress file-modification policy and server write permissions. `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, filesystem ownership and host-specific credential methods can prevent browser writes. Do not weaken the site's policy just to make the editor work.

## 10.6 Snapshots, concurrency and recovery

Before supported mutations, Code Studio stores the previous content in a bounded non-autoloaded option. Up to three snapshots are retained per relative file. Saves include a hash-based conflict check to avoid silently overwriting a file changed on the server since it was opened. Snapshot history is a convenience rollback layer, not a full backup or Git replacement.

If PHP changes cause WordPress to fail, the in-dashboard restore UI may be unavailable. Maintain independent files/database backups and a tested filesystem recovery method.

## 10.7 PHP parsing guard

For `.php` and `.phtml`, Studio uses PHP tokenizer parse mode where available to reject many syntax errors before saving. This does not execute code or guarantee runtime correctness. It cannot catch every fatal error, missing function, permission problem, database failure, security flaw or incorrect application behavior.

# 11. Page editor, shared code and views

## 11.1 Page editor

The older page editor presents three virtual files: `index.html`, `style.css` and `script.js`. These represent WordPress page fields, not physical source files. This remains compatibility code in v1.6.2 and is not exposed as a separate dashboard destination. For new work, build layouts in CodeCanvas Site templates and keep page content in the normal WordPress editor.

Do not treat legacy virtual page fields as the primary v1.6.2 workflow. Review and migrate any existing virtual page designs before switching fully to child-theme templates. Back up block-based content before changing how it is rendered.

## 11.2 Preview

The preview runs in a sandboxed iframe with restrictive policy. It blocks many network/API requests, external script execution, form submission and nested frames. This reduces risk but means some integrations will not behave identically in preview and production. Always test on staging and then in the target browser.

## 11.3 Shared header and footer

CodeCanvas has no Global Code screen and no global-code option written by the product. Shared markup lives in `header.php` and `footer.php`, shared styles in `assets/css/site.css`, and shared behavior in `assets/js/site.js` in the website theme. Older `codecanvas_global_code` rows may remain in the database from 1.7.x; nothing reads them.

## 11.4 Views and templates

CodeCanvas renders exactly one kind of request itself: a **coded page** (a Page whose code flag is set). Every other request resolves through the normal WordPress template hierarchy in the website theme, and there is no database layer that can override a theme file. A coded page is routed to the plugin's bundled `page.php`, `front-page.php` or `templates/blank-canvas.php` so a theme's more specific `page-{slug}.php` cannot hide it.

## 11.5 Dynamic tokens

The token language is a small replacement/loop syntax, not PHP or a general-purpose template language. Common tokens include:

| Token | Meaning |
|---|---|
| `{{site.name}}` | Site title |
| `{{site.description}}` | Site tagline |
| `{{site.url}}` | Site home URL |
| `{{post.title}}` | Current item's title |
| `{{post.content}}` | Current item's WordPress-formatted content |
| `{{post.excerpt}}` | Current excerpt or derived excerpt |
| `{{post.url}}` | Current permalink |
| `{{post.featured_image}}` | Featured-image markup or placeholder |
| `{{term.name}}` | Current term name |
| `{{term.description}}` | Current term description |
| `{{term.link}}` | Current term URL |
| `{{archive.title}}` | Current archive title |
| `{{#items}}...{{/items}}` | Loop through the current query or default recent posts |
| `{{#items:project}}...{{/items:project}}` | Query items for a registered post type key |
| `{{item.title}}` / `{{item.url}}` | Item title and permalink inside a loop |
| `{{item.excerpt}}` / `{{item.content}}` | Item excerpt/content inside a loop |
| `{{item.featured_image}}` | Item's featured image |
| `{{item.meta:field_key}}` | Supported item meta field value |

Do not use arbitrary PHP inside coded page HTML. The token system intentionally does not execute it.

---

# 12. Custom post types and taxonomies

Custom post types and taxonomies are registered in the website theme's PHP. The 2.0.0 release removed the admin registry, so there is no second source of truth. Examples include Projects, Galleries, Services, Case Studies, Events and related categories.

## 12.1 Example workflow

1. Add a `register_post_type( 'project', ... )` call to the website theme's `functions.php`, hooked on `init`.
2. Register a `project_category` taxonomy with `register_taxonomy()` and attach it to `project`.
3. Create items through the normal WordPress Admin content menu.
4. Implement `single-project.php`, `archive-project.php` and `taxonomy-project_category.php` in the website theme.
5. Test all routes; if they return 404, save Settings → Permalinks again.

## 12.2 Important limitation

WordPress generally recommends keeping custom post type registration separate from a theme so content remains available when presentation changes. CodeCanvas puts it in the theme because the product has no screen or database registry for it and the theme is what Studio, Git and MCP all edit. The trade-off is explicit: switching away from the theme hides those menus and archives until another theme or plugin registers the same types, and the records themselves remain in the database.

## 12.3 WordPress.org distribution

The complete CodeCanvas feature set includes plugin-like admin and content-registration functionality. It is intended for independent distribution. Do not claim that the full package will be accepted in the WordPress.org theme directory. A separate directory-oriented version would need to remove/rework features according to the current Theme Review Guidelines.

---

# 13. Security model and API credential policy

## 13.1 Threat model

The key risks are: compromised administrator accounts, unauthorized file writes, path traversal, malicious code saved into page templates, accidental credential exposure, broken PHP files, abused public API endpoints and loss of the only recovery copy.

## 13.2 Implemented controls in v1.6.2

- WordPress capability checks for administration and file roots.
- Nonce checks for mutation AJAX actions.
- Root-relative file paths and filesystem-boundary validation.
- Path traversal and symlink rejection.
- File extension and size restrictions.
- Secret-like path exclusions and best-effort token pattern detection.
- Bounded pre-change file snapshots.
- PHP parse/tokenizer checks where available.
- No execution of PHP from database view templates.
- Sandboxed page preview with restrictive policy.

These controls reduce risk; they do not make arbitrary code editing risk-free.

## 13.3 API/private key policy

**Private credentials must never be placed in browser-delivered code.** Do not put keys in page HTML, inline JavaScript, CSS, hidden fields, `data-*` attributes, custom fields, public JSON, query strings, screenshots, logs or a public Git repository.

Recommended pattern:

1. Store private values in host-managed environment variables, a server-side secret manager or constants configured outside the public theme/plugin source (for example, through `wp-config.php`).
2. Read the value in reviewed server-side PHP only.
3. Call the named API from server-side code using an allowlist of providers/operations.
4. Return only the necessary non-secret response to the browser.
5. Authenticate and authorize actions appropriately, validate request fields and control usage/rate limits.
6. Redact tokens and authorization headers from logs and errors.
7. If a key leaks, revoke/rotate it, inspect use and remove it from repository history where relevant.

Public browser keys are not secrets. Restrict their domains/origins, API permissions, usage quotas and billing alerts at the provider.

## 13.4 Future API vault requirement

A dedicated vault is planned, not present in v1.6.2. It should not store plaintext secrets in ordinary WordPress options. If encrypted storage is introduced, the encryption key must be held outside the database, access should be minimized/audited, the UI should not reveal existing values, and rotation/revocation must be documented. A simpler server environment/secret-manager integration may be safer than building a custom vault prematurely.

## 13.5 Code execution safeguards

- Never execute PHP merely because it was saved in an editor.
- Do not add an arbitrary shell terminal without a separate threat model and explicit administrator opt-in.
- Never accept an arbitrary client-supplied URL for privileged server-side fetching.
- Use staging, Git, backups and code review.
- Do not enable source writing on production solely for convenience.
- Maintain a recovery route outside WordPress in case PHP code prevents Admin from loading.

---

# 14. Permissions and capability matrix

| Operation | Expected protection |
|---|---|
| View normal Pages/Posts | Standard WordPress capabilities |
| Edit page HTML/CSS/JS | Appropriate content-edit capability and `unfiltered_html` for arbitrary executable markup/code |
| Save coded page HTML/CSS/JS | `edit_pages` plus `unfiltered_html` (administrators on a normal single site) |
| Manage Code Studio UI/settings | `manage_options` |
| Read a theme source file | Root-specific access as implemented and filesystem readability |
| Write theme source | `manage_options`, `edit_themes`, WordPress file-modification policy approval, nonce and filesystem write permission |
| Write plugin source | `manage_options`, `edit_plugins`, WordPress file-modification policy approval, nonce and filesystem write permission |
| Configure API credentials | Future feature; currently use server-side host configuration rather than an in-theme vault |
| Activate generated plugin/theme | Manual review and normal WordPress activation flow; scaffolds are not activated automatically |

Multisite and host policies may impose stricter restrictions. A nonce is not a substitute for capability checks.

---

# 15. Installation, updates and recovery

## 15.1 Requirements

The parent theme header specifies WordPress 6.6 or later and PHP 7.4 or later. Static linting was run in the build environment; hosting compatibility and front-end behavior must still be verified on the target environment.

## 15.2 Install via WordPress Admin

1. Back up the database and complete `wp-content` directory.
2. Open Appearance → Themes → Add New Theme → Upload Theme.
3. Upload `CodeCanvas-1.5.0.zip` and install it.
4. Activate CodeCanvas as the parent theme.
5. Open Code Studio from the WordPress Admin sidebar. The Themes root should show the child-theme setup card until a compatible child theme is active.
6. Choose **Create and activate CodeCanvas Site child theme** and confirm. Review the file list and verify the active theme is CodeCanvas Site with CodeCanvas as its parent.
7. Open Code Studio again. The default path should be `codecanvas-site/`; the parent `CodeCanvas/` directory should be marked read-only.
8. Test the native WordPress editor, public frontend, menus, media, custom content URLs and permalink behavior. Save Settings → Permalinks if needed.

## 15.3 Existing-folder conflict on XAMPP

If WordPress reports that the `CodeCanvas` destination folder already exists:

1. Back up theme files and the database.
2. Activate another theme temporarily if CodeCanvas is active.
3. Rename `C:\xampp\htdocs\demo\wp-content\themes\CodeCanvas` to `CodeCanvas-backup` or deploy through a controlled copy/Git process.
4. Upload and install the new ZIP.
5. Activate CodeCanvas, re-open Code Studio and complete the child-theme setup.

Do not delete or overwrite an existing folder without a verified backup. The child generator also refuses to overwrite an incompatible existing `codecanvas-site` folder and preserves user files.

## 15.4 Hosting policy and direct development

If the theme directory is not writable, `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` blocks writes, or the host requires filesystem credentials, create the child theme with VS Code/SFTP/Git and activate it through Appearance → Themes. Do not broaden permissions or disable hosting policy without understanding the impact.

Use a local Git checkout to work on CodeCanvas itself. Code Studio intentionally does not edit the parent framework files. Site-specific code belongs in the `CodeCanvas Site` child theme.

## 15.5 Recovery

- Keep a database backup and independent backup of all `wp-content` files.
- If frontend code breaks the site, switch to a fallback theme or restore files through filesystem access.
- If parent PHP is broken, Code Studio may not load; use local/SFTP/Git recovery.
- Do not rely on the three-entry source snapshot history as the only recovery path.

# 16. Development workflow

## 16.1 Recommended environment

- Local WordPress installation, such as XAMPP, for development.
- A staging site matching production PHP and WordPress versions.
- Git repository for source history and review.
- Database export and filesystem backup before risky changes.
- Browser developer tools and WordPress/PHP error logging.
- A production deploy path with rollback capability.

## 16.2 Safe change procedure

1. Create a branch or backup checkpoint.
2. Make one focused change.
3. Run syntax checks and static validation.
4. Test the relevant Admin path and frontend behavior locally.
5. Review logs and browser console.
6. Test with relevant permissions, including a non-administrator.
7. Commit the change and record its purpose.
8. Deploy to staging; smoke-test; then deploy to production.
9. Preserve the previous release until acceptance checks pass.

## 16.3 Contributing

A contribution should include its intent, affected files, security implications, compatibility notes, QA results and rollback instructions. Avoid adding features that write arbitrary files, run PHP or call external APIs without an explicit security review.

---

# 17. QA, validation and acceptance checklist

## 17.1 Static QA recorded for v1.6.2

The build-time QA reported the following checks:

- All parent theme PHP files passed PHP syntax lint.
- Code Studio and the existing page-editor JavaScript passed `node --check`.
- `theme.json` parsed as valid JSON.
- Studio CSS opening and closing brace counts matched.
- Duplicate-function scan found no repeated named function declarations in the parent PHP files.
- Child-theme generation was exercised in an isolated PHP harness using WordPress-function stubs.
- A second child-theme setup preserved a user-modified `assets/css/site.css` rather than overwriting it.
- Generated child `functions.php`, `front-page.php`, `page.php`, `single.php`, `archive.php`, and `404.php` passed PHP syntax lint.
- The ZIP passed integrity testing and version strings matched 1.6.2.

These checks are not the same as end-to-end integration tests inside WordPress.

## 17.2 Not verified by static checks

- Full behavior in a live WordPress database/site.
- Actual theme switch and front-end rendering on Windows/XAMPP and Linux hosting.
- Parent style, child style and JavaScript asset load order on the target site.
- Every WordPress role/capability combination, multisite setup and hosting filesystem credential model.
- Generated child templates under every query type or custom post type.
- Runtime activation of generated plugin/theme scaffolds.
- Full cross-browser accessibility and responsive behavior.

## 17.3 Local/staging acceptance checklist

- [ ] Back up files and database; install/activate CodeCanvas on local or staging WordPress.
- [ ] Confirm standard WordPress Admin and native Page/Post editing still work.
- [ ] Confirm the WordPress Admin sidebar shows one CodeCanvas → Code Studio entry and no CodeCanvas submenu collection.
- [ ] Open Code Studio; confirm it does not default to editing parent framework files.
- [ ] Use **Create and activate CodeCanvas Site child theme** and confirm the action explicitly before activation.
- [ ] Check that `codecanvas-site` exists, its `Template` header matches CodeCanvas, and WordPress identifies it as a child theme.
- [ ] Confirm that existing child files were preserved on repeat setup.
- [ ] Confirm that Code Studio defaults to `codecanvas-site/` and parent framework files are marked read-only.
- [ ] Edit/save/reopen `assets/css/site.css`; test a harmless file and verify exact content persists.
- [ ] Attempt to save/create/rename/delete a parent CodeCanvas framework file; ensure all writes are rejected.
- [ ] Test the child front page, page, single, archive and 404 templates.
- [ ] Confirm child assets load after the parent frontend CSS and do not load Code Studio editor assets on public pages.
- [ ] Test normal WordPress Pages, Posts, media, menus, custom content type single/archive/taxonomy URLs and permalink refresh.
- [ ] Test file operations and snapshots using disposable child/plugin files.
- [ ] Test traversal, absolute paths, symlinks, blocked secret/config files, unsupported extensions and files over 1 MB.
- [ ] Test permissions-denied and `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` scenarios.
- [ ] Inspect browser console, PHP errors and WordPress debug log after each workflow.

Do not enable production source writes until the local/staging acceptance checklist passes.

# 18. Known limitations and risks

| Risk / limitation | Impact | Mitigation |
|---|---|---|
| PHP source editing can break the site | Critical | Use local/staging, Git, backups, syntax checks and external recovery access |
| Framework editing through the website workspace | Could break the IDE/admin itself | Code Studio blocks writes to the CodeCanvas parent folder; use a separate local Git checkout for framework development |
| Child theme isn't active yet | Code Studio cannot safely assume where user frontend work belongs | The default theme workspace presents an explicit create/activate workflow and stays gated until child theme is active |
| Conflicting `codecanvas-site` folder | Could damage an unrelated theme | Validate parent header, reject symlinks/incompatible folder, never overwrite existing files |
| Theme-based CPT registration | Content menus/routes may disappear if parent is switched away | Document portability trade-off; export/restore registration definitions |
| In-product snapshots are limited | Incomplete recovery history | Use Git and full database/filesystem backups |
| Hosts may require FTP/SSH credentials | Browser source writes may be unavailable | Use VS Code/SFTP/Git; do not weaken host policy blindly |
| Syntax checks don't prove runtime correctness | Broken behavior can pass lint | Integration tests, error logs and staging validation |
| Secret detection is pattern-based | Some credentials may not be detected | Keep secrets out of browser/source; use server-side secret management |
| Page preview differs from public runtime | Integrations may be blocked by preview sandbox | Validate on staging and actual frontend |
| WordPress.org directory rules | Complete package may not be accepted as a theme | Distribute independently; create reduced compliant edition if required |

## 18.1 Theme-only portability trade-off

The website theme holds every site-specific template, style and content-type registration. Nothing site-specific lives in the plugin, so updating CodeCanvas never overwrites site work, and the theme remains readable and portable if the plugin is removed. The trade-off is that anything registered in the theme disappears if you switch themes, which is why deployments need Git or a backup rather than relying on the theme staying active.

# 19. Product roadmap

Roadmap items below are future work unless explicitly listed as implemented in Section 4.

## Phase 0 — Stabilize the child-theme-first release

- [ ] Run all local/staging acceptance tests on Windows/XAMPP and a Linux-like host.
- [ ] Test child creation, activation, existing-folder conflict, repeat setup and read-only parent enforcement.
- [ ] Test capability enforcement, file-modification policy and filesystem edge cases.
- [ ] Perform a full keyboard/accessibility and responsive UI audit of Code Studio.
- [ ] Test snapshots and rollback under write failures.
- [ ] Align README, changelog, theme headers, package ZIP and documentation version strings.
- [ ] Publish install/upgrade/recovery instructions alongside a verified release.

## Phase 1 — Improve Code Studio

- [ ] More complete syntax modes and editor settings.
- [ ] Multi-file editing, diff view and safer unsaved-change warnings.
- [ ] Better accessible keyboard navigation and narrow-screen behavior.
- [ ] Configurable snapshot retention and snapshot export.
- [ ] Clear source-root indicator, path breadcrumbs and recent files.

## Phase 2 — Version control and validation

- [ ] Git status/diff/commit/branch integration using secure, explicit configuration.
- [ ] PHP coding-standard and static-analysis hooks where supported.
- [ ] JavaScript/CSS validation pipeline.
- [ ] Automated tests for file operations, child-theme boundaries and permissions.
- [ ] Environment-aware deployment/checklist flow.

## Phase 3 — Backend and data development

- [ ] Custom fields and relationships schema UI.
- [ ] Database migration versioning, review and rollback.
- [ ] Structured server-side module patterns and REST endpoint scaffolds.
- [ ] Test data tools and data export/import.
- [ ] Health dashboard for jobs, logs and failures.

## Phase 4 — API and operations

- [ ] Secure integration settings using hosting-managed secrets or a reviewed secret store.
- [ ] Per-integration scopes, allowlists, usage controls and redacted logs.
- [ ] Backup verification and controlled restore workflow.
- [ ] Deployment pipeline with staging, preflight checks and rollback.
- [ ] Monitoring and audit events for source edits and privileged operations.

## Phase 5 — Full developer experience

- [ ] Integrated terminal only if safe isolation and hosting constraints can be handled.
- [ ] Debugging workflow with local/staging support; no unrestricted production execution by default.
- [ ] Dependency manager integration where the host supports it.
- [ ] Documentation browser and contextual onboarding.
- [ ] Project export/import and repeatable environment setup.

### Roadmap rule

Do not add unrestricted shell execution, arbitrary remote URL proxying, production PHP evaluation or arbitrary database mutation simply to match a traditional IDE. These require separate threat models and secure deployment/hosting assumptions.

# 20. Definition of done

A release is ready for general use only when:

1. Normal WordPress dashboard and native editing still work without requiring CodeCanvas.
2. Code Studio reads/writes only allowed paths and respects capabilities, nonces and WordPress file policy.
3. Source bytes survive save/reopen unchanged for supported files.
4. Every mutation creates a recoverable snapshot or fails without partial damage.
5. Path traversal, symlinks, protected config paths, binary files and oversize files are rejected.
6. PHP syntax guard behavior is tested and limits are documented.
7. New scaffolds are reviewed and not automatically activated.
8. The public frontend does not enqueue Code Studio assets.
9. The CodeCanvas Site child theme is the default website workspace; parent framework write attempts are denied.
10. Installation, update and recovery procedures work on target environments.
11. The full staging acceptance checklist passes and QA evidence is bundled.
12. README, changelog, theme header, bootstrap constant and ZIP name agree on version.
13. Known limitations and security responsibilities are visible to users.

---

# 21. Support, contribution and ownership

**Developer:** [Suraj Dev](https://surajdev.com/)  
**Credit / project home:** [Webot](https://webot.surajdev.com/)  
**License:** GPL-2.0-or-later

For security issues, use the contact method published on Suraj Dev's website. Avoid posting a working exploit publicly before maintainers have had a reasonable opportunity to investigate. Contributions should include tests, compatibility notes and security impact.

Sections 1–23 describe the v1.6.x specification and roadmap; the Version 1.7.0 addendum records the current MCP release. Neither is a warranty that every planned capability exists or that arbitrary code edits are risk-free.

---

# 22. Glossary

| Term | Definition |
|---|---|
| Code Studio | The real-file editor for eligible theme/plugin source files. |
| MCP | Model Context Protocol; optional JSON-RPC interface for AI clients, documented in `docs/11-mcp-integration.md`. |
| Bearer token | A private credential sent in the `Authorization` header; CodeCanvas stores only its HMAC hash. |
| Page editor | The code-first editor for per-page HTML/CSS/JS stored in WordPress content/meta. |
| Virtual page file | A UI abstraction such as `index.html` that maps to WordPress fields rather than a real server file. |
| Real source file | A physical theme/plugin file read/written on the server filesystem. |
| Coded page | A WordPress Page whose HTML lives in post content and whose CSS/JS/layout live in meta; the only rendering CodeCanvas stores in the database. |
| Token | A CodeCanvas placeholder replaced with known WordPress data; not PHP evaluation. |
| CPT | Custom post type registered in the website theme, such as Project or Gallery Item. |
| Taxonomy | A classification system, such as Project Category. |
| Snapshot | A bounded pre-change copy of a source file stored for quick restore. |
| Capability | A WordPress permission checked before an operation. |
| Nonce | A WordPress request verification token; it complements but does not replace authorization. |
| Secret | A private API credential, token, password or key that must not be sent to public browser code. |
| Staging | A test site that resembles production but is isolated from real visitors and data where possible. |

---

# 23. Official technical references

The following official documentation is useful for implementation and maintenance:

1. WordPress Theme Handbook — [Theme basics and template hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/)
2. WordPress Plugin Handbook — [Registering custom post types](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/)
3. WordPress Developer Resources — [Filesystem API](https://developer.wordpress.org/apis/filesystem/)
4. WordPress Developer Resources — [Roles and capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/)
5. WordPress Developer Resources — [Nonces](https://developer.wordpress.org/apis/security/nonces/)
6. WordPress Theme Review — [Theme Review Handbook](https://make.wordpress.org/themes/handbook/review/)
7. WordPress Theme Handbook — [Theme security](https://developer.wordpress.org/themes/advanced-topics/security/)

---

**End of document — CodeCanvas Complete Project Documentation, document edition 1.8, describing software package v1.7.11 (with historical v1.6.x sections retained above).**
