# CodeCanvas Site website-theme workspace

CodeCanvas is a **plugin**. It contains the admin screens, Studio UI, editor JavaScript/CSS, coded-page rendering, MCP server and shared WordPress integration. Website-specific frontend work belongs in the theme CodeCanvas generates.

## First-time setup

1. Activate the CodeCanvas plugin.
2. Open **CodeCanvas → Code Studio**.
3. Choose **Create and activate CodeCanvas Site theme**. This requires administrator-level theme capabilities and filesystem permissions. The action is explicit because it creates files and switches the public theme; CodeCanvas does not silently change the active theme.
4. CodeCanvas creates `wp-content/themes/codecanvas-site` from the plugin's bundled `site-theme/` scaffold. It never overwrites existing files.
5. The generated theme is standalone: it has no `Template` header and does not need CodeCanvas to render, although CodeCanvas supplies the view renderer while active.

If WordPress hosting policy or filesystem permissions block this operation, create the theme through VS Code/SFTP/Git and then activate it using the regular WordPress Themes screen. Do not loosen file permissions indiscriminately.

## Starter file map

```text
codecanvas-site/
├── style.css                 # Standalone theme header
├── functions.php             # Theme supports, assets, site hooks and safe fallbacks
├── front-page.php            # Homepage
├── home.php                  # Posts index
├── index.php                 # Generic fallback
├── page.php                  # Static pages
├── single.php                # Single posts/content fallback
├── archive.php               # Archives/listings
├── search.php                # Search results
├── 404.php                   # Not-found page
├── comments.php              # Comments
├── header.php                # Shared header, opens <main>
├── footer.php                # Shared footer, closes <main>
├── theme.json                # Block editor settings and palette
├── templates/
│   └── blank-canvas.php      # Page template without shared header/footer
└── assets/
    ├── css/site.css          # Main public site styles
    ├── css/editor.css        # Editor styles
    └── js/site.js            # Public browser interactions
```

Add more templates as needed—for example `single-project.php`, `archive-project.php` or `taxonomy-project_category.php`—when corresponding routes are registered.

The generated templates reuse the plugin's `cc-container`/`cc-section`/`cc-page-heading` classes. `header.php` already opens `<main id="main-content">`; templates must not add another `<main>` element.

On first activation, CodeCanvas copies the current theme's navigation menu locations, custom logo and header image into the new theme where its values are empty. Later Customizer changes are not overwritten.

## What belongs where?

| Work | Correct location |
|---|---|
| Homepage/site layout | `front-page.php` in `codecanvas-site` |
| Default page layout | `page.php` |
| Single post/custom-item layout | `single.php` or a more specific template |
| Custom CSS | `assets/css/site.css` |
| Public site JavaScript | `assets/js/site.js` |
| Site-specific hook or integration | `functions.php`, carefully reviewed |
| CodeCanvas Admin UI, editor, security and builder fixes | The CodeCanvas plugin source, through a separate local Git checkout |
| Custom post types and taxonomies | The website theme's `functions.php`, on the `init` hook |
| Private API key | Server environment or protected `wp-config.php` constant; never browser JavaScript |

## Code Studio safety boundary

The CodeCanvas plugin folder can be inspected but is read-only through Code Studio. The check is case-insensitive and is also verified against the resolved filesystem path, so a lowercase alias such as `codecanvas/includes/...` cannot bypass it on Windows or macOS. This prevents a routine website edit from accidentally breaking the code editor or administration interface. The general Code Studio file writer should only edit the generated website theme for normal frontend work and the plugins root for plugin development, subject to WordPress capabilities and hosting file policy.

## Coded pages

The plugin's `site-theme/` templates are also the renderer for coded pages. Those pages keep working if the active theme is switched, because the plugin's bundled template prints the stored HTML. Every other route comes from the active theme, which remains the recommended home for site-specific layouts.

## Limitations

- CodeCanvas creates the theme only when the user confirms the action; it must not switch the public theme silently.
- If an incompatible or legacy child theme named `codecanvas-site` exists, setup stops and preserves it. Convert or rename it manually rather than allowing an overwrite.
- If the website theme is switched off, templates return to the active theme. Content remains in the database, and views still render through the plugin, but theme-only visual work is inactive.
- The plugin's fallback templates require CodeCanvas functions; if the plugin is deactivated, the generated theme's own templates still render using its built-in fallbacks.
