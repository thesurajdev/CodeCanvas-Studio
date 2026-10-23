# Install, upgrade, and recovery

Applies to **CodeCanvas 2.0.0**. Back up the database and `wp-content` before any upgrade or source change.

---

## 1. Requirements

- WordPress 6.6+ (tested on 7.1.3).
- PHP 7.4+ (tested on 8.0.30).
- MySQL/MariaDB compatible with your WordPress version.
- A writable `wp-content/themes` if you want browser-based Studio writes and website-theme setup.
- HTTPS for any site that uses the MCP endpoint.
- No companion plugin required.

## 2. Fresh installation

1. Back up the site (files + database), or use a local/staging site.
2. In **Plugins → Add New → Upload Plugin**, upload `codecanvas-2.0.0.zip`.
3. Click **Activate**.
4. Open **CodeCanvas → Code Studio**.
5. Click **Create and activate CodeCanvas Site theme**.
   - The generated theme is standalone; it has no `Template` header and does not require CodeCanvas to render.
   - Existing files in `codecanvas-site` are never overwritten.
   - If the folder exists as a legacy child theme or another incompatible theme, setup stops and asks you to convert or rename it.
   - If the filesystem is not writable, create the theme through SFTP/VS Code instead.
6. Confirm the site front page still renders.
7. Reassign menus and the logo in **Appearance → Customize** if the one-time copy did not carry them (it only fills empty values).
8. Set permalinks to a pretty structure (**Settings → Permalinks**) if the site uses plain `?p=` URLs.

### Post-install checklist

- [ ] Front page, a page, a post, a category, search and a 404 return the expected status.
- [ ] Normal WordPress Pages/Posts editing still works.
- [ ] **CodeCanvas → Welcome**, **Code Studio** and **MCP & Settings** open without errors, and no removed screen appears in the menu.
- [ ] **Pages → CodeCanvas Editor** opens and can save a test page.
- [ ] Studio opens `codecanvas-site` and shows the plugin folder as read-only.
- [ ] A test file can be created, saved, reloaded and deleted in the website theme.
- [ ] Public pages load no `studio.js`, `studio.css` or `mcp-settings.css`.
- [ ] The site is backed up again after setup.

## 3. Upgrading from the 1.7.x theme edition

The 1.7.x release was a theme. The 2.0.0 release is a plugin and generates a standalone website theme. Content, views, content-type definitions, Studio history and MCP settings keep their option names, so they survive the move.

1. **Back up** the database and the current `wp-content/themes/CodeCanvas` folder.
2. Switch the site to a safe default theme (for example Twenty Twenty-Five) in **Appearance → Themes**. Do not delete the old theme yet.
3. Install and activate `codecanvas-2.0.0.zip` under **Plugins**.
4. Open **Code Studio** and click **Create and activate CodeCanvas Site theme**.
5. Check the front page, a coded page, an archive, search and a 404.
6. Confirm **Welcome**, **Code Studio** and **MCP & Settings** open, and that coded pages and MCP behave as expected.
7. Only after the new theme is verified, remove the old `CodeCanvas` theme folder through **Appearance → Themes** or SFTP.
8. If the old theme used a child theme, compare its templates and `assets/` with the generated `codecanvas-site` theme and port deliberate customizations.

### What survives the upgrade

| Item | Survives? | Where |
|---|---|---|
| Site content, menus, widgets, users | Yes | Database |
| Coded pages | Yes | Post content and `_codecanvas_page_*` meta |
| Views, global code, content-type definitions from 1.7.x | Kept but unused | `codecanvas_views`, `codecanvas_global_code`, `codecanvas_content_types` options |
| Studio file snapshots | Yes | Non-autoloaded options |
| MCP settings, tokens and audit log | Yes (server keeps its previous on/off state) | Options |
| Old theme's template/CSS edits | No, unless copied | Previous `wp-content/themes/CodeCanvas` folder |

## 4. Upgrading CodeCanvas

1. Back up the database and `wp-content`.
2. In **Plugins → Add New → Upload Plugin**, choose the new `codecanvas-*.zip` and click **Replace current with uploaded**.
3. Keep the plugin active.
4. Visit **Settings → Permalinks** (or **CodeCanvas → MCP & Settings** once) so rewrite rules refresh after an update.
5. Confirm the website theme is still active and the front end renders.
6. Re-run the post-install checklist for anything the release notes call out.

The generated `codecanvas-site` theme is never overwritten by a plugin update. Plugin updates replace only `wp-content/plugins/codecanvas`.

## 5. Upgrading the website theme

The generated theme is yours; CodeCanvas never updates it automatically. Keep it in Git. When a new CodeCanvas release includes new starter templates:

1. Compare the bundled `wp-content/plugins/codecanvas/site-theme/` scaffold with your theme.
2. Merge new files deliberately; **do not overwrite an existing theme file**.
3. Re-test templates, assets and menus on staging.

## 6. Moving between environments

1. Deploy the plugin, the website theme and the database.
2. Update `WP_HOME`/`WP_SITEURL` and run a search-replace for the domain.
3. Re-save permalinks once.
4. MCP tokens are environment-specific because the hash uses that site's salts. Generate a new token per environment; never copy a production token to staging.
5. Do not copy `wp-config.php`, `.env` or MCP tokens into Git.

## 7. Recovery and rollback

### 0. Automatic recovery (1.7.4+)

CodeCanvas can install a recovery guard in `wp-content/mu-plugins/codecanvas-recovery.php`; it loads before the theme. If a PHP fatal error comes from a theme or plugin file that has a CodeCanvas snapshot, the guard restores the previous contents and the next request loads normally. Check **CodeCanvas → MCP & Settings → Recovery guard** for the log and to install, repair or remove it. It only acts on files with a snapshot, and only when that snapshot is less than 24 hours old, so keep Git/backups for everything else.

### A. A PHP change broke the site (white screen / 500)

1. **Rename the offending file** through SFTP/SSH, e.g. `page.php` → `page.php.bak`. WordPress falls back to the next template.
2. Or restore from **Code Studio → History** if the admin still loads:
   - Open the file, choose **History**, restore the last snapshot. The current broken content is snapshotted first.
3. Or restore from Git/backup.
4. Clear OPcache/page cache if the host uses them.

Code Studio stores at most **three** snapshots per file in `wp_options`. They are not a backup replacement.

### B. The website theme itself is broken

- Activate another theme temporarily in **Appearance → Themes**.
- Fix or restore `wp-content/themes/codecanvas-site`.
- Reactivate the website theme.
- Re-check menus/logo; the one-time inheritance runs only during setup.

### C. A plugin update failed or produced errors

1. Deactivate CodeCanvas through **Plugins** if wp-admin still loads; otherwise rename `wp-content/plugins/codecanvas` via SFTP.
2. Restore the previous plugin ZIP or folder.
3. Reactivate CodeCanvas and confirm the website theme is active.
4. Restore the database backup only if options were changed by the failed update.

WordPress's plugin uploader may leave an incomplete plugin folder. Keeping a previous ZIP and a database backup is the reliable rollback.

### D. You are locked out of wp-admin

- Restore access through hosting (SFTP/SSH/phpMyAdmin).
- To disable a broken plugin, rename its folder; WordPress deactivates it on the next request.
- To switch the active theme in the database:
  ```sql
  UPDATE wp_options SET option_value='twentytwentyfive' WHERE option_name='template';
  UPDATE wp_options SET option_value='twentytwentyfive' WHERE option_name='stylesheet';
  ```
  Replace the prefix and theme slug with your own. Back up first.
- Do **not** edit `wp-config.php` through a browser editor.

### E. MCP token leaked or client compromised

1. **MCP & Settings → Revoke token** (immediately disables the server).
2. Review **Recent MCP activity**.
3. Inspect file modification times and Studio history for unexpected changes.
4. Restore affected files from Git/backup.
5. Generate a new token only after cleaning the client configuration.
6. If the site is compromised more broadly, rotate WordPress salts (which also invalidates tokens), reset administrator passwords, and audit users/plugins.

### F. Lost MCP token

The plaintext cannot be recovered. Click **Generate a replacement token**, update the client, and revoke/ignore the old one. The old token is invalid as soon as the replacement is stored.

### G. Website-theme setup failed

- Read the on-screen error: it distinguishes an unwritable themes folder, a conflicting folder, a symlink, or a failed theme-header validation.
- Create `wp-content/themes/codecanvas-site/style.css` manually with a standalone header (`Theme Name: CodeCanvas Site`, **no** `Template` line), add `functions.php` and the templates, then activate it in **Appearance → Themes**.
- Or fix ownership/permissions on the themes folder. Do not chmod 777.
- As a temporary fallback, activate any other theme; coded pages still render through the plugin's bundled templates.

## 8. Uninstalling

1. Activate another theme.
2. Deactivate and delete **CodeCanvas** under **Plugins**.
3. Delete `codecanvas-site` from **Appearance → Themes** (or SFTP).
4. Remove `wp-content/mu-plugins/codecanvas-recovery.php` if the recovery guard was installed.
5. Optionally remove CodeCanvas options:
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE 'codecanvas_%';
   ```
   This removes coded-page options, legacy view/global-code options, Studio history and MCP settings. Back up first.
6. Delete `_codecanvas_page_*` post meta if you used the coded-page editor:
   ```sql
   DELETE FROM wp_postmeta WHERE meta_key LIKE '_codecanvas_%';
   ```
7. Remove the `CODECANVAS_MCP_TOKEN` constant from `wp-config.php` if you added it.

Deleting the plugin or theme does **not** delete posts, pages, media, users or standard WordPress settings. Content of a custom post type registered by a theme you delete remains in the database, but stays hidden from the admin until a theme registers that type again.

## 9. Rollback checklist

- [ ] Previous plugin ZIP available.
- [ ] Database backup available.
- [ ] Website theme backed up separately.
- [ ] MCP token revoked if the release is being removed.
- [ ] Site switched to a safe theme during the rollback.
- [ ] Front end re-tested after reactivation.
- [ ] Menus/logo confirmed.
- [ ] Caches cleared.

## 10. Support information to collect

When reporting a problem, include:

- CodeCanvas, WordPress and PHP versions.
- Whether the website theme is active.
- The exact error text and the file/line if shown.
- Whether `DISALLOW_FILE_EDIT`/`DISALLOW_FILE_MODS` are set.
- Hosting type (Apache/Nginx, Windows/Linux).
- Steps to reproduce and whether they affect the front end, wp-admin, Studio or MCP.

Never send passwords, MCP tokens, API keys, database exports or full `wp-config.php` contents.

---

Developer: [Suraj Dev](https://surajdev.com/) · Credit: [Webot](https://webot.surajdev.com/) · License: GPL-2.0-or-later.
