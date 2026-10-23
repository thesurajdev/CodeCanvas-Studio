# CodeCanvas IDE workspace and preview

CodeCanvas 1.3.0 uses a VS Code-inspired full-height workbench rather than the previous settings-card editor.

## Workspace parts

- **Title bar:** edits the current WordPress Page title and provides Save, Pages, focus-mode, preview and settings actions.
- **Activity bar:** switches Explorer, Search and Page Settings.
- **Explorer:** lists the three virtual source fields stored for the current Page: `index.html`, `style.css`, and `script.js`.
- **Editor tabs:** switch between the page's HTML, CSS and JavaScript using WordPress CodeMirror.
- **Search:** searches current-page HTML/CSS/JavaScript and opens a result at its file and line.
- **Preview:** hidden by default so code gets the main workspace. Use the toolbar Preview button to show it, then use Desktop, Tablet, Mobile and Refresh controls.
- **Status bar:** shows save state, current language, cursor position, indentation hint and encoding.

## Important distinction: virtual page files vs theme source files

The editor's `index.html`, `style.css` and `script.js` are UI labels for WordPress page content and post metadata. They are not physical files created in the theme directory. To edit PHP template files or physical CSS/JavaScript assets, use VS Code against the theme folder and deploy changes through a controlled workflow such as Git or SFTP.

## Keyboard and responsive behavior

- `Ctrl/Cmd + S` saves the Page through WordPress.
- Left/Right/Home/End moves between the editor tabs while a tab is focused.
- On desktop, the workspace enters full IDE focus mode by default. Use **Exit IDE** to restore the WordPress admin menu. Activate the focus button again to return to the IDE view.
- The preview opens as a side panel on desktop and an overlay on narrower screens. The Explorer can be collapsed from its heading.
- Search input is focused when the Search activity button is selected.

## Preview limits

The preview iframe is sandboxed and network/API requests are blocked. External resources may not appear as they do on the published page. Always test the saved page on the actual front end before release.
