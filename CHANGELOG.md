# Changelog

## 2.0.0 — 22 October 2026

### CodeCanvas is now a plugin, and your website gets a real theme

- Converted CodeCanvas from a theme into a plugin. Themes cannot ship file editing, OAuth or MCP without failing WordPress.org review; plugins can.
- The builder now **generates a standalone `CodeCanvas Site` theme** in `wp-content/themes/` instead of a child theme. Studio's setup action creates the theme from the plugin's bundled scaffold, never overwrites existing files, copies the current logo/menu settings, and activates it explicitly.
- The theme is standalone and has no `Template` header. Its templates include safe fallbacks so the site still renders if the plugin is deactivated.
- Moved front-end page rendering into the plugin. Coded pages work with any active theme by using the plugin's `site-theme/` templates.
- The coded-page editor, Code Studio, recovery guard, MCP server, OAuth server and settings screens now load as plugin modules.
- Removed the **Views & Templates**, **Content Types** and **Global Code** admin screens. The front end is now theme files plus coded pages, with nothing stored in the database that can override a template; custom post types and taxonomies are registered in the website theme. The old options are left untouched but are no longer read or written.
- Fixed the generated theme's `archive.php`, `index.php`, `home.php` and `search.php`, which called an undefined `codecanvas_site_default_card()` and returned a fatal error on every listing page. Listing pages now render the standard card.
- Rebuilt **MCP & Settings**: the 25 tools are grouped into read-only, file-write and content-write blocks with their enabled counts, each expandable, and the setup snippets and 50-row activity log sit behind summaries. The screen is roughly a third of its previous height, and collapsed groups still submit every selected tool.
- Build output is now a plugin ZIP (`dist/codecanvas-2.0.0.zip`) with `codecanvas/` at its root, plus a WordPress.org `readme.txt`.
- The coded-page workspace is reachable under **Pages → CodeCanvas Editor**.
- Test suite updated for the plugin architecture: **301 passed, 0 failed, 1 skipped**, including checks that the removed screens stay removed and that every wrapper a scaffold template calls is defined. The suite now lives in `tests/codecanvas/` outside the distributed plugin folder.
- Passed the official WordPress Plugin Check with **0 errors and 0 warnings**.

## 1.7.11 — 22 October 2026

### Readable project menu and a workspace that fits the screen

- Fixed the project dropdown: the closed control and every option now use an explicit light text colour on the dark header, with `color-scheme: dark` and an option background. The unselected “Plugins” row in the native popup was black on dark and effectively invisible.
- Fixed the endless page scrollbar below Code Studio. On desktop the workspace wrapper now has a definite viewport height (`100vh - 32px`) with `overflow: hidden`, and the workbench’s `min-height` is released. Panels and the editor scroll inside the workspace, so a long file no longer stretches the page to several thousand pixels.
- Phone layout is unchanged: the header still scrolls with the page and the explorer remains a drawer.
- Test suite: **282 passed, 0 failed, 1 skipped**, plus a 1920×1080 browser run with a 500-line file (page height stayed at the viewport while the editor scrolled internally).

## 1.7.10 — 22 October 2026

### Code Studio starts reliably and keeps the real editor

- Fixed the Studio workspace failing to start. `init()` bound an undeclared `.cc-studio-wrap` reference, threw, and left the Explorer on “Loading files…” while the project switch, search/history panels and keyboard shortcuts were never wired. All controls now initialise.
- Fixed CodeMirror never starting: the soft-wrap preference was declared inside `init()`, but `activateTab()` read it from the outer scope, so the editor threw and the silent fallback used a plain textarea. The preference is now shared, and editor start-up failures are logged instead of hidden.
- Unexpected initialisation errors now appear in the status bar and toast, rather than leaving a half-working page.
- The project selector always lists **Website themes** and **Plugins**; a root that this site’s role or server policy forbids is labelled “not available” and explained when selected, instead of a disabled option that can vanish from the native popup. The explorer’s initial project label now shows the actual root instead of “CodeCanvas”.

## 1.7.9 — 22 October 2026

### MCP: safer defaults and honest tool contracts

- `get_php_error_log` no longer returns a tool error when no log exists. The normal state is now an empty result with `available: false`, `lines: []` and a `hint` explaining how to enable `WP_DEBUG`/`WP_DEBUG_LOG` only while debugging and turn them off afterwards.
- `update_menu` now documents the required IDs: `action: create` returns `menu_id`; every other action needs `menu_id` from `create` or `list_menus`, and `update_item`/`remove_item` also need `item_id`. No behavioural change.
- `delete_file` can remove an **empty folder**, so scaffolding cleanup no longer leaves dead directories behind. A folder with any contents is refused with a count, the project root is protected, and folders are not snapshotted (the response says so).
- `create_plugin` no longer adds the new plugin to the MCP project allow-list automatically. The default is scope-neutral: the plugin is created but cannot be edited over MCP until the owner enables it in **MCP & Settings**, or the caller explicitly passes `enable_mcp: true`. This makes the write scope change a separate, visible decision.
- Test suite now covers the no-log response, empty/non-empty folder deletion, the default no-auto-allow behaviour, the explicit `enable_mcp` opt-in, and both scaffolded plugins are removed afterwards: **276 passed, 0 failed, 1 skipped**.

## 1.7.8 — 22 October 2026

### Mobile-first Code Studio

- On screens up to 782px the Explorer is now an **off-canvas drawer** instead of disappearing. The activity rail becomes a horizontal 46px bar, tapping the folder icon (or Search/History) opens the matching panel over the editor, and a backdrop, a close button and `Esc` dismiss it. Picking a file, tab or search result slides it away automatically.
- Touch-first controls: 38px title-bar buttons, 44px activity buttons, 40px file rows, a 16px search field (no iOS zoom), larger tab close targets and safe-area padding on the drawer.
- The title bar stacks cleanly: compact brand row, a full-width project selector, then touch-sized actions with Save labelled and pinned to the right. On phones it scrolls with the page instead of sticking, so it can never cover the policy note or the editor.
- The editor uses 15px type at 1.75 line height on phones, keeps soft wrap on by default, and the workspace uses `100dvh` so browser toolbars do not cut off the status bar. The status bar drops the encoding and cursor fields when space runs out.
- Accessibility: the explorer toggle now exposes `aria-expanded` and `aria-controls`, every icon-only control has an accessible name, and the drawer close control is keyboard reachable.
- Desktop behaviour is unchanged; the breakpoints build on the 1.7.7 refresh (1240px icons, 960px stacked, 782px drawer, 400px compact status bar).

## 1.7.7 — 22 October 2026

### Studio UI refresh

- Rebuilt the Studio top bar as a dark, grouped toolbar: project selector, file actions, view actions and Save each get their own segment, every button now has an icon, and hover, focus-visible and disabled states are consistent. The bar is sticky and uses the CodeCanvas green instead of the old default-admin grey.
- Responsive by design: below 1240px the buttons collapse to icons, below 960px the brand and actions stack into two rows, and below 640px the sidebar hides so the editor gets the full width. The inspector also hides earlier, and the status bar drops secondary fields on small screens.
- The “website code belongs in CodeCanvas Site” note is now a slim amber strip with a **dismiss** button. Dismissal is remembered per theme version, so a new release brings the note back once.
- Editor readability: **soft wrap is on by default**, with a **Wrap** toggle in the editor toolbar and `Alt+Z`; long lines wrap instead of forcing a horizontal scrollbar. Added comfortable line height, editor padding, `scrollPastEnd`, dimmer line numbers, and slim dark scrollbars for the editor, Explorer, search results and inspector.
- The workbench now fills the remaining height with flexbox instead of a hard-coded `calc()`, so the page no longer grows an extra page-level scrollbar and keeps fitting when the top bar wraps.
- **Focus mode is now strictly on demand**: it is no longer restored from a previous session, so WordPress chrome never disappears unless you press Focus or `Ctrl/Cmd + Shift + M`. `Esc` still exits.
- The Studio footer was removed to leave more room for code; credits remain in the Welcome screen and the site footer.

## 1.7.6 — 22 October 2026

### Code Studio: focus mode

- Added a **Focus** control to the Studio title bar. It hides the WordPress admin bar, admin menu, footer, screen options and admin notices, and lets the workbench fill the viewport.
- Exit with the same button, with `Esc`, or toggle it from anywhere in Studio with `Ctrl+Shift+M` (`Cmd+Shift+M` on macOS).
- The preference is remembered for the browser tab session, so a reload stays in focus mode; closing the tab returns Studio to the normal admin layout.
- CodeMirror is refreshed after the layout change so the editor keeps the correct height and cursor position.
- Focus mode is CSS + one small script and adds nothing to public pages.

## 1.7.5 — 22 October 2026

### Branded welcome screen

- Added a branded **Welcome** page as the first item in the CodeCanvas admin menu. It shows the brand hero, the active parent/child theme, MCP server state with the enabled tool count, and recovery-guard status, plus shortcuts to Code Studio, the coded-page editor, MCP settings, Pages, Themes and Reading settings.
- The menu is now **Welcome → Code Studio → MCP & Settings**; the previous silent redirect from the CodeCanvas parent URL is gone, so `admin.php?page=codecanvas` renders the page instead of jumping to Studio.
- Theme activation and the first-run redirect now land on the welcome screen.
- The page has short “Start here” and “Safety first” panels and links only to screens that exist in 1.7.x; the old onboarding checklist with removed routes is gone.

## 1.7.4 — 22 October 2026

### Recovery guard: no more File Manager repairs after a bad write

- Added a must-use plugin, `wp-content/mu-plugins/codecanvas-recovery.php`, installed automatically by CodeCanvas (and refreshed whenever the shipped guard changes) and also installable/removable from **CodeCanvas → MCP & Settings → Recovery guard**.
- It loads before the theme and plugins, and on a PHP fatal error (`E_ERROR`, `E_PARSE`, `E_COMPILE_ERROR`, uncaught `E_USER_ERROR`) inside a theme or plugin file it restores the newest CodeCanvas snapshot for that exact file, invalidates the opcache entry and logs the event. Files without a snapshot are never touched.
- A repeated restore of the same file inside 30 seconds is skipped to avoid loops, and a snapshot older than 24 hours is refused so an old version can never silently replace newer work. The last 20 recoveries are listed on the settings screen.
- Verified end to end on the local site: after deliberately corrupting the child theme's `front-page.php`, the first request returned `500` and the guard restored the file; the next request returned `200` with the exact previous contents.
- The guard never overwrites an unrelated `codecanvas-recovery.php`, respects `DISALLOW_FILE_MODS`, and reports install failures in the settings panel.

### MCP: prefer content WordPress can edit

- Updated the `initialize` instructions: page copy, headings and images belong in WordPress records created with `create_content`/`update_content`, because the owner edits them in the block editor. A code-built `front-page.php` is explicitly called out as the wrong place for site copy unless the user asked for a code-level layout, and the instructions explain using `create_content` plus `update_settings` (`show_on_front=page`, `page_on_front`) for an editable homepage.
- The instructions also state that the recovery guard is a safety net, not a substitute for `render_url`/`get_php_error_log` checks.

## 1.7.3 — 22 October 2026

### MCP: create new plugin projects

- Added `create_plugin`: scaffolds a brand new folder in `wp-content/plugins` with a valid plugin header (`Plugin Name`, `Description`, `Version`, `Author`, `License`, `Text Domain`), optional extra files (including nested folders), PHP syntax validation for each file, and then adds `plugin:<slug>` to the MCP project allow-list so the client can keep editing without a manual trip to Settings. The plugin is never activated; activation stays a manual, explicit step in Plugins.
- `codecanvas_mcp_available_projects()` accepts a refresh flag so a folder created during a request is visible to the settings sanitizer and to `list_projects` immediately.
- Creating a plugin requires the same gates as any write: the server enabled, **Allow file writes**, `edit_plugins`, and file-modification policy that permits writes. Existing folders are never overwritten, and extra files are confined to the new plugin folder.
- The MCP settings screen lists `create_plugin` with the other file-write tools.

## 1.7.2 — 22 October 2026

### MCP: finish-the-job tools

- Added `patch_file`: replaces one exact `old_string` with `new_string` (optionally `replace_all`) instead of resending a whole file. Missing or ambiguous matches fail with an actionable error, and the result reports how many replacements were made. New module `inc/mcp-tools.php`.
- Added `delete_content`: moves a page or post to the trash by default and deletes permanently only with `confirm: true` and `force: true`, mirroring `delete_file`. `update_content` also accepts `status: "trash"` and restores a trashed record with any other status.
- Added `list_snapshots` and `restore_snapshot`: the three stored pre-change snapshots are now visible to clients and can be restored; a restore snapshots the current contents first, and it recreates a file that was deleted.
- Added `render_url` and `get_php_error_log`: fetch a page on this site (status, title, body, PHP error notices) and tail `debug.log` or the configured `error_log`, so a runtime PHP error can be found after a syntactically valid write.
- Content fields are complete: `slug`, `parent`, `template`, `featured_media`, `categories`, `tags` and public custom fields are readable and writable, and `create_content` returns the final permalink. `get_content` gained the same fields plus `edit_url`.
- Added site-level controls: `get_settings`, `update_settings` (title, tagline, static front page, posts page, posts per page, permalink structure) and `list_menus`/`update_menu` (create a menu, add/edit/remove items, assign a theme location).
- Added `upload_media` for base64 images and documents up to 700 KB, with alt text and attachment metadata.
- Write conflict protection no longer depends on the client: the server remembers the hash it last returned per file, so `write_file` and `patch_file` refuse a stale edit even when `expected_sha256` is omitted. Conflicts now report the current hash and modification time.
- `list_files` gained `depth` (1–10), `max_items` and `offset` for one-call recursive listings; `search_files` and `list_content` gained `offset`/`next_offset`, and `list_content` reports `total` and accepts `post_type: "any"`.
- `list_projects` now explains why a project is unavailable (`note`), and each project reports its read/write state.
- `tools/list` now returns one registered catalogue of all 24 tools with `annotations.codecanvasEnabled`, and disabled tools state which Settings switch enables them, so a tool no longer disappears between client sessions. Dispatch still enforces the allow-list and write switches.
- Added `create_files` for scaffolding up to 20 files or folders per call.
- Settings screen labels list the new tools, and the tool panel explains that disabled tools remain discoverable but are refused.

### Documentation

- Updated `docs/11-mcp-integration.md` (tool tables, undo and render workflows, limitations), `README.md`, `docs/10-complete-project-documentation.md`, `docs/12-developer-guide.md`, `docs/01-getting-started.md`, `docs/13-install-upgrade-recovery.md` and `QA-REPORT.md` for 1.7.2.

## 1.7.1 — 21 October 2026

### MCP: OAuth 2.1 sign-in

- Added an OAuth 2.1 authorization server for the MCP endpoint so browser-based clients such as claude.ai can connect with **Sign in now** instead of a fixed header. The static bearer token keeps working unchanged.
- Added discovery documents at `/.well-known/oauth-protected-resource` (RFC 9728) and `/.well-known/oauth-authorization-server` (RFC 8414), and the `401` challenge now advertises `resource_metadata`.
- Added `/codecanvas-oauth/authorize`, `/token`, `/register` and `/revoke`: authorization-code grant, mandatory PKCE `S256`, single-use five-minute codes, one-hour access tokens, rotating 30-day refresh tokens, RFC 7591 public-client registration, RFC 7009 revocation and Client ID Metadata Document support.
- Added an administrator consent screen showing the client, redirect host, signed-in account and the exact tools, projects and write modes in scope. Only `manage_options` users can approve, and grants stop working if that account loses the capability.
- Tokens and codes are stored only as HMAC/SHA-256 hashes; registration is rate limited to 20 clients per IP per hour, with caps of 100 clients and 200 grants.
- Added an **OAuth sign-in** panel to **CodeCanvas → MCP & Settings** with active-grant counts, the issuer and discovery URLs, and a single **Revoke all OAuth connections** action.
- Added `inc/mcp-oauth.php`, updated `docs/11-mcp-integration.md` (section 4.1 and the claude.ai connector steps) and extended the test suite.

### Fixes and hardening

- Theme version bumped to 1.7.1; the OAuth rewrite rules are flushed once per version instead of on every request.

## 1.7.0 — 21 October 2026

### MCP (Model Context Protocol)

- Added an optional, off-by-default MCP server at `/wp-json/codecanvas/v1/mcp` (JSON-RPC over Streamable HTTP `POST`; protocol revisions 2025-06-18, 2025-03-26 and 2024-11-05).
- Added bearer-token authentication backed by an HMAC hash, bound to the generating administrator, with nonce-protected generation/revocation and a one-time token display.
- Added 12 allow-listed tools: 7 read-only (site info, projects, files, search, content reads), 3 file-write (write/create/delete) and 2 content-write (create/update).
- Added per-project and per-tool allow-lists, separate file-write and content-write switches, a configurable 1–600 requests/minute rate limit, `WWW-Authenticate`/`Retry-After` headers and a 50-entry audit log that never stores file contents or tokens.
- File writes are limited to enabled theme/plugin projects, exclude the CodeCanvas framework, reject traversal/symlinks/blocked names, validate PHP with `TOKEN_PARSE`, detect credential-like content, snapshot the previous revision and support `expected_sha256` conflict detection.
- Added **CodeCanvas → MCP & Settings** with connection status, endpoint, token lifecycle, project/tool scopes, client snippets (Claude Code, Cursor, VS Code), audit table and security notes.
- Added a `CODECANVAS_MCP_TOKEN` constant override for permanent deployments; it takes precedence over the stored hash and maps to a current administrator when no token was generated in the UI.
- Added an MCP link to the Code Studio toolbar.

### Fixes and hardening

- Fixed a real settings bug: token generation silently did not persist because the `register_setting()` sanitizer runs on every `update_option()` call and discarded the token fields. The sanitizer now validates and preserves them.
- Fixed invalid nested `<form>` markup in the MCP settings screen that detached the settings fields and Save button from their form in browsers.
- Added an explicit “MCP settings saved.” notice (WordPress's generic notice was filtered out by setting name).
- The “Exposed tools” counter now reports 0 while the server is disabled instead of counting configured tools.
- Kept the MCP endpoint POST-only while answering other methods with `405` and `Allow: POST`, and preserved JSON-RPC `-32700` parse errors by handling the raw body before WordPress's REST JSON validation.
- Skip the shared menu script (`frontend.js`) on blank-canvas layouts; keep the handle when inline administrator JavaScript needs it.
- Added `tests/bootstrap.php` and `tests/run-tests.php`: 129 passing integration/security checks covering path policy, framework casing, MCP auth/protocol/tools, the `CODECANVAS_MCP_TOKEN` constant, settings sanitization, snapshots, conflicts, rate limiting and audit redaction.
- Added `docs/11-mcp-integration.md`, `docs/12-developer-guide.md`, `docs/13-install-upgrade-recovery.md` and `PERFORMANCE.md`; updated the README, security policy and QA report.

## 1.6.3 — 21 October 2026

- Fixed the Code Studio framework read-only guard so it compares theme folder names case-insensitively. Windows and macOS filesystems resolve `codecanvas/inc/...` to the protected `CodeCanvas` folder, so a lowercase path could previously be saved; framework writes are now rejected for every casing and also verified against the resolved filesystem path.
- Carried the parent theme's Customizer values (navigation menu locations, custom logo and header images) into the CodeCanvas Site child theme once, when it becomes active, so switching workspaces no longer leaves the site without its menus or logo.
- Rewrote the generated child-theme starter templates: they no longer nest a second `<main>` landmark inside the parent shell, reuse the `cc-*` layout classes, and use the parent archive/404 helpers so pages look correct immediately after activation.
- The generated `front-page.php` now starts by including the parent homepage, so activating the child theme does not change the live front page until the developer replaces it.
- Fixed the `CodeCanvas` sidebar parent link: it now redirects to Code Studio on `admin_init` before wp-admin prints its header, instead of attempting a redirect from the page callback (which produced “headers already sent” warnings and a blank screen). The callback keeps a link fallback.
- Load the parent stylesheet explicitly and print the child `style.css` after the base CSS, versioned by file modification time so frontend edits are not served from cache.
- Code Studio now shows `Read only` in the status bar when a framework file is open, instead of `Saved`.

## 1.6.2 — 21 October 2026

- Simplified navigation to **CodeCanvas → Code Studio** with exactly one child submenu.
- The CodeCanvas parent item redirects directly into Code Studio.
- Removed all legacy CodeCanvas child menu items (Getting Started, Views & Templates, Global Code, Content Types and Security & Help) from the sidebar.
- Kept the standard WordPress dashboard menus and editing workflows unchanged.
- Preserved the child-theme-first workflow: website frontend files default to `codecanvas-site`; the CodeCanvas framework remains read-only in Studio.

## 1.6.1 — 21 October 2026

- Removed WordPress’s automatically-created duplicate Code Studio submenu so the developer tool is a single sidebar destination.
- Kept the standard WordPress dashboard, pages, posts, media, plugins, themes and settings menus unchanged.
- Clarified the child-theme-first source workflow and the legacy status of old CodeCanvas settings screens.

## 1.6.0 — 21 October 2026

- Simplified WordPress Admin navigation to **CodeCanvas → Code Studio** only; removed the legacy submenu list.
- Removed the separate CodeCanvas Page Editor submenu under Pages; the normal WordPress Pages editor remains available.
- Kept the managed `CodeCanvas Site` child theme as the default website source workspace.
- Removed the duplicate child-theme activation button from the Studio toolbar; first-run setup has one explicit action.
- Made the Studio project selector label clearer and removed a link to a hidden legacy Security & Help screen.
- Updated the activation redirect to open Code Studio directly.
- Kept the WordPress dashboard itself intact; this is a developer feature, not a replacement admin.

## 1.5.0

- Added a managed CodeCanvas Site child theme workflow and protected parent framework files in Studio.
- Added website child-theme starter templates and documented source-file boundaries.
