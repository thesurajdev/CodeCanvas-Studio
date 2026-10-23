# CodeCanvas 1.7.0 performance benchmark

This report records what was **measured** on the local development site. It does not claim a PageSpeed score, a production transfer size, or a comparison against every host.

## Environment

| Item | Value |
|---|---|
| WordPress | 7.1.3 |
| PHP | 8.0.30 (XAMPP, Windows) |
| Web server | Apache 2.4.58 |
| Theme | CodeCanvas 1.7.0 parent + `codecanvas-site` 1.0.0 child |
| Caching | None enabled (no page cache, no CDN, no object cache) |
| HTTP | HTTP/1.1 over `http://localhost/demo` |
| Date | 21 October 2026 |

Because caching and compression are off, these numbers show **relative asset weight and request isolation**, not absolute production timings. Enable page caching, compression and HTTP/2 on production and re-measure there.

## Method

- Fetched the homepage and a themed page with `curl`, saving the HTML.
- Counted `<link rel="stylesheet">` and `<script src>` tags and resolved their URLs.
- Confirmed that no Code Studio or MCP settings assets appear in public HTML.
- Measured uncompressed file sizes with `wc -c`.
- Ran five homepage requests and three page requests with `curl -w '%{time_total} %{size_download}'`.
- Created a temporary published page using the `templates/blank-canvas.php` template to measure the blank-canvas layout, then deleted it.

Reproduce:

```bash
curl -s -o home.html -w 'status=%{http_code} bytes=%{size_download} time=%{time_total}\n' https://example.com/
grep -oE '<link[^>]+stylesheet[^>]*>|<script[^>]+src=[^>]*>' home.html
```

## Results

### Homepage (shared header/footer)

| Metric | Result |
|---|---|
| HTTP status | 200 |
| HTML transfer | 26,416 bytes |
| Theme stylesheets | 4 requests |
| Theme scripts | 2 requests |
| Studio CSS/JS requests | **0** |
| MCP settings CSS requests | **0** |
| CodeMirror / editor assets | **0** |
| Time to first byte (local, 5 runs) | 0.497 s – 0.704 s |

Stylesheets, in order:

1. `CodeCanvas/style.css` — 578 B
2. `CodeCanvas/assets/css/frontend.css` — 10,020 B
3. `codecanvas-site/style.css` — 559 B
4. `codecanvas-site/assets/css/site.css` — 227 B

Scripts:

1. `CodeCanvas/assets/js/frontend.js` — 678 B
2. `codecanvas-site/assets/js/site.js` — 151 B

Total uncompressed theme CSS + JS: **12,213 bytes** across 6 requests.

### Blank-canvas page

| Metric | Result |
|---|---|
| HTTP status | 200 |
| HTML transfer | 24,664 bytes |
| Theme stylesheets | 4 requests |
| Theme scripts | **1** request |
| Shared menu script | Not loaded |
| Studio/MCP assets | **0** |

The blank canvas skips `frontend.js` because the shared header and menu toggle are not rendered. Its own inline JavaScript is preserved when configured (the script handle is enqueued if inline JS exists).

### Themed page (`page.php`)

| Metric | Result |
|---|---|
| HTTP status | 200 |
| HTML transfer | 29,276 bytes |
| Local time (3 runs) | 0.615 s – 0.827 s |

### Development assets intentionally excluded from the frontend

| File | Size | Loaded on public pages? |
|---|---|---|
| `assets/js/studio.js` | 32,904 B | No |
| `assets/css/studio.css` | 18,867 B | No |
| `assets/css/mcp-settings.css` | 3,758 B | No |
| `assets/css/admin.css` | 22 KB (approx.) | No |
| CodeMirror (WordPress bundled) | — | No |

This keeps roughly **55 KB of uncompressed editor CSS/JS** off every public page in addition to avoiding admin AJAX initialization.

## Acceptance targets

| Target | Status | Evidence |
|---|---|---|
| No Code Studio JS/CSS on ordinary public pages | Pass | 0 matches in homepage/blank HTML |
| No unnecessary frontend asset requests on a blank custom layout | Pass (partial) | 4 CSS + 1 child JS; shared menu JS removed. The child's own `site.js` and stylesheets still load because they are the site's design layer. |
| No repeated filesystem tree scans on frontend requests | Pass | Frontend uses option/post-meta reads; directory iteration exists only in Studio/MCP search handlers |
| No unbounded custom-post-type archive queries | Pass | Theme registry archives use standard `WP_Query`; token repeaters cap at 6/12 items; MCP content listing caps at 100 |
| No avoidable synchronous third-party requests | Pass | Theme makes no external HTTP requests; no external CDNs/fonts |
| No significant regression vs a minimal baseline | Not measured | Requires a controlled theme switch on a staging copy; see limitations |

## What was not measured

- Lighthouse/PageSpeed scores, LCP/CLS/INP field data.
- Compressed (gzip/Brotli) transfer sizes or HTTP/2 multiplexing.
- Shared hosting with slow disk/database, object caching, or a CDN.
- High-traffic concurrency or PHP-FPM vs mod_php differences.
- A direct baseline comparison against Twenty Twenty-Five. Switching the live demo theme would change the user's site and was not performed.
- Database query counts. No query-monitor plugin is installed, and adding one would change the runtime under test.

## Performance guardrails for future work

1. Keep Studio, MCP settings and CodeMirror assets behind `admin_enqueue_scripts` screen checks.
2. Do not enqueue frontend assets from `inc/dev-studio.php` or `inc/mcp*.php`.
3. Avoid `scandir`/`RecursiveDirectoryIterator` in template rendering.
4. Keep option reads memoised; `codecanvas_get_active_view()` already caches per request.
5. Cap every new archive/repeater query.
6. Version assets with `filemtime()` only for the website theme during development; consider immutable production versions.
7. Re-run this benchmark on the target host with caching enabled before making performance claims.

---

Measured on the local XAMPP demo. Re-measure on staging/production before publishing any performance claim.
