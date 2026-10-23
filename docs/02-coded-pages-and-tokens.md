# Coded pages and dynamic tokens

A **coded page** is one WordPress Page given its own HTML, CSS and JavaScript. It is the only thing CodeCanvas renders itself; every other request comes from a theme file, so there is no stored template that can override your PHP.

## Where a coded page lives

| Part | Stored in |
|---|---|
| HTML | The page's `post_content` |
| CSS | `_codecanvas_page_css` post meta |
| JavaScript | `_codecanvas_page_js` post meta |
| Page shell (`global` or `blank`) | `_codecanvas_page_layout` post meta |
| The coded-page flag | `_codecanvas_page_code_enabled` post meta |

Because it is all post data, the page is editable in three places that agree: **Pages → CodeCanvas Editor**, Code Studio over the same files and database, and MCP through `update_content`.

## The page shell

- `global` keeps the site header and footer.
- `blank` renders without them, and also sets the "CodeCanvas Blank Canvas" page template, so the choice survives in normal WordPress.

## Routing

A coded page is rendered by the plugin's bundled `page.php`, `front-page.php` or `templates/blank-canvas.php`, whichever the request needs. A theme's more specific `page-{slug}.php` cannot hide a coded page. Clear the `_codecanvas_page_code_enabled` flag (or remove the page's code in the editor) to return the page to the theme's normal template.

## Dynamic tokens

Tokens are replaced server-side and never evaluate PHP.

| Token | Output |
|---|---|
| `{{site.name}}`, `{{site.description}}`, `{{site.url}}` | Site values |
| `{{post.title}}`, `{{post.content}}`, `{{post.excerpt}}` | Current record |
| `{{post.url}}`, `{{post.featured_image}}`, `{{post.date}}`, `{{post.author}}` | Current record |
| `{{post.meta:key}}` | One scalar custom field (keys starting with `_` are refused) |
| `{{term.name}}`, `{{term.description}}`, `{{term.link}}`, `{{term.count}}` | Queried term |
| `{{archive.title}}` | Archive heading |
| `{{#items}}…{{/items}}` | Repeats the current archive query, or recent posts on a static page |
| `{{#items:project}}…{{/items:project}}` | Repeats a named public post type |
| `{{item.title}}`, `{{item.url}}`, `{{item.excerpt}}`, `{{item.content}}`, `{{item.featured_image}}`, `{{item.date}}`, `{{item.author}}`, `{{item.terms}}`, `{{item.meta:key}}` | Fields inside a repeater |

Text outside the braces is emitted untouched. Do not put private API keys or credentials in a coded page: the HTML, CSS and JavaScript are public.
