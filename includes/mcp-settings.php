<?php
/**
 * CodeCanvas MCP settings screen and token lifecycle.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Query arguments only select or display admin screen state; every state change is a nonce-protected POST request.

/** Register the MCP option and its server-side sanitizer. */
function codecanvas_mcp_register_settings() {
    register_setting( 'codecanvas_mcp_group', 'codecanvas_mcp_options', array(
        'type'              => 'array',
        'sanitize_callback' => 'codecanvas_mcp_sanitize_options',
        'default'           => codecanvas_mcp_default_options(),
    ) );
}
add_action( 'admin_init', 'codecanvas_mcp_register_settings' );

/** Validate every MCP setting against real tools and installed projects. */
function codecanvas_mcp_sanitize_options( $input ) {
    $existing = codecanvas_mcp_options();
    $input    = is_array( $input ) ? $input : array();
    $out      = $existing;

    $out['enabled']              = ! empty( $input['enabled'] );
    $out['allow_writes']         = ! empty( $input['allow_writes'] );
    $out['allow_content_writes'] = ! empty( $input['allow_content_writes'] );
    $out['rate_limit']           = isset( $input['rate_limit'] ) ? max( 1, min( 600, absint( $input['rate_limit'] ) ) ) : 60;

    /*
     * register_setting() attaches this sanitizer to every update_option() call,
     * not only options.php. The settings form never submits the token fields, so
     * they must be carried over from the input when present (token generation and
     * revocation use update_option directly) and otherwise from the stored value.
     */
    $token_hash = isset( $input['token_hash'] ) && is_string( $input['token_hash'] ) ? $input['token_hash'] : $existing['token_hash'];
    if ( '' !== $token_hash && ! preg_match( '/^[a-f0-9]{64}$/', $token_hash ) ) {
        $token_hash = $existing['token_hash'];
    }
    $out['token_hash']    = $token_hash;
    $out['token_hint']    = isset( $input['token_hint'] ) && is_string( $input['token_hint'] ) ? sanitize_text_field( $input['token_hint'] ) : $existing['token_hint'];
    $out['token_created'] = isset( $input['token_created'] ) ? absint( $input['token_created'] ) : (int) $existing['token_created'];
    $out['user_id']       = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : (int) $existing['user_id'];

    $tools = isset( $input['tools'] ) && is_array( $input['tools'] ) ? array_map( 'sanitize_key', $input['tools'] ) : array();
    $out['tools'] = array_values( array_intersect( $tools, array_keys( codecanvas_mcp_tools() ) ) );

    $available = codecanvas_mcp_available_projects();
    $projects  = isset( $input['projects'] ) && is_array( $input['projects'] ) ? array_map( 'sanitize_text_field', $input['projects'] ) : array();
    $out['projects'] = array_values( array_intersect( $projects, array_keys( $available ) ) );

    if ( $out['enabled'] && empty( $out['token_hash'] ) && ! ( defined( 'CODECANVAS_MCP_TOKEN' ) && CODECANVAS_MCP_TOKEN ) ) {
        $out['enabled'] = false;
        add_settings_error( 'codecanvas_mcp_options', 'cc_mcp_no_token', __( 'MCP was not enabled because no token exists yet. Generate a token below, then enable the server.', 'codecanvas' ), 'error' );
    }
    if ( $out['enabled'] && empty( $out['projects'] ) ) {
        $out['enabled'] = false;
        add_settings_error( 'codecanvas_mcp_options', 'cc_mcp_no_projects', __( 'MCP was not enabled because no project is selected. Choose at least one theme or plugin project first.', 'codecanvas' ), 'error' );
    }
    return $out;
}

/** Add the MCP & Settings submenu next to Code Studio. */
function codecanvas_mcp_settings_menu() {
    add_submenu_page(
        'codecanvas',
        __( 'MCP & Settings', 'codecanvas' ),
        __( 'MCP & Settings', 'codecanvas' ),
        'manage_options',
        'codecanvas-settings',
        'codecanvas_mcp_settings_page'
    );
}
add_action( 'admin_menu', 'codecanvas_mcp_settings_menu', 18 );

/** Load the shared admin stylesheet only on this screen. */
function codecanvas_mcp_settings_assets( $hook ) {
    if ( 'codecanvas_page_codecanvas-settings' !== (string) $hook ) { return; }
    wp_enqueue_style( 'codecanvas-admin', CODECANVAS_URI . '/assets/css/admin.css', array(), CODECANVAS_VERSION );
    wp_enqueue_style( 'codecanvas-mcp-settings', CODECANVAS_URI . '/assets/css/mcp-settings.css', array( 'codecanvas-admin' ), CODECANVAS_VERSION );
}
add_action( 'admin_enqueue_scripts', 'codecanvas_mcp_settings_assets' );

/** Generate a token through a nonce-protected admin-post request. */
function codecanvas_mcp_handle_generate_token() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage the MCP server.', 'codecanvas' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'codecanvas_mcp_generate_token' );
    codecanvas_mcp_generate_token();
    wp_safe_redirect( add_query_arg( array( 'page' => 'codecanvas-settings', 'cc_mcp_notice' => 'token-created' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_mcp_generate_token', 'codecanvas_mcp_handle_generate_token' );

/** Revoke the active token. */
function codecanvas_mcp_handle_revoke_token() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage the MCP server.', 'codecanvas' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'codecanvas_mcp_revoke_token' );
    codecanvas_mcp_revoke_token();
    wp_safe_redirect( add_query_arg( array( 'page' => 'codecanvas-settings', 'cc_mcp_notice' => 'token-revoked' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_mcp_revoke_token', 'codecanvas_mcp_handle_revoke_token' );

/** Clear the audit log. */
function codecanvas_mcp_handle_clear_audit() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage the MCP server.', 'codecanvas' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'codecanvas_mcp_clear_audit' );
    delete_option( 'codecanvas_mcp_audit' );
    wp_safe_redirect( add_query_arg( array( 'page' => 'codecanvas-settings', 'cc_mcp_notice' => 'audit-cleared' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_mcp_clear_audit', 'codecanvas_mcp_handle_clear_audit' );

/** One-time token display for the current administrator. */
function codecanvas_mcp_take_new_token() {
    $key   = 'codecanvas_mcp_new_token_' . get_current_user_id();
    $token = get_transient( $key );
    if ( $token ) { delete_transient( $key ); }
    return is_string( $token ) ? $token : '';
}

/** Render the MCP & Settings screen. */
function codecanvas_mcp_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }

    $options      = codecanvas_mcp_options();
    $available    = codecanvas_mcp_available_projects();
    $tools        = codecanvas_mcp_tools();
    $enabled      = codecanvas_mcp_enabled_tools();
    $exposed_count = $options['enabled'] ? count( $enabled ) : 0;
    $new_token    = codecanvas_mcp_take_new_token();
    $endpoint     = rest_url( 'codecanvas/v1/mcp' );
    $token_active = ( ! empty( $options['token_hash'] ) ) || ( defined( 'CODECANVAS_MCP_TOKEN' ) && CODECANVAS_MCP_TOKEN );
    $token_for_ui = $new_token ? $new_token : ( $token_active ? 'ccmcp_YOUR_TOKEN' : 'ccmcp_YOUR_TOKEN' );
    $last_used    = (int) get_option( 'codecanvas_mcp_last_used', 0 );
    $notice        = isset( $_GET['cc_mcp_notice'] ) ? sanitize_key( wp_unslash( $_GET['cc_mcp_notice'] ) ) : '';
    $safety_notice = isset( $_GET['cc_safety_notice'] ) ? sanitize_key( wp_unslash( $_GET['cc_safety_notice'] ) ) : '';
    $audit        = get_option( 'codecanvas_mcp_audit', array() );
    $audit        = is_array( $audit ) ? $audit : array();
    $oauth_counts = function_exists( 'codecanvas_oauth_token_counts' ) ? codecanvas_oauth_token_counts() : array( 'access' => 0, 'refresh' => 0, 'clients' => 0 );
    $oauth_active = ( $oauth_counts['access'] + $oauth_counts['refresh'] ) > 0;
    ?>
    <div class="wrap cc-admin-wrap cc-mcp-wrap">
        <div class="cc-admin-header">
            <div>
                <div class="cc-admin-mark">CC</div>
                <div>
                    <p class="cc-admin-kicker">CODECANVAS · MCP</p>
                    <h1><?php esc_html_e( 'MCP & Settings', 'codecanvas' ); ?></h1>
                    <p class="cc-admin-subtitle"><?php esc_html_e( 'Connect an AI assistant to this WordPress site with a scoped, revocable token.', 'codecanvas' ); ?></p>
                </div>
            </div>
            <a class="cc-admin-pill" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-studio' ) ); ?>">Code Studio <span aria-hidden="true">→</span></a>
        </div>

        <?php if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'MCP settings saved.', 'codecanvas' ); ?></p></div>
        <?php endif; ?>
        <?php settings_errors( 'codecanvas_mcp_options' ); ?>

        <?php if ( 'token-created' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'New MCP token created.', 'codecanvas' ); ?></strong> <?php esc_html_e( 'Copy it now: it is shown only once. Then enable the server below.', 'codecanvas' ); ?></p></div>
        <?php elseif ( 'token-revoked' === $notice ) : ?>
            <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'MCP token revoked and the server switched off. Any connected client is disconnected immediately.', 'codecanvas' ); ?></p></div>
        <?php elseif ( 'audit-cleared' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'MCP audit log cleared.', 'codecanvas' ); ?></p></div>
        <?php elseif ( 'oauth-revoked' === $notice ) : ?>
            <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'All OAuth connections were revoked. Connected apps must sign in again; the static bearer token still works.', 'codecanvas' ); ?></p></div>
        <?php endif; ?>

        <?php if ( 'guard-installed' === $safety_notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Recovery guard installed. It will restore the last snapshot if a theme or plugin file written here causes a fatal error.', 'codecanvas' ); ?></p></div>
        <?php elseif ( 'guard-removed' === $safety_notice ) : ?>
            <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Recovery guard removed. Fatal-error recovery is off until it is installed again.', 'codecanvas' ); ?></p></div>
        <?php elseif ( 'guard-error' === $safety_notice ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The recovery guard could not be changed. See the error under Recovery guard below.', 'codecanvas' ); ?></p></div>
        <?php endif; ?>

        <?php if ( $new_token ) : ?>
            <div class="cc-admin-panel cc-mcp-token-panel">
                <h2><?php esc_html_e( 'Copy your new token', 'codecanvas' ); ?></h2>
                <p><?php esc_html_e( 'This is the only time the full token is displayed. Store it in your MCP client configuration, not in a repository or chat history.', 'codecanvas' ); ?></p>
                <div class="cc-mcp-copy-row">
                    <input type="text" id="cc-mcp-new-token" class="regular-text code" readonly value="<?php echo esc_attr( $new_token ); ?>" aria-label="<?php esc_attr_e( 'New MCP token', 'codecanvas' ); ?>">
                    <button type="button" class="button button-primary" id="cc-mcp-copy-token"><?php esc_html_e( 'Copy token', 'codecanvas' ); ?></button>
                </div>
                <p class="description"><?php esc_html_e( 'If you lose it, revoke it and generate a new token. Tokens are stored only as an HMAC hash.', 'codecanvas' ); ?></p>
            </div>
        <?php endif; ?>

        <div class="cc-mcp-status-grid">
            <div class="cc-admin-stat">
                <span><?php esc_html_e( 'Server', 'codecanvas' ); ?></span>
                <strong class="<?php echo $options['enabled'] ? 'is-on' : 'is-off'; ?>"><?php echo $options['enabled'] ? esc_html__( 'Enabled', 'codecanvas' ) : esc_html__( 'Disabled', 'codecanvas' ); ?></strong>
                <small><?php echo $options['enabled'] ? esc_html__( 'Listening for authenticated requests', 'codecanvas' ) : esc_html__( 'No MCP requests are accepted', 'codecanvas' ); ?></small>
            </div>
            <div class="cc-admin-stat">
                <span><?php esc_html_e( 'Token', 'codecanvas' ); ?></span>
                <strong class="<?php echo $token_active ? 'is-on' : 'is-off'; ?>"><?php echo $token_active ? esc_html__( 'Active', 'codecanvas' ) : esc_html__( 'None', 'codecanvas' ); ?></strong>
                <small><?php echo $token_active && $options['token_hint'] ? esc_html( $options['token_hint'] ) : esc_html__( 'Generate a token to connect a client', 'codecanvas' ); ?></small>
            </div>
            <div class="cc-admin-stat">
                <span><?php esc_html_e( 'Exposed tools', 'codecanvas' ); ?></span>
                <strong><?php echo esc_html( (string) $exposed_count ); ?></strong>
                <small><?php
                    /* translators: %d: number of projects exposed to MCP. */
                    echo $options['enabled'] ? esc_html( sprintf( _n( '%d project selected', '%d projects selected', count( $options['projects'] ), 'codecanvas' ), count( $options['projects'] ) ) ) : esc_html__( 'Server is disabled', 'codecanvas' );
                ?></small>
            </div>
            <div class="cc-admin-stat">
                <span><?php esc_html_e( 'Last request', 'codecanvas' ); ?></span>
                <strong class="is-small"><?php echo $last_used ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_used ) ) : esc_html__( 'Never', 'codecanvas' ); ?></strong>
                <small><?php
                    /* translators: %d: requests allowed per minute. */
                    echo esc_html( sprintf( __( 'Rate limit: %d requests/minute', 'codecanvas' ), (int) $options['rate_limit'] ) );
                ?></small>
            </div>
        </div>

        <div class="cc-admin-panel cc-mcp-endpoint-panel">
            <h2><?php esc_html_e( 'Endpoint', 'codecanvas' ); ?></h2>
            <p><?php esc_html_e( 'This is a stateless MCP Streamable HTTP endpoint. Authenticate every request with the bearer token.', 'codecanvas' ); ?></p>
            <code class="cc-mcp-endpoint"><?php echo esc_html( $endpoint ); ?></code>
            <p class="description"><?php esc_html_e( 'The endpoint is separate from the Code Studio admin screens and does not accept WordPress cookies as authentication.', 'codecanvas' ); ?></p>
            <p class="description"><?php
                /* translators: %s: sign-in link markup. */
                printf( esc_html__( 'Browser clients that cannot send a header can sign in instead: add the URL above as a custom connector and choose %s.', 'codecanvas' ), '<code>Sign in now</code>' );
            ?></p>
        </div>

        <div class="cc-admin-panel">
            <h2><?php esc_html_e( 'OAuth sign-in', 'codecanvas' ); ?></h2>
            <p><?php esc_html_e( 'claude.ai, Claude Desktop and other browser-based clients connect with OAuth 2.1 instead of a fixed header. Each app is approved once by an administrator on a consent screen and can be revoked here.', 'codecanvas' ); ?></p>
            <div class="cc-mcp-status-grid">
                <div class="cc-admin-stat">
                    <span><?php esc_html_e( 'Active grants', 'codecanvas' ); ?></span>
                    <strong class="<?php echo $oauth_active ? 'is-on' : 'is-off'; ?>"><?php echo esc_html( (string) (int) $oauth_counts['access'] ); ?></strong>
                    <small><?php
                        /* translators: %d: number of active refresh tokens. */
                        echo esc_html( sprintf( __( '%d refresh token(s)', 'codecanvas' ), (int) $oauth_counts['refresh'] ) );
                    ?></small>
                </div>
                <div class="cc-admin-stat">
                    <span><?php esc_html_e( 'Registered clients', 'codecanvas' ); ?></span>
                    <strong><?php echo esc_html( (string) (int) $oauth_counts['clients'] ); ?></strong>
                    <small><?php esc_html_e( 'Created by dynamic registration', 'codecanvas' ); ?></small>
                </div>
                <div class="cc-admin-stat">
                    <span><?php esc_html_e( 'Issuer', 'codecanvas' ); ?></span>
                    <strong class="is-small"><code><?php echo esc_html( codecanvas_oauth_issuer() ); ?></code></strong>
                    <small><?php esc_html_e( 'Access tokens expire after one hour and refresh for 30 days', 'codecanvas' ); ?></small>
                </div>
            </div>
            <p class="description"><?php
                /* translators: %1$s and %2$s: discovery document URLs. */
                printf( esc_html__( 'Discovery documents: %1$s and %2$s.', 'codecanvas' ), '<code>' . esc_html( codecanvas_oauth_resource_metadata_url() ) . '</code>', '<code>' . esc_html( codecanvas_oauth_well_known_url( 'oauth-authorization-server' ) ) . '</code>' );
            ?></p>
            <p class="description"><?php esc_html_e( 'OAuth requires the theme to be installed at the domain root so the discovery URLs resolve. Connections are limited to administrators with the manage_options capability.', 'codecanvas' ); ?></p>
            <?php if ( $oauth_active ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="codecanvas_oauth_revoke_all">
                    <?php wp_nonce_field( 'codecanvas_oauth_revoke_all' ); ?>
                    <button type="submit" class="button button-link-delete" onclick="return window.confirm('Revoke every OAuth connection? Connected apps must sign in again.');"><?php esc_html_e( 'Revoke all OAuth connections', 'codecanvas' ); ?></button>
                </form>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'No app has signed in yet.', 'codecanvas' ); ?></p>
            <?php endif; ?>
        </div>

        <form method="post" action="options.php" class="cc-mcp-settings-form">
            <?php settings_fields( 'codecanvas_mcp_group' ); ?>

            <div class="cc-admin-two-col">
                <div class="cc-admin-panel">
                    <h2><?php esc_html_e( 'Server access', 'codecanvas' ); ?></h2>
                    <p><?php esc_html_e( 'MCP is off by default. Enabling it exposes only the tools and projects selected here.', 'codecanvas' ); ?></p>
                    <p class="cc-mcp-toggle">
                        <label>
                            <input type="checkbox" name="codecanvas_mcp_options[enabled]" value="1" <?php checked( $options['enabled'] ); ?> <?php disabled( ! $token_active ); ?>>
                            <strong><?php esc_html_e( 'Enable the MCP server', 'codecanvas' ); ?></strong>
                        </label>
                    </p>
                    <?php if ( ! $token_active ) : ?>
                        <p class="description"><?php esc_html_e( 'Generate a token first. The checkbox becomes available once a token exists.', 'codecanvas' ); ?></p>
                    <?php endif; ?>
                    <p class="cc-mcp-toggle">
                        <label>
                            <input type="checkbox" name="codecanvas_mcp_options[allow_writes]" value="1" <?php checked( $options['allow_writes'] ); ?>>
                            <strong><?php esc_html_e( 'Allow file writes (write_file, patch_file, create_file, create_files, create_plugin, delete_file, restore_snapshot)', 'codecanvas' ); ?></strong>
                        </label>
                    </p>
                    <p class="cc-mcp-toggle">
                        <label>
                            <input type="checkbox" name="codecanvas_mcp_options[allow_content_writes]" value="1" <?php checked( $options['allow_content_writes'] ); ?>>
                            <strong><?php esc_html_e( 'Allow content writes (create_content, update_content, delete_content, update_settings, update_menu, upload_media)', 'codecanvas' ); ?></strong>
                        </label>
                    </p>
                    <p class="cc-mcp-field">
                        <label for="cc-mcp-rate"><?php esc_html_e( 'Rate limit (requests per minute)', 'codecanvas' ); ?></label>
                        <input type="number" id="cc-mcp-rate" min="1" max="600" step="1" name="codecanvas_mcp_options[rate_limit]" value="<?php echo esc_attr( (string) $options['rate_limit'] ); ?>" class="small-text">
                    </p>
                    <div class="cc-admin-callout cc-admin-callout-warning">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <p><strong><?php esc_html_e( 'Execute code with care.', 'codecanvas' ); ?></strong> <?php esc_html_e( 'File write access lets a connected client change real PHP, CSS and JavaScript on this site. Every write is snapshotted and PHP is syntax-checked, but that is not a sandbox. Use staging and version control for risky changes.', 'codecanvas' ); ?></p>
                    </div>
                </div>

                <div class="cc-admin-panel">
                    <h2><?php esc_html_e( 'Token', 'codecanvas' ); ?></h2>
                    <?php if ( $token_active ) : ?>
                        <p><?php esc_html_e( 'A token is active. It belongs to the administrator who generated it and can be revoked at any time.', 'codecanvas' ); ?></p>
                        <p class="cc-mcp-token-meta">
                            <span><?php esc_html_e( 'Hint:', 'codecanvas' ); ?> <code><?php echo esc_html( $options['token_hint'] ? $options['token_hint'] : 'CODECANVAS_MCP_TOKEN' ); ?></code></span>
                            <?php if ( ! empty( $options['token_created'] ) ) : ?>
                                <span><?php esc_html_e( 'Created:', 'codecanvas' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) $options['token_created'] ) ); ?></span>
                            <?php endif; ?>
                            <?php if ( defined( 'CODECANVAS_MCP_TOKEN' ) && CODECANVAS_MCP_TOKEN ) : ?>
                                <span><?php esc_html_e( 'A CODECANVAS_MCP_TOKEN constant is also configured and takes precedence.', 'codecanvas' ); ?></span>
                            <?php endif; ?>
                        </p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No token exists yet. Generate one to connect Claude Code, Cursor, VS Code or another MCP client.', 'codecanvas' ); ?></p>
                    <?php endif; ?>
                    <div class="cc-mcp-token-actions">
                        <button type="submit" form="cc-mcp-generate-form" class="button <?php echo $token_active ? '' : 'button-primary'; ?>"><?php echo $token_active ? esc_html__( 'Generate a replacement token', 'codecanvas' ) : esc_html__( 'Generate token', 'codecanvas' ); ?></button>
                        <?php if ( $token_active ) : ?>
                            <button type="submit" form="cc-mcp-revoke-form" class="button button-link-delete" onclick="return window.confirm('Revoke the active token and disable MCP? Connected clients will stop working immediately.');"><?php esc_html_e( 'Revoke token', 'codecanvas' ); ?></button>
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php esc_html_e( 'Generating a replacement immediately invalidates the previous token.', 'codecanvas' ); ?></p>
                    <p class="description"><?php esc_html_e( 'Permanent deployments can instead define CODECANVAS_MCP_TOKEN in wp-config.php; the stored token hash is then ignored.', 'codecanvas' ); ?></p>
                </div>
            </div>

            <div class="cc-admin-panel">
                <h2><?php esc_html_e( 'Projects exposed to MCP', 'codecanvas' ); ?></h2>
                <p><?php esc_html_e( 'A client can only list, read or write projects selected here. CodeCanvas plugin source is never exposed.', 'codecanvas' ); ?></p>
                <?php if ( empty( $available ) ) : ?>
                    <p class="description"><?php esc_html_e( 'No theme or plugin projects are available.', 'codecanvas' ); ?></p>
                <?php else : ?>
                    <div class="cc-mcp-project-grid">
                        <?php foreach ( $available as $key => $project ) : ?>
                            <label class="cc-mcp-project">
                                <input type="checkbox" name="codecanvas_mcp_options[projects][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $options['projects'], true ) ); ?>>
                                <span>
                                    <strong><?php echo esc_html( $project['label'] ); ?></strong>
                                    <code><?php echo esc_html( $key ); ?></code>
                                    <small>
                                        <?php echo 'theme' === $project['type'] ? esc_html__( 'Theme', 'codecanvas' ) : esc_html__( 'Plugin', 'codecanvas' ); ?>
                                        <?php if ( $project['site'] ) : ?> · <?php esc_html_e( 'Website theme', 'codecanvas' ); ?><?php endif; ?>
                                        <?php if ( $project['active'] ) : ?> · <?php esc_html_e( 'Active', 'codecanvas' ); ?><?php endif; ?>
                                    </small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cc-admin-panel">
                <h2><?php esc_html_e( 'Tools exposed to MCP', 'codecanvas' ); ?></h2>
                <p><?php esc_html_e( 'Expand a capability to choose the tools an AI client may call. Write tools also need the matching switch above. Anything left off, or blocked by a switch, is refused when a client tries to use it.', 'codecanvas' ); ?></p>
                <?php
                $tool_groups = array(
                    'read'    => array( 'label' => __( 'Read-only tools', 'codecanvas' ), 'items' => array() ),
                    'write'   => array( 'label' => __( 'File write tools', 'codecanvas' ), 'items' => array() ),
                    'content' => array( 'label' => __( 'Content write tools', 'codecanvas' ), 'items' => array() ),
                );
                foreach ( $tools as $name => $tool ) {
                    $group_key = isset( $tool_groups[ $tool['mode'] ] ) ? $tool['mode'] : 'read';
                    $tool_groups[ $group_key ]['items'][ $name ] = $tool;
                }
                ?>
                <div class="cc-mcp-tool-groups">
                    <?php foreach ( $tool_groups as $group_key => $group ) : ?>
                        <?php if ( empty( $group['items'] ) ) { continue; } ?>
                        <?php
                        $group_selected = 0;
                        foreach ( $group['items'] as $group_tool_name => $group_tool ) {
                            if ( in_array( $group_tool_name, $options['tools'], true ) ) { $group_selected++; }
                        }
                        $group_total = count( $group['items'] );
                        ?>
                        <details class="cc-mcp-group">
                            <summary>
                                <span class="cc-mcp-group-title"><?php echo esc_html( $group['label'] ); ?></span>
                                <span class="cc-mcp-group-meta"><?php
                                    /* translators: %1$d: number of enabled tools, %2$d: total tools in the group. */
                                    echo esc_html( sprintf( __( '%1$d of %2$d enabled', 'codecanvas' ), $group_selected, $group_total ) );
                                ?></span>
                            </summary>
                            <div class="cc-mcp-group-body">
                                <?php foreach ( $group['items'] as $name => $tool ) : ?>
                                    <label class="cc-mcp-tool">
                                        <input type="checkbox" name="codecanvas_mcp_options[tools][]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $options['tools'], true ) ); ?>>
                                        <span class="cc-mcp-tool-copy">
                                            <strong><?php echo esc_html( $tool['title'] ); ?><code><?php echo esc_html( $name ); ?></code></strong>
                                            <small title="<?php echo esc_attr( $tool['description'] ); ?>"><?php echo esc_html( $tool['description'] ); ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php submit_button( __( 'Save MCP settings', 'codecanvas' ) ); ?>
        </form>

        <?php /* Token actions live outside the settings form: nesting forms is invalid HTML and silently detaches the settings submit button. */ ?>
        <form id="cc-mcp-generate-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
            <input type="hidden" name="action" value="codecanvas_mcp_generate_token">
            <?php wp_nonce_field( 'codecanvas_mcp_generate_token' ); ?>
        </form>
        <form id="cc-mcp-revoke-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
            <input type="hidden" name="action" value="codecanvas_mcp_revoke_token">
            <?php wp_nonce_field( 'codecanvas_mcp_revoke_token' ); ?>
        </form>

        <div class="cc-admin-panel">
            <h2><?php esc_html_e( 'Connect a client', 'codecanvas' ); ?></h2>
            <p><?php esc_html_e( 'Use the endpoint and token above. The examples use a placeholder token; replace it with the real one.', 'codecanvas' ); ?></p>
            <details class="cc-mcp-details">
                <summary><?php esc_html_e( 'Show setup snippets for Claude Code, Cursor and VS Code', 'codecanvas' ); ?></summary>

            <h3><?php esc_html_e( 'Claude Code', 'codecanvas' ); ?></h3>
            <pre class="cc-mcp-snippet">claude mcp add --transport http codecanvas <?php echo esc_html( $endpoint ); ?> \
  --header "Authorization: Bearer <?php echo esc_html( $token_for_ui ); ?>"</pre>
            <h3><?php esc_html_e( 'Cursor (~/.cursor/mcp.json)', 'codecanvas' ); ?></h3>
            <pre class="cc-mcp-snippet">{
  "mcpServers": {
    "codecanvas": {
      "url": "<?php echo esc_html( $endpoint ); ?>",
      "headers": { "Authorization": "Bearer <?php echo esc_html( $token_for_ui ); ?>" }
    }
  }
}</pre>
            <h3><?php esc_html_e( 'VS Code (.vscode/mcp.json)', 'codecanvas' ); ?></h3>
            <pre class="cc-mcp-snippet">{
  "servers": {
    "codecanvas": {
      "type": "http",
      "url": "<?php echo esc_html( $endpoint ); ?>",
      "headers": { "Authorization": "Bearer <?php echo esc_html( $token_for_ui ); ?>" }
    }
  }
}</pre>
            <p class="description"><?php
                /* translators: %1$s: developer guide link, %2$s: MCP reference link. */
                printf( esc_html__( 'Developer guide: %1$s · MCP reference: %2$s', 'codecanvas' ), '<a href="' . esc_url( CODECANVAS_URI . '/docs/12-developer-guide.md' ) . '" target="_blank" rel="noopener noreferrer">docs/12-developer-guide.md</a>', '<a href="' . esc_url( CODECANVAS_URI . '/docs/11-mcp-integration.md' ) . '" target="_blank" rel="noopener noreferrer">docs/11-mcp-integration.md</a>' );
            ?></p>
            </details>
        </div>

        <div class="cc-admin-panel">
            <div class="cc-mcp-audit-heading">
                <h2><?php esc_html_e( 'Recent MCP activity', 'codecanvas' ); ?></h2>
                <?php if ( ! empty( $audit ) ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="codecanvas_mcp_clear_audit">
                        <?php wp_nonce_field( 'codecanvas_mcp_clear_audit' ); ?>
                        <button type="submit" class="button button-small"><?php esc_html_e( 'Clear log', 'codecanvas' ); ?></button>
                    </form>
                <?php endif; ?>
            </div>
            <p><?php esc_html_e( 'The last 50 tool calls are recorded without file contents or tokens.', 'codecanvas' ); ?></p>
            <?php if ( empty( $audit ) ) : ?>
                <p class="description"><?php esc_html_e( 'No MCP tool calls yet.', 'codecanvas' ); ?></p>
            <?php else : ?>
                <?php
                $audit_errors = 0;
                foreach ( $audit as $audit_entry ) {
                    if ( is_array( $audit_entry ) && empty( $audit_entry['ok'] ) ) { $audit_errors++; }
                }
                ?>
                <details class="cc-mcp-details">
                    <summary><?php
                        /* translators: %1$d: number of recorded calls, %2$d: number of failed calls. */
                        echo esc_html( sprintf( __( 'Show the last %1$d calls (%2$d failed)', 'codecanvas' ), count( $audit ), $audit_errors ) );
                    ?></summary>
                <table class="widefat striped cc-mcp-audit-table">
                    <thead><tr>
                        <th scope="col"><?php esc_html_e( 'Time', 'codecanvas' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Tool', 'codecanvas' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Result', 'codecanvas' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Duration', 'codecanvas' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Summary', 'codecanvas' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $audit as $entry ) : ?>
                        <?php if ( ! is_array( $entry ) ) { continue; } ?>
                        <tr>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), isset( $entry['time'] ) ? (int) $entry['time'] : 0 ) ); ?></td>
                            <td><code><?php echo esc_html( isset( $entry['tool'] ) ? $entry['tool'] : '' ); ?></code></td>
                            <td><?php echo ! empty( $entry['ok'] ) ? '<span class="cc-mcp-ok">' . esc_html__( 'OK', 'codecanvas' ) . '</span>' : '<span class="cc-mcp-fail">' . esc_html__( 'Error', 'codecanvas' ) . '</span>'; ?></td>
                            <td><?php echo esc_html( isset( $entry['ms'] ) ? (string) (int) $entry['ms'] : '0' ); ?> ms</td>
                            <td><span class="cc-mcp-audit-summary"><?php echo esc_html( isset( $entry['summary'] ) ? $entry['summary'] : '' ); ?></span><?php if ( ! empty( $entry['message'] ) && 'ok' !== $entry['message'] ) : ?><small><?php echo esc_html( $entry['message'] ); ?></small><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </details>
            <?php endif; ?>
        </div>

        <?php if ( function_exists( 'codecanvas_safety_settings_panel' ) ) { codecanvas_safety_settings_panel(); } ?>

        <div class="cc-admin-panel">
            <h2><?php esc_html_e( 'Security model', 'codecanvas' ); ?></h2>
            <ul class="cc-check-list">
                <li><?php esc_html_e( 'Requests need a bearer token; a WordPress login cookie alone is not accepted.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Only the administrator account that generated the token is used, and only while it keeps the manage_options capability.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Projects, tools and write access are all allow-listed; there is no arbitrary filesystem or SQL access.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Paths are canonicalised, traversal and symlinks are rejected, and CodeCanvas framework files are read-only.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Every file write is snapshotted, PHP is syntax-checked and the previous hash can be required to prevent overwriting concurrent edits.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Requests are rate limited and audited without logging file contents or tokens.', 'codecanvas' ); ?></li>
            </ul>
        </div>

        <p class="cc-admin-credit">CodeCanvas by <a href="https://surajdev.com/" target="_blank" rel="noopener noreferrer">Suraj Dev</a> · Credit to <a href="https://webot.surajdev.com/" target="_blank" rel="noopener noreferrer">Webot</a></p>
    </div>
    <script>
    (function () {
        var button = document.getElementById('cc-mcp-copy-token');
        var input = document.getElementById('cc-mcp-new-token');
        if (!button || !input) { return; }
        button.addEventListener('click', function () {
            input.select();
            input.setSelectionRange(0, input.value.length);
            var done = function () {
                button.textContent = 'Copied';
                window.setTimeout(function () { button.textContent = 'Copy token'; }, 1800);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    })();
    </script>
    <?php
}
