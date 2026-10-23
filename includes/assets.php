<?php
/** Front-end assets. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue the front-end stylesheet and the code of the current coded page.
 *
 * The plugin owns the builder's responsive base styles and prints the CSS and
 * JavaScript stored with a coded page. Everything else belongs to the active
 * website theme, which loads its own stylesheet afterwards.
 */
function codecanvas_enqueue_assets() {
    wp_enqueue_style( 'codecanvas-frontend', CODECANVAS_URI . 'assets/css/frontend.css', array(), CODECANVAS_VERSION );

    $view = codecanvas_get_active_view();
    $css  = isset( $view['css'] ) ? (string) $view['css'] : '';
    $js   = isset( $view['js'] ) ? (string) $view['js'] : '';

    // The shared header (and its menu toggle script) is omitted on blank canvases.
    // Keep the handle when page-authored inline JavaScript needs a target.
    if ( 'blank' !== codecanvas_current_layout() || '' !== trim( $js ) ) {
        wp_enqueue_script( 'codecanvas-frontend', CODECANVAS_URI . 'assets/js/frontend.js', array(), CODECANVAS_VERSION, true );
    }

    if ( '' !== trim( $css ) ) {
        // Prevent page code from accidentally terminating the HTML style element.
        wp_add_inline_style( 'codecanvas-frontend', "\n" . str_ireplace( '</style', '<\\/style', $css ) );
    }

    // Page JavaScript is deliberately front-end visible. Never put private API keys or secrets here.
    if ( '' !== trim( $js ) ) {
        // Keep a literal closing tag inside a JS string/comment from terminating the inline script element.
        wp_add_inline_script( 'codecanvas-frontend', "\n" . str_ireplace( '</script', '<\\/script', $js ), 'after' );
    }
}
add_action( 'wp_enqueue_scripts', 'codecanvas_enqueue_assets' );
