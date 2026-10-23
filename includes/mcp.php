<?php
/**
 * CodeCanvas MCP server.
 *
 * Exposes a deliberately small, allow-listed set of developer tools to MCP
 * clients (AI assistants and editors) over HTTP. Access requires an explicit
 * administrator opt-in, a dedicated bearer token, and per-tool capabilities.
 * File writes are limited to allowed theme/plugin projects, PHP is validated
 * before writing, and every call is rate limited and audited.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Protocol revisions understood by this server, newest first. */
function codecanvas_mcp_protocol_versions() {
    return array( '2025-06-18', '2025-03-26', '2024-11-05' );
}

/** Advertised protocol revision. */
function codecanvas_mcp_protocol_version() {
    return '2025-06-18';
}

/** Default, read-only-first configuration. */
function codecanvas_mcp_default_options() {
    return array(
        'enabled'              => false,
        'allow_writes'         => false,
        'allow_content_writes' => false,
        'tools'                => array( 'site_info', 'list_projects', 'list_files', 'read_file', 'search_files', 'list_content', 'get_content', 'get_settings', 'list_menus', 'list_snapshots', 'render_url', 'get_php_error_log' ),
        'projects'             => array( 'theme:' . codecanvas_studio_site_child_slug() ),
        'rate_limit'           => 60,
        'user_id'              => 0,
        'token_hash'           => '',
        'token_hint'           => '',
        'token_created'        => 0,
    );
}

/** Read merged MCP settings. */
function codecanvas_mcp_options() {
    $saved = get_option( 'codecanvas_mcp_options', array() );
    $saved = is_array( $saved ) ? $saved : array();
    $options = array_merge( codecanvas_mcp_default_options(), $saved );
    $options['tools']    = is_array( $options['tools'] ) ? array_values( array_intersect( $options['tools'], array_keys( codecanvas_mcp_tools() ) ) ) : array();
    $options['projects'] = is_array( $options['projects'] ) ? array_values( array_intersect( $options['projects'], array_keys( codecanvas_mcp_available_projects() ) ) ) : array();
    $options['rate_limit'] = max( 1, min( 600, (int) $options['rate_limit'] ) );
    return $options;
}

/** Hash a bearer token without storing the token itself. */
function codecanvas_mcp_hash_token( $token ) {
    return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

/** Current bearer token, if any. */
function codecanvas_mcp_request_token( $request ) {
    $header = $request->get_header( 'authorization' );
    if ( is_string( $header ) && preg_match( '/^\s*Bearer\s+(.+)$/i', $header, $matches ) ) {
        return trim( $matches[1] );
    }
    $fallback = $request->get_header( 'x-codecanvas-token' );
    return is_string( $fallback ) ? trim( $fallback ) : '';
}

/** Generate, store and return a new token. The plaintext is only available once. */
function codecanvas_mcp_generate_token() {
    $options = codecanvas_mcp_options();
    $token   = 'ccmcp_' . wp_generate_password( 40, false, false );
    $options['token_hash']    = codecanvas_mcp_hash_token( $token );
    $options['token_hint']    = substr( $token, 0, 10 ) . '…' . substr( $token, -4 );
    $options['token_created'] = time();
    $options['user_id']       = get_current_user_id();
    update_option( 'codecanvas_mcp_options', $options, false );
    set_transient( 'codecanvas_mcp_new_token_' . get_current_user_id(), $token, 300 );
    return $token;
}

/** Revoke the active token and switch the server off. */
function codecanvas_mcp_revoke_token() {
    $options = codecanvas_mcp_options();
    $options['enabled']       = false;
    $options['token_hash']    = '';
    $options['token_hint']    = '';
    $options['token_created'] = 0;
    $options['user_id']       = 0;
    update_option( 'codecanvas_mcp_options', $options, false );
    delete_transient( 'codecanvas_mcp_last_used' );
}

/** All installed projects that can be granted to an MCP client. */
function codecanvas_mcp_available_projects( $refresh = false ) {
    static $cached = null;
    if ( null !== $cached && ! $refresh ) { return $cached; }
    $projects = array();
    $child    = codecanvas_studio_site_child_slug();
    $framework = codecanvas_studio_parent_theme_slug();
    $themes   = function_exists( 'wp_get_themes' ) ? wp_get_themes() : array();
    foreach ( $themes as $slug => $theme ) {
        if ( 0 === strcasecmp( (string) $slug, $framework ) ) {
            continue; // Framework source is never exposed to MCP clients.
        }
        $projects[ 'theme:' . $slug ] = array(
            'label'  => (string) $theme->get( 'Name' ) ?: $slug,
            'type'   => 'theme',
            'slug'   => (string) $slug,
            'active' => ( get_stylesheet() === $slug || get_template() === $slug ),
            'site'   => ( $child === $slug ),
        );
    }
    $plugin_root = codecanvas_studio_root_path( 'plugins' );
    if ( $plugin_root && is_dir( $plugin_root ) ) {
        $dirs = @scandir( $plugin_root );
        if ( is_array( $dirs ) ) {
            $active_plugins = (array) get_option( 'active_plugins', array() );
            foreach ( $dirs as $dir ) {
                if ( '.' === $dir || '..' === $dir || ! is_dir( $plugin_root . DIRECTORY_SEPARATOR . $dir ) || is_link( $plugin_root . DIRECTORY_SEPARATOR . $dir ) ) {
                    continue;
                }
                if ( 0 === strcasecmp( $dir, $framework ) ) {
                    continue; // CodeCanvas plugin source is never exposed to MCP clients.
                }
                $is_active = false;
                foreach ( $active_plugins as $active ) {
                    if ( 0 === strpos( (string) $active, $dir . '/' ) ) { $is_active = true; break; }
                }
                $projects[ 'plugin:' . $dir ] = array(
                    'label'  => $dir,
                    'type'   => 'plugin',
                    'slug'   => $dir,
                    'active' => $is_active,
                    'site'   => false,
                );
            }
        }
    }
    $cached = $projects;
    return $cached;
}

/** Resolve and validate a project key such as theme:codecanvas-site. */
function codecanvas_mcp_project( $project ) {
    if ( ! is_string( $project ) || ! preg_match( '/^(theme|plugin):([A-Za-z0-9][A-Za-z0-9._-]{0,63})$/', $project, $matches ) ) {
        return new WP_Error( 'cc_mcp_project', __( 'Use a project key from list_projects, for example theme:codecanvas-site.', 'codecanvas' ) );
    }
    $type = strtolower( $matches[1] );
    $slug = $matches[2];
    $root = ( 'theme' === $type ) ? 'themes' : 'plugins';
    if ( 0 === strcasecmp( $slug, codecanvas_studio_framework_slug() ) ) {
        return new WP_Error( 'cc_mcp_framework', __( 'CodeCanvas plugin files are not exposed over MCP. Work in the CodeCanvas Site theme or another project.', 'codecanvas' ) );
    }
    $available = codecanvas_mcp_available_projects();
    $key       = $type . ':' . $slug;
    if ( ! isset( $available[ $key ] ) ) {
        return new WP_Error( 'cc_mcp_missing_project', __( 'That theme or plugin project does not exist on this site.', 'codecanvas' ) );
    }
    return array(
        'key'      => $key,
        'root'     => $root,
        'slug'     => $slug,
        'type'     => $type,
        'path'     => codecanvas_studio_root_path( $root ) . DIRECTORY_SEPARATOR . $slug,
        'relative' => $slug,
        'active'   => (bool) $available[ $key ]['active'],
    );
}

/** True when the project is enabled in Settings. */
function codecanvas_mcp_project_allowed( $project ) {
    $options = codecanvas_mcp_options();
    return is_array( $project ) && isset( $project['key'] ) && in_array( $project['key'], $options['projects'], true );
}

/** Resolve a file inside an allowed project, rejecting traversal and symlinks. */
function codecanvas_mcp_resolve_file( $project_key, $path, $allow_missing = false ) {
    $project = codecanvas_mcp_project( $project_key );
    if ( is_wp_error( $project ) ) { return $project; }
    if ( ! codecanvas_mcp_project_allowed( $project ) ) {
        return new WP_Error( 'cc_mcp_scope', __( 'That project is not enabled for MCP in CodeCanvas → Settings.', 'codecanvas' ) );
    }
    if ( ! is_string( $path ) || '' === trim( $path ) ) {
        return new WP_Error( 'cc_mcp_path', __( 'Provide a file path relative to the project folder.', 'codecanvas' ) );
    }
    $relative = $project['relative'] . '/' . ltrim( wp_normalize_path( $path ), '/' );
    $full     = codecanvas_studio_resolve( $project['root'], $relative, $allow_missing );
    if ( is_wp_error( $full ) ) { return $full; }
    if ( codecanvas_studio_is_framework_target( $project['root'], $relative ) ) {
        return new WP_Error( 'cc_mcp_framework', __( 'CodeCanvas framework files are read-only.', 'codecanvas' ) );
    }
    return array( 'project' => $project, 'relative' => $relative, 'path' => $full );
}

/** Tool catalogue. Descriptions are written for an AI client, not for the UI. */
function codecanvas_mcp_tools() {
    static $tools = null;
    if ( null !== $tools ) { return $tools; }
    $empty = (object) array();
    $tools = array(
        'site_info' => array(
            'title'       => __( 'Site information', 'codecanvas' ),
            'description' => 'Return the WordPress version, site URL, active theme, child theme status and permalink structure. Read-only.',
            'mode'        => 'read',
            'inputSchema' => array( 'type' => 'object', 'properties' => $empty, 'additionalProperties' => false ),
        ),
        'list_projects' => array(
            'title'       => __( 'List projects', 'codecanvas' ),
            'description' => 'List theme and plugin projects, their project keys, which one is active, and whether the administrator enabled it for MCP. Read-only.',
            'mode'        => 'read',
            'inputSchema' => array( 'type' => 'object', 'properties' => $empty, 'additionalProperties' => false ),
        ),
        'list_files' => array(
            'title'       => __( 'List files', 'codecanvas' ),
            'description' => 'List supported source files and folders inside an enabled project. depth > 1 walks subfolders in one call, and offset/next_offset page through long listings. Read-only.',
            'mode'        => 'read',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'project'   => array( 'type' => 'string', 'description' => 'Project key from list_projects.' ),
                    'path'      => array( 'type' => 'string', 'description' => 'Folder path relative to the project. Empty for the project root.' ),
                    'depth'     => array( 'type' => 'integer', 'description' => 'How many folder levels to walk. 1 lists only this folder (default); 2-10 walks subfolders.', 'minimum' => 1, 'maximum' => 10 ),
                    'max_items' => array( 'type' => 'integer', 'description' => 'Maximum entries to return (default 200, maximum 1000).', 'minimum' => 1, 'maximum' => 1000 ),
                    'offset'    => array( 'type' => 'integer', 'description' => 'Entries to skip. Use next_offset from the previous response.', 'minimum' => 0 ),
                ),
                'required'   => array( 'project' ),
            ),
        ),
        'read_file' => array(
            'title'       => __( 'Read file', 'codecanvas' ),
            'description' => 'Read a supported source file (up to 256 KB) and return its content, SHA-256 and modification time. Read-only.',
            'mode'        => 'read',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'project'   => array( 'type' => 'string', 'description' => 'Project key from list_projects.' ),
                    'path'      => array( 'type' => 'string', 'description' => 'File path relative to the project.' ),
                    'max_bytes' => array( 'type' => 'integer', 'description' => 'Optional byte limit (maximum 262144).', 'minimum' => 1, 'maximum' => 262144 ),
                ),
                'required'   => array( 'project', 'path' ),
            ),
        ),
        'search_files' => array(
            'title'       => __( 'Search files', 'codecanvas' ),
            'description' => 'Case-insensitive text search inside an enabled project. Returns file, line number and a short snippet. Read-only.',
            'mode'        => 'read',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'project'     => array( 'type' => 'string' ),
                    'query'       => array( 'type' => 'string', 'description' => 'Search text, at least 2 characters.' ),
                    'path'        => array( 'type' => 'string', 'description' => 'Optional folder path relative to the project.' ),
                    'max_results' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
                    'offset'      => array( 'type' => 'integer', 'description' => 'Matches to skip. Use next_offset from the previous response to continue.', 'minimum' => 0 ),
                ),
                'required'   => array( 'project', 'query' ),
            ),
        ),
        'write_file' => array(
            'title'       => __( 'Write file', 'codecanvas' ),
            'description' => 'Overwrite an existing supported source file after snapshotting the current contents. Prefer patch_file for small edits. PHP is syntax-checked before writing. Requires file writes in Settings.',
            'mode'        => 'write',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'project'         => array( 'type' => 'string' ),
                    'path'            => array( 'type' => 'string' ),
                    'content'         => array( 'type' => 'string', 'description' => 'Full replacement file contents.' ),
                    'expected_sha256' => array( 'type' => 'string', 'description' => 'Optional SHA-256 from read_file. When omitted, the server uses the hash it last returned for this file and still refuses a stale write.' ),
                ),
                'required'   => array( 'project', 'path', 'content' ),
            ),
        ),
        'create_file' => array(
            'title'       => __( 'Create file or folder', 'codecanvas' ),
            'description' => 'Create a new source file or folder inside an enabled project. Never overwrites existing paths. Requires write access in Settings.',
            'mode'        => 'write',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'project' => array( 'type' => 'string' ),
                    'path'    => array( 'type' => 'string', 'description' => 'New path relative to the project.' ),
                    'kind'    => array( 'type' => 'string', 'enum' => array( 'file', 'folder' ) ),
                    'content' => array( 'type' => 'string', 'description' => 'Initial contents for a file. A minimal PHP header is used when omitted for .php files.' ),
                ),
                'required'   => array( 'project', 'path' ),
            ),
        ),
        'delete_file' => array(
            'title'       => __( 'Delete file or empty folder', 'codecanvas' ),
            'description' => 'Delete one supported source file after snapshotting it, or delete an empty folder created by scaffolding. Files can be recovered with list_snapshots and restore_snapshot; folders are not snapshotted. Requires file writes and confirm=true. Protected theme bootstrap files cannot be deleted, and a non-empty folder is refused.',
            'mode'        => 'write',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'project' => array( 'type' => 'string' ),
                    'path'    => array( 'type' => 'string' ),
                    'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true; the caller confirms the deletion.' ),
                ),
                'required'   => array( 'project', 'path', 'confirm' ),
            ),
        ),
        'list_content' => array(
            'title'       => __( 'List content', 'codecanvas' ),
            'description' => 'List posts, pages or every public type with ID, title, status, slug, URL and modification time. Returns total and next_offset so long lists can be paged. Read-only.',
            'mode'        => 'read',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type' => array( 'type' => 'string', 'description' => 'Defaults to page. Use post for blog posts or any for every public type.' ),
                    'status'    => array( 'type' => 'string', 'description' => 'publish, draft, pending, private, trash or any.', 'enum' => array( 'any', 'publish', 'draft', 'pending', 'private', 'trash' ) ),
                    'limit'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
                    'offset'    => array( 'type' => 'integer', 'description' => 'Records to skip. Use next_offset from the previous response.', 'minimum' => 0 ),
                    'search'    => array( 'type' => 'string' ),
                ),
            ),
        ),
        'get_content' => array(
            'title'       => __( 'Get content', 'codecanvas' ),
            'description' => 'Return one post or page with its raw editable content, slug, parent, page template, featured image, categories, tags and custom fields. Read-only.',
            'mode'        => 'read',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
                'required'   => array( 'id' ),
            ),
        ),
        'create_content' => array(
            'title'       => __( 'Create content', 'codecanvas' ),
            'description' => 'Create a page or post with an optional slug, parent, page template, featured image, categories, tags and custom fields. Drafts are the default; the response includes the final permalink. Requires content writes in Settings.',
            'mode'        => 'content',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'title'          => array( 'type' => 'string' ),
                    'content'        => array( 'type' => 'string' ),
                    'post_type'      => array( 'type' => 'string', 'description' => 'Defaults to page.' ),
                    'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ) ),
                    'excerpt'        => array( 'type' => 'string' ),
                    'slug'           => array( 'type' => 'string', 'description' => 'URL slug. WordPress derives one from the title when omitted.' ),
                    'parent'         => array( 'type' => 'integer', 'description' => 'Parent page ID for a hierarchical page.' ),
                    'template'       => array( 'type' => 'string', 'description' => 'Page template file, for example templates/blank-canvas.php.' ),
                    'featured_media' => array( 'type' => 'integer', 'description' => 'Attachment ID to use as the featured image.' ),
                    'categories'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Category names or IDs.' ),
                    'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tag names.' ),
                    'meta'           => array( 'type' => 'object', 'description' => 'Custom fields as key/value pairs.' ),
                ),
                'required'   => array( 'title', 'content' ),
            ),
        ),
        'update_content' => array(
            'title'       => __( 'Update content', 'codecanvas' ),
            'description' => 'Update the title, content, excerpt, status, slug, parent, template, featured image, categories, tags or custom fields of an existing page or post. status: trash moves it to the trash without the confirm flag; use delete_content for permanent deletion. Requires content writes in Settings.',
            'mode'        => 'content',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'id'             => array( 'type' => 'integer', 'minimum' => 1 ),
                    'title'          => array( 'type' => 'string' ),
                    'content'        => array( 'type' => 'string' ),
                    'excerpt'        => array( 'type' => 'string' ),
                    'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private', 'trash' ) ),
                    'slug'           => array( 'type' => 'string' ),
                    'parent'         => array( 'type' => 'integer' ),
                    'template'       => array( 'type' => 'string' ),
                    'featured_media' => array( 'type' => 'integer', 'description' => 'Attachment ID; 0 removes the featured image.' ),
                    'categories'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                    'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                    'meta'           => array( 'type' => 'object' ),
                ),
                'required'   => array( 'id' ),
            ),
        ),
    );
    $tools = apply_filters( 'codecanvas_mcp_tool_catalog', $tools );
    return $tools;
}

/** Tools currently offered to clients, honouring the write switches. */
function codecanvas_mcp_enabled_tools() {
    $options = codecanvas_mcp_options();
    $enabled = array();
    foreach ( codecanvas_mcp_tools() as $name => $tool ) {
        if ( ! in_array( $name, $options['tools'], true ) ) { continue; }
        if ( 'write' === $tool['mode'] && ! $options['allow_writes'] ) { continue; }
        if ( 'content' === $tool['mode'] && ! $options['allow_content_writes'] ) { continue; }
        $enabled[ $name ] = $tool;
    }
    return $enabled;
}

/** Why a catalogue tool is not currently callable. */
function codecanvas_mcp_availability_note( $name, $tool, $options ) {
    if ( ! in_array( $name, $options['tools'], true ) ) {
        return 'Not enabled: an administrator must tick it under CodeCanvas → MCP & Settings → Tools exposed to MCP.';
    }
    if ( 'write' === $tool['mode'] && empty( $options['allow_writes'] ) ) {
        return 'Currently disabled: enable “Allow file writes” in CodeCanvas → MCP & Settings.';
    }
    if ( 'content' === $tool['mode'] && empty( $options['allow_content_writes'] ) ) {
        return 'Currently disabled: enable “Allow content writes” in CodeCanvas → MCP & Settings.';
    }
    return 'Currently disabled in CodeCanvas → MCP & Settings.';
}

/** Scalar argument helper. */
function codecanvas_mcp_arg_string( $args, $key, $required = false, $max = 262144 ) {
    if ( ! isset( $args[ $key ] ) || '' === $args[ $key ] ) {
        /* translators: %s: parameter name. */
        if ( $required ) { return new WP_Error( 'cc_mcp_param', sprintf( __( 'Missing required parameter: %s', 'codecanvas' ), $key ) ); }
        return '';
    }
    /* translators: %s: parameter name. */
    if ( ! is_string( $args[ $key ] ) ) { return new WP_Error( 'cc_mcp_param', sprintf( __( 'Parameter %s must be a string.', 'codecanvas' ), $key ) ); }
    /* translators: %s: parameter name. */
    if ( strlen( $args[ $key ] ) > $max ) { return new WP_Error( 'cc_mcp_param', sprintf( __( 'Parameter %s is too large.', 'codecanvas' ), $key ) ); }
    return $args[ $key ];
}

/** Integer argument helper. */
function codecanvas_mcp_arg_int( $args, $key, $default, $min, $max ) {
    if ( ! isset( $args[ $key ] ) || '' === $args[ $key ] ) { return $default; }
    /* translators: %s: parameter name. */
    if ( ! is_numeric( $args[ $key ] ) ) { return new WP_Error( 'cc_mcp_param', sprintf( __( 'Parameter %s must be a number.', 'codecanvas' ), $key ) ); }
    return max( $min, min( $max, (int) $args[ $key ] ) );
}

/** Tool: site info. */
function codecanvas_mcp_tool_site_info( $args ) {
    $data = array(
        'name'              => get_bloginfo( 'name' ),
        'url'               => home_url( '/' ),
        'wordpress_version' => get_bloginfo( 'version' ),
        'php_version'       => PHP_VERSION,
        'active_theme'      => get_stylesheet(),
        'parent_theme'      => get_template(),
        'codecanvas_site_child_active' => codecanvas_studio_site_child_active(),
        'permalink_structure' => get_option( 'permalink_structure' ),
        'timezone'          => wp_timezone_string(),
        'language'          => get_locale(),
        'projects_enabled'  => count( codecanvas_mcp_options()['projects'] ),
    );
    return array( 'summary' => 'Site information loaded.', 'data' => $data );
}

/** Tool: list projects. */
function codecanvas_mcp_tool_list_projects( $args ) {
    $options  = codecanvas_mcp_options();
    $projects = array();
    foreach ( codecanvas_mcp_available_projects() as $key => $project ) {
        $enabled  = in_array( $key, $options['projects'], true );
        $writable = $enabled && $options['allow_writes'];
        if ( ! $enabled ) {
            $note = sprintf( 'Not exposed. An administrator must tick %s under CodeCanvas → MCP & Settings → Projects exposed to MCP, then save.', $key );
        } elseif ( ! $options['allow_writes'] ) {
            $note = 'Readable. File writes are disabled for MCP; enable “Allow file writes” in CodeCanvas → MCP & Settings.';
        } else {
            $note = 'Readable and writable.';
        }
        $projects[] = array(
            'project'   => $key,
            'label'     => $project['label'],
            'type'      => $project['type'],
            'active'    => (bool) $project['active'],
            'site_theme'=> (bool) $project['site'],
            'mcp_enabled' => $enabled,
            'writable'  => $writable,
            'note'      => $note,
        );
    }
    return array(
        'summary' => sprintf( '%d projects found.', count( $projects ) ),
        'data'    => array(
            'projects'       => $projects,
            'file_writes'    => (bool) $options['allow_writes'],
            'content_writes' => (bool) $options['allow_content_writes'],
            'framework_theme_hidden' => sprintf( 'The %s plugin source is never exposed over MCP. Work in %s.', codecanvas_studio_framework_slug(), 'theme:' . codecanvas_studio_site_child_slug() ),
        ),
    );
}

/** Tool: list files. */
function codecanvas_mcp_tool_list_files( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', false, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    $depth = codecanvas_mcp_arg_int( $args, 'depth', 1, 1, 10 );
    if ( is_wp_error( $depth ) ) { return $depth; }
    $max_items = codecanvas_mcp_arg_int( $args, 'max_items', 200, 1, 1000 );
    if ( is_wp_error( $max_items ) ) { return $max_items; }
    $offset = codecanvas_mcp_arg_int( $args, 'offset', 0, 0, 1000000 );
    if ( is_wp_error( $offset ) ) { return $offset; }
    $project = codecanvas_mcp_scoped_project( $project_key );
    if ( is_wp_error( $project ) ) { return $project; }
    $relative = $project['relative'] . ( '' !== trim( $path, '/' ) ? '/' . trim( wp_normalize_path( $path ), '/' ) : '' );
    $dir      = codecanvas_studio_resolve( $project['root'], $relative );
    if ( is_wp_error( $dir ) ) { return $dir; }
    if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) { return new WP_Error( 'cc_mcp_dir', __( 'That folder is not readable.', 'codecanvas' ) ); }
    $skip = array( '.', '..', '.git', '.svn', '.hg', 'node_modules' );
    $entries = array();
    $scanned = 0;
    $walk = function ( $folder, $prefix, $level ) use ( &$walk, &$entries, &$scanned, $skip, $depth, $project ) {
        if ( $scanned >= 5000 ) { return; }
        $names = @scandir( $folder );
        if ( ! is_array( $names ) ) { return; }
        $dirs  = array();
        $files = array();
        foreach ( $names as $name ) {
            if ( in_array( $name, $skip, true ) ) { continue; }
            $full = $folder . DIRECTORY_SEPARATOR . $name;
            if ( is_link( $full ) ) { continue; }
            if ( is_dir( $full ) ) { $dirs[] = $name; continue; }
            if ( is_file( $full ) && codecanvas_studio_is_allowed_file( $full ) ) { $files[] = $name; }
        }
        sort( $dirs, SORT_NATURAL | SORT_FLAG_CASE );
        sort( $files, SORT_NATURAL | SORT_FLAG_CASE );
        foreach ( $dirs as $name ) {
            $child_rel = ltrim( $prefix . '/' . $name, '/' );
            $entries[] = array(
                'name'  => $name,
                'type'  => 'dir',
                'path'  => codecanvas_mcp_display_path( $child_rel, $project['relative'] ),
                'depth' => $level,
            );
            $scanned++;
            if ( $level < $depth ) { $walk( $folder . DIRECTORY_SEPARATOR . $name, $child_rel, $level + 1 ); }
        }
        foreach ( $files as $name ) {
            $full = $folder . DIRECTORY_SEPARATOR . $name;
            $child_rel = ltrim( $prefix . '/' . $name, '/' );
            $entries[] = array(
                'name'     => $name,
                'type'     => 'file',
                'path'     => codecanvas_mcp_display_path( $child_rel, $project['relative'] ),
                'size'     => (int) filesize( $full ),
                'writable' => wp_is_writable( $full ),
                'depth'    => $level,
            );
            $scanned++;
        }
    };
    $walk( $dir, $relative, 1 );
    $total = count( $entries );
    $items = array_slice( $entries, $offset, $max_items );
    $next  = ( $offset + count( $items ) ) < $total ? $offset + count( $items ) : null;
    $summary = sprintf( '%d entr%s in %s (showing %d from offset %d).', $total, 1 === $total ? 'y' : 'ies', $project['key'] . '/' . ltrim( $path, '/' ), count( $items ), $offset );
    if ( $scanned >= 5000 ) { $summary .= ' The scan stopped at 5000 entries; narrow the path for a complete listing.'; }
    return array(
        'summary' => $summary,
        'data'    => array(
            'project'     => $project['key'],
            'path'        => trim( $path, '/' ),
            'depth'       => $depth,
            'total'       => $total,
            'offset'      => $offset,
            'returned'    => count( $items ),
            'next_offset' => $next,
            'items'       => $items,
        ),
    );
}

/** Tool: read file. */
function codecanvas_mcp_tool_read_file( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', true, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    $max = codecanvas_mcp_arg_int( $args, 'max_bytes', 262144, 1, 262144 );
    if ( is_wp_error( $max ) ) { return $max; }
    $resolved = codecanvas_mcp_resolve_file( $project_key, $path );
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    $file = $resolved['path'];
    if ( ! is_file( $file ) || ! is_readable( $file ) ) { return new WP_Error( 'cc_mcp_read', __( 'That file is not readable.', 'codecanvas' ) ); }
    if ( ! codecanvas_studio_is_allowed_file( $file ) ) { return new WP_Error( 'cc_mcp_type', __( 'This file type is not exposed over MCP.', 'codecanvas' ) ); }
    /* translators: %d: maximum file size in bytes. */
    if ( filesize( $file ) > $max ) { return new WP_Error( 'cc_mcp_size', sprintf( __( 'The file is larger than the %d byte limit.', 'codecanvas' ), $max ) ); }
    $content = file_get_contents( $file );
    if ( false === $content ) { return new WP_Error( 'cc_mcp_read', __( 'Could not read the file.', 'codecanvas' ) ); }
    $sha = hash( 'sha256', $content );
    codecanvas_mcp_remember_hash( $resolved['project']['root'], $resolved['relative'], $sha );
    return array(
        'summary' => sprintf( 'Read %s (%d bytes).', $resolved['relative'], strlen( $content ) ),
        'data'    => array(
            'project' => $resolved['project']['key'],
            'path'    => ltrim( $path, '/' ),
            'content' => $content,
            'sha256'  => $sha,
            'mtime'   => filemtime( $file ),
            'bytes'   => strlen( $content ),
        ),
    );
}

/** Tool: search files. */
function codecanvas_mcp_tool_search_files( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $query = codecanvas_mcp_arg_string( $args, 'query', true, 200 );
    if ( is_wp_error( $query ) ) { return $query; }
    $query = trim( $query );
    if ( strlen( $query ) < 2 ) { return new WP_Error( 'cc_mcp_param', __( 'Enter at least 2 characters to search.', 'codecanvas' ) ); }
    $path = codecanvas_mcp_arg_string( $args, 'path', false, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    $max_results = codecanvas_mcp_arg_int( $args, 'max_results', 30, 1, 50 );
    if ( is_wp_error( $max_results ) ) { return $max_results; }
    $offset = codecanvas_mcp_arg_int( $args, 'offset', 0, 0, 1000000 );
    if ( is_wp_error( $offset ) ) { return $offset; }
    $project = codecanvas_mcp_scoped_project( $project_key );
    if ( is_wp_error( $project ) ) { return $project; }
    $relative = $project['relative'] . ( '' !== trim( $path, '/' ) ? '/' . trim( wp_normalize_path( $path ), '/' ) : '' );
    $base     = codecanvas_studio_resolve( $project['root'], $relative );
    if ( is_wp_error( $base ) ) { return $base; }
    if ( ! is_dir( $base ) || ! is_readable( $base ) ) { return new WP_Error( 'cc_mcp_dir', __( 'That search folder is not readable.', 'codecanvas' ) ); }
    $results = array();
    $scanned = 0;
    $skipped = 0;
    $more    = false;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
                function ( $current ) {
                    if ( $current->isLink() ) { return false; }
                    if ( $current->isDir() && in_array( $current->getFilename(), array( '.git', 'node_modules', '.svn', '.hg' ), true ) ) { return false; }
                    return true;
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ( $iterator as $item ) {
            if ( $scanned >= 500 ) { break; }
            if ( ! $item->isFile() || $item->isLink() || ! codecanvas_studio_is_allowed_file( $item->getPathname() ) || $item->getSize() > 524288 || ! is_readable( $item->getPathname() ) ) { continue; }
            $scanned++;
            $text = @file_get_contents( $item->getPathname() );
            if ( ! is_string( $text ) || false === stripos( $text, $query ) ) { continue; }
            $lines = preg_split( "/\r\n|\n|\r/", $text );
            foreach ( $lines as $line_number => $line ) {
                if ( false === stripos( $line, $query ) ) { continue; }
                if ( $skipped < $offset ) { $skipped++; continue; }
                if ( count( $results ) >= $max_results ) { $more = true; break 2; }
                $full_rel = ltrim( str_replace( codecanvas_studio_root_path( $project['root'] ), '', wp_normalize_path( $item->getPathname() ) ), '/' );
                $results[] = array(
                    'path'    => codecanvas_mcp_display_path( $full_rel, $project['relative'] ),
                    'line'    => $line_number + 1,
                    'snippet' => substr( trim( preg_replace( '/\s+/', ' ', $line ) ), 0, 200 ),
                );
            }
        }
    } catch ( Exception $exception ) {
        return new WP_Error( 'cc_mcp_search', __( 'Search could not complete for this folder.', 'codecanvas' ) );
    }
    $next = $more ? $offset + count( $results ) : null;
    return array(
        'summary' => sprintf( '%d matches in %d scanned files%s.', count( $results ), $scanned, null !== $next ? sprintf( '; more matches exist, continue with offset %d', $next ) : '' ),
        'data'    => array(
            'project'     => $project['key'],
            'query'       => $query,
            'results'     => $results,
            'offset'      => $offset,
            'next_offset' => $next,
            'truncated'   => ( $more || $scanned >= 500 ),
            'scanned_files' => $scanned,
        ),
    );
}

/** Validate PHP before MCP writes executable code. */
function codecanvas_mcp_validate_php( $content ) {
    if ( ! defined( 'TOKEN_PARSE' ) ) { return true; }
    try {
        token_get_all( $content, TOKEN_PARSE );
    } catch ( ParseError $error ) {
        /* translators: %s: PHP parser error message. */
        return new WP_Error( 'cc_mcp_php', sprintf( __( 'PHP syntax error; nothing was written. %s', 'codecanvas' ), $error->getMessage() ) );
    }
    return true;
}

/** Shared pre-write validation. */
function codecanvas_mcp_validate_write( $file, $content, $project ) {
    $options = codecanvas_mcp_options();
    if ( ! $options['allow_writes'] ) {
        return new WP_Error( 'cc_mcp_writes_disabled', __( 'File writes are disabled for MCP. Enable them in CodeCanvas → Settings.', 'codecanvas' ) );
    }
    if ( ! codecanvas_studio_can_access_root( $project['root'], true ) ) {
        return new WP_Error( 'cc_mcp_policy', __( 'WordPress permissions or file-modification policy prevent writing to this project.', 'codecanvas' ) );
    }
    if ( strlen( $content ) > 262144 ) {
        return new WP_Error( 'cc_mcp_size', __( 'MCP writes are limited to 256 KB per file.', 'codecanvas' ) );
    }
    if ( ! codecanvas_studio_is_allowed_file( $file ) ) {
        return new WP_Error( 'cc_mcp_type', __( 'This file type cannot be written over MCP.', 'codecanvas' ) );
    }
    $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
    if ( in_array( $ext, array( 'php', 'phtml' ), true ) ) {
        $valid = codecanvas_mcp_validate_php( $content );
        if ( is_wp_error( $valid ) ) { return $valid; }
    }
    if ( in_array( $ext, array( 'html', 'htm', 'css', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'svg' ), true ) && codecanvas_contains_private_secret_pattern( $content ) ) {
        return new WP_Error( 'cc_mcp_secret', __( 'The content resembles a private credential. Keep secrets server-side; nothing was written.', 'codecanvas' ) );
    }
    return true;
}

/** Tool: write file. */
function codecanvas_mcp_tool_write_file( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', true, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) ) { return new WP_Error( 'cc_mcp_param', __( 'Missing required parameter: content', 'codecanvas' ) ); }
    $expected = codecanvas_mcp_arg_string( $args, 'expected_sha256', false, 64 );
    if ( is_wp_error( $expected ) ) { return $expected; }
    $resolved = codecanvas_mcp_resolve_file( $project_key, $path );
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    if ( ! is_file( $resolved['path'] ) ) {
        return new WP_Error( 'cc_mcp_missing', __( 'That file does not exist. Use create_file for new files.', 'codecanvas' ) );
    }
    $resolved['display'] = codecanvas_mcp_display_path( $resolved['relative'], $resolved['project']['relative'] );
    $result = codecanvas_mcp_commit_file( $resolved, $args['content'], 'mcp-save', $expected );
    if ( is_wp_error( $result ) ) { return $result; }
    return array(
        'summary' => sprintf( 'Saved %s (%d bytes). A pre-change snapshot was stored.', $resolved['display'], $result['bytes'] ),
        'data'    => $result,
    );
}

/** Create missing parent folders for a new file, without leaving the project. */
function codecanvas_mcp_prepare_parent_dirs( $project_key, $path ) {
    $project = codecanvas_mcp_project( $project_key );
    if ( is_wp_error( $project ) ) { return $project; }
    if ( ! codecanvas_mcp_project_allowed( $project ) ) { return new WP_Error( 'cc_mcp_scope', __( 'That project is not enabled for MCP.', 'codecanvas' ) ); }
    if ( ! codecanvas_studio_can_access_root( $project['root'], true ) ) {
        return new WP_Error( 'cc_mcp_policy', __( 'File changes are not permitted by WordPress or hosting policy.', 'codecanvas' ) );
    }
    $normalized = ltrim( wp_normalize_path( (string) $path ), '/' );
    if ( false !== strpos( $normalized, '..' ) || false !== strpos( $normalized, "\0" ) ) {
        return new WP_Error( 'cc_mcp_path', __( 'Invalid file path.', 'codecanvas' ) );
    }
    $segments = array_values( array_filter( explode( '/', $normalized ), 'strlen' ) );
    array_pop( $segments ); // The file name itself.
    $current  = codecanvas_studio_root_path( $project['root'] ) . DIRECTORY_SEPARATOR . $project['relative'];
    foreach ( $segments as $segment ) {
        if ( '.' === $segment || '..' === $segment ) { return new WP_Error( 'cc_mcp_path', __( 'Invalid folder segment.', 'codecanvas' ) ); }
        if ( in_array( strtolower( $segment ), codecanvas_studio_blocked_names(), true ) ) {
            return new WP_Error( 'cc_mcp_name', __( 'That folder name is reserved and cannot be created over MCP.', 'codecanvas' ) );
        }
        $current .= DIRECTORY_SEPARATOR . $segment;
        if ( is_link( $current ) ) { return new WP_Error( 'cc_mcp_path', __( 'Symlinked folders cannot be used.', 'codecanvas' ) ); }
        if ( ! is_dir( $current ) && ! wp_mkdir_p( $current ) ) {
            return new WP_Error( 'cc_mcp_mkdir', __( 'Could not create the parent folder.', 'codecanvas' ) );
        }
    }
    return true;
}

/** Tool: create file or folder. */
function codecanvas_mcp_tool_create_file( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', true, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    $kind = codecanvas_mcp_arg_string( $args, 'kind', false, 10 );
    if ( is_wp_error( $kind ) ) { return $kind; }
    $kind = $kind ? $kind : 'file';
    if ( ! in_array( $kind, array( 'file', 'folder' ), true ) ) { return new WP_Error( 'cc_mcp_param', __( 'kind must be file or folder.', 'codecanvas' ) ); }
    $content = ( isset( $args['content'] ) && is_string( $args['content'] ) ) ? $args['content'] : '';
    $prepared = codecanvas_mcp_prepare_parent_dirs( $project_key, $path );
    if ( is_wp_error( $prepared ) ) { return $prepared; }
    $resolved = codecanvas_mcp_resolve_file( $project_key, $path, true );
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    $options = codecanvas_mcp_options();
    if ( ! $options['allow_writes'] ) { return new WP_Error( 'cc_mcp_writes_disabled', __( 'File writes are disabled for MCP. Enable them in CodeCanvas → Settings.', 'codecanvas' ) ); }
    if ( ! codecanvas_studio_can_access_root( $resolved['project']['root'], true ) ) { return new WP_Error( 'cc_mcp_policy', __( 'File changes are not permitted by WordPress or hosting policy.', 'codecanvas' ) ); }
    if ( file_exists( $resolved['path'] ) ) { return new WP_Error( 'cc_mcp_exists', __( 'That path already exists; nothing was overwritten.', 'codecanvas' ) ); }
    $basename = basename( $resolved['path'] );
    if ( in_array( strtolower( $basename ), codecanvas_studio_blocked_names(), true ) || 0 === strpos( $basename, '.' ) || in_array( $basename, array( '.git', 'node_modules', '.svn', '.hg' ), true ) ) {
        return new WP_Error( 'cc_mcp_name', __( 'That file or folder name is reserved and cannot be created over MCP.', 'codecanvas' ) );
    }
    if ( 'folder' === $kind ) {
        if ( ! wp_mkdir_p( $resolved['path'] ) ) { return new WP_Error( 'cc_mcp_mkdir', __( 'Could not create the folder.', 'codecanvas' ) ); }
        return array( 'summary' => sprintf( 'Created folder %s.', $resolved['relative'] ), 'data' => array( 'project' => $resolved['project']['key'], 'path' => ltrim( $path, '/' ), 'kind' => 'folder' ) );
    }
    if ( '' === $content && 'php' === strtolower( pathinfo( $resolved['path'], PATHINFO_EXTENSION ) ) ) {
        $content = "<?php\n// Created with CodeCanvas MCP.\n";
    }
    $valid = codecanvas_mcp_validate_write( $resolved['path'], $content, $resolved['project'] );
    if ( is_wp_error( $valid ) ) { return $valid; }
    $parent = dirname( $resolved['path'] );
    if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) { return new WP_Error( 'cc_mcp_mkdir', __( 'Could not create the parent folder.', 'codecanvas' ) ); }
    if ( false === @file_put_contents( $resolved['path'], $content, LOCK_EX ) ) { return new WP_Error( 'cc_mcp_write', __( 'Could not create the file.', 'codecanvas' ) ); }
    $sha = hash( 'sha256', $content );
    codecanvas_mcp_remember_hash( $resolved['project']['root'], $resolved['relative'], $sha );
    return array( 'summary' => sprintf( 'Created %s (%d bytes).', $resolved['relative'], strlen( $content ) ), 'data' => array( 'project' => $resolved['project']['key'], 'path' => ltrim( $path, '/' ), 'kind' => 'file', 'bytes' => strlen( $content ), 'sha256' => $sha ) );
}

/** Tool: delete file. */
function codecanvas_mcp_tool_delete_file( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', true, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    // Accept "folder/" for folder deletion; the resolver rejects empty segments.
    $path = rtrim( trim( $path ), '/' );
    if ( '' === $path ) { return new WP_Error( 'cc_mcp_param', __( 'Provide a file or folder path.', 'codecanvas' ) ); }
    if ( empty( $args['confirm'] ) || true !== $args['confirm'] ) { return new WP_Error( 'cc_mcp_confirm', __( 'Refusing to delete: pass confirm: true.', 'codecanvas' ) ); }
    $resolved = codecanvas_mcp_resolve_file( $project_key, $path );
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    $options = codecanvas_mcp_options();
    if ( ! $options['allow_writes'] ) { return new WP_Error( 'cc_mcp_writes_disabled', __( 'File writes are disabled for MCP. Enable them in CodeCanvas → Settings.', 'codecanvas' ) ); }
    if ( ! codecanvas_studio_can_access_root( $resolved['project']['root'], true ) ) { return new WP_Error( 'cc_mcp_policy', __( 'File changes are not permitted by WordPress or hosting policy.', 'codecanvas' ) ); }
    $relative_in_project = ( 0 === strpos( $resolved['relative'], $resolved['project']['relative'] . '/' ) ) ? substr( $resolved['relative'], strlen( $resolved['project']['relative'] ) + 1 ) : $resolved['relative'];

    // Folders: remove only when empty, so a scaffolding cleanup cannot wipe files
    // that were not individually reviewed and snapshotted.
    if ( is_dir( $resolved['path'] ) ) {
        if ( '' === $relative_in_project || is_link( $resolved['path'] ) ) { return new WP_Error( 'cc_mcp_delete', __( 'The project root cannot be deleted over MCP.', 'codecanvas' ) ); }
        if ( ! wp_is_writable( $resolved['path'] ) || ! wp_is_writable( dirname( $resolved['path'] ) ) ) { return new WP_Error( 'cc_mcp_delete', __( 'That folder is not writable.', 'codecanvas' ) ); }
        $children = @scandir( $resolved['path'] );
        if ( ! is_array( $children ) ) { return new WP_Error( 'cc_mcp_delete', __( 'The folder could not be read.', 'codecanvas' ) ); }
        $children = array_values( array_diff( $children, array( '.', '..' ) ) );
        if ( ! empty( $children ) ) {
            /* translators: %d: number of items in the folder. */
            return new WP_Error( 'cc_mcp_not_empty', sprintf( __( 'That folder still contains %d item(s). Delete or move them first; only empty folders can be removed over MCP.', 'codecanvas' ), count( $children ) ) );
        }
        if ( ! codecanvas_rmdir( $resolved['path'] ) ) { return new WP_Error( 'cc_mcp_delete', __( 'Could not delete the empty folder.', 'codecanvas' ) ); }
        $display = codecanvas_mcp_display_path( $resolved['relative'], $resolved['project']['relative'] );
        return array(
            'summary' => sprintf( 'Deleted empty folder %s. Folders are not snapshotted; recreate it with create_file if needed.', $display ),
            'data'    => array( 'project' => $resolved['project']['key'], 'path' => ltrim( $path, '/' ), 'deleted' => true, 'type' => 'folder' ),
        );
    }

    if ( ! is_file( $resolved['path'] ) || ! codecanvas_studio_is_allowed_file( $resolved['path'] ) || ! wp_is_writable( $resolved['path'] ) || ! wp_is_writable( dirname( $resolved['path'] ) ) ) { return new WP_Error( 'cc_mcp_delete', __( 'Only supported source files and empty folders in writable locations can be deleted over MCP.', 'codecanvas' ) ); }
    if ( in_array( $relative_in_project, array( 'style.css', 'functions.php', 'index.php' ), true ) ) {
        return new WP_Error( 'cc_mcp_protected', __( 'Refusing to delete a theme bootstrap file (style.css, functions.php or index.php). Remove or replace it deliberately in Code Studio.', 'codecanvas' ) );
    }
    if ( filesize( $resolved['path'] ) > 262144 ) { return new WP_Error( 'cc_mcp_size', __( 'Files over 256 KB cannot be deleted over MCP because a snapshot cannot be stored safely.', 'codecanvas' ) ); }
    $content = file_get_contents( $resolved['path'] );
    if ( ! is_string( $content ) ) { return new WP_Error( 'cc_mcp_read', __( 'Could not snapshot the file before deletion.', 'codecanvas' ) ); }
    codecanvas_studio_make_backup( $resolved['project']['root'], $resolved['relative'], $content, 'mcp-delete' );
    wp_delete_file( $resolved['path'] );
    if ( file_exists( $resolved['path'] ) ) { return new WP_Error( 'cc_mcp_delete', __( 'Could not delete the file.', 'codecanvas' ) ); }
    $display = codecanvas_mcp_display_path( $resolved['relative'], $resolved['project']['relative'] );
    codecanvas_mcp_forget_hash( $resolved['project']['root'], $resolved['relative'] );
    return array(
        'summary' => sprintf( 'Deleted %s. Undo with restore_snapshot on the same path (see list_snapshots).', $display ),
        'data'    => array( 'project' => $resolved['project']['key'], 'path' => ltrim( $path, '/' ), 'deleted' => true, 'restore_with' => 'restore_snapshot index 0' ),
    );
}

/** Allowed post types for content tools. */
function codecanvas_mcp_content_post_types() {
    $types = get_post_types( array( 'show_ui' => true ), 'objects' );
    $allowed = array();
    foreach ( $types as $type => $object ) {
        if ( in_array( $type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) { continue; }
        $allowed[ $type ] = $object->labels->singular_name;
    }
    return $allowed;
}

/** Tool: list content. */
function codecanvas_mcp_tool_list_content( $args ) {
    $args = is_array( $args ) ? $args : array();
    $post_type = codecanvas_mcp_arg_string( $args, 'post_type', false, 40 );
    if ( is_wp_error( $post_type ) ) { return $post_type; }
    $post_type = $post_type ? $post_type : 'page';
    $types     = codecanvas_mcp_content_post_types();
    if ( 'any' !== $post_type && ! in_array( $post_type, array_keys( $types ), true ) ) { return new WP_Error( 'cc_mcp_type', __( 'Unsupported post type.', 'codecanvas' ) ); }
    $status = codecanvas_mcp_arg_string( $args, 'status', false, 20 );
    if ( is_wp_error( $status ) ) { return $status; }
    $status = in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ), true ) ? $status : 'any';
    $limit = codecanvas_mcp_arg_int( $args, 'limit', 20, 1, 100 );
    if ( is_wp_error( $limit ) ) { return $limit; }
    $offset = codecanvas_mcp_arg_int( $args, 'offset', 0, 0, 1000000 );
    if ( is_wp_error( $offset ) ) { return $offset; }
    $search = codecanvas_mcp_arg_string( $args, 'search', false, 200 );
    if ( is_wp_error( $search ) ) { return $search; }
    $query = array(
        'post_type'           => ( 'any' === $post_type ) ? array_keys( $types ) : $post_type,
        'post_status'         => $status,
        'posts_per_page'      => $limit,
        'offset'              => $offset,
        'orderby'             => 'modified',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => false,
    );
    if ( '' !== $search ) { $query['s'] = $search; }
    $result = new WP_Query( $query );
    $items  = array();
    foreach ( $result->posts as $post ) {
        if ( ! current_user_can( 'read_post', $post->ID ) ) { continue; }
        $items[] = array(
            'id'       => $post->ID,
            'title'    => get_the_title( $post ),
            'status'   => $post->post_status,
            'type'     => $post->post_type,
            'slug'     => $post->post_name,
            'url'      => get_permalink( $post ),
            'modified' => $post->post_modified_gmt,
        );
    }
    $total = (int) $result->found_posts;
    $next  = ( $offset + count( $items ) ) < $total ? $offset + count( $items ) : null;
    return array(
        'summary' => sprintf( '%d of %d %s record(s).', count( $items ), $total, 'any' === $post_type ? 'content' : $post_type ),
        'data'    => array(
            'post_type'   => $post_type,
            'status'      => $status,
            'total'       => $total,
            'offset'      => $offset,
            'returned'    => count( $items ),
            'next_offset' => $next,
            'items'       => $items,
        ),
    );
}

/** Tool: get content. */
function codecanvas_mcp_tool_get_content( $args ) {
    $args = is_array( $args ) ? $args : array();
    $id = codecanvas_mcp_arg_int( $args, 'id', 0, 1, PHP_INT_MAX );
    if ( is_wp_error( $id ) || ! $id ) { return is_wp_error( $id ) ? $id : new WP_Error( 'cc_mcp_param', __( 'Provide a valid post ID.', 'codecanvas' ) ); }
    $post = get_post( $id );
    if ( ! $post || ! in_array( $post->post_type, array_keys( codecanvas_mcp_content_post_types() ), true ) ) { return new WP_Error( 'cc_mcp_content', __( 'That content record does not exist.', 'codecanvas' ) ); }
    if ( ! current_user_can( 'read_post', $post->ID ) ) { return new WP_Error( 'cc_mcp_cap', __( 'You do not have permission to read that content.', 'codecanvas' ) ); }
    return array(
        'summary' => sprintf( 'Loaded %s #%d.', $post->post_type, $post->ID ),
        'data'    => codecanvas_mcp_content_payload( $post ),
    );
}

/** Guard for content writes. */
function codecanvas_mcp_content_write_allowed() {
    $options = codecanvas_mcp_options();
    if ( ! $options['allow_content_writes'] ) {
        return new WP_Error( 'cc_mcp_content_disabled', __( 'Content writes are disabled for MCP. Enable them in CodeCanvas → Settings.', 'codecanvas' ) );
    }
    return true;
}

/** Public custom fields for one post, without protected internals. */
function codecanvas_mcp_content_meta( $post_id ) {
    $meta = get_post_meta( $post_id );
    $out  = array();
    if ( ! is_array( $meta ) ) { return $out; }
    foreach ( $meta as $key => $values ) {
        if ( 0 === strpos( (string) $key, '_' ) ) { continue; }
        $values = is_array( $values ) ? $values : array( $values );
        $out[ $key ] = ( 1 === count( $values ) ) ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
    }
    return $out;
}

/** Terms for one post, grouped by taxonomy. */
function codecanvas_mcp_content_terms( $post_id, $type ) {
    $out = array();
    $taxonomies = get_object_taxonomies( $type );
    foreach ( (array) $taxonomies as $taxonomy ) {
        $tax = get_taxonomy( $taxonomy );
        if ( ! $tax || empty( $tax->show_ui ) ) { continue; }
        $terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'all' ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) { continue; }
        $rows = array();
        foreach ( $terms as $term ) {
            $rows[] = array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug );
        }
        $out[ $taxonomy ] = $rows;
    }
    return $out;
}

/** Full payload for one post or page. */
function codecanvas_mcp_content_payload( $post ) {
    $post     = get_post( $post );
    $thumb_id = (int) get_post_thumbnail_id( $post->ID );
    $parent   = (int) $post->post_parent;
    $terms    = codecanvas_mcp_content_terms( $post->ID, $post->post_type );
    return array(
        'id'             => (int) $post->ID,
        'title'          => get_the_title( $post ),
        'content'        => $post->post_content,
        'excerpt'        => $post->post_excerpt,
        'status'         => $post->post_status,
        'type'           => $post->post_type,
        'slug'           => $post->post_name,
        'url'            => get_permalink( $post ),
        'parent'         => $parent ? array( 'id' => $parent, 'title' => get_the_title( $parent ) ) : null,
        'menu_order'     => (int) $post->menu_order,
        'template'       => (string) get_page_template_slug( $post ),
        'featured_media' => $thumb_id ? array( 'id' => $thumb_id, 'url' => wp_get_attachment_url( $thumb_id ), 'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ) : null,
        'categories'     => isset( $terms['category'] ) ? $terms['category'] : array(),
        'tags'           => isset( $terms['post_tag'] ) ? $terms['post_tag'] : array(),
        'taxonomies'     => $terms,
        'meta'           => codecanvas_mcp_content_meta( $post->ID ),
        'author'         => get_the_author_meta( 'display_name', (int) $post->post_author ),
        'modified'       => $post->post_modified_gmt,
        'edit_url'       => admin_url( 'post.php?post=' . (int) $post->ID . '&action=edit' ),
    );
}

/** Apply template, featured image, terms and custom fields to a post. */
function codecanvas_mcp_apply_content_fields( $post_id, $args ) {
    $post = get_post( $post_id );
    if ( ! $post ) { return new WP_Error( 'cc_mcp_content', __( 'That content record does not exist.', 'codecanvas' ) ); }
    if ( array_key_exists( 'template', $args ) ) {
        $template = is_string( $args['template'] ) ? $args['template'] : '';
        if ( '' !== $template ) {
            $allowed = wp_get_theme()->get_page_templates( null, $post->post_type );
            if ( ! is_array( $allowed ) || ! isset( $allowed[ $template ] ) ) {
                /* translators: %s: comma-separated page template names. */
                return new WP_Error( 'cc_mcp_template', sprintf( __( 'Unknown page template. Available templates: %s', 'codecanvas' ), $allowed ? implode( ', ', array_keys( $allowed ) ) : 'none' ) );
            }
            update_post_meta( $post_id, '_wp_page_template', $template );
        } else {
            delete_post_meta( $post_id, '_wp_page_template' );
        }
    }
    if ( array_key_exists( 'featured_media', $args ) ) {
        $media = absint( $args['featured_media'] );
        if ( $media && 'attachment' !== get_post_type( $media ) ) {
            return new WP_Error( 'cc_mcp_param', __( 'featured_media must be an attachment ID; use upload_media or list the media library first.', 'codecanvas' ) );
        }
        if ( $media ) { set_post_thumbnail( $post_id, $media ); } else { delete_post_thumbnail( $post_id ); }
    }
    foreach ( array( 'categories' => 'category', 'tags' => 'post_tag' ) as $arg => $taxonomy ) {
        if ( ! array_key_exists( $arg, $args ) ) { continue; }
        /* translators: %s: argument name. */
        if ( ! is_array( $args[ $arg ] ) ) { return new WP_Error( 'cc_mcp_param', sprintf( __( '%s must be an array of names or IDs.', 'codecanvas' ), $arg ) ); }
        $values = array();
        foreach ( $args[ $arg ] as $value ) {
            if ( is_int( $value ) || is_string( $value ) ) { $values[] = sanitize_text_field( (string) $value ); }
        }
        if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) { continue; }
        $set = wp_set_object_terms( $post_id, $values, $taxonomy, false );
        if ( is_wp_error( $set ) ) { return $set; }
    }
    if ( array_key_exists( 'meta', $args ) ) {
        if ( null !== $args['meta'] && ! is_array( $args['meta'] ) ) { return new WP_Error( 'cc_mcp_param', __( 'meta must be an object of key/value pairs.', 'codecanvas' ) ); }
        foreach ( (array) $args['meta'] as $key => $value ) {
            $key = (string) $key;
            if ( ! preg_match( '/^[A-Za-z0-9._-]{1,100}$/', $key ) ) {
                /* translators: %s: custom field key. */
                return new WP_Error( 'cc_mcp_param', sprintf( __( 'Custom field name %s is not allowed. Use letters, numbers, dots, dashes or underscores.', 'codecanvas' ), $key ) );
            }
            if ( is_array( $value ) || is_object( $value ) ) {
                /* translators: %s: custom field key. */
                return new WP_Error( 'cc_mcp_param', sprintf( __( 'Custom field %s must be a string, number or boolean.', 'codecanvas' ), $key ) );
            }
            $value = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value;
            /* translators: %s: custom field key. */
            if ( strlen( $value ) > 10240 ) { return new WP_Error( 'cc_mcp_param', sprintf( __( 'Custom field %s is larger than 10 KB.', 'codecanvas' ), $key ) ); }
            if ( '' === $value ) { delete_post_meta( $post_id, $key ); } else { update_post_meta( $post_id, $key, wp_slash( $value ) ); }
        }
    }
    return true;
}

/** Tool: create content. */
function codecanvas_mcp_tool_create_content( $args ) {
    $args = is_array( $args ) ? $args : array();
    $allowed = codecanvas_mcp_content_write_allowed();
    if ( is_wp_error( $allowed ) ) { return $allowed; }
    $title = codecanvas_mcp_arg_string( $args, 'title', true, 300 );
    if ( is_wp_error( $title ) ) { return $title; }
    if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) ) { return new WP_Error( 'cc_mcp_param', __( 'Missing required parameter: content', 'codecanvas' ) ); }
    $post_type = codecanvas_mcp_arg_string( $args, 'post_type', false, 40 );
    if ( is_wp_error( $post_type ) ) { return $post_type; }
    $post_type = $post_type ? $post_type : 'page';
    $types = codecanvas_mcp_content_post_types();
    if ( ! isset( $types[ $post_type ] ) ) { return new WP_Error( 'cc_mcp_type', __( 'Unsupported post type.', 'codecanvas' ) ); }
    $type_object = get_post_type_object( $post_type );
    $create_cap  = ( $type_object && ! empty( $type_object->cap->edit_posts ) ) ? $type_object->cap->edit_posts : 'edit_posts';
    if ( ! current_user_can( $create_cap ) ) {
        return new WP_Error( 'cc_mcp_cap', __( 'This account cannot create that content type.', 'codecanvas' ) );
    }
    $status = codecanvas_mcp_arg_string( $args, 'status', false, 20 );
    if ( is_wp_error( $status ) ) { return $status; }
    $status = $status ? $status : 'draft';
    if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private' ), true ) ) { return new WP_Error( 'cc_mcp_param', __( 'Unsupported content status.', 'codecanvas' ) ); }
    if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) { return new WP_Error( 'cc_mcp_cap', __( 'This account cannot publish content over MCP.', 'codecanvas' ) ); }
    $excerpt = codecanvas_mcp_arg_string( $args, 'excerpt', false, 2000 );
    if ( is_wp_error( $excerpt ) ) { return $excerpt; }
    $slug = codecanvas_mcp_arg_string( $args, 'slug', false, 200 );
    if ( is_wp_error( $slug ) ) { return $slug; }
    $parent = codecanvas_mcp_arg_int( $args, 'parent', 0, 0, PHP_INT_MAX );
    if ( is_wp_error( $parent ) ) { return $parent; }
    if ( $parent && 'page' !== get_post_type( $parent ) ) { return new WP_Error( 'cc_mcp_param', __( 'parent must be an existing page ID.', 'codecanvas' ) ); }
    $postarr = array(
        'post_title'   => $title,
        'post_content' => $args['content'],
        'post_excerpt' => $excerpt,
        'post_type'    => $post_type,
        'post_status'  => $status,
        'post_parent'  => $parent,
    );
    if ( '' !== $slug ) { $postarr['post_name'] = sanitize_title( $slug ); }
    $post_id = wp_insert_post( wp_slash( $postarr ), true );
    if ( is_wp_error( $post_id ) ) { return $post_id; }
    $fields = codecanvas_mcp_apply_content_fields( $post_id, $args );
    if ( is_wp_error( $fields ) ) {
        // The record exists; report the field failure instead of hiding it.
        /* translators: %1$s: post type, %2$d: post ID, %3$s: error message. */
        return new WP_Error( 'cc_mcp_fields', sprintf( __( 'Created %1$s #%2$d but could not apply extra fields: %3$s', 'codecanvas' ), $post_type, $post_id, $fields->get_error_message() ) );
    }
    $payload = codecanvas_mcp_content_payload( $post_id );
    return array(
        'summary' => sprintf( 'Created %s #%d as %s at %s.', $post_type, $post_id, $status, $payload['url'] ),
        'data'    => array_merge( $payload, array( 'permalink' => $payload['url'] ) ),
    );
}

/** Tool: update content. */
function codecanvas_mcp_tool_update_content( $args ) {
    $args = is_array( $args ) ? $args : array();
    $allowed = codecanvas_mcp_content_write_allowed();
    if ( is_wp_error( $allowed ) ) { return $allowed; }
    $id = codecanvas_mcp_arg_int( $args, 'id', 0, 1, PHP_INT_MAX );
    if ( is_wp_error( $id ) || ! $id ) { return is_wp_error( $id ) ? $id : new WP_Error( 'cc_mcp_param', __( 'Provide a valid post ID.', 'codecanvas' ) ); }
    $post = get_post( $id );
    if ( ! $post || ! in_array( $post->post_type, array_keys( codecanvas_mcp_content_post_types() ), true ) ) { return new WP_Error( 'cc_mcp_content', __( 'That content record does not exist.', 'codecanvas' ) ); }
    if ( ! current_user_can( 'edit_post', $post->ID ) ) { return new WP_Error( 'cc_mcp_cap', __( 'You do not have permission to edit that content.', 'codecanvas' ) ); }
    $changes = array( 'ID' => $post->ID );
    foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' ) as $arg => $field ) {
        if ( array_key_exists( $arg, $args ) ) {
            /* translators: %s: argument name. */
            if ( ! is_string( $args[ $arg ] ) ) { return new WP_Error( 'cc_mcp_param', sprintf( __( '%s must be a string.', 'codecanvas' ), $arg ) ); }
            $changes[ $field ] = $args[ $arg ];
        }
    }
    if ( array_key_exists( 'slug', $args ) ) {
        if ( ! is_string( $args['slug'] ) ) { return new WP_Error( 'cc_mcp_param', __( 'slug must be a string.', 'codecanvas' ) ); }
        $changes['post_name'] = sanitize_title( $args['slug'] );
    }
    if ( array_key_exists( 'parent', $args ) ) {
        $parent = codecanvas_mcp_arg_int( $args, 'parent', 0, 0, PHP_INT_MAX );
        if ( is_wp_error( $parent ) ) { return $parent; }
        if ( $parent && 'page' !== get_post_type( $parent ) ) { return new WP_Error( 'cc_mcp_param', __( 'parent must be an existing page ID.', 'codecanvas' ) ); }
        if ( (int) $parent === (int) $post->ID ) { return new WP_Error( 'cc_mcp_param', __( 'A page cannot be its own parent.', 'codecanvas' ) ); }
        $changes['post_parent'] = $parent;
    }
    if ( isset( $args['status'] ) ) {
        $status = is_string( $args['status'] ) ? $args['status'] : '';
        if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private', 'trash' ), true ) ) { return new WP_Error( 'cc_mcp_param', __( 'Unsupported content status.', 'codecanvas' ) ); }
        if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) { return new WP_Error( 'cc_mcp_cap', __( 'This account cannot publish content over MCP.', 'codecanvas' ) ); }
        if ( 'trash' === $status && ! current_user_can( 'delete_post', $post->ID ) ) { return new WP_Error( 'cc_mcp_cap', __( 'This account cannot trash that content.', 'codecanvas' ) ); }
        $changes['post_status'] = $status;
    }
    $result = wp_update_post( wp_slash( $changes ), true );
    if ( is_wp_error( $result ) ) { return $result; }
    $fields = codecanvas_mcp_apply_content_fields( $post->ID, $args );
    if ( is_wp_error( $fields ) ) { return $fields; }
    $payload = codecanvas_mcp_content_payload( $post->ID );
    return array(
        'summary' => sprintf( 'Updated %s #%d (status %s).', $post->post_type, $post->ID, $payload['status'] ),
        'data'    => $payload,
    );
}

/** Dispatch one tool call. */
function codecanvas_mcp_dispatch_tool( $name, $args ) {
    $tools = codecanvas_mcp_tools();
    if ( ! isset( $tools[ $name ] ) ) {
        /* translators: %s: tool name. */
        return new WP_Error( 'cc_mcp_tool', sprintf( __( 'Unknown tool: %s', 'codecanvas' ), $name ) );
    }
    if ( ! isset( codecanvas_mcp_enabled_tools()[ $name ] ) ) {
        /* translators: %s: tool name. */
        return new WP_Error( 'cc_mcp_tool_disabled', sprintf( __( 'Tool %s is not enabled for MCP. Enable it in CodeCanvas → Settings.', 'codecanvas' ), $name ) );
    }
    if ( ! is_array( $args ) ) { $args = array(); }
    $callback = 'codecanvas_mcp_tool_' . $name;
    if ( ! function_exists( $callback ) ) {
        return new WP_Error( 'cc_mcp_tool', __( 'That tool is not implemented.', 'codecanvas' ) );
    }
    return call_user_func( $callback, $args );
}

/** Compact, content-free audit summary of tool arguments. */
function codecanvas_mcp_audit_summary( $name, $args ) {
    $parts = array();
    foreach ( array( 'project', 'path', 'query', 'post_type', 'status', 'id' ) as $key ) {
        if ( isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) ) {
            $parts[] = $key . '=' . substr( (string) $args[ $key ], 0, 80 );
        }
    }
    if ( isset( $args['content'] ) && is_string( $args['content'] ) ) { $parts[] = 'content_bytes=' . strlen( $args['content'] ); }
    return $name . ( $parts ? ' (' . implode( ', ', $parts ) . ')' : '' );
}

/** Append a bounded audit entry. Never stores file contents or tokens. */
function codecanvas_mcp_audit( $tool, $args, $ok, $message, $duration_ms ) {
    $entry = array(
        'time'    => time(),
        'tool'    => sanitize_key( $tool ),
        'summary' => sanitize_text_field( codecanvas_mcp_audit_summary( $tool, is_array( $args ) ? $args : array() ) ),
        'ok'      => (bool) $ok,
        'message' => sanitize_text_field( substr( (string) $message, 0, 300 ) ),
        'ms'      => (int) $duration_ms,
    );
    $log = get_option( 'codecanvas_mcp_audit', array() );
    $log = is_array( $log ) ? $log : array();
    array_unshift( $log, $entry );
    $log = array_slice( $log, 0, 50 );
    update_option( 'codecanvas_mcp_audit', $log, false );
    update_option( 'codecanvas_mcp_last_used', time(), false );
}

/** Authorization used by the REST route: bearer token, mapped administrator, rate limit. */
function codecanvas_mcp_authorize( $request ) {
    $options = codecanvas_mcp_options();
    if ( ! $options['enabled'] ) {
        return new WP_Error( 'cc_mcp_disabled', __( 'The CodeCanvas MCP server is disabled. Enable it in CodeCanvas → Settings.', 'codecanvas' ), array( 'status' => 503 ) );
    }
    $token = codecanvas_mcp_request_token( $request );
    if ( '' === $token ) {
        return new WP_Error( 'cc_mcp_token_missing', __( 'Missing bearer token. Send Authorization: Bearer <token>.', 'codecanvas' ), array( 'status' => 401 ) );
    }
    $constant_token = ( defined( 'CODECANVAS_MCP_TOKEN' ) && is_string( CODECANVAS_MCP_TOKEN ) && '' !== CODECANVAS_MCP_TOKEN ) ? CODECANVAS_MCP_TOKEN : '';
    if ( '' !== $constant_token ) {
        // A configured constant takes precedence over the stored token hash.
        $valid = hash_equals( $constant_token, $token );
    } else {
        $valid = ( '' !== $options['token_hash'] ) && hash_equals( $options['token_hash'], codecanvas_mcp_hash_token( $token ) );
    }
    $user_id = (int) $options['user_id'];
    if ( ! $valid ) {
        // Access tokens issued by the OAuth sign-in flow are bound to the
        // administrator who approved the request, not to the stored token user.
        $oauth_user = function_exists( 'codecanvas_oauth_access_user' ) ? codecanvas_oauth_access_user( $token ) : 0;
        if ( ! $oauth_user ) {
            return new WP_Error( 'cc_mcp_token_invalid', __( 'Invalid MCP token. Generate a new token in CodeCanvas → Settings if this one was revoked.', 'codecanvas' ), array( 'status' => 401 ) );
        }
        $user_id = (int) $oauth_user;
        $valid   = true;
    }
    if ( ! $user_id && '' !== $constant_token ) {
        // A constant-only deployment has no generated-token user; use a current administrator.
        $admins  = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
        $user_id = $admins ? (int) $admins[0] : 0;
    }
    $user    = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
        return new WP_Error( 'cc_mcp_user', __( 'The administrator account that created this token is unavailable. Generate a new token.', 'codecanvas' ), array( 'status' => 503 ) );
    }
    wp_set_current_user( $user_id );

    $rate_key = 'cc_mcp_rl_' . substr( hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ), 0, 20 );
    $count    = (int) get_transient( $rate_key );
    if ( $count >= $options['rate_limit'] ) {
        return new WP_Error( 'cc_mcp_rate', __( 'MCP rate limit reached. Wait a moment and retry.', 'codecanvas' ), array( 'status' => 429 ) );
    }
    set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );
    return true;
}

/** JSON-RPC error object. */
function codecanvas_mcp_jsonrpc_error( $id, $code, $message, $data = null ) {
    $error = array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => (int) $code, 'message' => (string) $message ) );
    if ( null !== $data ) { $error['error']['data'] = $data; }
    return $error;
}

/** Convert a tool result or WP_Error into an MCP tools/call result. */
function codecanvas_mcp_tool_result( $result ) {
    if ( is_wp_error( $result ) ) {
        return array(
            'content' => array( array( 'type' => 'text', 'text' => $result->get_error_message() ) ),
            'isError' => true,
        );
    }
    $summary = isset( $result['summary'] ) ? (string) $result['summary'] : 'Done.';
    $data    = isset( $result['data'] ) ? $result['data'] : array();
    $json    = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
    return array(
        'content' => array( array( 'type' => 'text', 'text' => $summary . "\n\n" . ( is_string( $json ) ? $json : '{}' ) ) ),
        'isError' => false,
    );
}

/** Handle one JSON-RPC message. */
function codecanvas_mcp_handle_message( $message ) {
    if ( ! is_array( $message ) || ! isset( $message['method'] ) || ! is_string( $message['method'] ) ) {
        return codecanvas_mcp_jsonrpc_error( null, -32600, 'Invalid Request' );
    }
    $id     = array_key_exists( 'id', $message ) ? $message['id'] : null;
    $method = $message['method'];
    $params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

    if ( 'initialize' === $method ) {
        $requested = isset( $params['protocolVersion'] ) && is_string( $params['protocolVersion'] ) ? $params['protocolVersion'] : '';
        $version   = in_array( $requested, codecanvas_mcp_protocol_versions(), true ) ? $requested : codecanvas_mcp_protocol_version();
        return array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => array(
                'protocolVersion' => $version,
                'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
                'serverInfo'      => array( 'name' => 'codecanvas', 'title' => 'CodeCanvas Studio', 'version' => CODECANVAS_VERSION ),
                'instructions'    => 'CodeCanvas exposes real WordPress theme/plugin source files and content. Call list_projects first, then list_files with depth for a recursive view and read_file before editing. Use patch_file for small edits instead of resending a whole file. Every write is snapshotted: list_snapshots and restore_snapshot undo a change, including a delete. IMPORTANT: page copy, headings and images belong in WordPress records created with create_content/update_content, because the owner edits them in the block editor; do not hardcode page content into front-page.php or another template unless the user explicitly asked for a code-level layout, and when a home page should be editable use create_content plus update_settings with show_on_front=page and page_on_front. After changing PHP or CSS call render_url and get_php_error_log to confirm the page still works. Tools marked codecanvasEnabled: false are refused until an administrator enables them in CodeCanvas → MCP & Settings. Prefer the CodeCanvas Site child theme; the CodeCanvas framework theme is not exposed. Never place private credentials in frontend files. A recovery guard restores the last snapshot when a theme or plugin fatal error occurs, but it is a safety net, not a test harness.',
            ),
        );
    }
    if ( 'notifications/initialized' === $method || 'notifications/cancelled' === $method ) {
        return null;
    }
    if ( 'ping' === $method ) {
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => (object) array() );
    }
    if ( 'tools/list' === $method ) {
        /*
         * One registered set: every tool the server implements is listed, so a
         * client never has to "search" for a tool that appears later. Tools the
         * administrator has not enabled say so in their description and are
         * still refused by the dispatcher.
         */
        $catalog = codecanvas_mcp_tools();
        $enabled = codecanvas_mcp_enabled_tools();
        $options = codecanvas_mcp_options();
        $tools   = array();
        foreach ( $catalog as $name => $tool ) {
            $is_enabled  = isset( $enabled[ $name ] );
            $description = $tool['description'];
            if ( ! $is_enabled ) {
                $description .= ' ' . codecanvas_mcp_availability_note( $name, $tool, $options );
            }
            $tools[] = array(
                'name'        => $name,
                'title'       => $tool['title'],
                'description' => $description,
                'inputSchema' => $tool['inputSchema'],
                'annotations' => array(
                    'readOnlyHint'    => ( 'read' === $tool['mode'] ),
                    'destructiveHint' => in_array( $name, array( 'delete_file', 'delete_content' ), true ),
                    'codecanvasEnabled' => $is_enabled,
                ),
            );
        }
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'tools' => $tools ) );
    }
    if ( 'tools/call' === $method ) {
        $name = isset( $params['name'] ) ? (string) $params['name'] : '';
        $args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
        if ( '' === $name ) { return codecanvas_mcp_jsonrpc_error( $id, -32602, 'Missing tool name' ); }
        $started = microtime( true );
        $result  = codecanvas_mcp_dispatch_tool( $name, $args );
        codecanvas_mcp_audit( $name, $args, ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : 'ok', ( microtime( true ) - $started ) * 1000 );
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => codecanvas_mcp_tool_result( $result ) );
    }
    if ( 'logging/setLevel' === $method ) {
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => (object) array() );
    }
    if ( 'resources/list' === $method || 'prompts/list' === $method ) {
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => (object) array() );
    }
    return codecanvas_mcp_jsonrpc_error( $id, -32601, 'Method not found: ' . $method );
}

/** Parse the request body and answer one or more JSON-RPC messages. */
function codecanvas_mcp_process_body( $body ) {
    if ( strlen( $body ) > 1048576 ) {
        return array( array( 'response' => codecanvas_mcp_jsonrpc_error( null, -32600, 'Request body exceeds 1 MB' ), 'notification' => false ) );
    }
    $payload = json_decode( $body, true );
    if ( JSON_ERROR_NONE !== json_last_error() ) {
        return array( array( 'response' => codecanvas_mcp_jsonrpc_error( null, -32700, 'Parse error: invalid JSON' ), 'notification' => false ) );
    }
    $messages = array();
    if ( is_array( $payload ) && ! empty( $payload ) && array_keys( $payload ) === range( 0, count( $payload ) - 1 ) ) {
        if ( count( $payload ) > 20 ) {
            return array( array( 'response' => codecanvas_mcp_jsonrpc_error( null, -32600, 'Batch limit is 20 messages' ), 'notification' => false ) );
        }
        $messages = $payload;
    } else {
        $messages = array( $payload );
    }
    $out = array();
    foreach ( $messages as $message ) {
        $notification = is_array( $message ) && ! array_key_exists( 'id', $message );
        $response     = codecanvas_mcp_handle_message( $message );
        if ( null === $response ) { continue; }
        $out[] = array( 'response' => $response, 'notification' => $notification );
    }
    return $out;
}

/**
 * Keep WordPress from rejecting malformed JSON before the MCP handler runs.
 *
 * MCP requires a JSON-RPC parse error (-32700) rather than WordPress's
 * rest_invalid_json response, so the raw body is decoded by the MCP handler.
 */
function codecanvas_mcp_pre_dispatch( $result, $server, $request ) {
    if ( ! $request instanceof WP_REST_Request || '/codecanvas/v1/mcp' !== $request->get_route() ) {
        return $result;
    }
    if ( 'POST' !== strtoupper( $request->get_method() ) ) {
        // Answer unsupported methods explicitly; the endpoint does not provide SSE.
        $response = new WP_REST_Response( array(
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => array( 'code' => -32000, 'message' => 'This MCP endpoint accepts JSON-RPC over HTTP POST only.' ),
        ), 405 );
        $response->header( 'Allow', 'POST' );
        $response->header( 'Cache-Control', 'no-store, private' );
        return $response;
    }
    if ( $request->is_json_content_type() ) {
        // Present the body as opaque text; the MCP handler parses and validates it.
        $request->set_header( 'content-type', 'text/plain' );
    }
    return $result;
}
add_filter( 'rest_pre_dispatch', 'codecanvas_mcp_pre_dispatch', 5, 3 );

/** REST callback for POST /wp-json/codecanvas/v1/mcp. */
function codecanvas_mcp_rest_handle( $request ) {
    $authorized = codecanvas_mcp_authorize( $request );
    if ( is_wp_error( $authorized ) ) {
        $error_data = $authorized->get_error_data();
        $status     = ( is_array( $error_data ) && isset( $error_data['status'] ) ) ? (int) $error_data['status'] : 401;
        $payload = array(
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => array( 'code' => -32001, 'message' => $authorized->get_error_message(), 'data' => array( 'status' => $status ) ),
        );
        $response = new WP_REST_Response( $payload, $status );
        if ( 401 === $status ) {
            // resource_metadata lets OAuth-capable clients discover the sign-in flow (RFC 9728).
            $challenge = 'Bearer realm="CodeCanvas MCP"';
            if ( function_exists( 'codecanvas_oauth_resource_metadata_url' ) ) {
                $challenge .= ', resource_metadata="' . codecanvas_oauth_resource_metadata_url() . '"';
            }
            $response->header( 'WWW-Authenticate', $challenge );
        }
        if ( 429 === $status ) { $response->header( 'Retry-After', '60' ); }
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
        return $response;
    }

    $results = codecanvas_mcp_process_body( (string) $request->get_body() );
    $responses = array();
    foreach ( $results as $result ) {
        $responses[] = $result['response'];
    }
    if ( empty( $responses ) ) {
        $response = new WP_REST_Response( null, 202 );
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
        return $response;
    }
    $body = ( 1 === count( $responses ) ) ? $responses[0] : $responses;
    $response = new WP_REST_Response( $body, 200 );
    $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
    $response->header( 'X-CodeCanvas-MCP', '1' );
    return $response;
}

/** Register the MCP REST route. Other HTTP methods are answered in rest_pre_dispatch. */
function codecanvas_mcp_register_rest_routes() {
    register_rest_route( 'codecanvas/v1', '/mcp', array(
        'methods'             => 'POST',
        'callback'            => 'codecanvas_mcp_rest_handle',
        'permission_callback' => '__return_true', // Bearer token is verified in the callback.
    ) );
}
add_action( 'rest_api_init', 'codecanvas_mcp_register_rest_routes' );
