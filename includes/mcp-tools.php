<?php
/**
 * CodeCanvas MCP extended tools.
 *
 * Adds the tools an AI client needs to actually finish a job: string patching,
 * snapshot listing and restore, a page render probe, the PHP error log, site
 * settings, navigation menus, media upload and content deletion.
 *
 * Every tool keeps the same rules as the base server: allow-listed project,
 * capability checked against the mapped administrator, snapshotted writes,
 * PHP syntax validation and a bounded response.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Add the extended tools to the single MCP catalogue. */
function codecanvas_mcp_extended_tools( $tools ) {
    $tools['get_settings'] = array(
        'title'       => __( 'Get site settings', 'codecanvas' ),
        'description' => 'Return the site title, tagline, permalink structure, static front page and posts page, reading settings, page templates, upload limits and menus with their theme locations. Use this before changing site-level options. Read-only.',
        'mode'        => 'read',
        'inputSchema' => array( 'type' => 'object', 'properties' => (object) array(), 'additionalProperties' => false ),
    );
    $tools['list_menus'] = array(
        'title'       => __( 'List menus', 'codecanvas' ),
        'description' => 'List navigation menus, their locations and every menu item with its parent, order and target. Read-only.',
        'mode'        => 'read',
        'inputSchema' => array( 'type' => 'object', 'properties' => (object) array(), 'additionalProperties' => false ),
    );
    $tools['list_snapshots'] = array(
        'title'       => __( 'List snapshots', 'codecanvas' ),
        'description' => 'List the pre-change snapshots stored for one file (up to three, newest first) with their reason, hash, time and a short preview. Read-only. Use restore_snapshot to roll back.',
        'mode'        => 'read',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'project'         => array( 'type' => 'string', 'description' => 'Project key from list_projects.' ),
                'path'            => array( 'type' => 'string', 'description' => 'File path relative to the project. A deleted file still has snapshots.' ),
                'include_content' => array( 'type' => 'boolean', 'description' => 'Return the full snapshot text instead of a 200 character preview.' ),
            ),
            'required'   => array( 'project', 'path' ),
        ),
    );
    $tools['render_url'] = array(
        'title'       => __( 'Render a page', 'codecanvas' ),
        'description' => 'Fetch a page on this site over HTTP and return the status code, content type, title, body and any PHP error notice. Use it to confirm a page renders after an edit. Only URLs on this site are accepted. Read-only.',
        'mode'        => 'read',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'url'          => array( 'type' => 'string', 'description' => 'Site path such as / or /about/, or an absolute URL on this site. Defaults to the home page.' ),
                'include_body' => array( 'type' => 'boolean', 'description' => 'Return the response body. Defaults to true.' ),
                'max_bytes'    => array( 'type' => 'integer', 'description' => 'Body cap in bytes (maximum 262144).', 'minimum' => 1024, 'maximum' => 262144 ),
            ),
        ),
    );
    $tools['get_php_error_log'] = array(
        'title'       => __( 'Read PHP error log', 'codecanvas' ),
        'description' => 'Return the tail of the PHP error log (WordPress debug.log or the configured error_log) so runtime errors that PHP syntax checks cannot catch are visible. Read-only.',
        'mode'        => 'read',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'lines' => array( 'type' => 'integer', 'description' => 'Number of trailing lines (default 100, maximum 500).', 'minimum' => 1, 'maximum' => 500 ),
            ),
        ),
    );
    $tools['patch_file'] = array(
        'title'       => __( 'Patch file', 'codecanvas' ),
        'description' => 'Replace an exact string inside an existing file without resending the whole file. Fails when old_string is missing or matches more than once unless replace_all is true. The file is snapshotted first and PHP is syntax-checked. Requires file writes in Settings.',
        'mode'        => 'write',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'project'         => array( 'type' => 'string', 'description' => 'Project key from list_projects.' ),
                'path'            => array( 'type' => 'string', 'description' => 'File path relative to the project.' ),
                'old_string'      => array( 'type' => 'string', 'description' => 'Exact text to replace, including whitespace. Keep it unique.' ),
                'new_string'      => array( 'type' => 'string', 'description' => 'Replacement text. Use an empty string to delete the match.' ),
                'replace_all'     => array( 'type' => 'boolean', 'description' => 'Replace every occurrence instead of failing on more than one.' ),
                'expected_sha256' => array( 'type' => 'string', 'description' => 'Optional SHA-256 from read_file. When omitted, the hash the server last returned for this file is used.' ),
            ),
            'required'   => array( 'project', 'path', 'old_string', 'new_string' ),
        ),
    );
    $tools['create_files'] = array(
        'title'       => __( 'Create several files', 'codecanvas' ),
        'description' => 'Create up to 20 files or folders in one call for scaffolding. Existing paths are never overwritten; each result is reported separately. Requires file writes in Settings.',
        'mode'        => 'write',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'project' => array( 'type' => 'string' ),
                'files'   => array(
                    'type'        => 'array',
                    'description' => 'Files to create, in order.',
                    'maxItems'    => 20,
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'path'    => array( 'type' => 'string' ),
                            'content' => array( 'type' => 'string' ),
                            'kind'    => array( 'type' => 'string', 'enum' => array( 'file', 'folder' ) ),
                        ),
                        'required'   => array( 'path' ),
                    ),
                ),
            ),
            'required'   => array( 'project', 'files' ),
        ),
    );
    $tools['restore_snapshot'] = array(
        'title'       => __( 'Restore a snapshot', 'codecanvas' ),
        'description' => 'Restore one of the three stored pre-change snapshots for a file, recreating the file if it was deleted. The current contents are snapshotted first, so the restore can itself be undone. Requires file writes in Settings.',
        'mode'        => 'write',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'project' => array( 'type' => 'string' ),
                'path'    => array( 'type' => 'string' ),
                'index'   => array( 'type' => 'integer', 'description' => 'Snapshot index from list_snapshots; 0 is the newest. Defaults to 0.', 'minimum' => 0, 'maximum' => 2 ),
            ),
            'required'   => array( 'project', 'path' ),
        ),
    );
    $tools['create_plugin'] = array(
        'title'       => __( 'Create a plugin', 'codecanvas' ),
        'description' => 'Create a brand new plugin project: makes wp-content/plugins/<slug>/ with a valid plugin header and optional extra files. By default the new plugin is NOT added to the MCP project allow-list, so it cannot be edited over MCP until the owner enables it in CodeCanvas → MCP & Settings or the caller explicitly passes enable_mcp: true. The plugin is not activated either. Requires file writes in Settings.',
        'mode'        => 'write',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'slug'        => array( 'type' => 'string', 'description' => 'Folder and text-domain slug: lowercase letters, numbers and dashes, up to 60 characters.' ),
                'name'        => array( 'type' => 'string', 'description' => 'Plugin name shown in the Plugins screen.' ),
                'description' => array( 'type' => 'string', 'description' => 'One-line plugin description.' ),
                'version'     => array( 'type' => 'string', 'description' => 'Plugin version. Defaults to 0.1.0.' ),
                'author'      => array( 'type' => 'string' ),
                'license'     => array( 'type' => 'string', 'description' => 'License identifier. Defaults to GPL-2.0-or-later.' ),
                'enable_mcp'  => array( 'type' => 'boolean', 'description' => 'Set true to also add the new plugin project to the MCP allow-list in the same call. Defaults to false so editing a new plugin needs a separate approval.' ),
                'files'       => array(
                    'type'        => 'array',
                    'description' => 'Optional extra files to scaffold, relative to the plugin folder (for example includes/helpers.php). Maximum 20.',
                    'maxItems'    => 20,
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'path'    => array( 'type' => 'string' ),
                            'content' => array( 'type' => 'string' ),
                        ),
                        'required'   => array( 'path' ),
                    ),
                ),
            ),
            'required'   => array( 'slug', 'name' ),
        ),
    );
    $tools['delete_content'] = array(
        'title'       => __( 'Delete content', 'codecanvas' ),
        'description' => 'Move a page or post to the trash, or delete it permanently with force=true. Trashing is reversible; permanent deletion is not. Requires content writes in Settings and confirm=true.',
        'mode'        => 'content',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'id'      => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true; the caller confirms the deletion.' ),
                'force'   => array( 'type' => 'boolean', 'description' => 'Permanently delete instead of trashing. Defaults to false.' ),
            ),
            'required'   => array( 'id', 'confirm' ),
        ),
    );
    $tools['update_settings'] = array(
        'title'       => __( 'Update site settings', 'codecanvas' ),
        'description' => 'Change the site title, tagline, static front page, posts page, posts per page or permalink structure, then flush rewrite rules. Requires content writes in Settings.',
        'mode'        => 'content',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'title'               => array( 'type' => 'string', 'description' => 'Site title.' ),
                'tagline'             => array( 'type' => 'string', 'description' => 'Site tagline.' ),
                'show_on_front'       => array( 'type' => 'string', 'enum' => array( 'posts', 'page' ), 'description' => 'Show the latest posts or a static page on the home page.' ),
                'page_on_front'       => array( 'type' => 'integer', 'description' => 'Page ID for the static front page.' ),
                'page_for_posts'      => array( 'type' => 'integer', 'description' => 'Page ID for the posts page. Use 0 for none.' ),
                'posts_per_page'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
                'permalink_structure' => array( 'type' => 'string', 'description' => 'Permalink structure such as /%postname%/. Rewrite rules are flushed after the change.' ),
            ),
        ),
    );
    $tools['update_menu'] = array(
        'title'       => __( 'Update a menu', 'codecanvas' ),
        'description' => 'Create a menu, add or edit an item, remove an item, assign a menu to a theme location, or delete a menu. action=create returns the new menu_id. Every other action requires menu_id from create or list_menus; update_item and remove_item also require item_id. Run list_menus first. Requires content writes in Settings; removals need confirm=true.',
        'mode'        => 'content',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'action'         => array( 'type' => 'string', 'enum' => array( 'create', 'add_item', 'update_item', 'remove_item', 'assign_location', 'delete_menu' ) ),
                'menu_id'        => array( 'type' => 'integer', 'description' => 'Menu term ID. Required for add_item, update_item, remove_item, assign_location and delete_menu; get it from create or list_menus. Not used by create.' ),
                'name'           => array( 'type' => 'string', 'description' => 'Menu name for create.' ),
                'item_id'        => array( 'type' => 'integer', 'description' => 'Existing item ID. Required for update_item and remove_item; returned by add_item.' ),
                'title'          => array( 'type' => 'string', 'description' => 'Item label.' ),
                'url'            => array( 'type' => 'string', 'description' => 'Link for a custom item. Defaults to #.' ),
                'object'         => array( 'type' => 'string', 'description' => 'Object type such as page or post for a linked item.' ),
                'object_id'      => array( 'type' => 'integer', 'description' => 'Post or term ID for a linked item.' ),
                'parent_item_id' => array( 'type' => 'integer', 'description' => 'Parent menu item ID; 0 for top level.' ),
                'position'       => array( 'type' => 'integer', 'description' => 'Sort position within the menu.' ),
                'location'       => array( 'type' => 'string', 'description' => 'Theme location slug, for example primary or footer.' ),
                'confirm'        => array( 'type' => 'boolean', 'description' => 'Required for remove_item and delete_menu.' ),
            ),
            'required'   => array( 'action' ),
        ),
    );
    $tools['upload_media'] = array(
        'title'       => __( 'Upload media', 'codecanvas' ),
        'description' => 'Store a base64 encoded file in the media library and return its attachment ID and URL. Uploads are limited to 700 KB per call and to the file types WordPress allows. Requires content writes in Settings.',
        'mode'        => 'content',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array(
                'filename'    => array( 'type' => 'string', 'description' => 'File name including the extension, such as hero.jpg.' ),
                'data_base64' => array( 'type' => 'string', 'description' => 'Base64 encoded file bytes. Raw payload must be 700 KB or less.' ),
                'title'       => array( 'type' => 'string' ),
                'alt_text'    => array( 'type' => 'string', 'description' => 'Alternative text for images.' ),
                'post_parent' => array( 'type' => 'integer', 'description' => 'Optional post ID to attach the media to.' ),
            ),
            'required'   => array( 'filename', 'data_base64' ),
        ),
    );
    return $tools;
}
add_filter( 'codecanvas_mcp_tool_catalog', 'codecanvas_mcp_extended_tools' );

/**
 * Remember the hash the server handed to a client for a file.
 *
 * Tracked hashes let an edit be refused when the file changed since it was
 * read, without forcing the client to send expected_sha256 on every call.
 */
function codecanvas_mcp_hash_key( $root, $relative ) {
    return 'cc_mcp_hash_' . md5( strtolower( (string) $root ) . ':' . ltrim( wp_normalize_path( (string) $relative ), '/' ) );
}

/** Store the last known hash for a file. */
function codecanvas_mcp_remember_hash( $root, $relative, $sha ) {
    if ( ! is_string( $sha ) || '' === $sha ) { return; }
    set_transient( codecanvas_mcp_hash_key( $root, $relative ), array( 'sha' => $sha, 'time' => time() ), WEEK_IN_SECONDS );
}

/** Last hash the server returned for a file, or an empty string. */
function codecanvas_mcp_last_hash( $root, $relative ) {
    $stored = get_transient( codecanvas_mcp_hash_key( $root, $relative ) );
    return ( is_array( $stored ) && ! empty( $stored['sha'] ) ) ? (string) $stored['sha'] : '';
}

/** Forget a tracked hash, for example after a deletion. */
function codecanvas_mcp_forget_hash( $root, $relative ) {
    delete_transient( codecanvas_mcp_hash_key( $root, $relative ) );
}

/** Recoverable conflict error carrying the current hash and modification time. */
function codecanvas_mcp_conflict_error( $file ) {
    $current = is_file( $file ) ? file_get_contents( $file ) : '';
    $sha     = is_string( $current ) ? hash( 'sha256', $current ) : '';
    $mtime   = is_file( $file ) ? (int) filemtime( $file ) : 0;
    return new WP_Error(
        'cc_mcp_conflict',
        sprintf(
            /* translators: 1: sha256 hash, 2: UTC timestamp */
            __( 'The file changed since you last read it. Nothing was written. Current sha256: %1$s, last modified: %2$s. Read the file again, reapply your change and pass that hash.', 'codecanvas' ),
            $sha ? $sha : 'unknown',
            $mtime ? gmdate( 'Y-m-d H:i:s', $mtime ) . ' UTC' : 'unknown'
        )
    );
}

/**
 * Validate, snapshot and write a file through the shared MCP rules.
 *
 * @param array  $resolved        Result of codecanvas_mcp_resolve_file().
 * @param string $content         New contents.
 * @param string $reason          Snapshot reason key.
 * @param string $expected_sha256 Hash supplied by the caller; empty uses the tracked hash.
 * @param bool   $check_hash      False for an intentional rollback.
 * @return array|WP_Error
 */
function codecanvas_mcp_commit_file( $resolved, $content, $reason, $expected_sha256 = '', $check_hash = true ) {
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    $file = $resolved['path'];
    $root = $resolved['project']['root'];
    $rel  = $resolved['relative'];
    $display = isset( $resolved['display'] ) ? $resolved['display'] : codecanvas_mcp_display_path( $rel, $resolved['project']['relative'] );
    if ( ! is_string( $content ) ) { return new WP_Error( 'cc_mcp_param', __( 'content must be a string.', 'codecanvas' ) ); }
    $exists  = is_file( $file );
    $current = $exists ? file_get_contents( $file ) : '';
    if ( false === $current ) { return new WP_Error( 'cc_mcp_read', __( 'Could not read the existing file before saving.', 'codecanvas' ) ); }
    if ( $check_hash ) {
        $expected = is_string( $expected_sha256 ) ? trim( $expected_sha256 ) : '';
        if ( '' === $expected ) { $expected = codecanvas_mcp_last_hash( $root, $rel ); }
        if ( '' !== $expected && $exists && ! hash_equals( hash( 'sha256', $current ), $expected ) ) {
            return codecanvas_mcp_conflict_error( $file );
        }
    }
    $valid = codecanvas_mcp_validate_write( $file, $content, $resolved['project'] );
    if ( is_wp_error( $valid ) ) { return $valid; }
    if ( ! $exists && ! codecanvas_studio_can_access_root( $root, true ) ) {
        return new WP_Error( 'cc_mcp_policy', __( 'File changes are not permitted by WordPress or hosting policy.', 'codecanvas' ) );
    }
    $parent = dirname( $file );
    if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
        return new WP_Error( 'cc_mcp_mkdir', __( 'Could not create the parent folder.', 'codecanvas' ) );
    }
    if ( $exists ) { codecanvas_studio_make_backup( $root, $rel, $current, $reason ); }
    if ( false === @file_put_contents( $file, $content, LOCK_EX ) ) {
        return new WP_Error( 'cc_mcp_write', __( 'The file could not be written. Check filesystem permissions.', 'codecanvas' ) );
    }
    clearstatcache( true, $file );
    $sha = hash( 'sha256', $content );
    codecanvas_mcp_remember_hash( $root, $rel, $sha );
    return array(
        'project'      => $resolved['project']['key'],
        'path'         => ltrim( (string) $display, '/' ),
        'bytes'        => strlen( $content ),
        'sha256'       => $sha,
        'mtime'        => (int) filemtime( $file ),
        'created'      => ! $exists,
        'previous_sha' => $exists ? hash( 'sha256', $current ) : '',
    );
}

/** Display path helper used by commit results. */
function codecanvas_mcp_display_path( $relative, $project_relative ) {
    $relative = ltrim( wp_normalize_path( (string) $relative ), '/' );
    if ( '' !== $project_relative && 0 === strpos( $relative, $project_relative . '/' ) ) {
        return substr( $relative, strlen( $project_relative ) + 1 );
    }
    return $relative;
}

/** Stored snapshot history for one file. */
function codecanvas_mcp_snapshot_history( $root, $relative ) {
    $history = get_option( 'codecanvas_studio_bk_' . md5( $root . ':' . ltrim( wp_normalize_path( (string) $relative ), '/' ) ), array() );
    return is_array( $history ) ? array_values( $history ) : array();
}

/** Boolean argument helper. */
function codecanvas_mcp_arg_bool( $args, $key, $default = false ) {
    if ( ! isset( $args[ $key ] ) ) { return $default; }
    if ( is_bool( $args[ $key ] ) ) { return $args[ $key ]; }
    if ( is_string( $args[ $key ] ) ) {
        return in_array( strtolower( $args[ $key ] ), array( '1', 'true', 'yes' ), true );
    }
    return (bool) $args[ $key ];
}

/** Resolve a project and reject it unless it is enabled for MCP. */
function codecanvas_mcp_scoped_project( $project_key ) {
    $project = codecanvas_mcp_project( $project_key );
    if ( is_wp_error( $project ) ) { return $project; }
    if ( ! codecanvas_mcp_project_allowed( $project ) ) {
        return new WP_Error( 'cc_mcp_scope', __( 'That project is not enabled for MCP. Enable it in CodeCanvas → MCP & Settings.', 'codecanvas' ) );
    }
    return $project;
}

/** Tool: get site settings. */
function codecanvas_mcp_tool_get_settings( $args ) {
    $front_id = (int) get_option( 'page_on_front' );
    $blog_id  = (int) get_option( 'page_for_posts' );
    $page_data = function ( $id ) {
        if ( ! $id ) { return null; }
        $page = get_post( $id );
        if ( ! $page ) { return null; }
        return array( 'id' => $page->ID, 'title' => get_the_title( $page ), 'status' => $page->post_status, 'url' => get_permalink( $page ) );
    };
    $pages = array();
    $page_posts = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
    foreach ( $page_posts as $page ) {
        if ( ! current_user_can( 'read_post', $page->ID ) ) { continue; }
        $pages[] = array( 'id' => $page->ID, 'title' => get_the_title( $page ), 'status' => $page->post_status );
    }
    $templates = array();
    $theme     = wp_get_theme();
    foreach ( (array) $theme->get_page_templates( null, get_stylesheet() ) as $file => $name ) {
        $templates[] = array( 'file' => $file, 'name' => $name );
    }
    $menu_summary = array();
    foreach ( wp_get_nav_menus() as $menu ) {
        $items = wp_get_nav_menu_items( $menu->term_id );
        $menu_summary[] = array(
            'id'    => (int) $menu->term_id,
            'name'  => $menu->name,
            'slug'  => $menu->slug,
            'items' => is_array( $items ) ? count( $items ) : 0,
        );
    }
    $locations = array();
    $assigned  = get_nav_menu_locations();
    foreach ( get_registered_nav_menus() as $slug => $label ) {
        $locations[] = array( 'location' => $slug, 'label' => $label, 'menu_id' => isset( $assigned[ $slug ] ) ? (int) $assigned[ $slug ] : 0 );
    }
    $uploads = wp_upload_dir( null, false );
    return array(
        'summary' => 'Site settings loaded.',
        'data'    => array(
            'title'              => get_option( 'blogname' ),
            'tagline'            => get_option( 'blogdescription' ),
            'url'                => home_url( '/' ),
            'timezone'           => wp_timezone_string(),
            'language'           => get_locale(),
            'permalink_structure'=> (string) get_option( 'permalink_structure' ),
            'category_base'      => (string) get_option( 'category_base' ),
            'tag_base'           => (string) get_option( 'tag_base' ),
            'show_on_front'      => get_option( 'show_on_front' ),
            'page_on_front'      => $page_data( $front_id ),
            'page_for_posts'     => $page_data( $blog_id ),
            'posts_per_page'     => (int) get_option( 'posts_per_page' ),
            'pages'              => $pages,
            'page_templates'     => $templates,
            'menus'              => $menu_summary,
            'menu_locations'     => $locations,
            'uploads'            => array(
                'path'     => isset( $uploads['basedir'] ) ? str_replace( ABSPATH, '', $uploads['basedir'] ) : '',
                'url'      => isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '',
                'max_size' => (int) wp_max_upload_size(),
            ),
            'theme'              => array( 'stylesheet' => get_stylesheet(), 'template' => get_template() ),
        ),
    );
}

/** Tool: list menus. */
function codecanvas_mcp_tool_list_menus( $args ) {
    $menus = array();
    foreach ( wp_get_nav_menus() as $menu ) {
        $items = wp_get_nav_menu_items( $menu->term_id );
        $rows  = array();
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $rows[] = array(
                    'id'          => (int) $item->ID,
                    'title'       => $item->title,
                    'url'         => $item->url,
                    'type'        => isset( $item->type ) ? $item->type : '',
                    'object'      => isset( $item->object ) ? $item->object : '',
                    'object_id'   => isset( $item->object_id ) ? (int) $item->object_id : 0,
                    'parent_id'   => (int) $item->menu_item_parent,
                    'position'    => (int) $item->menu_order,
                    'target'      => isset( $item->target ) ? $item->target : '',
                    'status'      => isset( $item->post_status ) ? $item->post_status : 'publish',
                );
            }
        }
        $menus[] = array(
            'id'        => (int) $menu->term_id,
            'name'      => $menu->name,
            'slug'      => $menu->slug,
            'locations' => array(),
            'items'     => $rows,
        );
    }
    $assigned  = get_nav_menu_locations();
    $by_id     = array();
    foreach ( $menus as $index => $menu ) { $by_id[ $menu['id'] ] = $index; }
    $registered = get_registered_nav_menus();
    foreach ( $assigned as $location => $menu_id ) {
        if ( isset( $by_id[ (int) $menu_id ] ) ) {
            $menus[ $by_id[ (int) $menu_id ] ]['locations'][] = $location;
        }
    }
    $locations = array();
    foreach ( $registered as $slug => $label ) {
        $locations[] = array( 'location' => $slug, 'label' => $label, 'menu_id' => isset( $assigned[ $slug ] ) ? (int) $assigned[ $slug ] : 0 );
    }
    return array(
        'summary' => sprintf( '%d menu(s) found.', count( $menus ) ),
        'data'    => array( 'menus' => $menus, 'locations' => $locations ),
    );
}

/** Tool: list snapshots. */
function codecanvas_mcp_tool_list_snapshots( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', true, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    $include = codecanvas_mcp_arg_bool( $args, 'include_content', false );
    $resolved = codecanvas_mcp_resolve_file( $project_key, $path, true );
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    $history = codecanvas_mcp_snapshot_history( $resolved['project']['root'], $resolved['relative'] );
    $entries = array();
    foreach ( $history as $index => $entry ) {
        if ( ! is_array( $entry ) ) { continue; }
        $content = isset( $entry['content'] ) && is_string( $entry['content'] ) ? $entry['content'] : '';
        $row = array(
            'index'   => (int) $index,
            'time'    => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
            'date'    => isset( $entry['time'] ) ? gmdate( 'Y-m-d H:i:s', (int) $entry['time'] ) . ' UTC' : '',
            'reason'  => isset( $entry['reason'] ) ? sanitize_key( $entry['reason'] ) : 'save',
            'sha256'  => isset( $entry['sha256'] ) ? (string) $entry['sha256'] : hash( 'sha256', $content ),
            'bytes'   => strlen( $content ),
        );
        if ( $include ) {
            $row['content'] = $content;
        } else {
            $row['preview'] = substr( preg_replace( '/\s+/', ' ', $content ), 0, 200 );
        }
        $entries[] = $row;
    }
    return array(
        'summary' => $entries
            ? sprintf( '%d snapshot(s) available for %s.', count( $entries ), codecanvas_mcp_display_path( $resolved['relative'], $resolved['project']['relative'] ) )
            : __( 'No snapshots are stored for this file yet.', 'codecanvas' ),
        'data'    => array(
            'project'     => $resolved['project']['key'],
            'path'        => codecanvas_mcp_display_path( $resolved['relative'], $resolved['project']['relative'] ),
            'file_exists' => is_file( $resolved['path'] ),
            'snapshots'   => $entries,
        ),
    );
}

/** Tool: restore a snapshot. */
function codecanvas_mcp_tool_restore_snapshot( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', true, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    $index = codecanvas_mcp_arg_int( $args, 'index', 0, 0, 2 );
    if ( is_wp_error( $index ) ) { return $index; }
    $resolved = codecanvas_mcp_resolve_file( $project_key, $path, true );
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    $history = codecanvas_mcp_snapshot_history( $resolved['project']['root'], $resolved['relative'] );
    if ( ! isset( $history[ $index ] ) || ! is_array( $history[ $index ] ) || ! isset( $history[ $index ]['content'] ) ) {
        /* translators: %d: snapshot index. */
        return new WP_Error( 'cc_mcp_snapshot', sprintf( __( 'There is no snapshot at index %d for that file. Call list_snapshots first.', 'codecanvas' ), (int) $index ) );
    }
    $entry   = $history[ $index ];
    $content = (string) $entry['content'];
    $resolved['display'] = codecanvas_mcp_display_path( $resolved['relative'], $resolved['project']['relative'] );
    $result = codecanvas_mcp_commit_file( $resolved, $content, 'mcp-restore', '', false );
    if ( is_wp_error( $result ) ) { return $result; }
    return array(
        'summary' => sprintf( 'Restored snapshot %d for %s (%d bytes).', (int) $index, $resolved['display'], strlen( $content ) ),
        'data'    => array_merge( $result, array(
            'restored_index' => (int) $index,
            'snapshot_time'  => isset( $entry['time'] ) ? gmdate( 'Y-m-d H:i:s', (int) $entry['time'] ) . ' UTC' : '',
            'snapshot_reason'=> isset( $entry['reason'] ) ? sanitize_key( $entry['reason'] ) : 'save',
        ) ),
    );
}

/** Tool: patch an exact string in a file. */
function codecanvas_mcp_tool_patch_file( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    $path = codecanvas_mcp_arg_string( $args, 'path', true, 500 );
    if ( is_wp_error( $path ) ) { return $path; }
    $old = isset( $args['old_string'] ) && is_string( $args['old_string'] ) ? $args['old_string'] : null;
    if ( null === $old || '' === $old ) { return new WP_Error( 'cc_mcp_param', __( 'old_string is required and cannot be empty.', 'codecanvas' ) ); }
    if ( strlen( $old ) > 65536 ) { return new WP_Error( 'cc_mcp_param', __( 'old_string is limited to 64 KB. Use write_file for larger replacements.', 'codecanvas' ) ); }
    if ( ! array_key_exists( 'new_string', $args ) || ! is_string( $args['new_string'] ) ) { return new WP_Error( 'cc_mcp_param', __( 'new_string is required; pass an empty string to delete the match.', 'codecanvas' ) ); }
    $new = $args['new_string'];
    if ( strlen( $new ) > 262144 ) { return new WP_Error( 'cc_mcp_param', __( 'new_string is limited to 256 KB.', 'codecanvas' ) ); }
    $replace_all = codecanvas_mcp_arg_bool( $args, 'replace_all', false );
    $expected    = codecanvas_mcp_arg_string( $args, 'expected_sha256', false, 64 );
    if ( is_wp_error( $expected ) ) { return $expected; }
    $resolved = codecanvas_mcp_resolve_file( $project_key, $path );
    if ( is_wp_error( $resolved ) ) { return $resolved; }
    if ( ! is_file( $resolved['path'] ) ) { return new WP_Error( 'cc_mcp_missing', __( 'That file does not exist. Use create_file for a new file.', 'codecanvas' ) ); }
    $content = file_get_contents( $resolved['path'] );
    if ( false === $content ) { return new WP_Error( 'cc_mcp_read', __( 'Could not read the file.', 'codecanvas' ) ); }
    if ( '' !== trim( $expected ) && ! hash_equals( hash( 'sha256', $content ), $expected ) ) {
        return codecanvas_mcp_conflict_error( $resolved['path'] );
    }
    $count = substr_count( $content, $old );
    if ( 0 === $count ) {
        return new WP_Error( 'cc_mcp_patch_missing', __( 'old_string was not found in the file. Read the file again and copy the exact text, including indentation.', 'codecanvas' ) );
    }
    if ( $count > 1 && ! $replace_all ) {
        /* translators: %d: number of matches found. */
        return new WP_Error( 'cc_mcp_patch_ambiguous', sprintf( __( 'old_string appears %d times. Include more surrounding context or pass replace_all: true.', 'codecanvas' ), $count ) );
    }
    $patched = str_replace( $old, $new, $content );
    $resolved['display'] = codecanvas_mcp_display_path( $resolved['relative'], $resolved['project']['relative'] );
    $result = codecanvas_mcp_commit_file( $resolved, $patched, 'mcp-patch', $expected );
    if ( is_wp_error( $result ) ) { return $result; }
    return array(
        'summary' => sprintf( 'Patched %s (%d replacement%s). A pre-change snapshot was stored.', $resolved['display'], $count, 1 === $count ? '' : 's' ),
        'data'    => array_merge( $result, array( 'replacements' => $count ) ),
    );
}

/** Tool: create several files or folders in one call. */
function codecanvas_mcp_tool_create_files( $args ) {
    $args = is_array( $args ) ? $args : array();
    $project_key = codecanvas_mcp_arg_string( $args, 'project', true, 80 );
    if ( is_wp_error( $project_key ) ) { return $project_key; }
    if ( empty( $args['files'] ) || ! is_array( $args['files'] ) ) { return new WP_Error( 'cc_mcp_param', __( 'files must be an array of objects with a path.', 'codecanvas' ) ); }
    $files = array_values( $args['files'] );
    if ( count( $files ) > 20 ) { return new WP_Error( 'cc_mcp_param', __( 'A maximum of 20 files can be created per call.', 'codecanvas' ) ); }
    $created = array();
    $failed  = array();
    foreach ( $files as $entry ) {
        if ( ! is_array( $entry ) || empty( $entry['path'] ) || ! is_string( $entry['path'] ) ) {
            $failed[] = array( 'path' => '', 'error' => __( 'Each entry needs a path string.', 'codecanvas' ) );
            continue;
        }
        $single = array(
            'project' => $project_key,
            'path'    => $entry['path'],
            'kind'    => isset( $entry['kind'] ) && is_string( $entry['kind'] ) ? $entry['kind'] : 'file',
            'content' => isset( $entry['content'] ) && is_string( $entry['content'] ) ? $entry['content'] : '',
        );
        $outcome = codecanvas_mcp_tool_create_file( $single );
        if ( is_wp_error( $outcome ) ) {
            $failed[] = array( 'path' => ltrim( (string) $entry['path'], '/' ), 'error' => $outcome->get_error_message() );
            continue;
        }
        $created[] = $outcome['data'];
    }
    return array(
        'summary' => sprintf( 'Created %d of %d path(s).', count( $created ), count( $files ) ),
        'data'    => array( 'created' => $created, 'failed' => $failed ),
    );
}

/** Clean a value for a plugin header line. */
function codecanvas_mcp_header_value( $value, $fallback = '' ) {
    $value = is_string( $value ) ? $value : '';
    $value = trim( preg_replace( '/\s+/', ' ', $value ) );
    $value = str_replace( array( '*/', '<?php' ), '', $value );
    return '' !== $value ? $value : $fallback;
}

/** Tool: scaffold a new plugin project. */
function codecanvas_mcp_tool_create_plugin( $args ) {
    $args    = is_array( $args ) ? $args : array();
    $options = codecanvas_mcp_options();
    if ( empty( $options['allow_writes'] ) ) {
        return new WP_Error( 'cc_mcp_writes_disabled', __( 'File writes are disabled for MCP. Enable them in CodeCanvas → MCP & Settings.', 'codecanvas' ) );
    }
    if ( ! codecanvas_studio_can_access_root( 'plugins', true ) ) {
        return new WP_Error( 'cc_mcp_policy', __( 'WordPress permissions or file-modification policy prevent creating plugins on this site.', 'codecanvas' ) );
    }
    $slug = codecanvas_mcp_arg_string( $args, 'slug', true, 60 );
    if ( is_wp_error( $slug ) ) { return $slug; }
    $slug = strtolower( trim( $slug ) );
    if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,59}$/', $slug ) ) {
        return new WP_Error( 'cc_mcp_param', __( 'slug must be lowercase letters, numbers and dashes only, starting with a letter or number.', 'codecanvas' ) );
    }
    $name = codecanvas_mcp_arg_string( $args, 'name', true, 200 );
    if ( is_wp_error( $name ) ) { return $name; }
    $description = codecanvas_mcp_arg_string( $args, 'description', false, 500 );
    if ( is_wp_error( $description ) ) { return $description; }
    $version = codecanvas_mcp_arg_string( $args, 'version', false, 20 );
    if ( is_wp_error( $version ) ) { return $version; }
    $version = '' !== $version ? $version : '0.1.0';
    if ( ! preg_match( '/^[0-9][0-9A-Za-z.\-]*$/', $version ) ) {
        return new WP_Error( 'cc_mcp_param', __( 'version must start with a number.', 'codecanvas' ) );
    }
    $author = codecanvas_mcp_arg_string( $args, 'author', false, 200 );
    if ( is_wp_error( $author ) ) { return $author; }
    $license = codecanvas_mcp_arg_string( $args, 'license', false, 100 );
    if ( is_wp_error( $license ) ) { return $license; }
    // Creating a folder is not approval to change its code over MCP. The client
    // must ask for that separately with enable_mcp, or the owner enables it in Settings.
    $enable_mcp = codecanvas_mcp_arg_bool( $args, 'enable_mcp', false );

    $root = codecanvas_studio_root_path( 'plugins' );
    if ( '' === $root ) {
        return new WP_Error( 'cc_mcp_plugins', __( 'The plugins folder could not be resolved.', 'codecanvas' ) );
    }
    $dir = $root . DIRECTORY_SEPARATOR . $slug;
    if ( file_exists( $dir ) ) {
        /* translators: %s: plugin folder slug. */
        return new WP_Error( 'cc_mcp_exists', sprintf( __( 'A plugin folder named %s already exists. Work in that project instead of creating a new one.', 'codecanvas' ), $slug ) );
    }
    if ( ! wp_mkdir_p( $dir ) ) {
        return new WP_Error( 'cc_mcp_mkdir', __( 'The plugin folder could not be created. Check that wp-content/plugins is writable.', 'codecanvas' ) );
    }

    $header = "<?php\n/**\n"
        . ' * Plugin Name: ' . codecanvas_mcp_header_value( $name, $slug ) . "\n"
        . ' * Description: ' . codecanvas_mcp_header_value( $description, __( 'Created with CodeCanvas MCP.', 'codecanvas' ) ) . "\n"
        . ' * Version: ' . $version . "\n"
        . ' * Author: ' . codecanvas_mcp_header_value( $author, get_bloginfo( 'name' ) ) . "\n"
        . ' * License: ' . codecanvas_mcp_header_value( $license, 'GPL-2.0-or-later' ) . "\n"
        . ' * Text Domain: ' . $slug . "\n"
        . " */\n\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n";

    $valid = codecanvas_mcp_validate_php( $header );
    if ( is_wp_error( $valid ) ) {
        codecanvas_rmdir( $dir );
        return $valid;
    }
    $main_file = $dir . DIRECTORY_SEPARATOR . $slug . '.php';
    if ( false === @file_put_contents( $main_file, $header, LOCK_EX ) ) {
        codecanvas_rmdir( $dir );
        return new WP_Error( 'cc_mcp_write', __( 'The plugin header file could not be written.', 'codecanvas' ) );
    }

    $created = array( array( 'path' => $slug . '.php', 'bytes' => strlen( $header ) ) );
    $failed  = array();
    if ( isset( $args['files'] ) && is_array( $args['files'] ) ) {
        $files = array_values( $args['files'] );
        if ( count( $files ) > 20 ) {
            $failed[] = array( 'path' => '', 'error' => __( 'A maximum of 20 files can be scaffolded per call.', 'codecanvas' ) );
            $files    = array_slice( $files, 0, 20 );
        }
        foreach ( $files as $entry ) {
            $relative = ( is_array( $entry ) && isset( $entry['path'] ) && is_string( $entry['path'] ) ) ? ltrim( wp_normalize_path( $entry['path'] ), '/' ) : '';
            $content  = ( is_array( $entry ) && isset( $entry['content'] ) && is_string( $entry['content'] ) ) ? $entry['content'] : '';
            $segments = '' !== $relative ? explode( '/', $relative ) : array();
            $safe     = '' !== $relative && false === strpos( $relative, '\\' );
            foreach ( $segments as $segment ) {
                if ( '' === $segment || '.' === $segment || '..' === $segment || false !== strpos( $segment, "\0" ) ) { $safe = false; }
            }
            if ( ! $safe ) {
                $failed[] = array( 'path' => (string) $relative, 'error' => __( 'Unsafe file path.', 'codecanvas' ) );
                continue;
            }
            $target = $dir . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
            if ( file_exists( $target ) ) {
                $failed[] = array( 'path' => $relative, 'error' => __( 'That path already exists.', 'codecanvas' ) );
                continue;
            }
            if ( ! codecanvas_studio_is_allowed_file( $target ) ) {
                $failed[] = array( 'path' => $relative, 'error' => __( 'That file type is not allowed.', 'codecanvas' ) );
                continue;
            }
            if ( strlen( $content ) > 262144 ) {
                $failed[] = array( 'path' => $relative, 'error' => __( 'Files are limited to 256 KB.', 'codecanvas' ) );
                continue;
            }
            $ext = strtolower( pathinfo( $target, PATHINFO_EXTENSION ) );
            if ( in_array( $ext, array( 'php', 'phtml' ), true ) ) {
                $check = codecanvas_mcp_validate_php( $content );
                if ( is_wp_error( $check ) ) {
                    $failed[] = array( 'path' => $relative, 'error' => $check->get_error_message() );
                    continue;
                }
            }
            $parent = dirname( $target );
            if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
                $failed[] = array( 'path' => $relative, 'error' => __( 'The parent folder could not be created.', 'codecanvas' ) );
                continue;
            }
            if ( false === @file_put_contents( $target, $content, LOCK_EX ) ) {
                $failed[] = array( 'path' => $relative, 'error' => __( 'The file could not be written.', 'codecanvas' ) );
                continue;
            }
            $created[] = array( 'path' => $relative, 'bytes' => strlen( $content ) );
        }
    }

    // A new folder only appears after a rescan; refresh so list_projects and the
    // settings sanitizer see it immediately.
    codecanvas_mcp_available_projects( true );
    $key     = 'plugin:' . $slug;
    $enabled = false;
    if ( $enable_mcp ) {
        $options  = codecanvas_mcp_options();
        $projects = is_array( $options['projects'] ) ? $options['projects'] : array();
        if ( ! in_array( $key, $projects, true ) ) { $projects[] = $key; }
        $options['projects'] = array_values( $projects );
        update_option( 'codecanvas_mcp_options', $options, false );
        $saved   = codecanvas_mcp_options();
        $enabled = in_array( $key, $saved['projects'], true );
    }

    return array(
        'summary' => $enabled
            ? sprintf( 'Created plugin %s (%d file(s)). enable_mcp was set, so it is enabled for MCP; it is still NOT active — activate it in Plugins → Installed Plugins when the code is ready.', $key, count( $created ) )
            : sprintf( 'Created plugin %s (%d file(s)). It is NOT enabled for MCP and NOT active. Review the files, add the project in CodeCanvas → MCP & Settings if it should be edited over MCP, then activate it in Plugins.', $key, count( $created ) ),
        'data'    => array(
            'project'     => $key,
            'folder'      => 'wp-content/plugins/' . $slug,
            'main_file'   => $slug . '.php',
            'files'       => $created,
            'failed'      => $failed,
            'mcp_enabled' => $enabled,
            'active'      => false,
            'next_step'   => $enabled
                ? 'Review the generated files, then activate the plugin in Plugins → Installed Plugins. MCP cannot activate plugins.'
                : 'Review the generated files, enable the project in CodeCanvas → MCP & Settings if MCP should edit it, then activate it in Plugins. MCP cannot activate plugins.',
        ),
    );
}

/** Restrict a URL to this site so render_url cannot be used as an open proxy. */
function codecanvas_mcp_local_url( $url ) {
    $home = wp_parse_url( home_url( '/' ) );
    if ( ! $home || empty( $home['host'] ) ) { return new WP_Error( 'cc_mcp_url', __( 'The site URL is not configured.', 'codecanvas' ) ); }
    if ( '' === trim( (string) $url ) ) { return home_url( '/' ); }
    $parts = wp_parse_url( $url );
    if ( ! $parts ) { return new WP_Error( 'cc_mcp_url', __( 'That URL could not be parsed.', 'codecanvas' ) ); }
    if ( empty( $parts['host'] ) ) {
        return home_url( '/' . ltrim( (string) $url, '/' ) );
    }
    if ( 0 !== strcasecmp( $parts['host'], $home['host'] ) ) {
        return new WP_Error( 'cc_mcp_url', __( 'render_url only fetches pages on this site.', 'codecanvas' ) );
    }
    if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
        return new WP_Error( 'cc_mcp_url', __( 'Credentials in the URL are not accepted.', 'codecanvas' ) );
    }
    $home_port = isset( $home['port'] ) ? (int) $home['port'] : 0;
    $url_port  = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
    if ( $url_port !== $home_port ) {
        // A different port could reach an unrelated local service on the same host.
        return new WP_Error( 'cc_mcp_url', __( 'render_url only uses the port this site is served on.', 'codecanvas' ) );
    }
    $scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
    if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
        return new WP_Error( 'cc_mcp_url', __( 'Only http and https URLs are supported.', 'codecanvas' ) );
    }
    return $url;
}

/** Tool: render a page on this site. */
function codecanvas_mcp_tool_render_url( $args ) {
    $args = is_array( $args ) ? $args : array();
    $raw = codecanvas_mcp_arg_string( $args, 'url', false, 2000 );
    if ( is_wp_error( $raw ) ) { return $raw; }
    $url = codecanvas_mcp_local_url( $raw );
    if ( is_wp_error( $url ) ) { return $url; }
    $include_body = codecanvas_mcp_arg_bool( $args, 'include_body', true );
    $max_bytes    = codecanvas_mcp_arg_int( $args, 'max_bytes', 131072, 1024, 262144 );
    if ( is_wp_error( $max_bytes ) ) { return $max_bytes; }
    $started  = microtime( true );
    $response = wp_remote_get( $url, array(
        'timeout'             => 20,
        'redirection'         => 3,
        'limit_response_size' => 1048576,
        'headers'             => array(
            'User-Agent' => 'CodeCanvas-MCP/' . CODECANVAS_VERSION,
            'Accept'     => 'text/html,application/xhtml+xml,application/json;q=0.8,*/*;q=0.5',
        ),
    ) );
    $elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );
    if ( is_wp_error( $response ) ) {
        /* translators: %s: HTTP error message. */
        return new WP_Error( 'cc_mcp_render', sprintf( __( 'The site could not be reached: %s. On some hosts the server cannot call its own public URL. Check the page in a browser and use get_php_error_log for runtime errors.', 'codecanvas' ), $response->get_error_message() ) );
    }
    $status   = (int) wp_remote_retrieve_response_code( $response );
    $ctype    = (string) wp_remote_retrieve_header( $response, 'content-type' );
    $body     = (string) wp_remote_retrieve_body( $response );
    $bytes    = strlen( $body );
    $title    = '';
    if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $matches ) ) {
        $title = trim( wp_strip_all_tags( $matches[1] ) );
    }
    $error_markers = array( 'Fatal error', 'There has been a critical error on this website', 'Parse error', 'Uncaught Error' );
    $found_error   = '';
    foreach ( $error_markers as $marker ) {
        if ( false !== stripos( $body, $marker ) ) { $found_error = $marker; break; }
    }
    $truncated = false;
    if ( $include_body && strlen( $body ) > $max_bytes ) {
        $body      = substr( $body, 0, $max_bytes );
        $truncated = true;
    }
    $data = array(
        'url'          => $url,
        'status'       => $status,
        'content_type' => $ctype,
        'bytes'        => $bytes,
        'title'        => $title,
        'php_error'    => '' !== $found_error ? $found_error : null,
        'elapsed_ms'   => $elapsed,
    );
    if ( $include_body ) {
        $data['truncated'] = $truncated;
        $data['body']      = $body;
    }
    $summary = sprintf( '%s returned HTTP %d in %d ms', $url, $status, $elapsed );
    if ( '' !== $found_error ) {
        $summary .= sprintf( '. A PHP error notice (%s) was found in the response; check get_php_error_log.', $found_error );
    }
    return array( 'summary' => $summary, 'data' => $data );
}

/** Path of the PHP error log, when it is one of the two safe locations. */
function codecanvas_mcp_error_log_path() {
    $candidates = array();
    if ( defined( 'WP_DEBUG_LOG' ) ) {
        if ( true === WP_DEBUG_LOG ) {
            $candidates[] = WP_CONTENT_DIR . '/debug.log';
        } elseif ( is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
            $candidates[] = WP_DEBUG_LOG;
        }
    }
    $ini = ini_get( 'error_log' );
    if ( is_string( $ini ) && '' !== trim( $ini ) ) { $candidates[] = $ini; }
    $candidates[] = WP_CONTENT_DIR . '/debug.log';
    foreach ( $candidates as $candidate ) {
        $real = realpath( $candidate );
        if ( ! $real || ! is_file( $real ) ) { continue; }
        $allowed = false;
        foreach ( array( WP_CONTENT_DIR, ABSPATH ) as $base ) {
            $base_real = realpath( $base );
            if ( $base_real && ( $real === $base_real || 0 === strpos( $real, rtrim( $base_real, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) { $allowed = true; break; }
        }
        if ( ! $allowed ) { continue; }
        if ( preg_match( '/(?:\.env|wp-config\.php|id_rsa|\.htaccess|\.user\.ini)$/i', $real ) ) { continue; }
        return $real;
    }
    return '';
}

/** Tool: PHP error log tail. */
function codecanvas_mcp_tool_get_php_error_log( $args ) {
    $args  = is_array( $args ) ? $args : array();
    $lines = codecanvas_mcp_arg_int( $args, 'lines', 100, 1, 500 );
    if ( is_wp_error( $lines ) ) { return $lines; }
    $path = codecanvas_mcp_error_log_path();
    if ( '' === $path ) {
        // No log is a normal state, not a tool failure. Return an empty tail with
        // instructions so a client can continue instead of aborting the build.
        return array(
            'summary' => 'No PHP error log is available yet. This is normal on sites without WP_DEBUG_LOG.',
            'data'    => array(
                'path'      => '',
                'bytes'     => 0,
                'modified'  => '',
                'lines'     => array(),
                'truncated' => false,
                'available' => false,
                'hint'      => __( 'To capture runtime PHP errors, add define( \'WP_DEBUG\', true ); and define( \'WP_DEBUG_LOG\', true ); to wp-config.php only while debugging, reproduce the problem, then read this tool again. Turn both constants off afterwards.', 'codecanvas' ),
            ),
        );
    }
    $size = (int) filesize( $path );
    $read = min( 262144, max( 1, $size ) );
    $chunk = $size > $read ? (string) file_get_contents( $path, false, null, $size - $read, $read ) : (string) file_get_contents( $path );
    if ( '' === $chunk && $size > 0 ) { return new WP_Error( 'cc_mcp_log', __( 'The error log exists but is not readable by PHP.', 'codecanvas' ) ); }
    $split = preg_split( "/\r\n|\n|\r/", $chunk );
    $split = is_array( $split ) ? array_values( array_filter( $split, 'strlen' ) ) : array();
    $tail  = array_slice( $split, -1 * $lines );
    return array(
        'summary' => sprintf( 'Returned the last %d line(s) of %s.', count( $tail ), str_replace( ABSPATH, '', $path ) ),
        'data'    => array(
            'path'     => str_replace( ABSPATH, '', $path ),
            'bytes'    => $size,
            'modified' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $path ) ) . ' UTC',
            'lines'    => $tail,
            'truncated'=> $size > $read,
            'available'=> true,
        ),
    );
}

/** Tool: delete or trash a page or post. */
function codecanvas_mcp_tool_delete_content( $args ) {
    $args = is_array( $args ) ? $args : array();
    $allowed = codecanvas_mcp_content_write_allowed();
    if ( is_wp_error( $allowed ) ) { return $allowed; }
    $id = codecanvas_mcp_arg_int( $args, 'id', 0, 1, PHP_INT_MAX );
    if ( is_wp_error( $id ) || ! $id ) { return is_wp_error( $id ) ? $id : new WP_Error( 'cc_mcp_param', __( 'Provide a valid post ID.', 'codecanvas' ) ); }
    if ( ! codecanvas_mcp_arg_bool( $args, 'confirm', false ) ) {
        return new WP_Error( 'cc_mcp_confirm', __( 'Refusing to delete: pass confirm: true. Content is moved to the trash unless force is true.', 'codecanvas' ) );
    }
    $force = codecanvas_mcp_arg_bool( $args, 'force', false );
    $post  = get_post( $id );
    if ( ! $post || ! in_array( $post->post_type, array_keys( codecanvas_mcp_content_post_types() ), true ) ) {
        return new WP_Error( 'cc_mcp_content', __( 'That content record does not exist.', 'codecanvas' ) );
    }
    if ( 'trash' === $post->post_status && ! $force ) {
        return new WP_Error( 'cc_mcp_trashed', __( 'That item is already in the trash. Pass force: true to delete it permanently, or update_content with status draft to restore it.', 'codecanvas' ) );
    }
    if ( ! current_user_can( 'delete_post', $post->ID ) ) {
        return new WP_Error( 'cc_mcp_cap', __( 'You do not have permission to delete that content.', 'codecanvas' ) );
    }
    $previous = $post->post_status;
    if ( $force ) {
        if ( ! current_user_can( 'delete_post', $post->ID ) ) {
            return new WP_Error( 'cc_mcp_cap', __( 'You do not have permission to delete that content permanently.', 'codecanvas' ) );
        }
        $done = wp_delete_post( $post->ID, true );
        if ( ! $done ) { return new WP_Error( 'cc_mcp_delete', __( 'Permanent deletion failed.', 'codecanvas' ) ); }
        return array(
            'summary' => sprintf( 'Permanently deleted %s #%d (%s). This cannot be undone.', $post->post_type, $post->ID, $previous ),
            'data'    => array( 'id' => (int) $post->ID, 'type' => $post->post_type, 'action' => 'deleted', 'previous_status' => $previous ),
        );
    }
    $trashed = wp_trash_post( $post->ID );
    if ( ! $trashed ) { return new WP_Error( 'cc_mcp_delete', __( 'The item could not be moved to the trash.', 'codecanvas' ) ); }
    return array(
        'summary' => sprintf( 'Moved %s #%d to the trash. Restore it with update_content status draft, or pass force: true to delete permanently.', $post->post_type, $post->ID ),
        'data'    => array( 'id' => (int) $post->ID, 'type' => $post->post_type, 'action' => 'trashed', 'previous_status' => $previous ),
    );
}

/** Tool: update site level settings. */
function codecanvas_mcp_tool_update_settings( $args ) {
    $args = is_array( $args ) ? $args : array();
    $allowed = codecanvas_mcp_content_write_allowed();
    if ( is_wp_error( $allowed ) ) { return $allowed; }
    if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'cc_mcp_cap', __( 'You do not have permission to change site settings.', 'codecanvas' ) ); }
    $changes = array();
    $title = codecanvas_mcp_arg_string( $args, 'title', false, 300 );
    if ( is_wp_error( $title ) ) { return $title; }
    if ( '' !== $title ) { update_option( 'blogname', $title ); $changes[] = 'title'; }
    if ( array_key_exists( 'tagline', $args ) ) {
        if ( ! is_string( $args['tagline'] ) ) { return new WP_Error( 'cc_mcp_param', __( 'tagline must be a string; use an empty string to clear it.', 'codecanvas' ) ); }
        $tagline = substr( $args['tagline'], 0, 500 );
        update_option( 'blogdescription', $tagline );
        $changes[] = 'tagline';
    }
    if ( isset( $args['show_on_front'] ) ) {
        $mode = is_string( $args['show_on_front'] ) ? $args['show_on_front'] : '';
        if ( ! in_array( $mode, array( 'posts', 'page' ), true ) ) { return new WP_Error( 'cc_mcp_param', __( 'show_on_front must be posts or page.', 'codecanvas' ) ); }
        if ( 'page' === $mode && ! ( isset( $args['page_on_front'] ) ? absint( $args['page_on_front'] ) : (int) get_option( 'page_on_front' ) ) ) {
            return new WP_Error( 'cc_mcp_param', __( 'A static front page needs page_on_front set to a page ID.', 'codecanvas' ) );
        }
        update_option( 'show_on_front', $mode );
        $changes[] = 'show_on_front';
    }
    foreach ( array( 'page_on_front' => 'page', 'page_for_posts' => 'page' ) as $option => $expected_type ) {
        if ( ! array_key_exists( $option, $args ) ) { continue; }
        $page_id = absint( $args[ $option ] );
        if ( $page_id && 'page' !== get_post_type( $page_id ) ) {
            /* translators: %s: parameter name. */
            return new WP_Error( 'cc_mcp_param', sprintf( __( '%s must be the ID of an existing page.', 'codecanvas' ), $option ) );
        }
        update_option( $option, $page_id );
        $changes[] = $option;
    }
    if ( isset( $args['posts_per_page'] ) ) {
        $per_page = codecanvas_mcp_arg_int( $args, 'posts_per_page', 10, 1, 100 );
        if ( is_wp_error( $per_page ) ) { return $per_page; }
        update_option( 'posts_per_page', $per_page );
        $changes[] = 'posts_per_page';
    }
    if ( isset( $args['permalink_structure'] ) && is_string( $args['permalink_structure'] ) ) {
        $structure = sanitize_option( 'permalink_structure', $args['permalink_structure'] );
        update_option( 'permalink_structure', $structure );
        $changes[] = 'permalink_structure';
    }
    if ( empty( $changes ) ) { return new WP_Error( 'cc_mcp_param', __( 'Pass at least one setting to change.', 'codecanvas' ) ); }
    flush_rewrite_rules( false );
    $snapshot = codecanvas_mcp_tool_get_settings( array() );
    return array(
        'summary' => sprintf( 'Updated %s and flushed rewrite rules.', implode( ', ', array_unique( $changes ) ) ),
        'data'    => array( 'changed' => array_values( array_unique( $changes ) ), 'settings' => $snapshot['data'] ),
    );
}

/** Tool: create or change a navigation menu. */
function codecanvas_mcp_tool_update_menu( $args ) {
    $args = is_array( $args ) ? $args : array();
    $allowed = codecanvas_mcp_content_write_allowed();
    if ( is_wp_error( $allowed ) ) { return $allowed; }
    if ( ! current_user_can( 'edit_theme_options' ) ) { return new WP_Error( 'cc_mcp_cap', __( 'You do not have permission to manage menus.', 'codecanvas' ) ); }
    $action = codecanvas_mcp_arg_string( $args, 'action', true, 20 );
    if ( is_wp_error( $action ) ) { return $action; }
    $menu_id = codecanvas_mcp_arg_int( $args, 'menu_id', 0, 0, PHP_INT_MAX );
    if ( is_wp_error( $menu_id ) ) { return $menu_id; }

    if ( 'create' === $action ) {
        $name = codecanvas_mcp_arg_string( $args, 'name', true, 200 );
        if ( is_wp_error( $name ) ) { return $name; }
        $created = wp_create_nav_menu( $name );
        if ( is_wp_error( $created ) ) { return $created; }
        return array( 'summary' => sprintf( 'Created menu "%s" (#%d).', $name, (int) $created ), 'data' => array( 'action' => 'create', 'menu_id' => (int) $created, 'name' => $name ) );
    }

    if ( 'delete_menu' === $action ) {
        if ( ! codecanvas_mcp_arg_bool( $args, 'confirm', false ) ) { return new WP_Error( 'cc_mcp_confirm', __( 'Pass confirm: true to delete this menu.', 'codecanvas' ) ); }
        if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) { return new WP_Error( 'cc_mcp_menu', __( 'That menu does not exist.', 'codecanvas' ) ); }
        $done = wp_delete_nav_menu( $menu_id );
        if ( is_wp_error( $done ) || ! $done ) { return new WP_Error( 'cc_mcp_menu', __( 'The menu could not be deleted.', 'codecanvas' ) ); }
        return array( 'summary' => 'Menu deleted.', 'data' => array( 'action' => 'delete_menu', 'menu_id' => (int) $menu_id ) );
    }

    if ( 'assign_location' === $action ) {
        $location = codecanvas_mcp_arg_string( $args, 'location', true, 100 );
        if ( is_wp_error( $location ) ) { return $location; }
        $registered = get_registered_nav_menus();
        if ( ! isset( $registered[ $location ] ) ) {
            /* translators: %s: comma-separated theme location names. */
            return new WP_Error( 'cc_mcp_menu', sprintf( __( 'Unknown theme location. Available: %s', 'codecanvas' ), implode( ', ', array_keys( $registered ) ) ) );
        }
        if ( $menu_id && ! wp_get_nav_menu_object( $menu_id ) ) { return new WP_Error( 'cc_mcp_menu', __( 'That menu does not exist.', 'codecanvas' ) ); }
        $locations = get_nav_menu_locations();
        $locations[ $location ] = (int) $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
        return array( 'summary' => sprintf( 'Location %s now uses menu #%d.', $location, (int) $menu_id ), 'data' => array( 'action' => 'assign_location', 'location' => $location, 'menu_id' => (int) $menu_id ) );
    }

    if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) {
        return new WP_Error( 'cc_mcp_menu', __( 'menu_id is required and must reference an existing menu. Call list_menus first.', 'codecanvas' ) );
    }

    if ( 'remove_item' === $action ) {
        if ( ! codecanvas_mcp_arg_bool( $args, 'confirm', false ) ) { return new WP_Error( 'cc_mcp_confirm', __( 'Pass confirm: true to remove this menu item.', 'codecanvas' ) ); }
        $item_id = codecanvas_mcp_arg_int( $args, 'item_id', 0, 1, PHP_INT_MAX );
        if ( is_wp_error( $item_id ) || ! $item_id ) { return is_wp_error( $item_id ) ? $item_id : new WP_Error( 'cc_mcp_param', __( 'item_id is required.', 'codecanvas' ) ); }
        if ( 'nav_menu_item' !== get_post_type( $item_id ) ) { return new WP_Error( 'cc_mcp_menu', __( 'That ID is not a menu item.', 'codecanvas' ) ); }
        $done = wp_delete_post( $item_id, true );
        if ( ! $done ) { return new WP_Error( 'cc_mcp_menu', __( 'The menu item could not be removed.', 'codecanvas' ) ); }
        return array( 'summary' => 'Menu item removed.', 'data' => array( 'action' => 'remove_item', 'menu_id' => (int) $menu_id, 'item_id' => (int) $item_id ) );
    }

    if ( 'add_item' === $action || 'update_item' === $action ) {
        $item_id = 0;
        if ( 'add_item' === $action ) {
            $title = codecanvas_mcp_arg_string( $args, 'title', true, 300 );
            if ( is_wp_error( $title ) ) { return $title; }
        } else {
            $item_id = codecanvas_mcp_arg_int( $args, 'item_id', 0, 1, PHP_INT_MAX );
            if ( is_wp_error( $item_id ) || ! $item_id ) { return is_wp_error( $item_id ) ? $item_id : new WP_Error( 'cc_mcp_param', __( 'item_id is required for update_item.', 'codecanvas' ) ); }
            if ( 'nav_menu_item' !== get_post_type( $item_id ) ) { return new WP_Error( 'cc_mcp_menu', __( 'That ID is not a menu item.', 'codecanvas' ) ); }
        }
        $item_args = array( 'menu-item-status' => 'publish' );
        $title = codecanvas_mcp_arg_string( $args, 'title', false, 300 );
        if ( is_wp_error( $title ) ) { return $title; }
        if ( '' !== $title ) { $item_args['menu-item-title'] = $title; }
        $url = codecanvas_mcp_arg_string( $args, 'url', false, 2000 );
        if ( is_wp_error( $url ) ) { return $url; }
        if ( '' !== $url ) { $item_args['menu-item-url'] = $url; }
        $object = codecanvas_mcp_arg_string( $args, 'object', false, 40 );
        if ( is_wp_error( $object ) ) { return $object; }
        if ( '' !== $object ) { $item_args['menu-item-object'] = $object; }
        if ( isset( $args['object_id'] ) ) {
            $object_id = codecanvas_mcp_arg_int( $args, 'object_id', 0, 0, PHP_INT_MAX );
            if ( is_wp_error( $object_id ) ) { return $object_id; }
            $item_args['menu-item-object-id'] = $object_id;
            $item_args['menu-item-type'] = 'post_type';
        }
        if ( isset( $args['parent_item_id'] ) ) {
            $parent = codecanvas_mcp_arg_int( $args, 'parent_item_id', 0, 0, PHP_INT_MAX );
            if ( is_wp_error( $parent ) ) { return $parent; }
            $item_args['menu-item-parent-id'] = $parent;
        }
        if ( isset( $args['position'] ) ) {
            $position = codecanvas_mcp_arg_int( $args, 'position', 0, 0, 10000 );
            if ( is_wp_error( $position ) ) { return $position; }
            $item_args['menu-item-position'] = $position;
        }
        if ( 'add_item' === $action ) {
            // Custom links need a URL or a linked object; default to # so the item is valid.
            if ( ! isset( $item_args['menu-item-url'] ) ) { $item_args['menu-item-url'] = '#'; }
            if ( ! isset( $item_args['menu-item-type'] ) ) { $item_args['menu-item-type'] = 'custom'; }
        }
        $result = wp_update_nav_menu_item( $menu_id, $item_id, $item_args );
        if ( is_wp_error( $result ) ) { return $result; }
        return array(
            'summary' => sprintf( '%s menu item #%d in menu #%d.', 'add_item' === $action ? 'Added' : 'Updated', (int) $result, (int) $menu_id ),
            'data'    => array( 'action' => $action, 'menu_id' => (int) $menu_id, 'item_id' => (int) $result ),
        );
    }

    return new WP_Error( 'cc_mcp_param', __( 'Unsupported menu action.', 'codecanvas' ) );
}

/** Tool: upload media from base64. */
function codecanvas_mcp_tool_upload_media( $args ) {
    $args = is_array( $args ) ? $args : array();
    $allowed = codecanvas_mcp_content_write_allowed();
    if ( is_wp_error( $allowed ) ) { return $allowed; }
    if ( ! current_user_can( 'upload_files' ) ) { return new WP_Error( 'cc_mcp_cap', __( 'This account cannot upload media.', 'codecanvas' ) ); }
    $filename = codecanvas_mcp_arg_string( $args, 'filename', true, 200 );
    if ( is_wp_error( $filename ) ) { return $filename; }
    $filename = sanitize_file_name( basename( $filename ) );
    $encoded  = codecanvas_mcp_arg_string( $args, 'data_base64', true, 1048576 );
    if ( is_wp_error( $encoded ) ) { return $encoded; }
    $binary = base64_decode( preg_replace( '/\s+/', '', $encoded ), true );
    if ( false === $binary || '' === $binary ) { return new WP_Error( 'cc_mcp_media', __( 'data_base64 is not valid base64 data.', 'codecanvas' ) ); }
    if ( strlen( $binary ) > 700000 ) { return new WP_Error( 'cc_mcp_media', __( 'Media uploads are limited to 700 KB per call. Compress the file or upload it in wp-admin.', 'codecanvas' ) ); }
    $check = wp_check_filetype( $filename );
    if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
        return new WP_Error( 'cc_mcp_media', __( 'That file type is not allowed by WordPress on this site.', 'codecanvas' ) );
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $upload = wp_upload_bits( $filename, null, $binary );
    if ( ! empty( $upload['error'] ) ) { return new WP_Error( 'cc_mcp_media', $upload['error'] ); }
    $verified = wp_check_filetype_and_ext( $upload['file'], $filename );
    if ( empty( $verified['type'] ) ) {
        wp_delete_file( $upload['file'] );
        return new WP_Error( 'cc_mcp_media', __( 'The file contents do not match an allowed image or document type.', 'codecanvas' ) );
    }
    $title = codecanvas_mcp_arg_string( $args, 'title', false, 300 );
    if ( is_wp_error( $title ) ) { return $title; }
    $parent = codecanvas_mcp_arg_int( $args, 'post_parent', 0, 0, PHP_INT_MAX );
    if ( is_wp_error( $parent ) ) { return $parent; }
    $attachment_id = wp_insert_attachment( array(
        'post_mime_type' => $verified['type'],
        'post_title'     => '' !== $title ? $title : preg_replace( '/\.[^.]+$/', '', $filename ),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_parent'    => $parent,
    ), $upload['file'], $parent, true );
    if ( is_wp_error( $attachment_id ) ) { wp_delete_file( $upload['file'] ); return $attachment_id; }
    $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
    if ( is_array( $metadata ) ) { wp_update_attachment_metadata( $attachment_id, $metadata ); }
    $alt = codecanvas_mcp_arg_string( $args, 'alt_text', false, 500 );
    if ( ! is_wp_error( $alt ) && '' !== $alt ) { update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt ); }
    $data = array(
        'id'       => (int) $attachment_id,
        'url'      => wp_get_attachment_url( $attachment_id ),
        'mime'     => $verified['type'],
        'filename' => $filename,
        'bytes'    => strlen( $binary ),
    );
    if ( is_array( $metadata ) && ! empty( $metadata['width'] ) ) {
        $data['width']  = (int) $metadata['width'];
        $data['height'] = (int) $metadata['height'];
    }
    return array( 'summary' => sprintf( 'Uploaded %s as attachment #%d.', $filename, (int) $attachment_id ), 'data' => $data );
}
