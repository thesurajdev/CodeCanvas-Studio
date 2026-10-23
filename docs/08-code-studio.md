# CodeCanvas Studio — source-file workspace (1.7.0)

CodeCanvas Studio is an optional developer workspace under **CodeCanvas → Code Studio**. It does not replace the default WordPress dashboard or block editor. The normal Pages, Posts, Media and publishing experiences continue to work as standard WordPress screens. **CodeCanvas → MCP & Settings** is a separate configuration screen for optional AI-client access; see [11-mcp-integration.md](11-mcp-integration.md).

## What works in this release

- Browse source files under `wp-content/themes/` and `wp-content/plugins/` when the account has the corresponding WordPress capabilities and file modifications are allowed.
- Open and edit supported text source files with WordPress's bundled CodeMirror-based editor and syntax highlighting where the installed WordPress core supports the language mode.
- Create text files and folders, rename and delete a supported source file, and search readable text source files under the current folder.
- Create a basic theme or plugin scaffold. A scaffold is **not activated automatically**.
- Save a bounded three-version history snapshot to a non-autoloaded WordPress database option before each save, rename, delete or restore. Restore a snapshot from the History panel.
- Detect some common private credential formats before saving browser-delivered files. This is best-effort protection, not a substitute for keeping secrets server-side.
- Use `Ctrl/Cmd + S` to save, `Ctrl/Cmd + P` to open a file by root-relative path, and `Ctrl/Cmd + Shift + F` to search.
- **Focus mode** (1.7.6+): a title-bar control hides the WordPress admin bar, admin menu, footer, screen options and notices so the workbench fills the viewport. Toggle with the button or `Ctrl/Cmd + Shift + M` and leave with `Esc`; it is applied only on demand and is never restored automatically.
- **Soft wrap** (1.7.7): on by default, toggled from the editor toolbar or with `Alt+Z`, and remembered per browser. The toolbar also carries the line/word wrap state so long CSS, JS or HTML stays readable without a horizontal scrollbar.
- **Mobile-first layout** (1.7.8): at 782px and below the Explorer becomes an off-canvas drawer opened from the folder icon (or Search/History), with a backdrop, close button, `Esc` support and automatic closing when a file is picked. The activity rail runs horizontally, buttons and file rows meet 38–44px touch targets, the search field is 16px to prevent iOS zoom, the editor uses 15px type with wrap, and the workspace height uses `100dvh` with safe-area padding.

## Scope and limits

- This is a source-file editor, not the full Microsoft VS Code application. It does not include an integrated terminal, arbitrary command execution, Git client, debugger, dependency manager, live PHP sandbox, automated deployment, schema/migration studio or API-secret vault in this release.
- The project browser is limited to theme/plugin source roots. It deliberately does not allow editing WordPress core, `wp-config.php`, `.env`, `.htaccess`, `.user.ini`, SSH keys, filenames that resemble secrets, symlinks, binary files or files larger than 1 MB.
- Only supported text extensions appear in the tree. Large dependency folders such as `node_modules` and Git internals are skipped by the file browser/search.
- File writes require the correct WordPress capability (`edit_themes` / `edit_plugins`), `manage_options`, server read/write permissions, and WordPress file-modification policy to allow the operation. Sites configured with `DISALLOW_FILE_EDIT` or restrictive hosting permissions will have read-only or disabled functionality. Do not weaken production policies simply to enable this workspace.
- Before a source save, a snapshot of the current file (up to 1 MB) is stored in a non-autoloaded database option, with at most three snapshots retained per root-relative file. Database administrators can still access database records; keep normal database backups and access controls in place.
- Editing PHP can break the entire site or execute malicious code. Use local/staging environments, version control and backups. The theme cannot guarantee a full rollback if a PHP change prevents WordPress from loading or database writes fail.
- File writes use the PHP process's filesystem permissions. Hosts that require an FTP/SSH credential prompt for all writes may not support browser-based editing; use VS Code/SFTP or Git deployment in that case.
- CodeCanvas ships as a plugin, because source editing and an MCP server are not permitted in a WordPress.org directory theme under its current review rules. The website theme it generates is ordinary theme code with no required plugin.

## Security model

Every AJAX operation checks a WordPress nonce, `manage_options`, root-specific capabilities and the WordPress file modification policy. Paths are root-relative and checked against the real filesystem root; path traversal and symlinks are rejected. The editor never executes PHP as a result of saving. It writes source files that WordPress may execute later, so only highly trusted developers should have access.

Do not put private API keys in client-side HTML, CSS, JavaScript or public assets. Store private keys outside public files and call providers through a purpose-built server-side integration. Pattern-based secret detection may miss credentials or flag harmless examples; it is not a security boundary.

## Recommended workflow

1. Back up the database and site files.
2. Use a local or staging site, preferably with Git enabled.
3. Open **CodeCanvas → Code Studio**. If the Site website theme is not active, use the explicit setup action first. Afterward the Themes workspace should open `codecanvas-site/` by default; the CodeCanvas framework folder is read-only.
4. Select a source file in Explorer, edit it, and save with the button or `Ctrl/Cmd + S`.
5. Inspect History after an edit, and test the resulting site in a separate tab.
6. Activate a new theme/plugin only from WordPress's normal UI and only after reviewing it.

Developer: [Suraj Dev](https://surajdev.com/) · Credit: [Webot](https://webot.surajdev.com/).

## PHP safety checks

Before saving `.php` or `.phtml`, Studio uses PHP's tokenizer in `TOKEN_PARSE` mode where available to reject parse errors. This can catch many syntax errors but **cannot** catch all runtime exceptions, missing functions, fatal logic, or behavior problems. Test PHP changes on staging and keep an independent recovery path.


## Plugin/website-theme boundary (v2.0.0)

CodeCanvas is a plugin and contains its own admin/editor/security code. Do not use the website workspace to edit that framework. The generated website theme is a separate standalone theme directory with website-owned PHP templates, CSS and browser JavaScript. The setup action does not silently change the active theme; it asks an administrator to confirm, refuses symlink or incompatible destination folders, and never overwrites existing website-theme files. The read-only plugin rule compares plugin folder names case-insensitively and also checks the resolved filesystem path, so case variants of the CodeCanvas folder cannot be written on Windows or macOS. See `09-website-theme-workspace.md`.
