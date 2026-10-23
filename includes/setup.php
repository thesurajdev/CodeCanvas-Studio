<?php
/** Theme setup and common helpers. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Query arguments only select or display admin screen state; every state change is a nonce-protected POST request.

/** Remove an empty directory through the WordPress filesystem API. */
function codecanvas_rmdir( $dir ) {
    if ( ! is_dir( $dir ) ) { return false; }
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }
    if ( $wp_filesystem && $wp_filesystem->rmdir( $dir, false ) ) { return true; }
    return ! is_dir( $dir );
}

/** Move or rename a file through the WordPress filesystem API. */
function codecanvas_move_file( $from, $to ) {
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }
    if ( $wp_filesystem && $wp_filesystem->move( $from, $to, false ) ) { return true; }
    return ! file_exists( $from ) && file_exists( $to );
}

function codecanvas_maybe_redirect_to_welcome() {
    if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) || ! get_transient( 'codecanvas_welcome_redirect' ) ) {
        return;
    }
    if ( isset( $_GET['page'] ) && 'codecanvas' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
        delete_transient( 'codecanvas_welcome_redirect' );
        return;
    }
    delete_transient( 'codecanvas_welcome_redirect' );
    wp_safe_redirect( admin_url( 'admin.php?page=codecanvas' ) );
    exit;
}
add_action( 'admin_init', 'codecanvas_maybe_redirect_to_welcome', 20 );

function codecanvas_body_classes( $classes ) {
    $classes[] = 'codecanvas-theme';
    if ( is_page_template( 'templates/blank-canvas.php' ) ) {
        $classes[] = 'codecanvas-blank-layout';
        return $classes;
    }
    $view = function_exists( 'codecanvas_get_active_view' ) ? codecanvas_get_active_view() : array();
    if ( ! empty( $view['layout'] ) && 'blank' === $view['layout'] ) {
        $classes[] = 'codecanvas-blank-layout';
    }
    return $classes;
}
add_filter( 'body_class', 'codecanvas_body_classes' );

function codecanvas_current_layout() {
    if ( is_page_template( 'templates/blank-canvas.php' ) ) {
        return 'blank';
    }
    $view = function_exists( 'codecanvas_get_active_view' ) ? codecanvas_get_active_view() : array();
    return ( isset( $view['layout'] ) && 'blank' === $view['layout'] ) ? 'blank' : 'global';
}

function codecanvas_default_brand() {
    if ( has_custom_logo() ) {
        the_custom_logo();
    } else {
        echo '<a class="cc-brand" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
    }
}

function codecanvas_excerpt( $post = null, $length = 28 ) {
    $post = get_post( $post );
    if ( ! $post ) { return ''; }
    $text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
    $text = trim( preg_replace( '/\s+/', ' ', $text ) );
    return wp_trim_words( $text, absint( $length ), '…' );
}

function codecanvas_post_thumbnail_markup( $post_id = 0, $size = 'large', $class = 'cc-card-image' ) {
    $post_id = $post_id ? absint( $post_id ) : get_the_ID();
    if ( has_post_thumbnail( $post_id ) ) {
        return get_the_post_thumbnail( $post_id, $size, array( 'class' => $class, 'loading' => 'lazy' ) );
    }
    return '<span class="cc-image-placeholder" aria-hidden="true"><span>CC</span></span>';
}

function codecanvas_nav_fallback() {
    echo '<ul class="cc-menu">';
    echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'codecanvas' ) . '</a></li>';
    $pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 5, 'parent' => 0 ) );
    foreach ( $pages as $page ) {
        echo '<li><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a></li>';
    }
    echo '</ul>';
}

