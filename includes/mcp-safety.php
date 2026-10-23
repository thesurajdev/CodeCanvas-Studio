<?php
/**
 * CodeCanvas recovery guard installer and status.
 *
 * The guard is a must-use plugin so it loads before the theme and ordinary
 * plugins. If a request dies on a PHP fatal error inside a theme or plugin file
 * that has a CodeCanvas snapshot, the previous contents are restored and the
 * event is logged. That turns a site-down mistake into a one-request outage
 * instead of a File Manager repair job.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Absolute path of the installed guard. */
function codecanvas_safety_guard_target() {
    $dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    return rtrim( $dir, '/\\' ) . '/codecanvas-recovery.php';
}

/** Absolute path of the guard source shipped with the plugin. */
function codecanvas_safety_guard_source() {
    return CODECANVAS_DIR . 'includes/recovery-guard.php';
}

/** True when our guard is installed. */
function codecanvas_safety_guard_installed() {
    $target = codecanvas_safety_guard_target();
    if ( ! is_file( $target ) ) { return false; }
    $head = (string) @file_get_contents( $target, false, null, 0, 4096 );
    return false !== strpos( $head, 'CodeCanvas Recovery Guard' );
}

/**
 * Install or refresh the guard.
 *
 * @return true|WP_Error
 */
function codecanvas_safety_install_guard() {
    if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
        return new WP_Error( 'cc_safety_policy', __( 'DISALLOW_FILE_MODS is enabled, so CodeCanvas cannot install the recovery guard.', 'codecanvas' ) );
    }
    $source = codecanvas_safety_guard_source();
    if ( ! is_file( $source ) || ! is_readable( $source ) ) {
        return new WP_Error( 'cc_safety_source',__('The recovery guard source file is missing from the plugin.', 'codecanvas') );
    }
    $target = codecanvas_safety_guard_target();
    $dir    = dirname( $target );
    if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
        return new WP_Error( 'cc_safety_dir', __( 'The mu-plugins folder could not be created.', 'codecanvas' ) );
    }
    if ( is_file( $target ) && ! codecanvas_safety_guard_installed() ) {
        return new WP_Error( 'cc_safety_conflict', __( 'A different codecanvas-recovery.php already exists in mu-plugins. Rename it first; CodeCanvas will not overwrite an unrelated file.', 'codecanvas' ) );
    }
    $content = file_get_contents( $source );
    if ( ! is_string( $content ) || '' === $content ) {
        return new WP_Error( 'cc_safety_read', __( 'The recovery guard could not be read.', 'codecanvas' ) );
    }
    if ( false === @file_put_contents( $target, $content, LOCK_EX ) ) {
        return new WP_Error( 'cc_safety_write', __( 'The recovery guard could not be written. Check that wp-content/mu-plugins is writable.', 'codecanvas' ) );
    }
    update_option( 'codecanvas_safety_version', CODECANVAS_VERSION, false );
    delete_option( 'codecanvas_safety_error' );
    return true;
}

/** Remove the guard, but only when the installed file is ours. */
function codecanvas_safety_remove_guard() {
    $target = codecanvas_safety_guard_target();
    if ( ! is_file( $target ) ) { return true; }
    if ( ! codecanvas_safety_guard_installed() ) {
        return new WP_Error( 'cc_safety_conflict', __( 'That mu-plugin was not installed by CodeCanvas, so it was left alone.', 'codecanvas' ) );
    }
    wp_delete_file( $target );
    if ( file_exists( $target ) ) {
        return new WP_Error( 'cc_safety_remove', __( 'The recovery guard could not be removed.', 'codecanvas' ) );
    }
    delete_option( 'codecanvas_safety_version' );
    return true;
}

/** Install or refresh the guard once per release, for existing installs too. */
function codecanvas_safety_maybe_install() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) { return; }
    $installed_version = (string) get_option( 'codecanvas_safety_version', '' );
    $source            = codecanvas_safety_guard_source();
    $source_hash       = is_readable( $source ) ? md5_file( $source ) : '';
    $installed_hash    = codecanvas_safety_guard_installed() ? md5_file( codecanvas_safety_guard_target() ) : '';
    if ( CODECANVAS_VERSION === $installed_version && '' !== $source_hash && $source_hash === $installed_hash ) { return; }
    $result = codecanvas_safety_install_guard();
    if ( is_wp_error( $result ) ) {
        update_option( 'codecanvas_safety_error', $result->get_error_message(), false );
        return;
    }
    delete_option( 'codecanvas_safety_error' );
}
add_action( 'admin_init', 'codecanvas_safety_maybe_install', 30 );

/** Status payload for the settings panel. */
function codecanvas_safety_status() {
    $log = get_option( 'codecanvas_recovery_log', array() );
    return array(
        'installed' => codecanvas_safety_guard_installed(),
        'path'      => 'wp-content/mu-plugins/codecanvas-recovery.php',
        'error'     => (string) get_option( 'codecanvas_safety_error', '' ),
        'log'       => is_array( $log ) ? array_slice( $log, 0, 10 ) : array(),
    );
}

/** Nonce-protected install/repair action. */
function codecanvas_safety_handle_install() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage the recovery guard.', 'codecanvas' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'codecanvas_safety_install' );
    $result = codecanvas_safety_install_guard();
    $notice = is_wp_error( $result ) ? 'guard-error' : 'guard-installed';
    if ( is_wp_error( $result ) ) { update_option( 'codecanvas_safety_error', $result->get_error_message(), false ); }
    wp_safe_redirect( add_query_arg( array( 'page' => 'codecanvas-settings', 'cc_safety_notice' => $notice ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_safety_install', 'codecanvas_safety_handle_install' );

/** Nonce-protected removal action. */
function codecanvas_safety_handle_remove() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage the recovery guard.', 'codecanvas' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'codecanvas_safety_remove' );
    $result = codecanvas_safety_remove_guard();
    $notice = is_wp_error( $result ) ? 'guard-error' : 'guard-removed';
    if ( is_wp_error( $result ) ) { update_option( 'codecanvas_safety_error', $result->get_error_message(), false ); }
    wp_safe_redirect( add_query_arg( array( 'page' => 'codecanvas-settings', 'cc_safety_notice' => $notice ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_safety_remove', 'codecanvas_safety_handle_remove' );

/** Render the recovery guard panel on the MCP settings screen. */
function codecanvas_safety_settings_panel() {
    $status = codecanvas_safety_status();
    ?>
    <div class="cc-admin-panel">
        <h2><?php esc_html_e( 'Recovery guard', 'codecanvas' ); ?></h2>
        <p><?php esc_html_e( 'A small must-use plugin that loads before the theme. If a request dies on a PHP fatal error in a theme or plugin file that has a CodeCanvas snapshot, it restores the previous contents automatically and logs the event, so a bad write does not require a File Manager repair. It never touches files without a snapshot.', 'codecanvas' ); ?></p>
        <div class="cc-mcp-status-grid">
            <div class="cc-admin-stat">
                <span><?php esc_html_e( 'Guard', 'codecanvas' ); ?></span>
                <strong class="<?php echo $status['installed'] ? 'is-on' : 'is-off'; ?>"><?php echo $status['installed'] ? esc_html__( 'Installed', 'codecanvas' ) : esc_html__( 'Not installed', 'codecanvas' ); ?></strong>
                <small><code><?php echo esc_html( $status['path'] ); ?></code></small>
            </div>
            <div class="cc-admin-stat">
                <span><?php esc_html_e( 'Recoveries', 'codecanvas' ); ?></span>
                <strong><?php echo esc_html( (string) count( $status['log'] ) ); ?></strong>
                <small><?php esc_html_e( 'Last 10 restored files', 'codecanvas' ); ?></small>
            </div>
        </div>
        <?php if ( '' !== $status['error'] ) : ?>
            <p class="description"><strong><?php esc_html_e( 'Last guard error:', 'codecanvas' ); ?></strong> <?php echo esc_html( $status['error'] ); ?></p>
        <?php endif; ?>
        <?php if ( ! empty( $status['log'] ) ) : ?>
            <table class="widefat striped cc-mcp-audit-table">
                <thead><tr>
                    <th scope="col"><?php esc_html_e( 'Time', 'codecanvas' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Restored file', 'codecanvas' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Error', 'codecanvas' ); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $status['log'] as $entry ) : ?>
                    <?php if ( ! is_array( $entry ) ) { continue; } ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), isset( $entry['time'] ) ? (int) $entry['time'] : 0 ) ); ?></td>
                        <td><code><?php echo esc_html( isset( $entry['file'] ) ? $entry['file'] : '' ); ?></code></td>
                        <td><?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="cc-mcp-token-actions">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                <input type="hidden" name="action" value="codecanvas_safety_install">
                <?php wp_nonce_field( 'codecanvas_safety_install' ); ?>
                <button type="submit" class="button"><?php echo $status['installed'] ? esc_html__( 'Repair / refresh guard', 'codecanvas' ) : esc_html__( 'Install recovery guard', 'codecanvas' ); ?></button>
            </form>
            <?php if ( $status['installed'] ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                    <input type="hidden" name="action" value="codecanvas_safety_remove">
                    <?php wp_nonce_field( 'codecanvas_safety_remove' ); ?>
                    <button type="submit" class="button button-link-delete" onclick="return window.confirm('Remove the recovery guard? Fatal-error recovery stops until it is installed again.');"><?php esc_html_e( 'Remove guard', 'codecanvas' ); ?></button>
                </form>
            <?php endif; ?>
        </div>
        <p class="description"><?php esc_html_e( 'The guard is a file in wp-content/mu-plugins, so it keeps working if the theme is switched. Remove it before deleting the CodeCanvas theme.', 'codecanvas' ); ?></p>
    </div>
    <?php
}
