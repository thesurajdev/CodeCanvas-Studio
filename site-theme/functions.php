<?php
/**
 * CodeCanvas Site theme bootstrap.
 *
 * This theme is generated for the website by CodeCanvas Studio. CodeCanvas itself
 * lives in a plugin, so site-specific templates, styles and hooks belong here.
 *
 * @package CodeCanvasSite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme setup.
 */
function codecanvas_site_setup() {
    load_theme_textdomain( 'codecanvas', get_stylesheet_directory() . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'custom-logo', array( 'height' => 100, 'width' => 360, 'flex-height' => true, 'flex-width' => true ) );
    add_editor_style( 'assets/css/editor.css' );
    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'codecanvas' ),
        'footer'  => __( 'Footer Menu', 'codecanvas' ),
    ) );
}
add_action( 'after_setup_theme', 'codecanvas_site_setup' );

/**
 * Front-end assets.
 */
function codecanvas_site_enqueue_assets() {
    $version = (string) wp_get_theme()->get( 'Version' );
    $css     = get_stylesheet_directory() . '/assets/css/site.css';
    $js      = get_stylesheet_directory() . '/assets/js/site.js';

    wp_enqueue_style( 'codecanvas-site-style', get_stylesheet_uri(), array(), $version );
    wp_enqueue_style(
        'codecanvas-site-custom',
        get_stylesheet_directory_uri() . '/assets/css/site.css',
        array( 'codecanvas-site-style' ),
        is_readable( $css ) ? (string) filemtime( $css ) : $version
    );
    if ( is_readable( $js ) ) {
        wp_enqueue_script(
            'codecanvas-site-custom',
            get_stylesheet_directory_uri() . '/assets/js/site.js',
            array(),
            (string) filemtime( $js ),
            true
        );
    }
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'codecanvas_site_enqueue_assets', 20 );

/**
 * Content width used by embeds and wide blocks.
 */
function codecanvas_site_content_width() {
    $GLOBALS['content_width'] = 1160;
}
add_action( 'after_setup_theme', 'codecanvas_site_content_width', 0 );

/*
 * The CodeCanvas plugin provides the view renderer through these prefixed
 * wrappers. These fallbacks keep the website readable if the plugin is
 * deactivated, rather than causing a fatal error from a missing function.
 */
if ( ! function_exists( 'codecanvas_site_render_view' ) ) {
    /** Render a saved CodeCanvas view (plugin inactive fallback). */
    function codecanvas_site_render_view() {
        return false;
    }

    /** Whether a saved view supplies the body markup. */
    function codecanvas_site_has_custom_view_output() {
        return false;
    }

    /** Render a template token string. */
    function codecanvas_site_render_markup( $html ) {
        return (string) $html;
    }

    /** Current layout. */
    function codecanvas_site_current_layout() {
        return 'global';
    }

    /** Default post card. */
    function codecanvas_site_render_default_card( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return;
        }
        echo '<article class="cc-card"><div class="cc-card-body"><h2><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h2></div></article>';
    }

    /** Site brand link. */
    function codecanvas_site_default_brand() {
        if ( has_custom_logo() ) {
            the_custom_logo();
        } else {
            echo '<a class="cc-brand" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
        }
    }

    /** Primary menu fallback. */
    function codecanvas_site_nav_fallback() {
        echo '<ul class="cc-menu">';
        echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'codecanvas' ) . '</a></li>';
        foreach ( get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 5, 'parent' => 0 ) ) as $page ) {
            echo '<li><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a></li>';
        }
        echo '</ul>';
    }
}
