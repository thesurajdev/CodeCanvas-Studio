# VS Code workflow

For safe work, use a local development copy or staging site rather than editing production files directly.

Recommended process:

1. Install and activate the **CodeCanvas** plugin, then use **CodeCanvas → Code Studio** to create and activate the **CodeCanvas Site** theme. If server permissions block that action, create the theme through VS Code/SFTP/Git and activate it in **Appearance → Themes**.
2. Track `wp-content/themes/codecanvas-site/` in your own Git repository or branch.
3. Edit website PHP templates, CSS and JavaScript in that theme; use Code Studio for quick supported edits.
4. Run PHP lint checks on changed PHP files.
5. Test on a staging WordPress site, including mobile, page templates, custom-content singles, archives and taxonomy routes.
6. Back up the database, the CodeCanvas plugin and the website theme before deployment.
7. Deploy the tested theme files (and the plugin only when you are updating CodeCanvas itself).

To develop CodeCanvas itself, work in a separate local Git checkout of the plugin source. Code Studio intentionally prevents edits to CodeCanvas plugin files so routine site customization does not break its own development UI.

The website theme is the only owner of templates, styles and shared markup. Nothing in the database can override a PHP template: only a coded page replaces its own template, and its CSS/JS apply to that one page. Test the theme source directly.

Do not add private API credentials to theme files or Git history.
