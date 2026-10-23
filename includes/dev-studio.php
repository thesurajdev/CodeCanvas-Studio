<?php
/**
 * CodeCanvas Studio: guarded source-file development workspace.
 *
 * This plugin-bundled developer tool is for trusted administrators. Website work
 * defaults to the generated website theme; CodeCanvas plugin files are read-only in
 * Studio. Eligible plugin text files may be edited when WordPress policy and
 * filesystem rules allow it.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Query arguments only select or display admin screen state; every state change is a nonce-protected POST request.

/** Register the CodeCanvas menu: branded welcome screen, workspace and settings. */
function codecanvas_studio_menu() {
    add_menu_page(
        __( 'CodeCanvas', 'codecanvas' ),
        __( 'CodeCanvas', 'codecanvas' ),
        'manage_options',
        'codecanvas',
        'codecanvas_admin_welcome_page',
        'dashicons-editor-code',
        58
    );
    add_submenu_page(
        'codecanvas',
        __( 'Code Studio', 'codecanvas' ),
        __( 'Code Studio', 'codecanvas' ),
        'manage_options',
        'codecanvas-studio',
        'codecanvas_studio_render'
    );
    // WordPress repeats the parent as the first submenu. Give that entry the
    // welcome page's label instead of a second "CodeCanvas" row.
    global $submenu;
    if ( isset( $submenu['codecanvas'][0] ) && is_array( $submenu['codecanvas'][0] ) ) {
        $submenu['codecanvas'][0][0] = __( 'Welcome', 'codecanvas' );
    }
}
add_action( 'admin_menu', 'codecanvas_studio_menu', 15 );

/** Keep the CodeCanvas sidebar focused: welcome, Code Studio, the page editor and MCP settings. */
function codecanvas_cleanup_legacy_submenus() {
    global $submenu;
    if ( ! isset( $submenu['codecanvas'] ) || ! is_array( $submenu['codecanvas'] ) ) {
        return;
    }
    foreach ( $submenu['codecanvas'] as $item ) {
        $slug = isset( $item[2] ) ? (string) $item[2] : '';
        // Anything not in this list is a leftover registration from an older
        // version of the plugin and has no screen behind it any more.
        if ( ! in_array( $slug, array( 'codecanvas', 'codecanvas-studio', 'codecanvas-settings' ), true ) ) {
            remove_submenu_page( 'codecanvas', $slug );
        }
    }
}
add_action( 'admin_menu', 'codecanvas_cleanup_legacy_submenus', 999 );

/** Add editor-specific body class without changing the rest of wp-admin. */
function codecanvas_studio_body_class( $classes ) {
    if ( isset( $_GET['page'] ) && 'codecanvas-studio' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
        $classes .= ' codecanvas-studio-admin';
    }
    return $classes;
}
add_filter( 'admin_body_class', 'codecanvas_studio_body_class' );

/** File extensions supported by the in-dashboard text editor. */
function codecanvas_studio_allowed_extensions() {
    return array( 'php', 'phtml', 'css', 'scss', 'sass', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'html', 'htm', 'json', 'md', 'txt', 'xml', 'svg', 'yml', 'yaml', 'sql', 'graphql', 'gql', 'twig', 'vue', 'sh', 'conf', 'ini', 'dist', 'sample' );
}

/** File basenames which should never be handled by this workspace. */
function codecanvas_studio_blocked_names() {
    return array( 'wp-config.php', 'wp-config-sample.php', '.env', '.env.local', '.htaccess', '.user.ini', 'id_rsa', 'id_ed25519', 'authorized_keys', 'known_hosts' );
}

/** Check access to a root and respect WordPress's file-modification policy. */
function codecanvas_studio_can_access_root( $root, $write = false ) {
    if ( ! current_user_can( 'manage_options' ) ) { return false; }
    if ( ! in_array( $root, array( 'themes', 'plugins' ), true ) ) { return false; }
    $capability = ( 'themes' === $root ) ? 'edit_themes' : 'edit_plugins';
    if ( ! current_user_can( $capability ) ) { return false; }
    if ( $write && defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) { return false; }
    if ( $write && defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) { return false; }
    if ( $write && function_exists( 'wp_is_file_mod_allowed' ) && ! wp_is_file_mod_allowed( $capability ) ) { return false; }
    $base = ( 'themes' === $root ) ? trailingslashit( WP_CONTENT_DIR ) . 'themes' : WP_PLUGIN_DIR;
    // Root-level writability is checked per operation/parent directory. Existing files may be writable even if the root folder is not.
    return is_dir( $base ) && is_readable( $base );
}

/** Get canonical root path. */
function codecanvas_studio_root_path( $root ) {
    if ( ! in_array( $root, array( 'themes', 'plugins' ), true ) ) { return ''; }
    $path = ( 'themes' === $root ) ? trailingslashit( WP_CONTENT_DIR ) . 'themes' : WP_PLUGIN_DIR;
    $real = realpath( $path );
    return $real ? wp_normalize_path( $real ) : '';
}

/** Slug of the CodeCanvas builder plugin, whose source is read-only in Studio. */
function codecanvas_studio_framework_slug() {
    return basename( untrailingslashit( wp_normalize_path( CODECANVAS_DIR ) ) );
}

/** Slug of the CodeCanvas builder plugin (legacy helper name). */
function codecanvas_studio_parent_theme_slug() {
    return codecanvas_studio_framework_slug();
}

/** Name of the website theme created and edited by CodeCanvas Studio. */
function codecanvas_studio_site_child_slug() {
    return 'codecanvas-site';
}

/** Absolute path of the generated website theme. */
function codecanvas_studio_site_child_path() {
    $themes_root = codecanvas_studio_root_path( 'themes' );
    return $themes_root ? trailingslashit( $themes_root ) . codecanvas_studio_site_child_slug() : '';
}

/** True when the generated CodeCanvas Site theme exists as a standalone theme. */
function codecanvas_studio_site_child_exists() {
    $path = codecanvas_studio_site_child_path();
    if ( ! $path || is_link( $path ) || ! is_dir( $path ) || ! is_file( $path . '/style.css' ) ) { return false; }
    $headers = get_file_data( $path . '/style.css', array( 'Template' => 'Template' ), 'theme' );
    // A standalone theme has no Template header. A legacy child theme must be
    // migrated by CodeCanvas before Studio treats it as the website theme.
    return empty( $headers['Template'] );
}

/** True when the generated CodeCanvas Site theme is the active theme. */
function codecanvas_studio_site_child_active() {
    return codecanvas_studio_site_child_exists()
        && get_stylesheet() === codecanvas_studio_site_child_slug()
        && get_template() === codecanvas_studio_site_child_slug();
}

/** Protect CodeCanvas framework files from accidental editing in the website workspace. */
function codecanvas_studio_is_framework_path( $root, $relative ) {
    if ( 'plugins' !== $root ) { return false; }
    $relative = wp_normalize_path( trim( (string) $relative, '/' ) );
    if ( '' === $relative ) { return false; }
    $first = explode( '/', $relative, 2 );
    // Plugin folder names are case-insensitive on Windows and macOS filesystems, so a
    // path such as codecanvas/includes/admin.php resolves to the protected plugin.
    // Compare the first segment case-insensitively so the read-only rule cannot be bypassed.
    return isset( $first[0] ) && 0 === strcasecmp( $first[0], codecanvas_studio_framework_slug() );
}

/** True when a resolved path lives inside the CodeCanvas plugin directory. */
function codecanvas_studio_is_framework_target( $root, $relative ) {
    if ( codecanvas_studio_is_framework_path( $root, $relative ) ) { return true; }
    $resolved = codecanvas_studio_resolve( $root, $relative, true );
    if ( is_wp_error( $resolved ) ) { return false; }
    $framework = realpath( CODECANVAS_DIR );
    $target    = realpath( $resolved );
    if ( ! $target ) {
        // New files do not exist yet; their parent directory still proves the location.
        $target = realpath( dirname( $resolved ) );
    }
    if ( ! $framework || ! $target ) { return false; }
    $framework = rtrim( wp_normalize_path( $framework ), '/' );
    $target    = wp_normalize_path( $target );
    return 0 === strcasecmp( $target, $framework ) || 0 === stripos( $target, $framework . '/' );
}

/** Create the standalone website theme without overwriting any existing user files. */
function codecanvas_studio_create_site_child_files() {
    $root = codecanvas_studio_root_path( 'themes' );
    $slug = codecanvas_studio_site_child_slug();
    if ( ! $root || ! wp_is_writable( $root ) ) {
        return new WP_Error( 'cc_child_unwritable_root', __( 'The themes folder is not writable. Create the website theme with VS Code/SFTP, or fix hosting permissions through your normal deployment workflow.', 'codecanvas' ) );
    }
    $child = trailingslashit( $root ) . $slug;
    if ( is_link( $child ) ) { return new WP_Error( 'cc_child_symlink', __( 'The proposed website-theme folder is a symbolic link and cannot be used.', 'codecanvas' ) ); }
    if ( file_exists( $child ) && ! is_dir( $child ) ) { return new WP_Error( 'cc_child_path', __( 'A file already exists at the website-theme folder path. Rename it before continuing.', 'codecanvas' ) ); }
    if ( is_dir( $child ) && is_file( $child . '/style.css' ) ) {
        $headers = get_file_data( $child . '/style.css', array( 'Template' => 'Template' ), 'theme' );
        if ( ! empty( $headers['Template'] ) ) {
            return new WP_Error( 'cc_child_conflict', __( 'The codecanvas-site folder is still a child theme. Convert it to a standalone website theme before using CodeCanvas Studio.', 'codecanvas' ) );
        }
    }
    if ( ! file_exists( $child ) && ! wp_mkdir_p( $child ) ) { return new WP_Error( 'cc_child_create_dir', __( 'Could not create the website-theme folder. Check filesystem permissions.', 'codecanvas' ) ); }

    $source = trailingslashit( CODECANVAS_DIR ) . 'site-theme';
    if ( ! is_dir( $source ) ) { return new WP_Error( 'cc_child_source', __( 'The bundled website-theme starter is missing from the CodeCanvas plugin.', 'codecanvas' ) ); }

    $created  = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ( $iterator as $file ) {
        if ( ! $file->isFile() || $file->isLink() ) { continue; }
        $relative = ltrim( wp_normalize_path( substr( $file->getPathname(), strlen( $source ) ) ), '/' );
        if ( '' === $relative || preg_match( '#(^|/)(\.git|node_modules|dist|screenshots)(/|$)#', $relative ) ) { continue; }
        $target = $child . '/' . $relative;
        if ( file_exists( $target ) ) { continue; } // Never overwrite user files.
        $parent = dirname( $target );
        /* translators: %s: relative file or folder path. */
        if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) { return new WP_Error( 'cc_child_mkdir', sprintf( __( 'Could not create folder for %s.', 'codecanvas' ), $relative ) ); }
        if ( ! wp_is_writable( $parent ) || ! copy( $file->getPathname(), $target ) ) {
            /* translators: %s: relative file path. */
            return new WP_Error( 'cc_child_write', sprintf( __( 'Could not create %s. Check filesystem permissions; existing files were left untouched.', 'codecanvas' ), $relative ) );
        }
        $created[] = $relative;
    }
    // Validate the standalone theme header after seeding, before activation.
    if ( ! codecanvas_studio_site_child_exists() ) { return new WP_Error( 'cc_child_invalid', __( 'The website theme was created but its stylesheet header could not be validated. Review style.css before activation.', 'codecanvas' ) ); }
    return array( 'path' => $child, 'created' => $created );
}

/** Reject writes to CodeCanvas plugin files; site work belongs in the website theme. */
function codecanvas_studio_reject_framework_write( $root, $relative ) {
    if ( codecanvas_studio_is_framework_target( $root, $relative ) ) {
        wp_send_json_error( array( 'message' => __( 'CodeCanvas plugin files are read-only in Code Studio. Edit your website in the CodeCanvas Site theme; use a local Git checkout to develop CodeCanvas itself.', 'codecanvas' ) ), 403 );
    }
}

/** Carry logos, menus and header images into the generated website theme once. */
function codecanvas_studio_copy_theme_mods( $from_slug, $to_slug ) {
    if ( $from_slug === $to_slug || '' === $from_slug ) { return; }
    $source_mods = get_option( 'theme_mods_' . $from_slug, array() );
    $target_mods = get_option( 'theme_mods_' . $to_slug, array() );
    if ( ! is_array( $source_mods ) ) { return; }
    if ( ! is_array( $target_mods ) ) { $target_mods = array(); }
    $merged = $target_mods;
    foreach ( array( 'custom_logo', 'nav_menu_locations', 'header_image', 'header_image_data' ) as $key ) {
        // Only fill values the website theme does not already define.
        if ( ! empty( $source_mods[ $key ] ) && empty( $merged[ $key ] ) ) {
            $merged[ $key ] = $source_mods[ $key ];
        }
    }
    if ( $merged !== $target_mods ) {
        update_option( 'theme_mods_' . $to_slug, $merged );
    }
}

/** Create/activate the generated website theme through an explicit administrator action. */
function codecanvas_studio_ajax_setup_site_child_theme() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'switch_themes' ) || ! current_user_can( 'edit_themes' ) ) {
        wp_send_json_error( array( 'message' => __( 'You need administrator-level theme permissions to create and activate the CodeCanvas Site theme.', 'codecanvas' ) ), 403 );
    }

    if ( ! codecanvas_studio_site_child_exists() ) {
        if ( ! codecanvas_studio_can_access_root( 'themes', true ) ) {
            wp_send_json_error( array( 'message' => __( 'WordPress or hosting policy prevents theme file changes. Create the website theme using VS Code/SFTP and then return here.', 'codecanvas' ) ), 403 );
        }
        $previous_theme = get_stylesheet();
        $created = codecanvas_studio_create_site_child_files();
        if ( is_wp_error( $created ) ) {
            wp_send_json_error( array( 'message' => $created->get_error_message() ), 400 );
        }
        codecanvas_studio_copy_theme_mods( $previous_theme, codecanvas_studio_site_child_slug() );
    }

    wp_clean_themes_cache( true );
    switch_theme( codecanvas_studio_site_child_slug() );
    wp_clean_themes_cache( true );
    wp_send_json_success( array(
        'message'  => __( 'The CodeCanvas Site theme is active. Your website code now lives in its own theme, generated and edited by CodeCanvas Studio.', 'codecanvas' ),
        'redirect' => admin_url( 'admin.php?page=codecanvas-studio' ),
    ) );
}
add_action( 'wp_ajax_codecanvas_studio_setup_site_child_theme', 'codecanvas_studio_ajax_setup_site_child_theme' );

/** Validate and resolve a root-relative path; reject traversal and symlinks. */
function codecanvas_studio_resolve( $root, $relative, $allow_missing = false ) {
    $base = codecanvas_studio_root_path( $root );
    if ( ! $base ) { return new WP_Error( 'cc_bad_root', __( 'The selected source root is unavailable.', 'codecanvas' ) ); }
    if ( ! is_string( $relative ) ) { return new WP_Error( 'cc_bad_path', __( 'Invalid path.', 'codecanvas' ) ); }
    $relative = wp_normalize_path( trim( $relative ) );
    if ( false !== strpos( $relative, "\0" ) || '/' === substr( $relative, 0, 1 ) || preg_match( '/^[A-Za-z]:/', $relative ) ) {
        return new WP_Error( 'cc_bad_path', __( 'Absolute paths are not allowed.', 'codecanvas' ) );
    }
    $segments = ( '' === $relative ) ? array() : explode( '/', $relative );
    foreach ( $segments as $segment ) {
        if ( '' === $segment || '.' === $segment || '..' === $segment || false !== strpos( $segment, '\\' ) ) {
            return new WP_Error( 'cc_bad_path', __( 'The path contains an unsupported segment.', 'codecanvas' ) );
        }
    }
    $cursor = $base;
    $count  = count( $segments );
    foreach ( $segments as $i => $segment ) {
        if ( '' === $segment || '.' === $segment || '..' === $segment ) {
            return new WP_Error( 'cc_bad_path', __( 'Invalid path segment.', 'codecanvas' ) );
        }
        $cursor = $cursor . DIRECTORY_SEPARATOR . $segment;
        // Do not follow symlinks out of the permitted wp-content roots.
        if ( is_link( $cursor ) ) {
            return new WP_Error( 'cc_symlink', __( 'Symbolic links are not editable in CodeCanvas Studio.', 'codecanvas' ) );
        }
        if ( file_exists( $cursor ) ) {
            $real = realpath( $cursor );
            $base_n = rtrim( wp_normalize_path( $base ), '/' );
            $real_n = $real ? wp_normalize_path( $real ) : '';
            if ( ! $real || ( $real_n !== $base_n && 0 !== strpos( $real_n, $base_n . '/' ) ) ) {
                return new WP_Error( 'cc_outside_root', __( 'The path is outside the permitted source root.', 'codecanvas' ) );
            }
        } elseif ( ! $allow_missing || $i < $count - 1 ) {
            return new WP_Error( 'cc_not_found', __( 'The requested file or folder does not exist.', 'codecanvas' ) );
        }
    }
    $full = $base . ( $relative ? DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative ) : '' );
    if ( file_exists( $full ) ) {
        $real_full = realpath( $full );
        if ( ! $real_full ) { return new WP_Error( 'cc_not_found', __( 'The requested path could not be resolved.', 'codecanvas' ) ); }
        $real_full_n = wp_normalize_path( $real_full );
        $base_n      = rtrim( wp_normalize_path( $base ), '/' );
        if ( $real_full_n !== $base_n && 0 !== strpos( $real_full_n, $base_n . '/' ) ) {
            return new WP_Error( 'cc_outside_root', __( 'The requested path is outside the permitted source root.', 'codecanvas' ) );
        }
        $full = $real_full;
    } elseif ( $allow_missing ) {
        $parent = dirname( $full );
        $real_parent = realpath( $parent );
        $base_n = rtrim( wp_normalize_path( $base ), '/' );
        if ( ! $real_parent || ( wp_normalize_path( $real_parent ) !== $base_n && 0 !== strpos( wp_normalize_path( $real_parent ), $base_n . '/' ) ) ) {
            return new WP_Error( 'cc_bad_parent', __( 'The parent directory is not valid.', 'codecanvas' ) );
        }
    }
    return $full;
}

/** Only source-like files are eligible. */
function codecanvas_studio_is_allowed_file( $path ) {
    $basename = strtolower( basename( $path ) );
    if ( in_array( $basename, codecanvas_studio_blocked_names(), true ) || 0 === strpos( $basename, '.env' ) ) { return false; }
    if ( preg_match( '/(?:secret|credential|private[-_]?key|access[-_]?token)/i', $basename ) ) { return false; }
    $ext = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
    return in_array( $ext, codecanvas_studio_allowed_extensions(), true );
}

/** Create a bounded database snapshot before a destructive operation. */
function codecanvas_studio_make_backup( $root, $relative, $content, $reason = 'save' ) {
    if ( ! is_string( $content ) || strlen( $content ) > 1048576 ) { return false; }
    $key = 'codecanvas_studio_bk_' . md5( $root . ':' . $relative );
    $history = get_option( $key, array() );
    if ( ! is_array( $history ) ) { $history = array(); }
    array_unshift( $history, array(
        'time'    => time(),
        'user'    => get_current_user_id(),
        'reason'  => sanitize_key( $reason ),
        'sha256'  => hash( 'sha256', $content ),
        'content' => $content,
    ) );
    $history = array_slice( $history, 0, 3 );
    if ( get_option( $key, null ) === null ) { add_option( $key, $history, '', false ); }
    else { update_option( $key, $history, false ); }
    return true;
}

/** Decode and verify editor payloads. */
function codecanvas_studio_request() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'You do not have permission to use CodeCanvas Studio.', 'codecanvas' ) ), 403 ); }
    $root = isset( $_POST['root'] ) ? sanitize_key( wp_unslash( $_POST['root'] ) ) : '';
    if ( ! codecanvas_studio_can_access_root( $root, false ) ) {
        wp_send_json_error( array( 'message' => __( 'WordPress permissions or file-modification policy do not allow access to this source root. Check edit_themes/edit_plugins and DISALLOW_FILE_EDIT.', 'codecanvas' ) ), 403 );
    }
    return $root;
}

/** Read-only directory listing. */
function codecanvas_studio_ajax_list() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    $relative = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    $dir = codecanvas_studio_resolve( $root, $relative );
    if ( is_wp_error( $dir ) || ! is_dir( $dir ) || ! is_readable( $dir ) ) {
        wp_send_json_error( array( 'message' => is_wp_error( $dir ) ? $dir->get_error_message() : __( 'Folder is not readable.', 'codecanvas' ) ), 400 );
    }
    $items = array();
    $names = @scandir( $dir );
    if ( ! is_array( $names ) ) { wp_send_json_error( array( 'message' => __( 'Could not read this folder.', 'codecanvas' ) ), 500 ); }
    $skip_dirs = array( '.', '..', '.git', 'node_modules', '.svn', '.hg' );
    foreach ( $names as $name ) {
        if ( in_array( $name, $skip_dirs, true ) || is_link( $dir . DIRECTORY_SEPARATOR . $name ) ) { continue; }
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        if ( is_dir( $full ) ) {
            $items[] = array( 'name' => $name, 'type' => 'dir', 'path' => ltrim( trim( $relative, '/' ) . '/' . $name, '/' ), 'writable' => wp_is_writable( $full ) && ! codecanvas_studio_is_framework_path( $root, ltrim( trim( $relative, '/' ) . '/' . $name, '/' ) ) );
            continue;
        }
        if ( ! is_file( $full ) ) { continue; }
        if ( ! codecanvas_studio_is_allowed_file( $full ) ) { continue; }
        $items[] = array(
            'name'    => $name,
            'type'    => 'file',
            'path'    => ltrim( trim( $relative, '/' ) . '/' . $name, '/' ),
            'ext'     => strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
            'size'    => (int) filesize( $full ),
            'writable'=> wp_is_writable( $full ) && ! codecanvas_studio_is_framework_path( $root, ltrim( trim( $relative, '/' ) . '/' . $name, '/' ) ),
        );
    }
    usort( $items, function( $a, $b ) {
        if ( $a['type'] !== $b['type'] ) { return ( 'dir' === $a['type'] ) ? -1 : 1; }
        return strcasecmp( $a['name'], $b['name'] );
    } );
    wp_send_json_success( array( 'path' => trim( $relative, '/' ), 'items' => $items, 'root_label' => ( 'themes' === $root ) ? __( 'Themes', 'codecanvas' ) : __( 'Plugins', 'codecanvas' ) ) );
}
add_action( 'wp_ajax_codecanvas_studio_list', 'codecanvas_studio_ajax_list' );

/** Read a source file. */
function codecanvas_studio_ajax_read() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    $relative = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    $file = codecanvas_studio_resolve( $root, $relative );
    if ( is_wp_error( $file ) || ! is_file( $file ) || ! codecanvas_studio_is_allowed_file( $file ) || ! is_readable( $file ) ) {
        wp_send_json_error( array( 'message' => is_wp_error( $file ) ? $file->get_error_message() : __( 'This file type cannot be opened in the browser editor.', 'codecanvas' ) ), 400 );
    }
    if ( filesize( $file ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'This file is larger than 1 MB. Use your local editor for large files.', 'codecanvas' ) ), 413 ); }
    $content = file_get_contents( $file );
    if ( false === $content ) { wp_send_json_error( array( 'message' => __( 'Could not read file contents.', 'codecanvas' ) ), 500 ); }
    $key = 'codecanvas_studio_bk_' . md5( $root . ':' . ltrim( $relative, '/' ) );
    $history = get_option( $key, array() );
    $backups = array();
    if ( is_array( $history ) ) {
        foreach ( $history as $index => $entry ) {
            if ( ! is_array( $entry ) ) { continue; }
            $backups[] = array( 'index' => (int) $index, 'time' => isset( $entry['time'] ) ? (int) $entry['time'] : 0, 'reason' => isset( $entry['reason'] ) ? sanitize_key( $entry['reason'] ) : 'save', 'sha256' => isset( $entry['sha256'] ) ? $entry['sha256'] : '' );
        }
    }
    wp_send_json_success( array( 'path' => ltrim( $relative, '/' ), 'content' => $content, 'mtime' => filemtime( $file ), 'sha256' => hash( 'sha256', $content ), 'writable' => wp_is_writable( $file ) && ! codecanvas_studio_is_framework_path( $root, $relative ), 'backups' => $backups ) );
}
add_action( 'wp_ajax_codecanvas_studio_read', 'codecanvas_studio_ajax_read' );

/** Save source file using controlled direct writes after capability and path checks. */
function codecanvas_studio_ajax_save() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    if ( ! codecanvas_studio_can_access_root( $root, true ) ) { wp_send_json_error( array( 'message' => __( 'The selected source directory is not writable.', 'codecanvas' ) ), 403 ); }
    $relative = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    codecanvas_studio_reject_framework_write( $root, $relative );
    $file = codecanvas_studio_resolve( $root, $relative );
    if ( is_wp_error( $file ) || ! is_file( $file ) || ! codecanvas_studio_is_allowed_file( $file ) || ! wp_is_writable( $file ) ) {
        wp_send_json_error( array( 'message' => is_wp_error( $file ) ? $file->get_error_message() : __( 'This file cannot be edited by CodeCanvas.', 'codecanvas' ) ), 400 );
    }
    // Source contents must be written verbatim; only syntax-checked and secret-scanned, not HTML-sanitized.
    $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( ! is_string( $content ) ) { wp_send_json_error( array( 'message' => __( 'Missing file contents.', 'codecanvas' ) ), 400 ); }
    if ( strlen( $content ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'Files over 1 MB cannot be saved in the browser editor.', 'codecanvas' ) ), 413 ); }
    // Detect secrets in browser-bound or PHP code as a warning. PHP may legitimately
    // read environment values; do not block server-side integration code by default.
    $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
    if ( in_array( $ext, array( 'html', 'htm', 'css', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'svg' ), true ) && function_exists( 'codecanvas_contains_private_secret_pattern' ) && codecanvas_contains_private_secret_pattern( $content ) ) {
        wp_send_json_error( array( 'message' => __( 'The code resembles a private credential. Remove the key from browser-delivered files and read it on the server instead.', 'codecanvas' ) ), 400 );
    }
    // PHP syntax validation happens before the backup/write step, helping prevent a common
    // class of accidental fatal errors when editing active PHP files from the dashboard.
    if ( in_array( $ext, array( 'php', 'phtml' ), true ) && defined( 'TOKEN_PARSE' ) ) {
        try {
            token_get_all( $content, TOKEN_PARSE );
        } catch ( ParseError $parse_error ) {
            /* translators: %s: PHP parser error message. */
            wp_send_json_error( array( 'message' => sprintf( __( 'PHP syntax error; the file was not saved. %s', 'codecanvas' ), $parse_error->getMessage() ) ), 400 );
        }
    }
    $current = file_get_contents( $file );
    if ( false === $current ) { wp_send_json_error( array( 'message' => __( 'Could not read the existing file before saving.', 'codecanvas' ) ), 500 ); }
    $expected = isset( $_POST['expected_sha256'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_sha256'] ) ) : '';
    if ( $expected && ! hash_equals( hash( 'sha256', $current ), $expected ) ) {
        wp_send_json_error( array( 'message' => __( 'This file changed on the server after you opened it. Reload it first so you do not overwrite another edit.', 'codecanvas' ), 'conflict' => true, 'current_sha256' => hash( 'sha256', $current ) ), 409 );
    }
    codecanvas_studio_make_backup( $root, ltrim( $relative, '/' ), $current, 'save' );
    $written = @file_put_contents( $file, $content, LOCK_EX );
    if ( false === $written ) { wp_send_json_error( array( 'message' => __( 'The file could not be saved. Check file permissions and your hosting configuration.', 'codecanvas' ) ), 500 ); }
    clearstatcache( true, $file );
    wp_send_json_success( array( 'message' => __( 'File saved.', 'codecanvas' ), 'sha256' => hash( 'sha256', $content ), 'bytes' => (int) $written, 'mtime' => filemtime( $file ) ) );
}
add_action( 'wp_ajax_codecanvas_studio_save', 'codecanvas_studio_ajax_save' );

/** Create a text file or directory in the current source root. */
function codecanvas_studio_ajax_create() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    if ( ! codecanvas_studio_can_access_root( $root, true ) ) { wp_send_json_error( array( 'message' => __( 'This source root is not writable.', 'codecanvas' ) ), 403 ); }
    $parent_rel = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    codecanvas_studio_reject_framework_write( $root, $parent_rel );
    $name = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
    $kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'file';
    if ( '' === $name || '.' === $name || '..' === $name || false !== strpos( $name, '/' ) || false !== strpos( $name, '\\' ) ) { wp_send_json_error( array( 'message' => __( 'Enter a valid name without folder separators.', 'codecanvas' ) ), 400 ); }
    $parent = codecanvas_studio_resolve( $root, $parent_rel );
    if ( is_wp_error( $parent ) || ! is_dir( $parent ) || ! wp_is_writable( $parent ) ) { wp_send_json_error( array( 'message' => is_wp_error( $parent ) ? $parent->get_error_message() : __( 'The destination folder is not writable.', 'codecanvas' ) ), 400 ); }
    $target_rel = ltrim( trim( $parent_rel, '/' ) . '/' . $name, '/' );
    if ( 'themes' === $root && '' === trim( $parent_rel, '/' ) && codecanvas_studio_site_child_slug() === $name ) {
        wp_send_json_error( array( 'message' => __( 'The codecanvas-site folder is reserved for the generated website theme. Use the website-theme setup action instead.', 'codecanvas' ) ), 409 );
    }
    codecanvas_studio_reject_framework_write( $root, $target_rel );
    $target = codecanvas_studio_resolve( $root, $target_rel, true );
    if ( is_wp_error( $target ) ) { wp_send_json_error( array( 'message' => $target->get_error_message() ), 400 ); }
    if ( file_exists( $target ) ) { wp_send_json_error( array( 'message' => __( 'A file or folder with that name already exists.', 'codecanvas' ) ), 409 ); }
    if ( 'folder' === $kind ) {
        if ( ! wp_mkdir_p( $target ) ) { wp_send_json_error( array( 'message' => __( 'Could not create folder. Check permissions.', 'codecanvas' ) ), 500 ); }
    } else {
        if ( ! codecanvas_studio_is_allowed_file( $target ) ) { wp_send_json_error( array( 'message' => __( 'This file extension is not supported in the browser editor. Use VS Code for binary files and private configuration files.', 'codecanvas' ) ), 400 ); }
        $initial = '';
        $ext = strtolower( pathinfo( $target, PATHINFO_EXTENSION ) );
        if ( 'php' === $ext ) { $initial = "<?php\n// Created with CodeCanvas Studio.\n"; }
        if ( false === @file_put_contents( $target, $initial, LOCK_EX ) ) { wp_send_json_error( array( 'message' => __( 'Could not create file. Check permissions.', 'codecanvas' ) ), 500 ); }
    }
    wp_send_json_success( array( 'message' => ( 'folder' === $kind ) ? __( 'Folder created.', 'codecanvas' ) : __( 'File created.', 'codecanvas' ), 'path' => $target_rel, 'kind' => $kind ) );
}
add_action( 'wp_ajax_codecanvas_studio_create', 'codecanvas_studio_ajax_create' );

/** Rename a single supported source file. */
function codecanvas_studio_ajax_rename() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    if ( ! codecanvas_studio_can_access_root( $root, true ) ) { wp_send_json_error( array( 'message' => __( 'This source root is not writable.', 'codecanvas' ) ), 403 ); }
    $relative = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    codecanvas_studio_reject_framework_write( $root, $relative );
    $name = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
    if ( '' === $name || false !== strpos( $name, '/' ) || false !== strpos( $name, '\\' ) ) { wp_send_json_error( array( 'message' => __( 'Enter a valid new filename.', 'codecanvas' ) ), 400 ); }
    $file = codecanvas_studio_resolve( $root, $relative );
    if ( is_wp_error( $file ) || ! is_file( $file ) || ! codecanvas_studio_is_allowed_file( $file ) || ! wp_is_writable( dirname( $file ) ) ) { wp_send_json_error( array( 'message' => is_wp_error( $file ) ? $file->get_error_message() : __( 'Only supported source files in writable folders can be renamed here.', 'codecanvas' ) ), 400 ); }
    if ( filesize( $file ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'Files over 1 MB cannot be renamed from Studio because a database snapshot cannot be made safely.', 'codecanvas' ) ), 413 ); }
    $new_rel = ltrim( trim( dirname( $relative ), './' ) . '/' . $name, '/' );
    if ( '.' === dirname( $relative ) || '' === dirname( $relative ) ) { $new_rel = $name; }
    codecanvas_studio_reject_framework_write( $root, $new_rel );
    $new = codecanvas_studio_resolve( $root, $new_rel, true );
    if ( is_wp_error( $new ) ) { wp_send_json_error( array( 'message' => $new->get_error_message() ), 400 ); }
    if ( ! codecanvas_studio_is_allowed_file( $new ) ) { wp_send_json_error( array( 'message' => __( 'The new filename must use a supported source extension.', 'codecanvas' ) ), 400 ); }
    if ( file_exists( $new ) ) { wp_send_json_error( array( 'message' => __( 'A file with that name already exists.', 'codecanvas' ) ), 409 ); }
    $content = file_get_contents( $file );
    if ( is_string( $content ) ) { codecanvas_studio_make_backup( $root, ltrim( $relative, '/' ), $content, 'rename' ); }
    if ( ! codecanvas_move_file( $file, $new ) ) { wp_send_json_error( array( 'message' => __( 'Rename failed. Check folder permissions.', 'codecanvas' ) ), 500 ); }
    $old_history_key = 'codecanvas_studio_bk_' . md5( $root . ':' . ltrim( $relative, '/' ) );
    $new_history_key = 'codecanvas_studio_bk_' . md5( $root . ':' . ltrim( $new_rel, '/' ) );
    $old_history = get_option( $old_history_key, array() );
    if ( is_array( $old_history ) && ! empty( $old_history ) ) {
        $new_history = get_option( $new_history_key, array() );
        if ( ! is_array( $new_history ) ) { $new_history = array(); }
        $new_history = array_slice( array_merge( $old_history, $new_history ), 0, 3 );
        if ( get_option( $new_history_key, null ) === null ) { add_option( $new_history_key, $new_history, '', false ); }
        else { update_option( $new_history_key, $new_history, false ); }
        delete_option( $old_history_key );
    }
    wp_send_json_success( array( 'message' => __( 'File renamed.', 'codecanvas' ), 'path' => $new_rel ) );
}
add_action( 'wp_ajax_codecanvas_studio_rename', 'codecanvas_studio_ajax_rename' );

/** Delete one file with a database snapshot; directory deletion is deliberately disabled. */
function codecanvas_studio_ajax_delete() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    if ( ! codecanvas_studio_can_access_root( $root, true ) ) { wp_send_json_error( array( 'message' => __( 'This source root is not writable.', 'codecanvas' ) ), 403 ); }
    $relative = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    codecanvas_studio_reject_framework_write( $root, $relative );
    $file = codecanvas_studio_resolve( $root, $relative );
    if ( is_wp_error( $file ) || ! is_file( $file ) || ! codecanvas_studio_is_allowed_file( $file ) || ! wp_is_writable( $file ) || ! wp_is_writable( dirname( $file ) ) ) { wp_send_json_error( array( 'message' => is_wp_error( $file ) ? $file->get_error_message() : __( 'Only writable source files in writable folders can be deleted here.', 'codecanvas' ) ), 400 ); }
    if ( filesize( $file ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'Files over 1 MB cannot be deleted from Studio because a database backup cannot be created safely.', 'codecanvas' ) ), 413 ); }
    $content = file_get_contents( $file );
    if ( ! is_string( $content ) ) { wp_send_json_error( array( 'message' => __( 'Could not back up the file before deletion.', 'codecanvas' ) ), 500 ); }
    codecanvas_studio_make_backup( $root, ltrim( $relative, '/' ), $content, 'delete' );
    wp_delete_file( $file );
    if ( file_exists( $file ) ) { wp_send_json_error( array( 'message' => __( 'Could not delete the file. Check permissions.', 'codecanvas' ) ), 500 ); }
    wp_send_json_success( array( 'message' => __( 'File deleted. A snapshot is available in History if you need to restore it.', 'codecanvas' ) ) );
}
add_action( 'wp_ajax_codecanvas_studio_delete', 'codecanvas_studio_ajax_delete' );

/** Return bounded revision metadata for the currently open file. */
function codecanvas_studio_ajax_history() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    $relative = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    $file = codecanvas_studio_resolve( $root, $relative, true );
    if ( is_wp_error( $file ) ) { wp_send_json_error( array( 'message' => $file->get_error_message() ), 400 ); }
    $history = get_option( 'codecanvas_studio_bk_' . md5( $root . ':' . ltrim( $relative, '/' ) ), array() );
    $out = array();
    if ( is_array( $history ) ) {
        foreach ( $history as $index => $entry ) {
            if ( ! is_array( $entry ) ) { continue; }
            $out[] = array( 'index' => (int) $index, 'time' => isset( $entry['time'] ) ? (int) $entry['time'] : 0, 'reason' => isset( $entry['reason'] ) ? sanitize_key( $entry['reason'] ) : 'save', 'sha256' => isset( $entry['sha256'] ) ? $entry['sha256'] : '' );
        }
    }
    wp_send_json_success( array( 'history' => $out ) );
}
add_action( 'wp_ajax_codecanvas_studio_history', 'codecanvas_studio_ajax_history' );

/** Restore a saved version, first snapshotting current content when present. */
function codecanvas_studio_ajax_restore() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    if ( ! codecanvas_studio_can_access_root( $root, true ) ) { wp_send_json_error( array( 'message' => __( 'This source root is not writable.', 'codecanvas' ) ), 403 ); }
    $relative = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    codecanvas_studio_reject_framework_write( $root, $relative );
    $index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
    $file = codecanvas_studio_resolve( $root, $relative, true );
    if ( is_wp_error( $file ) ) { wp_send_json_error( array( 'message' => $file->get_error_message() ), 400 ); }
    $key = 'codecanvas_studio_bk_' . md5( $root . ':' . ltrim( $relative, '/' ) );
    $history = get_option( $key, array() );
    if ( ! is_array( $history ) || ! isset( $history[ $index ]['content'] ) || ! is_string( $history[ $index ]['content'] ) ) { wp_send_json_error( array( 'message' => __( 'That revision is no longer available.', 'codecanvas' ) ), 404 ); }
    if ( strlen( $history[ $index ]['content'] ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'This revision is too large to restore from the dashboard.', 'codecanvas' ) ), 413 ); }
    if ( file_exists( $file ) ) {
        if ( ! is_file( $file ) || ! wp_is_writable( $file ) || ! codecanvas_studio_is_allowed_file( $file ) ) { wp_send_json_error( array( 'message' => __( 'The current path is not a writable source file.', 'codecanvas' ) ), 400 ); }
        if ( filesize( $file ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'Files over 1 MB cannot be restored from Studio because a safety snapshot cannot be made.', 'codecanvas' ) ), 413 ); }
        $current = file_get_contents( $file );
        if ( is_string( $current ) ) { codecanvas_studio_make_backup( $root, ltrim( $relative, '/' ), $current, 'before-restore' ); }
    } else {
        if ( ! wp_is_writable( dirname( $file ) ) || ! codecanvas_studio_is_allowed_file( $file ) ) { wp_send_json_error( array( 'message' => __( 'The deleted file cannot be restored to this path.', 'codecanvas' ) ), 400 ); }
    }
    if ( false === @file_put_contents( $file, $history[ $index ]['content'], LOCK_EX ) ) { wp_send_json_error( array( 'message' => __( 'Restore failed. Check permissions.', 'codecanvas' ) ), 500 ); }
    wp_send_json_success( array( 'message' => __( 'Revision restored.', 'codecanvas' ), 'content' => $history[ $index ]['content'], 'sha256' => hash( 'sha256', $history[ $index ]['content'] ) ) );
}
add_action( 'wp_ajax_codecanvas_studio_restore', 'codecanvas_studio_ajax_restore' );

/** Create starter theme/plugin folders with safe, minimal source files. Never activates them. */
function codecanvas_studio_ajax_scaffold() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    $kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
    if ( ! in_array( $kind, array( 'plugin', 'theme' ), true ) || ( 'plugin' === $kind && 'plugins' !== $root ) || ( 'theme' === $kind && 'themes' !== $root ) ) {
        wp_send_json_error( array( 'message' => __( 'Select the matching Themes or Plugins root before creating a starter.', 'codecanvas' ) ), 400 );
    }
    if ( ! codecanvas_studio_can_access_root( $root, true ) ) { wp_send_json_error( array( 'message' => __( 'This source root is not writable.', 'codecanvas' ) ), 403 ); }
    $slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    if ( '' === $slug || ! preg_match( '/^[a-z0-9][a-z0-9-]{1,47}$/', $slug ) ) { wp_send_json_error( array( 'message' => __( 'Use a slug with 2–48 lowercase letters, numbers and hyphens.', 'codecanvas' ) ), 400 ); }
    if ( 'theme' === $kind && codecanvas_studio_site_child_slug() === $slug ) { wp_send_json_error( array( 'message' => __( 'The codecanvas-site folder is reserved for the generated CodeCanvas Site theme.', 'codecanvas' ) ), 409 ); }
    $base = codecanvas_studio_root_path( $root );
    $target = $base . DIRECTORY_SEPARATOR . $slug;
    if ( file_exists( $target ) ) { wp_send_json_error( array( 'message' => __( 'That folder already exists. Choose another slug.', 'codecanvas' ) ), 409 ); }
    if ( ! wp_mkdir_p( $target ) ) { wp_send_json_error( array( 'message' => __( 'Could not create starter folder.', 'codecanvas' ) ), 500 ); }
    $title = $title ? $title : ucwords( str_replace( '-', ' ', $slug ) );
    // Starter titles are inserted into PHP comment/header contexts. Keep them on one line and prevent comment termination.
    $title = str_replace( array( "\r", "\n", '*/' ), '', $title );
    $files = array();
    $symbol_prefix = 'cc_' . str_replace( '-', '_', $slug );
    $class_name = 'CodeCanvas_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $slug ) ) );
    if ( 'plugin' === $kind ) {
        $main = "<?php\n/**\n * Plugin Name: " . str_replace( array( "\r", "\n" ), '', $title ) . "\n * Description: A custom plugin created with CodeCanvas Studio.\n * Version: 0.1.0\n * Requires at least: 6.6\n * Requires PHP: 7.4\n * Author: Suraj Dev\n * Author URI: https://surajdev.com/\n * License: GPL-2.0-or-later\n * Text Domain: " . $slug . "\n */\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n\n// Add your hooks and functionality below.\n";
        $files[ $slug . '.php' ] = $main;
        $files['readme.txt'] = "=== " . $title . " ===\nContributors: thesurajdev\nTags: custom\nRequires at least: 6.6\nRequires PHP: 7.4\nStable tag: 0.1.0\nLicense: GPL-2.0-or-later\n\nA custom plugin scaffold created with CodeCanvas Studio.\n";
        $files['includes/class-' . $slug . '.php'] = "<?php\n/** Main plugin class. */\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n\nclass " . $class_name . " {\n    public function init() {\n        // Register hooks here.\n    }\n}\n";
    } else {
        $files['style.css'] = "/*\nTheme Name: " . str_replace( array( "\r", "\n" ), '', $title ) . "\nTheme URI: https://webot.surajdev.com/\nAuthor: Suraj Dev\nAuthor URI: https://surajdev.com/\nDescription: A custom theme scaffold created with CodeCanvas Studio.\nVersion: 0.1.0\nRequires at least: 6.6\nRequires PHP: 7.4\nLicense: GNU General Public License v2 or later\nLicense URI: https://www.gnu.org/licenses/gpl-2.0.html\nText Domain: " . $slug . "\n*/\n\n:root { color-scheme: light; }\n";
        $files['functions.php'] = "<?php\n/** Theme bootstrap for " . $title . ". */\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n\nfunction " . $symbol_prefix . "_setup() {\n    add_theme_support( 'title-tag' );\n    add_theme_support( 'post-thumbnails' );\n    register_nav_menus( array( 'primary' => __( 'Primary Menu', '" . $slug . "' ) ) );\n}\nadd_action( 'after_setup_theme', '" . $symbol_prefix . "_setup' );\n\nfunction " . $symbol_prefix . "_assets() {\n    wp_enqueue_style( '" . $slug . "-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );\n    wp_enqueue_style( '" . $slug . "-layout', get_template_directory_uri() . '/assets/css/theme.css', array( '" . $slug . "-style' ), wp_get_theme()->get( 'Version' ) );\n}\nadd_action( 'wp_enqueue_scripts', '" . $symbol_prefix . "_assets' );\n";
        $files['index.php'] = "<?php get_header(); ?>\n<main id=\"main\">\n<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>\n<article <?php post_class(); ?>>\n<h1><?php the_title(); ?></h1>\n<?php the_content(); ?>\n</article>\n<?php endwhile; else : ?>\n<p><?php esc_html_e( 'No content found.', '" . $slug . "' ); ?></p>\n<?php endif; ?>\n</main>\n<?php get_footer(); ?>\n";
        $files['header.php'] = "<!doctype html>\n<html <?php language_attributes(); ?>>\n<head><meta charset=\"<?php bloginfo( 'charset' ); ?>\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><?php wp_head(); ?></head>\n<body <?php body_class(); ?>>\n<?php wp_body_open(); ?>\n<header><a href=\"<?php echo esc_url( home_url( '/' ) ); ?>\"><?php bloginfo( 'name' ); ?></a></header>\n";
        $files['footer.php'] = "<footer><small>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></small></footer>\n<?php wp_footer(); ?>\n</body></html>\n";
        $files['assets/css/theme.css'] = ":root { --content-width: 72rem; }\nbody { margin: 0; font-family: system-ui, sans-serif; line-height: 1.6; }\nmain { width: min(100% - 2rem, var(--content-width)); margin-inline: auto; }\n";
    }
    foreach ( $files as $relative => $contents ) {
        $dest = $target . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
        wp_mkdir_p( dirname( $dest ) );
        if ( false === file_put_contents( $dest, $contents, LOCK_EX ) ) {
            // Remove only generated files/folders from this failed scaffold attempt.
            foreach ( $files as $cleanup_rel => $unused ) { $cleanup = $target . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $cleanup_rel ); if ( is_file( $cleanup ) ) { wp_delete_file( $cleanup ); } }
            codecanvas_rmdir( $target . DIRECTORY_SEPARATOR . 'includes' );
            codecanvas_rmdir( $target . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' );
            codecanvas_rmdir( $target . DIRECTORY_SEPARATOR . 'assets' );
            codecanvas_rmdir( $target );
            wp_send_json_error( array( 'message' => __( 'Could not write the starter files; check permissions. Partial generated files were cleaned where possible.', 'codecanvas' ) ), 500 );
        }
    }
    wp_send_json_success( array( 'message' => __( 'Starter created. It has not been activated.', 'codecanvas' ), 'path' => $slug, 'kind' => $kind ) );
}
add_action( 'wp_ajax_codecanvas_studio_scaffold', 'codecanvas_studio_ajax_scaffold' );

/** Enqueue WordPress's bundled editor before admin_head prints assets. */
function codecanvas_studio_enqueue_assets( $hook ) {
    if ( false === strpos( (string) $hook, 'codecanvas-studio' ) ) { return; }
    $mime_types = array(
        'php' => 'application/x-httpd-php', 'phtml' => 'application/x-httpd-php', 'css' => 'text/css', 'scss' => 'text/x-scss', 'sass' => 'text/x-sass',
        'js' => 'text/javascript', 'mjs' => 'text/javascript', 'cjs' => 'text/javascript', 'ts' => 'text/typescript', 'tsx' => 'text/typescript', 'jsx' => 'text/jsx',
        'html' => 'text/html', 'htm' => 'text/html', 'json' => 'application/json', 'md' => 'text/plain', 'txt' => 'text/plain', 'xml' => 'application/xml', 'svg' => 'image/svg+xml', 'yml' => 'text/yaml', 'yaml' => 'text/yaml', 'sql' => 'text/x-sql', 'graphql' => 'text/x-sql', 'gql' => 'text/x-sql', 'twig' => 'text/html', 'vue' => 'text/html', 'sh' => 'text/x-sh', 'conf' => 'text/plain', 'ini' => 'text/plain', 'dist' => 'text/plain', 'sample' => 'text/plain'
    );
    $editor_settings = array();
    foreach ( array_unique( array_values( $mime_types ) ) as $mime ) {
        $settings = wp_enqueue_code_editor( array( 'type' => $mime, 'codemirror' => array( 'theme' => 'codecanvas-dark', 'lineNumbers' => true, 'indentUnit' => 4, 'tabSize' => 4, 'lineWrapping' => false, 'matchBrackets' => true, 'autoCloseBrackets' => true, 'styleActiveLine' => true ) ) );
        if ( $settings ) {
            if ( ! isset( $settings['codemirror'] ) || ! is_array( $settings['codemirror'] ) ) { $settings['codemirror'] = array(); }
            $settings['codemirror']['theme'] = 'codecanvas-dark';
            $editor_settings[ $mime ] = $settings;
        }
    }
    $GLOBALS['codecanvas_studio_editor_settings'] = $editor_settings;
    wp_enqueue_style( 'codecanvas-studio', CODECANVAS_URI . '/assets/css/studio.css', array(), CODECANVAS_VERSION );
    wp_enqueue_script( 'codecanvas-studio', CODECANVAS_URI . '/assets/js/studio.js', array( 'jquery', 'code-editor' ), CODECANVAS_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'codecanvas_studio_enqueue_assets', 20 );

/** Render the Studio workspace. */
function codecanvas_studio_render() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to use CodeCanvas Studio.', 'codecanvas' ), '', array( 'response' => 403 ) ); }
    $framework_slug = codecanvas_studio_framework_slug();
    $child_slug = codecanvas_studio_site_child_slug();
    $child_exists = codecanvas_studio_site_child_exists();
    $child_active = codecanvas_studio_site_child_active();
    $themes_root = codecanvas_studio_root_path( 'themes' );
    $can_create_child = current_user_can( 'manage_options' ) && current_user_can( 'edit_themes' ) && current_user_can( 'switch_themes' )
        && codecanvas_studio_can_access_root( 'themes', true )
        && $themes_root && wp_is_writable( $themes_root );
    $can_activate_child = current_user_can( 'manage_options' ) && current_user_can( 'switch_themes' ) && $child_exists;
    $default_root = codecanvas_studio_can_access_root( 'themes' ) ? 'themes' : 'plugins';
    // The default project is always the generated website theme, never the CodeCanvas plugin itself.
    $default_path = ( 'themes' === $default_root ) ? $child_slug : '';
    $needs_site_setup = ( 'themes' === $default_root ) && ! $child_active;
    $setup_button_enabled = $child_exists ? $can_activate_child : $can_create_child;
    $editor_settings = isset( $GLOBALS['codecanvas_studio_editor_settings'] ) && is_array( $GLOBALS['codecanvas_studio_editor_settings'] ) ? $GLOBALS['codecanvas_studio_editor_settings'] : array();
    wp_add_inline_script( 'codecanvas-studio', 'window.CodeCanvasStudioConfig = ' . wp_json_encode( array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'codecanvas_studio' ),
        'version' => CODECANVAS_VERSION,
        'defaultRoot' => $default_root,
        'defaultPath' => $default_path,
        'childThemeSlug' => $child_slug,
        'childThemeExists' => $child_exists,
        'childThemeActive' => $child_active,
        'childThemeSetupAllowed' => $setup_button_enabled,
        'frameworkThemeSlug' => $framework_slug,
        'roots' => array( 'themes' => codecanvas_studio_can_access_root( 'themes' ), 'plugins' => codecanvas_studio_can_access_root( 'plugins' ) ),
        'editableRoots' => array( 'themes' => codecanvas_studio_can_access_root( 'themes', true ) && wp_is_writable( codecanvas_studio_root_path( 'themes' ) ), 'plugins' => codecanvas_studio_can_access_root( 'plugins', true ) && wp_is_writable( codecanvas_studio_root_path( 'plugins' ) ) ),
        'editorSettings' => $editor_settings,
        'allowedExtensions' => codecanvas_studio_allowed_extensions(),
        'siteUrl' => home_url( '/' ),
        'strings' => array(
            'loading' => __( 'Loading files…', 'codecanvas' ), 'saved' => __( 'Saved', 'codecanvas' ), 'unsaved' => __( 'Unsaved changes', 'codecanvas' ), 'saving' => __( 'Saving…', 'codecanvas' ), 'readOnly' => __( 'Read only', 'codecanvas' ), 'noFile' => __( 'Select a file to begin editing', 'codecanvas' ), 'noResults' => __( 'No matching files', 'codecanvas' ), 'confirmDelete' => __( 'Delete this source file? A revision snapshot will be created first.', 'codecanvas' ), 'confirmDiscard' => __( 'Discard unsaved changes in this tab?', 'codecanvas' ), 'namePrompt' => __( 'Enter a name:', 'codecanvas' ), 'slugPrompt' => __( 'Enter a folder slug (lowercase letters, numbers and hyphens):', 'codecanvas' ), 'titlePrompt' => __( 'Enter the display name:', 'codecanvas' ), 'error' => __( 'Something went wrong.', 'codecanvas' )
        )
    ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ), 'before' );
    ?>
    <div class="wrap cc-studio-wrap">
        <header class="cc-studio-titlebar">
            <div class="cc-studio-brand"><span class="cc-studio-mark">CC</span><div><strong>CodeCanvas Studio</strong><small><?php esc_html_e( 'Real source files · WordPress developer workspace', 'codecanvas' ); ?></small></div></div>
            <div class="cc-studio-actions">
                <div class="cc-studio-action-group cc-studio-project-group">
                    <label class="screen-reader-text" for="cc-studio-root"><?php esc_html_e( 'Project', 'codecanvas' ); ?></label>
                    <?php $themes_available = codecanvas_studio_can_access_root( 'themes' ); $plugins_available = codecanvas_studio_can_access_root( 'plugins' ); ?>
                    <select id="cc-studio-root" aria-label="<?php esc_attr_e( 'Project', 'codecanvas' ); ?>">
                        <option value="themes" <?php selected( $default_root, 'themes' ); ?>><?php echo esc_html( $themes_available ? __( 'Website themes', 'codecanvas' ) : __( 'Website themes — not available', 'codecanvas' ) ); ?></option>
                        <option value="plugins" <?php selected( $default_root, 'plugins' ); ?>><?php echo esc_html( $plugins_available ? __( 'Plugins', 'codecanvas' ) : __( 'Plugins — not available', 'codecanvas' ) ); ?></option>
                    </select>
                </div>
                <div class="cc-studio-action-group">
                    <button type="button" class="button" id="cc-studio-new-file" title="<?php esc_attr_e( 'New file', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'New file', 'codecanvas' ); ?>" <?php disabled( ! codecanvas_studio_can_access_root( $default_root, true ) || $needs_site_setup ); ?>><span class="dashicons dashicons-media-default" aria-hidden="true"></span><span class="cc-btn-label"><?php esc_html_e( 'New File', 'codecanvas' ); ?></span></button>
                    <button type="button" class="button" id="cc-studio-new-folder" title="<?php esc_attr_e( 'New folder', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'New folder', 'codecanvas' ); ?>" <?php disabled( ! codecanvas_studio_can_access_root( $default_root, true ) || $needs_site_setup ); ?>><span class="dashicons dashicons-category" aria-hidden="true"></span><span class="cc-btn-label"><?php esc_html_e( 'New Folder', 'codecanvas' ); ?></span></button>
                    <button type="button" class="button" id="cc-studio-scaffold" title="<?php esc_attr_e( 'Create a theme or plugin starter', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Create a theme or plugin starter', 'codecanvas' ); ?>"><span class="dashicons dashicons-welcome-add-page" aria-hidden="true"></span><span class="cc-btn-label"><?php esc_html_e( 'Starter…', 'codecanvas' ); ?></span></button>
                    <button type="button" class="button" id="cc-studio-history" title="<?php esc_attr_e( 'File history', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'File history', 'codecanvas' ); ?>"><span class="dashicons dashicons-backup" aria-hidden="true"></span><span class="cc-btn-label"><?php esc_html_e( 'History', 'codecanvas' ); ?></span></button>
                </div>
                <div class="cc-studio-action-group">
                    <button type="button" class="button" id="cc-studio-focus" aria-pressed="false" title="<?php esc_attr_e( 'Focus mode: hide WordPress chrome and fill the screen (Ctrl+Shift+M, Esc to exit)', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Focus mode', 'codecanvas' ); ?>"><span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span><span class="cc-btn-label cc-focus-label"><?php esc_html_e( 'Focus', 'codecanvas' ); ?></span></button>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-settings' ) ); ?>" title="<?php esc_attr_e( 'MCP settings', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'MCP settings', 'codecanvas' ); ?>"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span><span class="cc-btn-label"><?php esc_html_e( 'MCP', 'codecanvas' ); ?></span></a>
                </div>
                <div class="cc-studio-action-group cc-studio-save-group">
                    <button type="button" class="button button-primary" id="cc-studio-save" title="<?php esc_attr_e( 'Save file (Ctrl+S)', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Save file', 'codecanvas' ); ?>" disabled><span class="dashicons dashicons-saved" aria-hidden="true"></span><span class="cc-btn-label"><?php esc_html_e( 'Save', 'codecanvas' ); ?></span></button>
                </div>
            </div>
        </header>
        <div class="cc-studio-notice" id="cc-studio-notice" role="note"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><span class="cc-studio-notice-text cc-studio-notice-full"><?php esc_html_e( 'Website code belongs in the CodeCanvas Site theme. CodeCanvas plugin files are read-only here. Use version control and a backup before changing executable source files.', 'codecanvas' ); ?></span><span class="cc-studio-notice-text cc-studio-notice-short"><?php esc_html_e( 'Back up before editing. The CodeCanvas plugin is read-only here.', 'codecanvas' ); ?></span><button type="button" class="cc-studio-notice-close" id="cc-studio-notice-close" title="<?php esc_attr_e( 'Hide this note', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Hide this note', 'codecanvas' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
        <section class="cc-studio-child-setup" id="cc-studio-child-setup" <?php echo $needs_site_setup ? '' : 'hidden'; ?>>
            <div class="cc-studio-child-setup-icon"><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span></div>
            <div class="cc-studio-child-setup-copy"><p class="cc-studio-eyebrow"><?php esc_html_e( 'RECOMMENDED WEBSITE WORKSPACE', 'codecanvas' ); ?></p><h1><?php esc_html_e( 'Build your website in a real theme, without leaving WordPress.', 'codecanvas' ); ?></h1><p><?php esc_html_e( 'CodeCanvas is the builder: Studio, coded pages, custom content types and MCP. Your frontend templates, CSS, JavaScript and site-specific PHP live in the CodeCanvas Site theme. Create or activate it to make it the default project in this editor.', 'codecanvas' ); ?></p><ul><li><?php esc_html_e( 'Your website theme has its own front-end files and templates.', 'codecanvas' ); ?></li><li><?php esc_html_e( 'CodeCanvas plugin files can be inspected, but cannot be edited from this workspace.', 'codecanvas' ); ?></li><li><?php esc_html_e( 'Existing website-theme files will never be overwritten during setup.', 'codecanvas' ); ?></li></ul><button type="button" class="button button-primary button-hero" id="cc-studio-setup-child" <?php disabled( ! $setup_button_enabled ); ?>><?php echo esc_html( $child_exists ? __( 'Activate CodeCanvas Site theme', 'codecanvas' ) : __( 'Create and activate CodeCanvas Site theme', 'codecanvas' ) ); ?></button><?php if ( ! $setup_button_enabled ) : ?><p class="cc-studio-setup-warning"><?php esc_html_e( 'Theme file changes or theme switching are disabled by the current WordPress permissions or hosting policy. Use a controlled VS Code/SFTP workflow to create the theme, then return here.', 'codecanvas' ); ?></p><?php endif; ?></div>
        </section>
        <div class="cc-studio-workbench" id="cc-studio-workbench" <?php echo ( $needs_site_setup && 'themes' === $default_root ) ? 'hidden' : ''; ?>>
            <aside class="cc-studio-activity" aria-label="<?php esc_attr_e( 'Studio tools', 'codecanvas' ); ?>"><button type="button" class="is-active" data-cc-activity="explorer" title="<?php esc_attr_e( 'Explorer', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Explorer', 'codecanvas' ); ?>" aria-expanded="false" aria-controls="cc-studio-sidebar"><span class="dashicons dashicons-open-folder"></span></button><button type="button" data-cc-activity="search" title="<?php esc_attr_e( 'Search files', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Search files', 'codecanvas' ); ?>" aria-controls="cc-studio-sidebar"><span class="dashicons dashicons-search"></span></button><button type="button" data-cc-activity="history" title="<?php esc_attr_e( 'File history', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'File history', 'codecanvas' ); ?>" aria-controls="cc-studio-sidebar"><span class="dashicons dashicons-backup"></span></button><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Open site in a new tab', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Open site in a new tab', 'codecanvas' ); ?>"><span class="dashicons dashicons-external"></span></a></aside>
            <aside class="cc-studio-sidebar" id="cc-studio-sidebar"><div class="cc-studio-drawer-head"><strong><?php esc_html_e( 'WORKSPACE', 'codecanvas' ); ?></strong><button type="button" class="cc-studio-drawer-close" id="cc-studio-drawer-close" title="<?php esc_attr_e( 'Close explorer', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Close explorer', 'codecanvas' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div><section class="cc-studio-side-section" id="cc-studio-explorer"><div class="cc-studio-side-heading"><strong><?php esc_html_e( 'EXPLORER', 'codecanvas' ); ?></strong><div class="cc-studio-heading-actions"><button type="button" id="cc-studio-refresh" title="<?php esc_attr_e( 'Refresh files', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Refresh files', 'codecanvas' ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span></button></div></div><div class="cc-studio-project-label"><span class="dashicons dashicons-admin-site-alt3"></span><strong id="cc-studio-root-label"><?php echo esc_html( 'themes' === $default_root ? __( 'Website themes', 'codecanvas' ) : __( 'Plugins', 'codecanvas' ) ); ?></strong></div><div id="cc-studio-breadcrumbs" class="cc-studio-breadcrumbs"></div><div id="cc-studio-file-list" class="cc-studio-file-list" aria-live="polite"><p class="cc-studio-loading"><?php esc_html_e( 'Loading files…', 'codecanvas' ); ?></p></div></section>
                <section class="cc-studio-side-section cc-studio-search-section" id="cc-studio-search-panel" hidden><div class="cc-studio-side-heading"><strong><?php esc_html_e( 'SEARCH', 'codecanvas' ); ?></strong></div><input type="search" id="cc-studio-search-query" placeholder="<?php esc_attr_e( 'Search in current folder…', 'codecanvas' ); ?>"><button type="button" class="button" id="cc-studio-search-run"><?php esc_html_e( 'Search files', 'codecanvas' ); ?></button><div id="cc-studio-search-results" class="cc-studio-search-results"></div></section>
                <section class="cc-studio-side-section" id="cc-studio-history-panel" hidden><div class="cc-studio-side-heading"><strong><?php esc_html_e( 'FILE HISTORY', 'codecanvas' ); ?></strong></div><p class="description"><?php esc_html_e( 'The last 3 pre-change snapshots for the selected file are kept in the WordPress database.', 'codecanvas' ); ?></p><div id="cc-studio-history-list"></div></section>
                <div class="cc-studio-sidebar-foot"><strong><?php esc_html_e( 'WORKSPACE', 'codecanvas' ); ?></strong><span id="cc-studio-root-path"></span><span><?php esc_html_e( 'Read/write is limited by WordPress capabilities and server permissions.', 'codecanvas' ); ?></span></div>
            </aside>
            <main class="cc-studio-editor-area">
                <div class="cc-studio-tabs" id="cc-studio-tabs"><div class="cc-studio-empty-tab"><?php esc_html_e( 'No file open', 'codecanvas' ); ?></div></div>
                <div class="cc-studio-editor-toolbar"><span id="cc-studio-current-file"><?php esc_html_e( 'Select a source file from Explorer', 'codecanvas' ); ?></span><div><button type="button" class="button button-small" id="cc-studio-wrap" aria-pressed="true" title="<?php esc_attr_e( 'Soft wrap long lines (Alt+Z)', 'codecanvas' ); ?>" aria-label="<?php esc_attr_e( 'Soft wrap long lines', 'codecanvas' ); ?>"><span class="dashicons dashicons-editor-paragraph" aria-hidden="true"></span><span class="cc-toolbar-label"><?php esc_html_e( 'Wrap', 'codecanvas' ); ?></span></button><button type="button" class="button button-small" id="cc-studio-rename" disabled><?php esc_html_e( 'Rename', 'codecanvas' ); ?></button><button type="button" class="button button-small" id="cc-studio-delete" disabled><?php esc_html_e( 'Delete', 'codecanvas' ); ?></button></div></div>
                <div class="cc-studio-editor-host" id="cc-studio-editor-host"><div class="cc-studio-welcome"><div class="cc-studio-welcome-mark">CC</div><h1><?php esc_html_e( 'CodeCanvas Studio', 'codecanvas' ); ?></h1><p><?php esc_html_e( 'A real-file development workspace inside WordPress.', 'codecanvas' ); ?></p><div class="cc-studio-shortcuts"><span><kbd>Ctrl</kbd> + <kbd>S</kbd> <small><?php esc_html_e( 'Save file', 'codecanvas' ); ?></small></span><span><kbd>Ctrl</kbd> + <kbd>P</kbd> <small><?php esc_html_e( 'Quick open', 'codecanvas' ); ?></small></span><span><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>F</kbd> <small><?php esc_html_e( 'Search files', 'codecanvas' ); ?></small></span></div><p class="cc-studio-welcome-note"><?php esc_html_e( 'Select a PHP, CSS, JavaScript, HTML or other supported text file from the Explorer to begin.', 'codecanvas' ); ?></p></div></div>
                <div class="cc-studio-statusbar"><span id="cc-studio-status-message"><span class="cc-studio-status-dot"></span><?php esc_html_e( 'Ready', 'codecanvas' ); ?></span><span id="cc-studio-cursor">Ln 1, Col 1</span><span id="cc-studio-language">No file</span><span id="cc-studio-encoding">UTF-8</span></div>
            </main>
            <aside class="cc-studio-inspector"><div class="cc-studio-inspector-tabs"><span class="is-active"><?php esc_html_e( 'OUTLINE', 'codecanvas' ); ?></span><span><?php esc_html_e( 'PREVIEW', 'codecanvas' ); ?></span></div><div class="cc-studio-inspector-content" id="cc-studio-inspector-content"><div class="cc-studio-inspector-empty"><span class="dashicons dashicons-editor-code"></span><strong><?php esc_html_e( 'Source inspector', 'codecanvas' ); ?></strong><p><?php esc_html_e( 'Select a file to see file details, revisions and quick actions.', 'codecanvas' ); ?></p><a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview website', 'codecanvas' ); ?></a></div></div></aside>
        </div>
        <div class="cc-studio-drawer-backdrop" id="cc-studio-drawer-backdrop" aria-hidden="true"></div>
        <footer class="cc-studio-footer"><span><?php esc_html_e( 'CodeCanvas Studio edits real files under wp-content/themes and wp-content/plugins.', 'codecanvas' ); ?></span><span>Developed by <a href="https://surajdev.com/" target="_blank" rel="noopener noreferrer">Suraj Dev</a> · Credit to <a href="https://webot.surajdev.com/" target="_blank" rel="noopener noreferrer">Webot</a></span></footer>
        <div id="cc-studio-toast" class="cc-studio-toast" role="status" aria-live="polite" hidden></div>
    </div>
    <?php
}

/** Search readable source files without traversing symlinks or generated dependency folders. */
function codecanvas_studio_ajax_search() {
    check_ajax_referer( 'codecanvas_studio', 'nonce' );
    $root = codecanvas_studio_request();
    $query = isset( $_POST['query'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['query'] ) ) ) : '';
    $base_rel = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
    if ( strlen( $query ) < 2 ) { wp_send_json_error( array( 'message' => __( 'Enter at least 2 characters to search.', 'codecanvas' ) ), 400 ); }
    $base = codecanvas_studio_resolve( $root, $base_rel );
    if ( is_wp_error( $base ) || ! is_dir( $base ) || ! is_readable( $base ) ) { wp_send_json_error( array( 'message' => is_wp_error( $base ) ? $base->get_error_message() : __( 'Search folder is not readable.', 'codecanvas' ) ), 400 ); }
    $root_path = codecanvas_studio_root_path( $root );
    $results = array(); $visited = 0; $max_files = 300; $needle = strtolower( $query );
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
                function ( $current ) {
                    $name = $current->getFilename();
                    if ( $current->isLink() ) { return false; }
                    if ( $current->isDir() && in_array( $name, array( '.git', 'node_modules', '.svn', '.hg' ), true ) ) { return false; }
                    return true;
                }
            ), RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ( $iterator as $item ) {
            if ( $visited >= $max_files || count( $results ) >= 100 ) { break; }
            if ( ! $item->isFile() || $item->isLink() || ! codecanvas_studio_is_allowed_file( $item->getPathname() ) || $item->getSize() > 524288 || ! is_readable( $item->getPathname() ) ) { continue; }
            $visited++;
            $text = @file_get_contents( $item->getPathname() );
            if ( ! is_string( $text ) || false === stripos( $text, $query ) ) { continue; }
            $lines = preg_split( "/\r\n|\n|\r/", $text );
            foreach ( $lines as $line_number => $line ) {
                if ( false !== stripos( $line, $query ) ) {
                    $full = wp_normalize_path( $item->getPathname() );
                    $relative = ltrim( str_replace( wp_normalize_path( $root_path ), '', $full ), '/' );
                    $results[] = array( 'path' => $relative, 'name' => basename( $relative ), 'line' => $line_number + 1, 'snippet' => substr( trim( $line ), 0, 180 ) );
                    if ( count( $results ) >= 100 ) { break 2; }
                }
            }
        }
    } catch ( Exception $exception ) {
        wp_send_json_error( array( 'message' => __( 'Search could not complete for this directory.', 'codecanvas' ) ), 500 );
    }
    wp_send_json_success( array( 'results' => $results, 'files_scanned' => $visited, 'truncated' => $visited >= $max_files || count( $results ) >= 100 ) );
}
add_action( 'wp_ajax_codecanvas_studio_search', 'codecanvas_studio_ajax_search' );
