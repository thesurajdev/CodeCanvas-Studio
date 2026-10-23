<?php
/**
 * Security helpers for CodeCanvas editors.
 *
 * These checks are defense-in-depth only. Pattern matching cannot identify every
 * credential format, so developers must still keep private secrets server-side.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Detect several well-known private token/key formats before persisting code.
 *
 * Generic keys (for example, browser-restricted public API keys) are intentionally
 * not blocked by this function because the difference cannot be inferred safely.
 *
 * @param string $source Custom code to inspect.
 * @return bool
 */
function codecanvas_contains_private_secret_pattern( $source ) {
    if ( ! is_string( $source ) || '' === $source ) {
        return false;
    }

    $patterns = array(
        '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/',
        '/\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}\b/',
        '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{20,}\b/',
        '/\bgh[pousr]_[A-Za-z0-9]{20,}\b/',
        '/\bgithub_pat_[A-Za-z0-9_]{22,}\b/',
        '/\bglpat-[A-Za-z0-9_-]{20,}\b/',
        '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
    );

    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $source ) ) {
            return true;
        }
    }

    return false;
}

/** Stop saving well-known private credentials into browser-delivered code. */
function codecanvas_block_private_secrets( $source ) {
    if ( codecanvas_contains_private_secret_pattern( $source ) ) {
        wp_die(
            esc_html__( 'CodeCanvas did not save your code because it detected a pattern that resembles a private API token or private key. Remove the credential from HTML, CSS, JavaScript or the view template. Keep it server-side in wp-config.php or an environment-based secret store, then call the provider through a properly protected server-side integration. Use your browser Back button to return to the editor; your unsaved form values may be restored by the browser. No new code was saved.', 'codecanvas' ),
            esc_html__( 'Potential private credential detected', 'codecanvas' ),
            array( 'response' => 400, 'back_link' => true )
        );
    }
}
