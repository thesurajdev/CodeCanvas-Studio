<?php
/** CodeCanvas onboarding and administration screens. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Query arguments only select or display admin screen state; every state change is a nonce-protected POST request.

/** Load the shared admin stylesheet on CodeCanvas screens. */
function codecanvas_admin_styles( $hook ) {
    // Code Studio brings its own stylesheet; every other CodeCanvas screen shares this chrome.
    if ( false === strpos( (string) $hook, 'codecanvas' ) || 'toplevel_page_codecanvas-studio' === (string) $hook ) { return; }
    wp_enqueue_style( 'codecanvas-admin', CODECANVAS_URI . '/assets/css/admin.css', array(), CODECANVAS_VERSION );
}
add_action( 'admin_enqueue_scripts', 'codecanvas_admin_styles' );

function codecanvas_admin_header( $title, $subtitle = '' ) {
    echo '<div class="wrap cc-admin-wrap"><div class="cc-admin-header"><div><div class="cc-admin-mark">CC</div><div><p class="cc-admin-kicker">CODECANVAS PLUGIN</p><h1>' . esc_html( $title ) . '</h1>';
    if ( $subtitle ) { echo '<p class="cc-admin-subtitle">' . esc_html( $subtitle ) . '</p>'; }
    echo '</div></div><a class="cc-admin-pill" href="https://surajdev.com/" target="_blank" rel="noopener noreferrer">Made by Suraj Dev <span aria-hidden="true">↗</span></a></div>';
    if ( isset( $_GET['cc_message'] ) ) {
        $message = sanitize_key( wp_unslash( $_GET['cc_message'] ) );
        $messages = array( 'saved' => __( 'Your settings have been saved.', 'codecanvas' ), 'created' => __( 'Your content type has been created.', 'codecanvas' ), 'deleted' => __( 'The registration was removed. Existing content has not been deleted.', 'codecanvas' ), 'error' => __( 'The request could not be completed. Check the message below and try again.', 'codecanvas' ) );
        if ( isset( $messages[ $message ] ) ) { echo '<div class="notice notice-' . ( 'error' === $message ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $message ] ) . '</p></div>'; }
    }
}

function codecanvas_admin_footer_credit() {
    echo '<div class="cc-admin-credit">CodeCanvas is developed by <a href="https://surajdev.com/" target="_blank" rel="noopener noreferrer">Suraj Dev</a> · Credit to <a href="https://webot.surajdev.com/" target="_blank" rel="noopener noreferrer">Webot</a>.</div></div>';
}

function codecanvas_admin_welcome_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $site_theme_active = function_exists( 'codecanvas_studio_site_child_active' ) && codecanvas_studio_site_child_active();
    $mcp       = function_exists( 'codecanvas_mcp_options' ) ? codecanvas_mcp_options() : array( 'enabled' => false );
    $mcp_tools = ( ! empty( $mcp['enabled'] ) && function_exists( 'codecanvas_mcp_enabled_tools' ) ) ? count( codecanvas_mcp_enabled_tools() ) : 0;
    $guard     = function_exists( 'codecanvas_safety_guard_installed' ) ? codecanvas_safety_guard_installed() : false;
    codecanvas_admin_header( __( 'Welcome to CodeCanvas', 'codecanvas' ),__('A code-first WordPress builder with a real file workspace, a scoped MCP server, coded pages and a generated website theme.', 'codecanvas') );
    ?>
    <section class="cc-admin-hero">
        <div class="cc-admin-hero-copy">
            <span class="cc-admin-eyebrow"><?php
                /* translators: %s: CodeCanvas version number. */
                echo esc_html( sprintf( __( 'CODECANVAS %s · BUILT FOR MAKERS', 'codecanvas' ), CODECANVAS_VERSION ) );
            ?></span>
            <h2><?php esc_html_e( 'Your site. Your source code.', 'codecanvas' ); ?></h2>
            <p><?php esc_html_e( 'Design with pages and blocks, drop into real HTML, CSS and PHP when a layout needs it, and let an AI assistant work through a revocable, tool-scoped connection. Snapshots and the recovery guard sit behind every write.', 'codecanvas' ); ?></p>
            <a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-studio' ) ); ?>"><?php esc_html_e( 'Open Code Studio', 'codecanvas' ); ?></a>
            <a class="button button-secondary button-hero" href="<?php echo esc_url( codecanvas_page_editor_url() ); ?>"><?php esc_html_e( 'Create a coded page', 'codecanvas' ); ?></a>
            <a class="button button-secondary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-settings' ) ); ?>"><?php esc_html_e( 'MCP & Settings', 'codecanvas' ); ?></a>
        </div>
        <div class="cc-admin-hero-art"><div class="cc-art-window"><div class="cc-art-top"><i></i><i></i><i></i><span>welcome.php</span></div><pre><b>&lt;main&gt;</b>
  <em>&lt;h1&gt;</em>Make it yours<em>&lt;/h1&gt;</em>
  <em>&lt;p&gt;</em>Content in WordPress.<em>&lt;/p&gt;</em>
  <em>&lt;p&gt;</em>Design in code.<em>&lt;/p&gt;</em>
<b>&lt;/main&gt;</b>

<span>/* Safe by default */</span>
<b>.cc-snapshot {</b>
  restore: on-fatal;
<b>}</b></pre></div><span class="cc-art-caption">LESS FRICTION. MORE CONTROL.</span></div>
    </section>

    <section class="cc-admin-stat-grid">
        <div class="cc-admin-stat">
            <span><?php esc_html_e( 'Website theme', 'codecanvas' ); ?></span>
            <strong class="is-small"><?php echo esc_html( $site_theme_active ? __( 'CodeCanvas Site', 'codecanvas' ) : __( 'Setup needed', 'codecanvas' ) ); ?></strong>
            <small><?php echo esc_html( $site_theme_active ? __( 'Generated and editable in Code Studio', 'codecanvas' ) : __( 'Create it from Code Studio', 'codecanvas' ) ); ?></small>
        </div>
        <div class="cc-admin-stat">
            <span><?php esc_html_e( 'MCP server', 'codecanvas' ); ?></span>
            <strong class="<?php echo ! empty( $mcp['enabled'] ) ? 'is-on' : 'is-off'; ?>"><?php echo esc_html( ! empty( $mcp['enabled'] ) ? __( 'On', 'codecanvas' ) : __( 'Off', 'codecanvas' ) ); ?></strong>
            <small><?php
                /* translators: %d: number of enabled MCP tools. */
                echo esc_html( ! empty( $mcp['enabled'] ) ? sprintf( _n( '%d tool enabled', '%d tools enabled', $mcp_tools, 'codecanvas' ), $mcp_tools ) : __( 'Enable it in MCP & Settings', 'codecanvas' ) );
            ?></small>
        </div>
        <div class="cc-admin-stat">
            <span><?php esc_html_e( 'Recovery guard', 'codecanvas' ); ?></span>
            <strong class="<?php echo $guard ? 'is-on' : 'is-off'; ?>"><?php echo esc_html( $guard ? __( 'Installed', 'codecanvas' ) : __( 'Not installed', 'codecanvas' ) ); ?></strong>
            <small><?php echo esc_html( $guard ? __( 'Restores the last snapshot after a PHP fatal', 'codecanvas' ) : __( 'Install it before enabling file writes', 'codecanvas' ) ); ?></small>
        </div>
    </section>

    <h2 class="cc-admin-section-title"><?php esc_html_e( 'Three ways to build', 'codecanvas' ); ?></h2>
    <div class="cc-admin-step-grid">
        <a class="cc-admin-step" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-studio' ) ); ?>"><span class="cc-step-num">01</span><span class="dashicons dashicons-editor-code"></span><h3><?php esc_html_e( 'Code Studio', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Browse and edit supported theme and plugin files with syntax highlighting, PHP validation, conflict checks and per-file history.', 'codecanvas' ); ?></p><span class="cc-step-link"><?php esc_html_e( 'Open the workspace →', 'codecanvas' ); ?></span></a>
        <a class="cc-admin-step" href="<?php echo esc_url( codecanvas_page_editor_url() ); ?>"><span class="cc-step-num">02</span><span class="dashicons dashicons-layout"></span><h3><?php esc_html_e( 'Coded pages', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Give one page its own HTML, CSS and JavaScript with a live preview, while the rest of the site keeps the shared header and footer.', 'codecanvas' ); ?></p><span class="cc-step-link"><?php esc_html_e( 'Create a page →', 'codecanvas' ); ?></span></a>
        <a class="cc-admin-step" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-settings' ) ); ?>"><span class="cc-step-num">03</span><span class="dashicons dashicons-superhero-alt"></span><h3><?php esc_html_e( 'AI with MCP', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Connect an assistant with a revocable token or OAuth, choose which projects and tools it may touch, and review every call in the audit log.', 'codecanvas' ); ?></p><span class="cc-step-link"><?php esc_html_e( 'Set up MCP →', 'codecanvas' ); ?></span></a>
        <a class="cc-admin-step" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><span class="cc-step-num">04</span><span class="dashicons dashicons-welcome-write-blog"></span><h3><?php esc_html_e( 'Editable content', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Keep copy, menus and images in WordPress so they stay editable in the block editor. Set a static front page instead of hardcoding homepage text.', 'codecanvas' ); ?></p><span class="cc-step-link"><?php esc_html_e( 'Add a page →', 'codecanvas' ); ?></span></a>
        <a class="cc-admin-step" href="<?php echo esc_url( admin_url( 'themes.php' ) ); ?>"><span class="cc-step-num">05</span><span class="dashicons dashicons-admin-appearance"></span><h3><?php esc_html_e( 'Website theme', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'CodeCanvas generates a real theme for your site, so your templates and styles stay separate from the builder.', 'codecanvas' ); ?></p><span class="cc-step-link"><?php esc_html_e( 'Review themes →', 'codecanvas' ); ?></span></a>
        <a class="cc-admin-step" href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><span class="cc-step-num">06</span><span class="dashicons dashicons-admin-home"></span><h3><?php esc_html_e( 'Front page', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Choose your latest posts or a static page as the homepage, then edit that page like any other content.', 'codecanvas' ); ?></p><span class="cc-step-link"><?php esc_html_e( 'Reading settings →', 'codecanvas' ); ?></span></a>
    </div>

    <div class="cc-admin-two-col">
        <div class="cc-admin-panel">
            <h2><?php esc_html_e( 'Start here', 'codecanvas' ); ?></h2>
            <ol class="cc-check-list">
                <li><?php esc_html_e( 'Create and activate the CodeCanvas Site theme from Code Studio before editing PHP.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Write content in Pages and Posts; open Code Studio only for layout or functionality that content cannot express.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Decide the homepage under Settings → Reading. A static page keeps the copy editable.', 'codecanvas' ); ?></li>
                <li><?php esc_html_e( 'Enable MCP only while you are working with an assistant, and keep file writes off otherwise.', 'codecanvas' ); ?></li>
            </ol>
        </div>
        <div class="cc-admin-panel">
            <h2><?php esc_html_e( 'Safety first', 'codecanvas' ); ?></h2>
            <p><?php esc_html_e( 'CodeCanvas validates PHP before writing, snapshots every changed file, detects conflicting edits and refuses to leave wp-content. When a write still fatals, the recovery guard restores the previous file during that request so the site comes back without File Manager access.', 'codecanvas' ); ?></p>
            <p class="description"><?php esc_html_e( 'A guard is a safety net, not a sandbox: keep backups, use a staging site for risky work, and never put secrets in files or browser code.', 'codecanvas' ); ?></p>
            <p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-settings' ) ); ?>"><?php esc_html_e( 'Review MCP and guard status', 'codecanvas' ); ?></a></p>
        </div>
    </div>
    <?php codecanvas_admin_footer_credit();
}

function codecanvas_save_checklist_handler() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Not allowed.', 'codecanvas' ), '', array( 'response' => 403 ) ); }
    check_admin_referer( 'codecanvas_save_checklist' );
    $allowed = array( 'navigation', 'page', 'content', 'security' );
    $done_raw = isset( $_POST['done'] ) && is_array( $_POST['done'] ) ? wp_unslash( $_POST['done'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each entry is sanitized with sanitize_key() below.
    $done     = array();
    foreach ( $done_raw as $item ) {
        if ( is_string( $item ) ) { $done[] = sanitize_key( $item ); }
    }
    $done = array_values( array_unique( array_intersect( $done, $allowed ) ) );
    update_user_meta( get_current_user_id(), 'codecanvas_setup_progress', $done );
    wp_safe_redirect( add_query_arg( array( 'page' => 'codecanvas', 'cc_message' => 'saved' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_save_checklist', 'codecanvas_save_checklist_handler' );

