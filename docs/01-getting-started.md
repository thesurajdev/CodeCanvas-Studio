# Getting started

Applies to CodeCanvas 2.0.0.

## 1. Install and activate

1. Back up the site (or use local/staging).
2. Upload `codecanvas-2.0.0.zip` in **Plugins → Add New → Upload Plugin**, or copy the `codecanvas` folder to `wp-content/plugins/`.
3. Activate **CodeCanvas**.

WordPress keeps its normal dashboard, block editor, media library, users, themes, plugins and settings. CodeCanvas adds one sidebar menu:

- **Welcome** — brand page with version, website theme, MCP and recovery-guard status.
- **Code Studio** — the developer workspace.
- **MCP & Settings** — optional AI-client access (off by default).

Coded pages use **Pages → CodeCanvas Editor**.

CodeCanvas is a plugin, not a theme, so it can edit source files and serve MCP without conflicting with WordPress.org theme rules.

There are no admin screens for templates, content types or global CSS/JavaScript, and nothing of that kind is stored in the database. Everything the public site renders is a file in the website theme, which is why Studio, VS Code and MCP all edit the same thing.

## 2. Create the website theme

1. Activation opens **CodeCanvas → Welcome**; review the status, then open **Code Studio**.
2. Click **Create and activate CodeCanvas Site theme**.
3. Studio reopens with `wp-content/themes/codecanvas-site` selected as **WEBSITE THEME**.

The action copies the bundled theme scaffold, writes only missing files, validates the standalone `style.css` header, copies the current logo and menu settings into empty values, and activates the theme only after that explicit click. If the themes folder is not writable, create the theme with SFTP/VS Code and activate it in **Appearance → Themes**.

The CodeCanvas plugin folder is visible in Studio but **read-only**. Website work belongs in the generated theme.

## 3. Build the site

- **Templates:** edit `front-page.php`, `page.php`, `single.php`, `archive.php`, `404.php` in the website theme. They reuse the `cc-*` layout; do not add a second `<main>`.
- **Styles/scripts:** use `assets/css/site.css`, `assets/css/editor.css` and `assets/js/site.js`, or enqueue additional files from the theme's `functions.php`.
- **Content:** keep using the normal WordPress Pages/Posts editor. Templates are layouts; posts and pages are content records.
- **Menus/logo:** **Appearance → Customize**.
- **Structured content:** register custom post types and taxonomies in the website theme's `functions.php` on the `init` hook, then add templates such as `archive-project.php` and `single-project.php`. Flush permalinks once. See [04-content-types.md](04-content-types.md).

## 4. Optional: connect an AI client (MCP)

1. Open **CodeCanvas → MCP & Settings**.
2. Click **Generate token** and copy it immediately; it is shown once.
3. Select the project (`theme:codecanvas-site`) and the tools.
4. Leave file/content writes off until you need them.
5. Tick **Enable the MCP server** and save.
6. Add the endpoint and bearer token to Claude Code, Cursor, VS Code or another MCP client. For claude.ai or any client that only offers OAuth, add the endpoint as a custom connector with **Sign in now** and approve it on the consent screen instead.

Useful tools for a site build: `patch_file` for small edits, `list_snapshots`/`restore_snapshot` to undo, `render_url` and `get_php_error_log` to confirm a page still works, `update_settings` and `update_menu` for site structure, and `upload_media` for images.

Full reference: [11-mcp-integration.md](11-mcp-integration.md).

## 5. Safety rules

- Use HTTPS, strong administrator accounts and backups.
- Keep private API keys in `wp-config.php`/environment configuration, never in HTML, CSS or browser JavaScript.
- PHP syntax checks do not guarantee runtime safety. Test on staging.
- Revoke an MCP token if it may have leaked.
- Code Studio snapshots are bounded (three per file), not a backup system.

## 6. Where to go next

- [08-code-studio.md](08-code-studio.md) — editor, file policy, history.
- [09-website-theme-workspace.md](09-website-theme-workspace.md) — plugin/theme boundary.
- [11-mcp-integration.md](11-mcp-integration.md) — MCP tools and clients.
- [12-developer-guide.md](12-developer-guide.md) — architecture and template conventions.
- [13-install-upgrade-recovery.md](13-install-upgrade-recovery.md) — upgrades and recovery.
