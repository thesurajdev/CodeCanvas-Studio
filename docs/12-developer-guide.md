# CodeCanvas developer guide

This is the practical guide for developers working on a CodeCanvas site or on CodeCanvas itself. It documents the 2.0.0 plugin architecture, the plugin/website-theme boundary, template and asset conventions, the developer workflows, and the checks expected before deployment.

- **Product:** CodeCanvas 2.0.0 (plugin)
- **Developer:** [Suraj Dev](https://surajdev.com/)
- **Credit:** [Webot](https://webot.surajdev.com/)
- **License:** GPL-2.0-or-later
- **Companion plugins:** none required

---

## 1. Mental model

CodeCanvas keeps four concerns separate:

| Concern | Lives in | Edited by |
|---|---|---|
| WordPress content, users, media, settings | MySQL database | WordPress admin |
| Website code: templates, CSS, JS, site PHP | `wp-content/themes/codecanvas-site/` generated theme | Code Studio, VS Code, Git |
| Developer environment and admin UI | `wp-content/plugins/codecanvas/` plugin | CodeCanvas maintainers |
| Secrets and deployment configuration | `wp-config.php`, environment, host secret store | Server administrator |

The rule is: **WordPress manages the website. Developers control the code. Visitors receive only what the site needs.**

CodeCanvas does not replace the WordPress dashboard, block editor, media library or publishing workflow. It adds a developer workspace and an optional MCP endpoint.

## 2. Requirements and compatibility

- WordPress 6.6+ (tested on 7.1.3).
- PHP 7.4+ (tested on 8.0.30).
- A normal writable `wp-content/themes` when using Code Studio or website-theme setup.
- `edit_themes` / `edit_plugins` capabilities for source writes; `manage_options` for the Studio and MCP settings screens.
- `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS` are respected. When either blocks writes, Studio becomes read-only and reports why.
- Windows/XAMPP and Linux paths are normalised; symlinks are rejected.

## 3. Source tree

```
codecanvas/                     plugin
├── codecanvas.php              plugin header, constants, module loading
├── readme.txt                  WordPress.org plugin readme
├── includes/
│   ├── setup.php               text domain, helpers, welcome redirect
│   ├── views.php               coded-page resolution, tokens, template precedence
│   ├── security.php            credential-pattern detection
│   ├── assets.php              frontend enqueueing + inline CSS/JS
│   ├── admin.php               branded Welcome screen and admin helpers
│   ├── page-editor.php         coded-page workspace
│   ├── dev-studio.php          Code Studio, filesystem policy, theme setup
│   ├── mcp.php                 MCP server, tools, auth, audit
│   ├── mcp-oauth.php           OAuth 2.1 sign-in endpoints and metadata
│   ├── mcp-settings.php        MCP & Settings screen
│   ├── mcp-safety.php          recovery-guard installer and status panel
│   └── recovery-guard.php      mu-plugin source restored on fatal errors
├── assets/                     admin + frontend CSS/JS
├── site-theme/                 website-theme scaffold AND coded-page templates
│   ├── style.css               standalone theme header
│   ├── functions.php           theme supports, assets, safe fallbacks
│   ├── header.php, footer.php  shared chrome
│   ├── front-page.php, page.php, single.php, archive.php, home.php, search.php, 404.php, comments.php
│   ├── template-parts/         (none required)
│   ├── templates/blank-canvas.php
│   ├── theme.json
│   └── assets/css/site.css, assets/css/editor.css, assets/js/site.js
└── docs/                       product and developer documentation
```

The CLI test suite lives outside the distributed plugin, at `tests/codecanvas/` in the project repository, so it never ships in the release ZIP.

The generated website theme is deliberately small:

```
codecanvas-site/
├── style.css                  standalone theme header
├── functions.php              theme supports + asset enqueueing + safe fallbacks
├── header.php, footer.php     shared chrome
├── front-page.php             homepage
├── page.php, single.php       page/post templates
├── archive.php, home.php, search.php, 404.php
├── comments.php
├── templates/blank-canvas.php
├── theme.json
├── assets/css/site.css        site styles
├── assets/css/editor.css      editor styles
├── assets/js/site.js          site JavaScript
└── README.md                  workspace rules
```

## 4. Plugin/website-theme boundary

### CodeCanvas plugin

Contains the developer environment, admin screens, MCP server and the coded-page renderer. It is **read-only in Code Studio** and completely excluded from MCP. Update it by replacing the `codecanvas` plugin folder.

### Website theme

Contains the site's templates and styles. Activate it with **CodeCanvas → Code Studio → Create and activate CodeCanvas Site theme**. The setup action:

- validates the standalone `CodeCanvas Site` theme header (no `Template` line),
- refuses symlinked or conflicting folders,
- never overwrites existing files,
- creates only missing starter files,
- activates the theme only after the explicit click,
- copies the current theme's menu locations, logo and header/background images once, filling only empty values.

If setup cannot write files, create the theme through VS Code/SFTP/Git and activate it in **Appearance → Themes**. Do not loosen filesystem permissions just to use the browser editor.

### If the website theme is missing or broken

- Restore the folder from Git/backup, or recreate it with the setup action (existing files are preserved).
- If a PHP error breaks the site, rename the offending file through SFTP or Code Studio history; WordPress falls back to the remaining templates in the theme.
- Activate any other theme only as an emergency step; coded pages still render through the plugin's bundled templates. Re-activate the website theme afterwards and restore menus/logo.

## 5. Template hierarchy

CodeCanvas uses the standard WordPress hierarchy. A request resolves in this order (simplified):

1. If the request is a **coded page**, the plugin's bundled `page.php`, `front-page.php` or `templates/blank-canvas.php` prints the stored HTML; a theme's more specific `page-{slug}.php` cannot hide it.
2. The website theme's template (`front-page.php`, `page-{slug}.php`, `single-{type}.php`, `taxonomy-{tax}.php`, …).
3. The website theme's own fallback (`page.php`, `single.php`, `archive.php`, `404.php`, `index.php`).

Nothing else is consulted: there is no database layer of saved templates that can override a theme file. Override a template by creating a file with the same or a more specific name in the website theme. The CodeCanvas plugin folder is never part of the template hierarchy.

### What each template is for

| File | Renders |
|---|---|
| `front-page.php` | Site homepage |
| `home.php` | Blog posts index when the front page is static |
| `page.php` | Static pages |
| `single.php` | Single posts and custom post types without a specific template |
| `archive.php` | Category, tag, taxonomy, date and post-type archives |
| `search.php` | Search results |
| `404.php` | Not found |
| `comments.php` | Comment list/form |
| `templates/blank-canvas.php` | Page using the "CodeCanvas Blank Canvas" template |

### Template parts and components

Create reusable markup in `template-parts/` inside the **child** theme and include it with `get_template_part()`:

```php
<?php get_template_part( 'template-parts/card', 'project' ); ?>
```

For a component with arguments, use `get_template_part( 'template-parts/card', null, array( 'post_id' => $id ) )` and read `$args` in the part (WordPress 5.5+).

### Shared header and footer

The parent `header.php` prints the document head, skip link, site header and opens `<main id="main-content">`. The parent `footer.php` closes it. Child templates should call `get_header()` / `get_footer()` and must **not** add a second `<main>` landmark.

To replace the whole shell, create `header.php`/`footer.php` in the website theme and keep the `wp_head()`, `wp_body_open()` and `wp_footer()` calls.

## 6. Assets

The plugin enqueues its base layer first, then the website theme loads on top:

1. `assets/css/frontend.css` (`codecanvas-frontend`) — builder base styles
2. inline global/view CSS attached to `codecanvas-frontend`
3. the theme's `style.css` (`codecanvas-site-style`)
4. the theme's `assets/css/site.css` (`codecanvas-site-custom`), versioned by file modification time
5. `assets/js/frontend.js` (`codecanvas-frontend`) — **not on blank-canvas layouts**
6. inline global/view JavaScript
7. the theme's `assets/js/site.js` (`codecanvas-site-custom`)

Add site assets in the website theme:

```php
function my_site_assets() {
    wp_enqueue_style(
        'my-site',
        get_stylesheet_directory_uri() . '/assets/css/my-site.css',
        array( 'codecanvas-site-custom' ),
        (string) filemtime( get_stylesheet_directory() . '/assets/css/my-site.css' )
    );
}
add_action( 'wp_enqueue_scripts', 'my_site_assets', 20 );
```

Rules:

- Never load Studio/admin assets on the frontend.
- Do not put private credentials in any enqueued asset.
- Prefer one site stylesheet over many small files.
- Version assets with `filemtime()` during development; use the theme version in production if you prefer immutable caching.
- Blank-canvas pages intentionally skip the shared header script.

## 7. Coded pages and tokens

Coded pages carry a small **non-evaluating** token language. It never runs PHP from the database, and a coded page is the only rendering CodeCanvas stores there.

| Token | Output |
|---|---|
| `{{site.name}}`, `{{site.description}}`, `{{site.url}}` | Escaped site values |
| `{{post.title}}`, `{{post.excerpt}}`, `{{post.url}}`, `{{post.date}}`, `{{post.author}}` | Current post values |
| `{{post.content}}` | Filtered post content |
| `{{post.featured_image}}` | Responsive image markup |
| `{{post.meta:key}}` | Public post meta (keys beginning with `_` are blocked) |
| `{{archive.title}}` | Archive title |
| `{{term.name}}`, `{{term.description}}`, `{{term.link}}`, `{{term.count}}` | Current term |
| `{{#items}}…{{/items}}` | Up to 6 latest posts |
| `{{#items:project}}…{{/items:project}}` | Up to 12 items of a public post type |
| `{{item.title}}`, `{{item.url}}`, `{{item.excerpt}}`, `{{item.content}}`, `{{item.featured_image}}`, `{{item.date}}`, `{{item.author}}`, `{{item.terms}}`, `{{item.meta:key}}` | Item fields inside a repeater |

Coded pages store HTML in `post_content`, CSS/JS/layout in `_codecanvas_page_*` meta, and the `_codecanvas_page_code_enabled` flag that makes the plugin render them. The `codecanvas_views` and `codecanvas_global_code` options are only read by the pre-2.0 migration path; nothing writes them.

> **Important:** the CodeCanvas Editor is available under **Pages → CodeCanvas Editor**. The normal WordPress block editor remains authoritative for page content. A CodeCanvas page view or page-editor record takes precedence for that page; do not convert block content to raw HTML without a backup and explicit confirmation.

## 8. Content types and taxonomies

Register post types and taxonomies **in the website theme**, so they are ordinary reviewable code that survives CodeCanvas plugin updates and can be edited in Studio, in VS Code or over MCP:

```php
function my_site_content_types() {
    register_post_type( 'project', array(
        'labels'       => array( 'name' => __( 'Projects', 'my-site' ), 'singular_name' => __( 'Project', 'my-site' ) ),
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'rewrite'      => array( 'slug' => 'projects' ),
    ) );
    register_taxonomy( 'project_category', array( 'project' ), array(
        'labels'       => array( 'name' => __( 'Project categories', 'my-site' ) ),
        'public'       => true,
        'hierarchical' => true,
        'show_in_rest' => true,
    ) );
}
add_action( 'init', 'my_site_content_types', 20 );
```

Flush permalinks once after changing slugs (Settings → Permalinks → Save), or `flush_rewrite_rules( false )` on activation. Do not flush on every request.

Then add `archive-project.php`, `single-project.php` and `taxonomy-project_category.php` in the website theme.

## 9. Helper functions

| Function | Purpose |
|---|---|
| `codecanvas_current_layout()` | `'blank'` or `'global'` |
| `codecanvas_coded_page_id()` | Page ID when the request is a coded page, otherwise `0` |
| `codecanvas_get_active_view()` | Coded page HTML/CSS/JS/layout for the current request (memoised) |
| `codecanvas_render_active_view()` | Echo the coded page body; returns whether it rendered |
| `codecanvas_has_custom_view_output()` | Whether a coded page supplies its own `<main>` content |
| `codecanvas_render_template_markup( $html )` | Apply the token language |
| `codecanvas_render_default_card( $post_id )` | Standard archive card markup |
| `codecanvas_excerpt( $post, $length )` | Trimmed, escaped-ready excerpt |
| `codecanvas_post_thumbnail_markup( $post_id, $size, $class )` | Image or placeholder |
| `codecanvas_default_brand()` | Logo or site-name link |
| `codecanvas_nav_fallback()` | Home + top-level pages fallback menu |
| `codecanvas_contains_private_secret_pattern( $source )` | Known credential pattern check |

Studio/MCP internals (do not call from site code): `codecanvas_studio_resolve()`, `codecanvas_studio_is_allowed_file()`, `codecanvas_studio_can_access_root()`, `codecanvas_mcp_*`.

## 10. Code Studio workflow

1. **CodeCanvas → Code Studio**.
2. Choose the project root: **Website themes** or **Plugins**.
3. The default website project is `codecanvas-site` (shown as **WEBSITE THEME**).
4. The CodeCanvas plugin folder is visible but read-only (lock icons, disabled Save/Rename/Delete).
5. Create/rename/delete supported text files, search the project, and use History to restore the last three snapshots.
6. `Ctrl/Cmd + S` saves; `Ctrl/Cmd + P` opens a path; `Ctrl/Cmd + Shift + F` searches.
7. Unsaved tabs show a dot; closing or switching roots warns before discarding.
8. Saves send the SHA-256 of the version you opened. If the file changed on disk, the save is rejected instead of overwriting.

Limits: 1 MB per file, supported text extensions only, no symlinks, no blocked names (`wp-config.php`, `.env*`, `.htaccess`, `.user.ini`, SSH keys), no directory deletion, no terminal/debugger/Git client.

Starter scaffolds create a theme or plugin in the matching root. They are **never activated automatically**.

## 11. MCP for AI clients

See [11-mcp-integration.md](11-mcp-integration.md). Summary:

- **CodeCanvas → MCP & Settings**.
- Generate a bearer token (shown once).
- Select projects and tools; keep writes off until needed.
- Endpoint: `/wp-json/codecanvas/v1/mcp`.
- 25 tools: 12 read, 7 file-write, 6 content-write. New in 1.7.2: `patch_file`, `create_files`, `restore_snapshot`, `list_snapshots`, `render_url`, `get_php_error_log`, `get_settings`, `update_settings`, `list_menus`, `update_menu`, `upload_media`, `delete_content`. New in 1.7.3: `create_plugin`, which scaffolds a plugin folder and enables it for MCP without activating it.
- Recovery guard: `inc/recovery-guard.php` is copied to `wp-content/mu-plugins/` so a fatal error from a snapshotted theme/plugin file restores the previous contents automatically. It runs before the theme, adds one shutdown callback and no front-end queries.
- Framework theme is never exposed; PHP is validated; writes are snapshotted.

## 12. Security rules

1. Treat administrators with `edit_themes`/`edit_plugins` as server-level trusted.
2. Keep private credentials in `wp-config.php` constants or environment variables. Never in HTML/CSS/JS, the database, Git or chat.
3. Use HTTPS. MCP tokens are bearer credentials.
4. Revoke MCP tokens that are no longer needed or may have leaked.
5. Back up the database and `wp-content` before bulk or risky changes.
6. Validate custom code on staging; syntax checks are not a security boundary.
7. Do not weaken `DISALLOW_FILE_EDIT`/`DISALLOW_FILE_MODS` to make a UI work.
8. Do not expose `wp-config.php`, `.env`, keys or database exports through a web-accessible path.

## 13. Performance rules

- Studio/admin CSS and JavaScript must stay on their screens (`admin_enqueue_scripts` guards).
- The shared menu script is skipped on blank canvases.
- Avoid filesystem scans in frontend code; cache expensive lookups in options/transients.
- Bound custom queries (`numberposts`, `posts_per_page`) and avoid unbounded `-1` queries.
- Don't enqueue jQuery or editor libraries on the frontend.
- Measure with browser dev tools or `curl`; do not claim a score without measurement. See `docs/performance.md`.

## 14. Testing

From the WordPress root:

```bash
php tests/codecanvas/run-tests.php
```

The suite loads WordPress in CLI, exercises the live active theme and the plugin, and restores every option and file it touches. It covers path policy, framework protection, MCP auth/protocol/tools, settings sanitization, rate limiting and audit redaction.

Also run:

```bash
find wp-content/plugins/codecanvas wp-content/themes/codecanvas-site -name '*.php' -exec php -l {} \;
node --check wp-content/plugins/codecanvas/assets/js/studio.js
node --check wp-content/plugins/codecanvas/assets/js/frontend.js
node --check wp-content/themes/codecanvas-site/assets/js/site.js
wp plugin check codecanvas
```

For HTTP checks, use a staging site and a temporary token; revoke it afterwards.

## 15. Release checklist

1. Bump `CODECANVAS_VERSION`, the plugin header `Version:` line and `readme.txt`'s `Stable tag:` together.
2. Update `CHANGELOG.md`, `README.md`, `readme.txt`, `docs/qa-report.md` and `docs/performance.md`.
3. Lint every PHP file and syntax-check every JS file.
4. Run the test suite.
5. Run `wp plugin check codecanvas`; the release must report 0 errors and 0 warnings.
6. Verify the front page, page, single, archive, search, 404 and blank canvas.
7. Check the admin screens (Welcome, Code Studio, MCP & Settings, Pages → CodeCanvas Editor) at desktop and mobile widths.
8. Verify no Studio/MCP assets on public pages.
9. Build the ZIP with `php build-release.php` (it excludes tests, VCS metadata, screenshots, dumps and editor backups) and inspect the listing.
10. Install the ZIP on a clean WordPress install, then test an upgrade over the previous version with an active website theme.

## 16. Troubleshooting

| Problem | Resolution |
|---|---|
| Studio says "not writable" | Check file ownership/permissions and `DISALLOW_FILE_*`; use SFTP if the host forbids web writes. |
| Theme setup button disabled | Needs `manage_options`, `edit_themes`, `switch_themes` and a writable themes folder. |
| White screen after PHP edit | Restore the file from Studio history, Git or backup; or rename it via SFTP. |
| Styles not updating | The theme stylesheet is versioned by mtime; clear page/CDN caches. |
| MCP 401 | Token was replaced/revoked or salts rotated; generate a new token. |
| MCP 404 | REST API blocked or pretty permalinks disabled. |
| Custom post type 404 | Flush permalinks after registering or renaming its slug in the theme. |
| Menu/logo missing after theme setup | Re-save menus in Appearance → Menus; the one-time copy only fills empty values. |

## 17. Credits

CodeCanvas is developed by [Suraj Dev](https://surajdev.com/) with credit to [Webot](https://webot.surajdev.com/). Licensed GPL-2.0-or-later. See `docs/10-complete-project-documentation.md` for the full specification and `SECURITY.md` for the risk register.
