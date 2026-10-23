# CodeCanvas Page Editor

## Open the editor

**Pages → Add New** and existing Page edit links open the CodeCanvas workspace for users who can edit Pages. Accounts without WordPress `unfiltered_html` see a dedicated permission explanation and a link to the native editor; they cannot save arbitrary executable code from CodeCanvas.

## Edit a page

1. Enter the page title and optional URL slug.
2. Choose **Shared header & footer** or **Blank canvas**.
3. Write body markup in HTML, styling in CSS, and browser interactions in JavaScript. Do not paste a complete HTML document; the theme supplies the document head and WordPress hooks.
4. Preview the layout at desktop, tablet, and mobile widths.
5. Choose the status and save.

## Live preview and security

The preview is isolated in a sandboxed iframe. Network/API connections and form submissions are disabled in the preview for safety, so integrations may not work there. Test those features on a staging site. A pattern-based warning flags some credential-looking strings. Server-side checks block several well-known private-token formats on save, but no pattern scanner can detect every secret. Never paste private keys into client-side code.

## Storage and compatibility

HTML is saved in the WordPress Page's `post_content`, while page CSS, JavaScript, and shell choice are saved in post metadata. The code workspace is the authority for CodeCanvas-managed Pages. Legacy option-stored page views are imported into the editor on the next save. Existing block-based content is displayed as rendered HTML; back up a block-based page before converting it.

## Theme-file editing

Use VS Code to edit shared templates and default assets. Keep a Git repository or website theme for ongoing changes, and test updates on a staging site. The editor does not permit PHP execution from page code.
