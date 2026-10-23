=== CodeCanvas ===
Contributors: surajdev
Tags: code editor, developer tools, page builder, mcp, code snippets
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A code-first WordPress builder with a real-file Code Studio, coded pages, a scoped MCP server and a generated website theme.

== Description ==

CodeCanvas adds one **CodeCanvas** menu to WordPress:

* **Welcome** — version, website theme, MCP and recovery-guard status.
* **Code Studio** — a real-file editor for the site's theme and eligible plugins, with snapshots, PHP validation and conflict checks.
* **MCP & Settings** — optional, tool-scoped access for AI clients, off by default.

Coded pages use **Pages → CodeCanvas Editor**.

The plugin generates a real standalone theme (`CodeCanvas Site`) for the website. Your templates, CSS, JavaScript and site-specific PHP live in that theme, so they stay separate from the builder and survive CodeCanvas updates. The plugin's own source is read-only in Studio and is never exposed over MCP.

**Coded pages:** give a page its own HTML, CSS and JavaScript with a live preview, while the rest of the site keeps its shared header and footer.

**No database layer over your code:** there is no template or content-type store between you and the site. Post types, taxonomies, templates and styles are PHP/CSS files in the website theme, so an assistant connected over MCP edits exactly the files you do.

**MCP:** connect Claude Code, Cursor, VS Code, claude.ai or another MCP-capable client over a scoped HTTP endpoint, with bearer tokens or OAuth 2.1 with PKCE. File writes and content writes are separate, off-by-default switches. Every call is audited and rate-limited.

== Installation ==

1. Upload the `codecanvas` folder to `/wp-content/plugins/`, or install the ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate **CodeCanvas**.
3. Open **CodeCanvas → Code Studio** and click **Create and activate CodeCanvas Site theme** (or activate an existing theme).
4. Edit the generated theme, or use **CodeCanvas → Welcome** to create a coded page.

The generated theme is fully editable. Existing files are never overwritten during setup.

== Frequently Asked Questions ==

= Does this replace my theme? =

No. CodeCanvas generates and edits a standalone website theme. You can also keep using another theme; coded pages still render through CodeCanvas templates.

= Is MCP enabled by default? =

No. It is off until an administrator generates a token, selects tools and projects, and enables the server.

= Can I edit plugin files? =

Eligible theme and plugin text files can be edited by an administrator with the required capabilities. CodeCanvas plugin files are read-only.

== Changelog ==

= 2.0.0 =
* Converted CodeCanvas from a theme into a plugin. All functionality is preserved.
* The builder now generates a standalone CodeCanvas Site theme instead of a child theme.
* Moved front-end page rendering, Studio, MCP and the recovery guard into the plugin.
* Added an MCP framework guard that hides the CodeCanvas plugin source.
* Removed the **Views & Templates**, **Content Types** and **Global Code** screens. Rendering is coded pages plus theme files only, with nothing in the database that can override a template; custom post types and taxonomies are registered in the website theme's `functions.php`.

== Upgrade Notice ==

= 2.0.0 =
CodeCanvas is now a plugin and generates a standalone website theme. Deactivate the old CodeCanvas theme before activating this plugin.
