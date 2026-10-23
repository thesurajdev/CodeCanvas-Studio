<?php
/**
 * CodeCanvas visual code workspace for WordPress Pages.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Query arguments only select or display admin screen state; every state change is a nonce-protected POST request.

/** Register the coded-page workspace under Pages. */
function codecanvas_page_editor_menu() {
    add_submenu_page(
        'edit.php?post_type=page',
        __( 'CodeCanvas Editor', 'codecanvas' ),
        __( 'CodeCanvas Editor', 'codecanvas' ),
        'edit_pages',
        'codecanvas-page-editor',
        'codecanvas_render_page_editor'
    );
}
add_action( 'admin_menu', 'codecanvas_page_editor_menu' );

/** URL for the dedicated page editor. */
function codecanvas_page_editor_url( $post_id = 0 ) {
    $url = add_query_arg( array( 'post_type' => 'page', 'page' => 'codecanvas-page-editor' ), admin_url( 'edit.php' ) );
    if ( $post_id ) { $url = add_query_arg( 'post_id', absint( $post_id ), $url ); }
    return $url;
}

/** Route Add New Page into the CodeCanvas workspace. */
function codecanvas_route_new_page_to_editor() {
    if ( isset( $_GET['post_type'] ) && 'page' === sanitize_key( wp_unslash( $_GET['post_type'] ) ) && empty( $_GET['codecanvas_native'] ) && current_user_can( 'edit_pages' ) ) {
        // Route users to CodeCanvas even if they lack unfiltered_html. The workspace
        // explains the permission restriction instead of unexpectedly dropping them
        // into the standard editor with a different workflow.
        wp_safe_redirect( codecanvas_page_editor_url() );
        exit;
    }
}
// Keep the native WordPress editor as the default. CodeCanvas Editor remains an optional workflow.
// add_action( 'load-post-new.php', 'codecanvas_route_new_page_to_editor' );

/** Mark the dedicated editor screen so its workspace can use the full admin canvas. */
function codecanvas_page_editor_admin_body_class( $classes ) {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( 'codecanvas-page-editor' === $page ) {
        $classes .= ' codecanvas-editor-admin';
    }
    return $classes;
}
add_filter( 'admin_body_class', 'codecanvas_page_editor_admin_body_class' );

/** Route existing Page edit links into the CodeCanvas workspace. */
function codecanvas_route_existing_page_to_editor() {
    if ( empty( $_GET['post'] ) || ( isset( $_GET['action'] ) && 'edit' !== sanitize_key( wp_unslash( $_GET['action'] ) ) ) || ! empty( $_GET['codecanvas_native'] ) ) { return; }
    $post_id = absint( $_GET['post'] );
    $post = get_post( $post_id );
    if ( $post && 'page' === $post->post_type && current_user_can( 'edit_post', $post_id ) ) {
        // Keep the Page editing entry point consistent; the workspace will explain
        // if this account is not permitted to save executable custom code.
        wp_safe_redirect( codecanvas_page_editor_url( $post_id ) );
        exit;
    }
}
// Do not redirect normal WordPress Page editing into CodeCanvas automatically.
// add_action( 'load-post.php', 'codecanvas_route_existing_page_to_editor' );

/* The legacy virtual Page Editor is not registered in v1.6. The normal WordPress Pages editor remains unchanged. */

/** Enqueue WordPress CodeMirror and the CodeCanvas workspace assets. */
function codecanvas_page_editor_assets( $hook ) {
    if ( false === strpos( (string) $hook, 'codecanvas-page-editor' ) ) { return; }

    wp_enqueue_style( 'codecanvas-page-editor', CODECANVAS_URI . '/assets/css/page-editor.css', array(), CODECANVAS_VERSION );

    // Do not localize saved global code to users who cannot enter the code editor.
    if ( ! current_user_can( 'edit_pages' ) || ! current_user_can( 'unfiltered_html' ) ) {
        return;
    }

    $settings = array();
    $editor_types = array(
        'html' => 'text/html',
        'css'  => 'text/css',
        'js'   => 'text/javascript',
    );
    foreach ( $editor_types as $key => $mime ) {
        $editor_settings = wp_enqueue_code_editor( array(
            'type' => $mime,
            'codemirror' => array(
                'theme'        => 'codecanvas-dark',
                'lineNumbers'  => true,
                'indentUnit'   => 2,
                'tabSize'      => 2,
                'lineWrapping' => true,
            ),
        ) );
        if ( $editor_settings ) {
            // Set again on the returned settings to make the selected theme explicit.
            if ( ! isset( $editor_settings['codemirror'] ) || ! is_array( $editor_settings['codemirror'] ) ) {
                $editor_settings['codemirror'] = array();
            }
            $editor_settings['codemirror']['theme'] = 'codecanvas-dark';
            $settings[ $key ] = $editor_settings;
        }
    }

    wp_enqueue_script( 'codecanvas-page-editor', CODECANVAS_URI . '/assets/js/page-editor.js', array( 'jquery', 'code-editor' ), CODECANVAS_VERSION, true );

    $site_name = get_bloginfo( 'name' );
    $site_description = get_bloginfo( 'description' );
    $site_url = home_url( '/' );
    $site_parts = wp_parse_url( $site_url );
    $site_origin = '';
    if ( is_array( $site_parts ) && ! empty( $site_parts['scheme'] ) && ! empty( $site_parts['host'] ) ) {
        $site_origin = $site_parts['scheme'] . '://' . $site_parts['host'] . ( isset( $site_parts['port'] ) ? ':' . absint( $site_parts['port'] ) : '' );
    }
    // Capture the fallback callback too; it echoes its output when no menu is assigned.
    ob_start();
    $preview_menu_return = wp_nav_menu( array(
        'theme_location' => 'primary',
        'container'      => false,
        'fallback_cb'    => 'codecanvas_nav_fallback',
        'menu_class'     => 'cc-menu',
        'echo'           => false,
    ) );
    $preview_menu_echo = ob_get_clean();
    $preview_menu = is_string( $preview_menu_return ) && '' !== $preview_menu_return ? $preview_menu_return : (string) $preview_menu_echo;
    $preview_logo = '';
    $custom_logo_id = (int) get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id && function_exists( 'wp_get_attachment_image' ) ) {
        $preview_logo = wp_get_attachment_image( $custom_logo_id, 'full', false, array( 'class' => 'custom-logo', 'alt' => $site_name ) );
    }
    if ( '' === $preview_logo ) {
        $preview_logo = '<a class="cc-brand" href="' . esc_url( $site_url ) . '">' . esc_html( $site_name ) . '</a>';
    } else {
        $preview_logo = '<a class="custom-logo-link" href="' . esc_url( $site_url ) . '" rel="home">' . $preview_logo . '</a>';
    }
    $default_header = '<header class="cc-site-header"><div class="cc-container cc-header-inner"><div class="cc-brand-area">' . $preview_logo . '</div><nav id="cc-mobile-menu" class="cc-primary-nav" aria-label="' . esc_attr__( 'Primary navigation', 'codecanvas' ) . '">' . $preview_menu . '</nav><button class="cc-menu-toggle" type="button" aria-expanded="false" aria-controls="cc-mobile-menu"><span class="screen-reader-text">' . esc_html__( 'Toggle menu', 'codecanvas' ) . '</span><span></span><span></span><span></span></button></div></header>';
    $footer_menu = wp_nav_menu( array(
        'theme_location' => 'footer',
        'container'      => false,
        'fallback_cb'    => '__return_empty_string',
        'menu_class'     => 'cc-menu cc-footer-menu',
        'echo'           => false,
    ) );
    $default_footer = '<footer class="cc-site-footer"><div class="cc-container cc-footer-main"><div class="cc-footer-brand"><a class="cc-brand" href="' . esc_url( $site_url ) . '">' . esc_html( $site_name ) . '</a><p>' . esc_html( $site_description ) . '</p></div><nav aria-label="' . esc_attr__( 'Footer navigation', 'codecanvas' ) . '">' . ( is_string( $footer_menu ) ? $footer_menu : '' ) . '</nav></div><div class="cc-container cc-footer-bottom"><p>&copy; ' . esc_html( gmdate( 'Y' ) ) . ' ' . esc_html( $site_name ) . '.</p><p class="cc-attribution">' . esc_html__( 'Theme by', 'codecanvas' ) . ' <a href="https://surajdev.com/">Suraj Dev</a> <span aria-hidden="true">·</span> ' . esc_html__( 'Credit to', 'codecanvas' ) . ' <a href="https://webot.surajdev.com/">Webot</a></p></div></footer>';
    // Mirror the stylesheet order used on the front end: active theme style.css,
    // then CodeCanvas's responsive base stylesheet. This keeps the preview closer to
    // the rendered site, including simple CSS additions made directly in style.css.
    $base_css = '';
    $active_style_file = get_stylesheet_directory() . '/style.css';
    $frontend_css = CODECANVAS_DIR . '/assets/css/frontend.css';
    if ( is_readable( $active_style_file ) ) { $base_css .= file_get_contents( $active_style_file ) . "\n"; }
    if ( is_readable( $frontend_css ) ) { $base_css .= file_get_contents( $frontend_css ); }

    $editing_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    $preview_page_url = $editing_id ? get_permalink( $editing_id ) : home_url( '/' );

    wp_localize_script( 'codecanvas-page-editor', 'CodeCanvasPageEditor', array(
        'editorSettings' => $settings,
        'preview' => array(
            'siteName' => $site_name,
            'siteDescription' => $site_description,
            'siteUrl' => home_url( '/' ),
            'siteOrigin' => $site_origin,
            'baseCss' => (string) $base_css,
            'pageUrl' => $preview_page_url,
            'headerHtml' => $default_header,
            'footerHtml' => $default_footer,
        ),
        'strings' => array(
            'previewBlocked' => __( 'The preview runs in a sandbox. Network/API requests are disabled for safety.', 'codecanvas' ),
        ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'codecanvas_page_editor_assets' );

/** Load existing content from the current Page or older CodeCanvas view settings. */
function codecanvas_page_editor_get_data( $post_id ) {
    $post = $post_id ? get_post( $post_id ) : null;
    $starter_html = <<<'CCHTML'
<main class="welcome-page">
  <section class="hero">
    <p class="eyebrow">YOUR NEXT IDEA</p>
    <h1>Build something brilliant.</h1>
    <p>Edit this HTML, then adjust the CSS and JavaScript in the panels.</p>
    <a class="button-link" href="#get-started">Get started</a>
  </section>
  <section id="get-started" class="content-section">
    <h2>A clean canvas. Your rules.</h2>
    <p>Replace this starter content with your own design.</p>
  </section>
</main>
CCHTML;
    $starter_css = <<<'CCCSS'
body { margin: 0; font-family: system-ui, sans-serif; color: #19251f; }
.welcome-page { min-height: 100vh; }
.hero { padding: clamp(48px, 9vw, 120px) 24px; text-align: center; background: #f3f6ef; }
.eyebrow { color: #51725e; font-size: 12px; font-weight: 800; letter-spacing: .16em; }
h1 { max-width: 850px; margin: 12px auto; font-size: clamp(36px, 7vw, 78px); letter-spacing: -.06em; line-height: 1.02; }
.hero > p:not(.eyebrow) { max-width: 620px; margin: 20px auto; color: #637067; line-height: 1.8; }
.button-link { display: inline-block; padding: 13px 20px; border-radius: 8px; background: #315c4a; color: #fff; text-decoration: none; }
.content-section { max-width: 900px; margin: auto; padding: 70px 24px; line-height: 1.8; }
CCCSS;
    $starter_js = <<<'CCJS'
// Add browser interactions here. Never put private API keys in front-end JavaScript.
document.querySelectorAll("[data-example]").forEach(function (element) {
  element.addEventListener("click", function () {
    element.classList.toggle("is-active");
  });
});
CCJS;
    $defaults = array(
        'html' => $starter_html,
        'css' => $starter_css,
        'js' => $starter_js,
        'layout' => 'global',
        'status' => 'draft',
        'slug' => '',
        'parent' => 0,
        'excerpt' => '',
    );

    if ( ! $post ) { return $defaults; }
    $views = codecanvas_get_views();
    $legacy = isset( $views[ 'page_' . $post_id ] ) && is_array( $views[ 'page_' . $post_id ] ) ? $views[ 'page_' . $post_id ] : array();
    $enabled = get_post_meta( $post_id, '_codecanvas_page_code_enabled', true );

    if ( $enabled ) {
        $defaults['html'] = (string) $post->post_content;
        $defaults['css'] = (string) get_post_meta( $post_id, '_codecanvas_page_css', true );
        $defaults['js'] = (string) get_post_meta( $post_id, '_codecanvas_page_js', true );
        $defaults['layout'] = 'blank' === get_post_meta( $post_id, '_codecanvas_page_layout', true ) ? 'blank' : 'global';
    } elseif ( $legacy ) {
        $defaults['html'] = isset( $legacy['html'] ) ? (string) $legacy['html'] : (string) $post->post_content;
        $defaults['css'] = isset( $legacy['css'] ) ? (string) $legacy['css'] : '';
        $defaults['js'] = isset( $legacy['js'] ) ? (string) $legacy['js'] : '';
        $defaults['layout'] = isset( $legacy['layout'] ) && 'blank' === $legacy['layout'] ? 'blank' : 'global';
    } else {
        // Existing Gutenberg content is shown as rendered markup so the custom editor
        // does not expose serialized <!-- wp:... --> block delimiters to users.
        $defaults['html'] = function_exists( 'has_blocks' ) && has_blocks( $post->post_content ) ? do_blocks( $post->post_content ) : (string) $post->post_content;
        $defaults['css'] = (string) get_post_meta( $post_id, '_codecanvas_page_css', true );
        $defaults['js'] = (string) get_post_meta( $post_id, '_codecanvas_page_js', true );
        $defaults['layout'] = 'blank' === get_post_meta( $post_id, '_wp_page_template', true ) || 'templates/blank-canvas.php' === get_post_meta( $post_id, '_wp_page_template', true ) ? 'blank' : 'global';
    }

    $defaults['status'] = $post->post_status;
    $defaults['slug'] = $post->post_name;
    $defaults['parent'] = (int) $post->post_parent;
    $defaults['excerpt'] = $post->post_excerpt;
    return $defaults;
}

/** Main custom page editing workspace. */
function codecanvas_render_page_editor() {
    if ( ! current_user_can( 'edit_pages' ) ) { wp_die( esc_html__( 'You do not have permission to edit Pages.', 'codecanvas' ), '', array( 'response' => 403 ) ); }
    if ( ! current_user_can( 'unfiltered_html' ) ) {
        $native_url = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        $native_link = $native_url ? add_query_arg( array( 'post' => $native_url, 'action' => 'edit', 'codecanvas_native' => 1 ), admin_url( 'post.php' ) ) : admin_url( 'post-new.php?post_type=page&codecanvas_native=1' );
        echo '<div class="wrap cc-page-editor-wrap"><div class="cc-page-editor-topbar"><div class="cc-page-editor-brand"><span class="cc-page-editor-mark">CC</span><div><strong>CodeCanvas</strong><small>' . esc_html__( 'Code editor access', 'codecanvas' ) . '</small></div></div></div><section class="cc-editor-locked"><div class="cc-editor-locked-icon"><span class="dashicons dashicons-lock"></span></div><h1>' . esc_html__( 'Your account cannot save executable code', 'codecanvas' ) . '</h1><p>' . esc_html__( 'WordPress limits unfiltered HTML, CSS and JavaScript to trusted users. To protect your website, CodeCanvas only enables its custom code workspace for accounts with the unfiltered_html capability.', 'codecanvas' ) . '</p><p>' . esc_html__( 'You can use the standard WordPress editor below, or ask your site administrator to review your role and the site security policy. On WordPress Multisite, unfiltered HTML is normally restricted to Super Admins.', 'codecanvas' ) . '</p><p><a class="button button-primary" href="' . esc_url( $native_link ) . '">' . esc_html__( 'Open standard WordPress editor', 'codecanvas' ) . '</a></p><p class="description">' . esc_html__( 'No page content has been changed.', 'codecanvas' ) . '</p></section></div>';
        return;
    }

    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    $post = $post_id ? get_post( $post_id ) : null;
    if ( $post_id && ( ! $post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) ) {
        wp_die( esc_html__( 'This Page could not be found or you cannot edit it.', 'codecanvas' ), '', array( 'response' => 404 ) );
    }

    $data = codecanvas_page_editor_get_data( $post_id );
    $is_legacy_block_page = $post && empty( get_post_meta( $post_id, '_codecanvas_page_code_enabled', true ) ) && empty( codecanvas_get_views()[ 'page_' . $post_id ] ) && function_exists( 'has_blocks' ) && has_blocks( $post->post_content );
    $pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'exclude' => $post_id, 'number' => 500 ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Excludes only the page being edited from a capped admin list.
    $view_url = $post_id ? ( 'publish' === get_post_status( $post_id ) ? get_permalink( $post_id ) : get_preview_post_link( $post_id ) ) : '';
    $save_message = isset( $_GET['cc_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cc_saved'] ) );
    $error = isset( $_GET['cc_error'] ) ? sanitize_key( wp_unslash( $_GET['cc_error'] ) ) : '';
    $error_messages = array(
        'title' => __( 'Please enter a page title before saving.', 'codecanvas' ),
        'save' => __( 'The page could not be saved. Please try again. Your existing content has not been intentionally cleared.', 'codecanvas' ),
    );
    $saved_status_message = '';
    if ( $save_message && $post ) {
        $status_messages = array(
            'publish' => __( 'Page published successfully.', 'codecanvas' ),
            'draft' => __( 'Draft saved. It is not publicly visible yet.', 'codecanvas' ),
            'pending' => __( 'Page saved and submitted for review.', 'codecanvas' ),
            'private' => __( 'Private page saved. Only users with appropriate access can view it.', 'codecanvas' ),
            'future' => __( 'Scheduled page saved. WordPress will publish it at its existing scheduled time.', 'codecanvas' ),
        );
        $saved_status_message = isset( $status_messages[ $post->post_status ] ) ? $status_messages[ $post->post_status ] : __( 'Page saved.', 'codecanvas' );
    }
    ?>
    <div class="wrap cc-page-editor-wrap cc-ide-shell">
        <header class="cc-ide-titlebar">
            <div class="cc-ide-brand">
                <span class="cc-page-editor-mark" aria-hidden="true">CC</span>
                <div class="cc-ide-brand-text"><strong>CodeCanvas</strong><span>WordPress Development Workspace</span></div>
            </div>
            <div class="cc-ide-title-area">
                <label class="screen-reader-text" for="cc-page-title"><?php esc_html_e( 'Page title', 'codecanvas' ); ?></label>
                <input id="cc-page-title" name="post_title" form="cc-page-editor-form" type="text" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" placeholder="Untitled page" required autocomplete="off">
                <span class="cc-ide-title-suffix">— CodeCanvas</span>
            </div>
            <div class="cc-ide-title-actions">
                <button type="button" class="button cc-ide-action" id="cc-toggle-preview" aria-pressed="false"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span><?php esc_html_e( 'Preview', 'codecanvas' ); ?></span></button>
                <button type="button" class="button cc-ide-action" id="cc-toggle-settings" aria-pressed="false"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span><?php esc_html_e( 'Page settings', 'codecanvas' ); ?></span></button>
                <button type="button" class="button cc-focus-toggle" id="cc-focus-toggle" aria-pressed="true"><span class="dashicons dashicons-editor-contract" aria-hidden="true"></span><span class="cc-focus-label"><?php esc_html_e( 'Exit IDE', 'codecanvas' ); ?></span></button>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Pages', 'codecanvas' ); ?></a>
                <?php if ( $post_id && $view_url ) : ?><a class="button" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $post && 'publish' === $post->post_status ? esc_html__( 'View', 'codecanvas' ) : esc_html__( 'Preview link', 'codecanvas' ); ?> ↗</a><?php endif; ?>
                <button class="button button-primary cc-save-top" type="submit" form="cc-page-editor-form"><?php echo $post && 'publish' === $post->post_status ? esc_html__( 'Save', 'codecanvas' ) : esc_html__( 'Save page', 'codecanvas' ); ?></button>
            </div>
        </header>

        <?php if ( $save_message && 'permission' !== $error && '' !== $saved_status_message ) : ?><div class="notice notice-success is-dismissible cc-ide-notice"><p><?php echo esc_html( $saved_status_message ); ?></p></div><?php endif; ?>
        <?php if ( 'permission' === $error ) : ?><div class="notice notice-warning cc-ide-notice"><p><?php esc_html_e( 'Your account cannot publish Pages. The page was saved as a draft; it is not live yet.', 'codecanvas' ); ?></p></div><?php endif; ?>
        <?php if ( $is_legacy_block_page ) : ?><div class="notice notice-warning cc-ide-notice"><p><?php esc_html_e( 'This page contains WordPress block content. Saving here converts the rendered markup to CodeCanvas code. Back up the page first if you need to preserve its editable block structure.', 'codecanvas' ); ?></p></div><?php endif; ?>
        <?php if ( isset( $error_messages[ $error ] ) ) : ?><div class="notice notice-error cc-ide-notice" role="alert"><p><?php echo esc_html( $error_messages[ $error ] ); ?></p></div><?php endif; ?>

        <form id="cc-page-editor-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="codecanvas_save_page">
            <input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>">
            <?php wp_nonce_field( 'codecanvas_save_page_' . $post_id ); ?>

            <div class="cc-ide-workbench">
                <nav class="cc-ide-activitybar" aria-label="<?php esc_attr_e( 'Workspace panels', 'codecanvas' ); ?>">
                    <button type="button" class="is-active" data-ide-activity="explorer" aria-label="<?php esc_attr_e( 'Explorer', 'codecanvas' ); ?>" aria-pressed="true" title="Explorer"><span class="dashicons dashicons-media-document" aria-hidden="true"></span></button>
                    <button type="button" data-ide-activity="search" aria-label="<?php esc_attr_e( 'Search code', 'codecanvas' ); ?>" aria-pressed="false" title="Search"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
                    <button type="button" data-ide-activity="settings" aria-label="<?php esc_attr_e( 'Page settings', 'codecanvas' ); ?>" aria-pressed="false" title="Page settings"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span></button>
                </nav>

                <aside class="cc-ide-sidebar" id="cc-ide-sidebar" aria-label="<?php esc_attr_e( 'Explorer and settings', 'codecanvas' ); ?>">
                    <section class="cc-ide-sidebar-view is-active" data-ide-sidebar-view="explorer">
                        <div class="cc-ide-sidebar-heading"><span><?php esc_html_e( 'EXPLORER', 'codecanvas' ); ?></span><button type="button" data-ide-collapse-sidebar aria-label="<?php esc_attr_e( 'Collapse sidebar', 'codecanvas' ); ?>" title="Collapse sidebar"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button></div>
                        <div class="cc-ide-project-root"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><strong><?php echo esc_html( $post && $post->post_name ? strtoupper( $post->post_name ) : 'NEW PAGE' ); ?></strong></div>
                        <div class="cc-ide-file-tree" role="list" aria-label="<?php esc_attr_e( 'Page code files', 'codecanvas' ); ?>">
                            <button type="button" class="cc-ide-file is-active" data-code-tab="html" role="listitem"><span class="cc-file-icon cc-file-html">&lt;/&gt;</span><span>index.html</span><span class="cc-file-state" data-file-state="html"></span></button>
                            <button type="button" class="cc-ide-file" data-code-tab="css" role="listitem"><span class="cc-file-icon cc-file-css">#</span><span>style.css</span><span class="cc-file-state" data-file-state="css"></span></button>
                            <button type="button" class="cc-ide-file" data-code-tab="js" role="listitem"><span class="cc-file-icon cc-file-js">JS</span><span>script.js</span><span class="cc-file-state" data-file-state="js"></span></button>
                        </div>
                        <div class="cc-ide-sidebar-section"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><?php esc_html_e( 'WORDPRESS', 'codecanvas' ); ?></div>
                        <a class="cc-ide-side-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'All pages', 'codecanvas' ); ?></a>
                        <div class="cc-ide-sidebar-spacer"></div>
                        <div class="cc-ide-sidebar-about"><strong>CodeCanvas</strong><span>Developer: <a href="https://surajdev.com/" target="_blank" rel="noopener noreferrer">Suraj Dev</a></span><span>Credit: <a href="https://webot.surajdev.com/" target="_blank" rel="noopener noreferrer">Webot</a></span></div>
                    </section>

                    <section class="cc-ide-sidebar-view" data-ide-sidebar-view="search" hidden>
                        <div class="cc-ide-sidebar-heading"><span><?php esc_html_e( 'SEARCH', 'codecanvas' ); ?></span></div>
                        <label class="screen-reader-text" for="cc-ide-search-input"><?php esc_html_e( 'Search in page code', 'codecanvas' ); ?></label>
                        <input id="cc-ide-search-input" type="search" placeholder="<?php esc_attr_e( 'Search in files', 'codecanvas' ); ?>">
                        <div id="cc-ide-search-results" class="cc-ide-search-results"><p><?php esc_html_e( 'Search HTML, CSS and JavaScript in this page.', 'codecanvas' ); ?></p></div>
                    </section>

                    <section class="cc-ide-sidebar-view cc-ide-settings-view" data-ide-sidebar-view="settings" hidden>
                        <div class="cc-ide-sidebar-heading"><span><?php esc_html_e( 'PAGE SETTINGS', 'codecanvas' ); ?></span></div>
                        <div class="cc-setting-field"><label for="cc-page-slug"><?php esc_html_e( 'URL slug', 'codecanvas' ); ?></label><input id="cc-page-slug" name="post_name" type="text" value="<?php echo esc_attr( $data['slug'] ); ?>" placeholder="auto-from-title"><small><?php esc_html_e( 'Leave blank to generate from the title.', 'codecanvas' ); ?></small></div>
                        <div class="cc-setting-field"><label for="cc-page-status"><?php esc_html_e( 'Status', 'codecanvas' ); ?></label><select id="cc-page-status" name="post_status"><?php $statuses = array( 'draft' => __( 'Draft', 'codecanvas' ), 'pending' => __( 'Pending review', 'codecanvas' ), 'publish' => __( 'Published', 'codecanvas' ), 'private' => __( 'Private', 'codecanvas' ) ); if ( 'future' === $data['status'] ) { $statuses['future'] = __( 'Scheduled', 'codecanvas' ); } foreach ( $statuses as $status_key => $status_label ) : ?><option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $data['status'], $status_key ); ?>><?php echo esc_html( $status_label ); ?></option><?php endforeach; ?></select></div>
                        <div class="cc-setting-field"><label for="cc-page-layout"><?php esc_html_e( 'Template layout', 'codecanvas' ); ?></label><select id="cc-page-layout" name="layout"><option value="global" <?php selected( $data['layout'], 'global' ); ?>><?php esc_html_e( 'Shared header & footer', 'codecanvas' ); ?></option><option value="blank" <?php selected( $data['layout'], 'blank' ); ?>><?php esc_html_e( 'Blank canvas', 'codecanvas' ); ?></option></select></div>
                        <div class="cc-setting-field"><label for="cc-page-parent"><?php esc_html_e( 'Parent page', 'codecanvas' ); ?></label><select id="cc-page-parent" name="post_parent"><option value="0"><?php esc_html_e( 'No parent', 'codecanvas' ); ?></option><?php foreach ( $pages as $parent_page ) : ?><option value="<?php echo esc_attr( (string) $parent_page->ID ); ?>" <?php selected( (int) $data['parent'], (int) $parent_page->ID ); ?>><?php echo esc_html( $parent_page->post_title ); ?></option><?php endforeach; ?></select></div>
                        <div class="cc-setting-field"><label for="cc-page-excerpt"><?php esc_html_e( 'Excerpt / description', 'codecanvas' ); ?></label><textarea id="cc-page-excerpt" name="post_excerpt" rows="3" placeholder="<?php esc_attr_e( 'Optional summary for listings and SEO tools', 'codecanvas' ); ?>"><?php echo esc_textarea( $data['excerpt'] ); ?></textarea></div>
                        <div class="cc-ide-settings-help"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'The page title is editable in the top bar. Settings take effect when you save.', 'codecanvas' ); ?></div>
                    </section>
                </aside>

                <main class="cc-ide-main" id="cc-ide-main">
                    <div class="cc-ide-editor-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Open files', 'codecanvas' ); ?>">
                        <button type="button" id="cc-tab-html" class="cc-ide-editor-tab is-active" data-code-tab="html" role="tab" aria-selected="true" aria-controls="cc-pane-html"><span class="cc-file-icon cc-file-html">&lt;/&gt;</span>index.html<span class="cc-tab-dirty" data-file-state="html"></span></button>
                        <button type="button" id="cc-tab-css" class="cc-ide-editor-tab" data-code-tab="css" role="tab" aria-selected="false" aria-controls="cc-pane-css"><span class="cc-file-icon cc-file-css">#</span>style.css<span class="cc-tab-dirty" data-file-state="css"></span></button>
                        <button type="button" id="cc-tab-js" class="cc-ide-editor-tab" data-code-tab="js" role="tab" aria-selected="false" aria-controls="cc-pane-js"><span class="cc-file-icon cc-file-js">JS</span>script.js<span class="cc-tab-dirty" data-file-state="js"></span></button>
                        <div class="cc-ide-tab-spacer"></div><button type="button" class="cc-ide-icon-button" id="cc-font-minus" data-editor-font="-1" aria-label="<?php esc_attr_e( 'Decrease editor font size', 'codecanvas' ); ?>" title="Decrease font size">A−</button><button type="button" class="cc-ide-icon-button" id="cc-font-plus" data-editor-font="1" aria-label="<?php esc_attr_e( 'Increase editor font size', 'codecanvas' ); ?>" title="Increase font size">A+</button>
                    </div>
                    <div class="cc-ide-breadcrumb"><span><?php esc_html_e( 'Pages', 'codecanvas' ); ?></span><span>/</span><span><?php echo esc_html( $post && $post->post_name ? $post->post_name : 'new-page' ); ?></span><span>/</span><strong id="cc-ide-current-file">index.html</strong></div>
                    <section class="cc-code-workspace" aria-label="<?php esc_attr_e( 'Source editor', 'codecanvas' ); ?>">
                        <div class="cc-code-pane is-active" data-code-pane="html" id="cc-pane-html" role="tabpanel" aria-labelledby="cc-tab-html"><div class="cc-code-pane-label"><span>index.html</span><span>HTML</span></div><textarea id="cc-page-html" name="html" spellcheck="false" aria-label="HTML code"><?php echo esc_textarea( $data['html'] ); ?></textarea></div>
                        <div class="cc-code-pane" data-code-pane="css" id="cc-pane-css" role="tabpanel" aria-labelledby="cc-tab-css" hidden><div class="cc-code-pane-label"><span>style.css</span><span>CSS</span></div><textarea id="cc-page-css" name="css" spellcheck="false" aria-label="CSS code"><?php echo esc_textarea( $data['css'] ); ?></textarea></div>
                        <div class="cc-code-pane" data-code-pane="js" id="cc-pane-js" role="tabpanel" aria-labelledby="cc-tab-js" hidden><div class="cc-code-pane-label"><span>script.js</span><span>JavaScript</span></div><textarea id="cc-page-js" name="js" spellcheck="false" aria-label="JavaScript code"><?php echo esc_textarea( $data['js'] ); ?></textarea></div>
                    </section>
                </main>

                <aside class="cc-preview-workspace" id="cc-preview-panel" aria-label="<?php esc_attr_e( 'Live preview', 'codecanvas' ); ?>" hidden>
                    <div class="cc-preview-header"><div><strong><?php esc_html_e( 'Live preview', 'codecanvas' ); ?></strong><small id="cc-preview-status" role="status" aria-live="polite"><?php esc_html_e( 'Ready', 'codecanvas' ); ?></small></div><div class="cc-device-switcher" role="group" aria-label="<?php esc_attr_e( 'Preview viewport', 'codecanvas' ); ?>"><button type="button" class="is-active" data-preview-width="desktop" aria-label="Desktop preview" aria-pressed="true" title="Desktop"><span class="dashicons dashicons-desktop" aria-hidden="true"></span></button><button type="button" data-preview-width="tablet" aria-label="Tablet preview" aria-pressed="false" title="Tablet"><span class="dashicons dashicons-tablet" aria-hidden="true"></span></button><button type="button" data-preview-width="mobile" aria-label="Mobile preview" aria-pressed="false" title="Mobile"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span></button><button type="button" id="cc-refresh-preview" aria-label="Refresh preview" title="Refresh"><span class="dashicons dashicons-update" aria-hidden="true"></span></button><button type="button" id="cc-close-preview" aria-label="Close preview" title="Close preview"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div></div>
                    <div class="cc-preview-stage"><div class="cc-preview-frame-wrap" data-device="desktop"><iframe id="cc-live-preview" title="CodeCanvas live preview" sandbox="allow-scripts" loading="eager"></iframe></div></div>
                    <div class="cc-preview-footer"><span><?php esc_html_e( 'Sandboxed preview', 'codecanvas' ); ?></span><span><?php esc_html_e( 'Network requests are blocked', 'codecanvas' ); ?></span></div>
                </aside>
            </div>

            <div id="cc-secret-warning" class="cc-secret-warning" role="status" aria-live="polite" hidden><span class="dashicons dashicons-lock" aria-hidden="true"></span><div><strong><?php esc_html_e( 'Possible secret in page code', 'codecanvas' ); ?></strong><p><?php esc_html_e( 'Anything saved in page HTML or JavaScript can be exposed to visitors. Keep private credentials on the server and call APIs through a protected server-side endpoint.', 'codecanvas' ); ?></p></div></div>
            <footer class="cc-ide-statusbar"><div class="cc-ide-status-left"><span class="cc-ide-status-brand"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span>CodeCanvas</span><span id="cc-ide-save-status"><span class="cc-state-dot"></span><span id="cc-editor-state" role="status" aria-live="polite"><?php echo $post_id ? esc_html__( 'All changes saved', 'codecanvas' ) : esc_html__( 'New page — not saved yet', 'codecanvas' ); ?></span></span><span id="cc-ide-status-message">HTML</span></div><div class="cc-ide-status-right"><span id="cc-ide-cursor">Ln 1, Col 1</span><span>Spaces: 2</span><span>UTF-8</span><span>WordPress Page</span></div></footer>
        </form>
    </div>
    <?php
}

/** Validate and save page content from the custom editor. */
function codecanvas_save_page_handler() {
    if ( ! current_user_can( 'edit_pages' ) || ! current_user_can( 'unfiltered_html' ) ) {
        wp_die( esc_html__( 'You are not allowed to save executable HTML/CSS/JavaScript.', 'codecanvas' ), '', array( 'response' => 403 ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    check_admin_referer( 'codecanvas_save_page_' . $post_id );

    if ( $post_id ) {
        $existing = get_post( $post_id );
        if ( ! $existing || 'page' !== $existing->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You cannot edit this Page.', 'codecanvas' ), '', array( 'response' => 403 ) );
        }
    }

    $title = isset( $_POST['post_title'] ) && is_string( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
    if ( '' === trim( $title ) ) {
        wp_safe_redirect( add_query_arg( 'cc_error', 'title', codecanvas_page_editor_url( $post_id ) ) ); exit;
    }

    // Custom HTML/CSS/JS must be stored verbatim; it is checked for embedded secrets and only saved for users who can edit code.
    $html = isset( $_POST['html'] ) && is_string( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $css  = isset( $_POST['css'] ) && is_string( $_POST['css'] ) ? wp_unslash( $_POST['css'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $js   = isset( $_POST['js'] ) && is_string( $_POST['js'] ) ? wp_unslash( $_POST['js'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( strlen( $html ) > 500000 || strlen( $css ) > 250000 || strlen( $js ) > 250000 ) {
        wp_die( esc_html__( 'The page code is too large. Limits are 500 KB for HTML and 250 KB each for CSS and JavaScript.', 'codecanvas' ), '', array( 'response' => 413 ) );
    }
    codecanvas_block_private_secrets( $html . "\n" . $css . "\n" . $js );

    $requested_status = isset( $_POST['post_status'] ) && is_string( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : 'draft';
    $allowed_statuses = array( 'draft', 'pending', 'publish', 'private', 'future' );
    if ( ! in_array( $requested_status, $allowed_statuses, true ) ) { $requested_status = 'draft'; }
    $status_permission_downgraded = false;
    if ( in_array( $requested_status, array( 'publish', 'private', 'future' ), true ) && ! current_user_can( 'publish_pages' ) ) { $requested_status = 'draft'; $status_permission_downgraded = true; }
    if ( ! $post_id && 'future' === $requested_status ) { $requested_status = 'draft'; }

    $slug_input = isset( $_POST['post_name'] ) && is_string( $_POST['post_name'] ) ? sanitize_title( wp_unslash( $_POST['post_name'] ) ) : '';
    $parent_id = isset( $_POST['post_parent'] ) ? absint( $_POST['post_parent'] ) : 0;
    if ( ! isset( $_POST['post_parent'] ) ) { $parent_id = 0; }
    // Only allow a real parent Page; never allow self-parenting.
    if ( $parent_id && ( 'page' !== get_post_type( $parent_id ) || $parent_id === $post_id ) ) { $parent_id = 0; }
    if ( $parent_id && $post_id && in_array( $post_id, array_map( 'absint', get_post_ancestors( $parent_id ) ), true ) ) { $parent_id = 0; }
    $layout = isset( $_POST['layout'] ) && is_string( $_POST['layout'] ) && 'blank' === sanitize_key( wp_unslash( $_POST['layout'] ) ) ? 'blank' : 'global';
    $excerpt = isset( $_POST['post_excerpt'] ) && is_string( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';

    $post_data = array(
        'post_type' => 'page',
        'post_title' => $title,
        'post_name' => $slug_input,
        'post_status' => $requested_status,
        'post_parent' => $parent_id,
        'post_content' => $html,
        'post_excerpt' => $excerpt,
    );
    if ( $post_id ) {
        $post_data['ID'] = $post_id;
    } else {
        $post_data['post_author'] = get_current_user_id();
    }

    // WordPress requires an explicit date for a 'future' status. The editor does not
    // create new schedules, so preserve an existing scheduled date or safely fall back
    // to Draft instead of accidentally publishing at an unintended time.
    if ( 'future' === $requested_status ) {
        $existing_scheduled_page = $post_id ? get_post( $post_id ) : null;
        if ( $existing_scheduled_page && 'future' === $existing_scheduled_page->post_status && ! empty( $existing_scheduled_page->post_date ) ) {
            $post_data['post_date'] = $existing_scheduled_page->post_date;
            $post_data['post_date_gmt'] = $existing_scheduled_page->post_date_gmt;
        } else {
            $post_data['post_status'] = 'draft';
            $requested_status = 'draft';
        }
    }

    // wp_insert_post() expects slashed fields. The request was already unslashed
    // above, so slash the payload once to preserve literal backslashes in code.
    $saved_id = wp_insert_post( wp_slash( $post_data ), true );
    if ( is_wp_error( $saved_id ) || ! $saved_id ) {
        wp_safe_redirect( add_query_arg( array( 'cc_error' => 'save', 'post_id' => $post_id ), codecanvas_page_editor_url() ) ); exit;
    }

    update_post_meta( $saved_id, '_codecanvas_page_code_enabled', 1 );
    // Metadata APIs unslash values internally. Re-slash the user input first so
    // CSS/JS escapes and regular expressions survive round-trips unchanged.
    update_post_meta( $saved_id, '_codecanvas_page_css', wp_slash( $css ) );
    update_post_meta( $saved_id, '_codecanvas_page_js', wp_slash( $js ) );
    update_post_meta( $saved_id, '_codecanvas_page_layout', wp_slash( $layout ) );
    update_post_meta( $saved_id, '_wp_page_template', 'blank' === $layout ? 'templates/blank-canvas.php' : 'default' );

    // Remove any older option-stored per-page override; the new post-backed page content is authoritative.
    $views = codecanvas_get_views();
    unset( $views[ 'page_' . $saved_id ] );
    update_option( 'codecanvas_views', $views, false );

    $redirect_args = array( 'post_id' => $saved_id, 'cc_saved' => 1 );
    if ( $status_permission_downgraded ) { $redirect_args['cc_error'] = 'permission'; }
    wp_safe_redirect( add_query_arg( $redirect_args, codecanvas_page_editor_url( $saved_id ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_save_page', 'codecanvas_save_page_handler' );
