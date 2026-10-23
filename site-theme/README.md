# CodeCanvas Site

This is your website theme, generated and edited by CodeCanvas Studio. The
CodeCanvas builder itself lives in the CodeCanvas plugin; site-specific
templates, styles and hooks belong here.

## Main files

- `style.css`: theme metadata and site CSS.
- `functions.php`: theme supports, asset loading and site-specific hooks.
- `assets/css/site.css`: your site-wide styles.
- `assets/css/editor.css`: editor styles that mirror the front end.
- `assets/js/site.js`: public JavaScript (never put private API keys here).
- `header.php`, `footer.php`: shared chrome, rendered from these files alone.
  Edit them here, in Code Studio, or over MCP.
- `front-page.php`, `home.php`, `index.php`, `page.php`, `single.php`,
  `archive.php`, `search.php`, `404.php`, `comments.php`: front-end templates.
- `templates/blank-canvas.php`: a page template without the shared header and
  footer, for fully custom coded pages.
- `theme.json`: block editor settings and palette.

Use staging, Git and backups before editing PHP. Private API credentials must
stay in server-side configuration, never in HTML or browser JavaScript.

Developer: [Suraj Dev](https://surajdev.com/) · Credit: [Webot](https://webot.surajdev.com/).
