# CodeCanvas 2.0.0

**A free, code-first WordPress builder plugin by Suraj Dev. Credit: Webot.**

- **Developer:** [Suraj Dev](https://surajdev.com/)
- **Credit / project home:** [Webot](https://webot.surajdev.com/)
- **License:** GPL-2.0-or-later
- **Required companion plugins:** None
- **Recommended use:** local/staging first; review the security model before enabling source writes or MCP on production

## Architecture

CodeCanvas used to be a theme. From 2.0.0 it is a **plugin**, because source editing and MCP are plugin-territory functionality.

- **The plugin** contains the builder: Code Studio, coded pages, MCP, OAuth, settings, the recovery guard installer and the coded-page renderer.
- **The website theme** is a real standalone theme generated at `wp-content/themes/codecanvas-site/`. It contains the site's templates, `style.css`, `theme.json`, CSS and JavaScript. Studio edits this theme; CodeCanvas updates never replace it.
- **Coded pages** render through the plugin's bundled `site-theme/` templates, so they keep working even if a different theme is active.
- **Everything else is a file.** There is no template or content-type store in the database: post types, taxonomies, templates and styles live in the website theme, so what you edit in Studio, in VS Code or over MCP is exactly what renders.

## First-run workflow

1. Install and activate **CodeCanvas** under **Plugins**.
2. Review **CodeCanvas → Welcome** for the version, website theme, MCP and recovery-guard status.
3. Open **CodeCanvas → Code Studio**.
4. Click **Create and activate CodeCanvas Site theme**. This is explicit because it creates files and switches the active front-end theme; CodeCanvas never does this silently.
5. Studio opens `wp-content/themes/codecanvas-site/` as the default website project.
6. Edit website templates, PHP, CSS and JavaScript in that theme. CodeCanvas plugin files are readable but **read-only** in Studio.
7. Use the project selector for eligible plugin source files when needed.
8. Optionally enable MCP in **CodeCanvas → MCP & Settings** (see below).

If filesystem policy prevents setup, create a theme with VS Code/SFTP/Git and activate it in **Appearance → Themes**. Do not loosen filesystem permissions just to make browser edits work.

## Code Studio

- Browse and edit eligible real source files under `wp-content/themes` and `wp-content/plugins`.
- Create files/folders, rename and delete supported source files.
- Search text in supported files and jump to matching lines.
- Generate basic theme/plugin starter structures, which are never activated automatically.
- Keep a bounded recent snapshot history (three per file) for recovery.
- Use WordPress's bundled CodeMirror editor, with soft wrap on by default, dark slim scrollbars and a focus mode that hides the WordPress chrome (`Esc` or `Ctrl/Cmd + Shift + M` to leave).
- **Mobile-first:** the Explorer becomes a slide-in drawer, controls are touch-sized, and the editor keeps soft wrap with `100dvh` height.
- Reject PHP syntax errors before writing, and reject a save when the file changed on disk since it was opened.

This is a focused source workspace, not the complete VS Code desktop application. Integrated terminal, debugger, extension marketplace, full Git UI, database studio, secrets vault and deployment pipeline are **not** implemented.

## MCP for AI clients

CodeCanvas can connect Claude Code, Cursor, VS Code, claude.ai or any MCP-capable client to the site over a scoped HTTP endpoint.

- Enable at **CodeCanvas → MCP & Settings**.
- Generate a token (shown once, stored only as an HMAC hash).
- Select exactly which theme/plugin **projects** and **tools** are exposed.
- File writes and content writes have separate switches and are off by default.
- Endpoint: `https://example.com/wp-json/codecanvas/v1/mcp`.
- Bearer-token authentication, or OAuth 2.1 sign-in with PKCE for browser clients such as claude.ai; WordPress cookies are not accepted.
- 25 tools: 12 read-only, 7 file-write, 6 content-write.
- `patch_file` for surgical edits, `list_snapshots`/`restore_snapshot` to undo, and `render_url`/`get_php_error_log` to confirm the site still works after a change.
- `create_plugin` scaffolds a new plugin project from scratch; it is **not** added to the MCP write scope unless `enable_mcp: true` is passed or the owner enables it in Settings. Activation always remains a manual step in **Plugins**.
- CodeCanvas plugin source is never exposed. Paths are validated, PHP is syntax-checked, writes are snapshotted, and calls are rate-limited and audited.
- **Recovery guard:** a must-use plugin restores the last snapshot automatically when a PHP fatal error comes from a theme/plugin file, so a bad write is a one-request outage rather than a File Manager repair. Install, repair or remove it under **CodeCanvas → MCP & Settings**.
- Content created over MCP is a normal WordPress record (page/post), so it stays editable in the block editor. Site copy should live in pages, not in `front-page.php`.

Full protocol, tool and client reference: [docs/11-mcp-integration.md](docs/11-mcp-integration.md).

## The generated website theme

`CodeCanvas Site` is a standalone theme; it has no parent and no required plugin for rendering, because its templates use safe fallbacks if CodeCanvas is deactivated.

- `front-page.php`, `home.php`, `index.php`, `page.php`, `single.php`, `archive.php`, `search.php`, `404.php`, `comments.php` — editable templates that reuse the `cc-*` layout.
- `header.php`, `footer.php` — shared chrome, rendered from these files alone.
- `templates/blank-canvas.php` — a page template without the shared header and footer.
- `functions.php` — theme supports, asset loading and site hooks.
- `assets/css/site.css`, `assets/css/editor.css`, `assets/js/site.js` — site styles, editor styles and public JavaScript.
- `style.css`, `theme.json`, `screenshot.png` — theme metadata and block settings.

The theme's `header.php` opens `<main id="main-content">`; `footer.php` closes it. To design a fully custom shell, edit those files and keep `wp_head()`, `wp_body_open()` and `wp_footer()`.

## Security and recovery

- Do not put private API keys in HTML, CSS, JavaScript, screenshots or committed files.
- Keep credentials in `wp-config.php` constants or environment-based server configuration.
- Source editing is a privileged capability. Use HTTPS, strong administrator accounts, backups and staging.
- The built-in snapshots are bounded and are not a backup replacement.
- Revoke an MCP token immediately if it may have leaked.
- PHP syntax validation does not guarantee runtime correctness.
- The CodeCanvas plugin folder is read-only in Studio and unreachable over MCP.

## Performance

Measured on the local XAMPP demo: the homepage loads **5 stylesheets and 3 scripts** (the extra requests are the generated theme's `style.css` and `site.css`/`site.js`); a blank-canvas page loads **5 stylesheets and 2 scripts**. No Studio, MCP settings or CodeMirror assets load on public pages. See [docs/performance.md](docs/performance.md) for the original method and unmeasured items.

## Testing

From the WordPress root:

```bash
php tests/codecanvas/run-tests.php
```

The suite exercises path policy, framework protection, MCP authentication/protocol/tools, settings sanitization, rate limiting and audit redaction, then restores every option and removes its temporary files. See [docs/qa-report.md](docs/qa-report.md).

## Documentation

| Document | Contents |
|---|---|
| [docs/01-getting-started.md](docs/01-getting-started.md) | First-run overview |
| [docs/02-coded-pages-and-tokens.md](docs/02-coded-pages-and-tokens.md) | Coded pages and the token language |
| [docs/04-content-types.md](docs/04-content-types.md) | Registering post types and taxonomies in the theme |
| [docs/08-code-studio.md](docs/08-code-studio.md) | Studio interface and file policy |
| [docs/09-website-theme-workspace.md](docs/09-website-theme-workspace.md) | Website theme boundaries and starters |
| [docs/10-complete-project-documentation.md](docs/10-complete-project-documentation.md) | Full product specification |
| [docs/11-mcp-integration.md](docs/11-mcp-integration.md) | MCP reference and client setup |
| [docs/12-developer-guide.md](docs/12-developer-guide.md) | Architecture, templates, assets, workflows |
| [docs/13-install-upgrade-recovery.md](docs/13-install-upgrade-recovery.md) | Install, upgrade, rollback, recovery |
| [SECURITY.md](SECURITY.md) | Security model and risk register |
| [docs/qa-report.md](docs/qa-report.md) | Automated, HTTP and manual test results |
| [docs/performance.md](docs/performance.md) | Measured frontend benchmark |
| [CHANGELOG.md](CHANGELOG.md) | Release history |

## Current limitations

- No admin screens for templates, global CSS/JS or content types; those are theme files by design.
- No terminal, debugger, Git client, database console or deployment tooling.
- No media uploads, plugin/theme activation, SQL or WP-CLI over MCP.
- No multisite network-wide settings; each site is configured separately.
- Snapshot history is three revisions per file in the WordPress database.


**Developer:** [Suraj Dev](https://surajdev.com/) · **Credit:** [Webot](https://webot.surajdev.com/).
