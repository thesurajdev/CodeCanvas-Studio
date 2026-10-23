# Security and API Keys

Private keys belong on the server. Do not put them in page code or global JS/CSS/HTML, even if the JavaScript is minified or the HTML field is hidden from the page editor.

Example server-side configuration in `wp-config.php`:

```php
define( 'CODECANVAS_AI_API_KEY', 'replace-with-your-secret' );
```

Keep the value out of public repositories and backups shared with untrusted people. A hosting environment variable or secret manager may be preferable. In server-side PHP, check `defined( 'CODECANVAS_AI_API_KEY' )` before reading the constant; never echo it or include it in browser JSON.

Any browser-facing key should be treated as public and restricted through the API provider's controls. Avoid generic proxy endpoints. Integrations should only call known providers/operations and apply permission checks, validation, rate/usage controls and safe error redaction.
