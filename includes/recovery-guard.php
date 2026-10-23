<?php
/**
 * Plugin Name: CodeCanvas Recovery Guard
 * Description: Restores the most recent CodeCanvas snapshot when a PHP fatal error comes from a theme or plugin file changed in Code Studio or over MCP.
 * Version: 1.0.0
 * Author: Suraj Dev
 * Author URI: https://surajdev.com/
 * License: GPL-2.0-or-later
 *
 * This file is installed as a must-use plugin by CodeCanvas so it loads before
 * the theme and plugins. If a fatal error (parse error, undefined function,
 * uncaught exception) comes from a file that has a CodeCanvas snapshot, the
 * previous contents are written back and the failure is logged. The current
 * request still shows an error; the next one loads the restored file.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Conditional declarations so a direct include cannot redeclare the mu-plugin.
if ( ! function_exists( 'codecanvas_recovery_restore_file' ) ) :

/**
 * Map an absolute file path to a CodeCanvas snapshot root and relative path.
 *
 * @param string $file Absolute file path from the fatal error.
 * @return array|null
 */
function codecanvas_recovery_relative_file( $file ) {
    $file = str_replace( '\\', '/', (string) $file );
    if ( '' === $file ) { return null; }
    $base = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' );
    if ( 0 === strpos( $file, $base . '/themes/' ) ) {
        return array( 'root' => 'themes', 'relative' => substr( $file, strlen( $base . '/themes/' ) ) );
    }
    if ( 0 === strpos( $file, $base . '/plugins/' ) ) {
        return array( 'root' => 'plugins', 'relative' => substr( $file, strlen( $base . '/plugins/' ) ) );
    }
    return null;
}

/**
 * Restore the newest CodeCanvas snapshot for a file.
 *
 * @param string $file    Absolute file path.
 * @param string $message Fatal error message, stored in the log.
 * @return bool True when the file was restored.
 */
function codecanvas_recovery_restore_file( $file, $message = '' ) {
    $map = codecanvas_recovery_relative_file( $file );
    if ( ! $map || '' === $map['relative'] || false !== strpos( $map['relative'], '..' ) ) { return false; }
    if ( ! is_file( $file ) || ! wp_is_writable( $file ) ) { return false; }
    $key     = 'codecanvas_studio_bk_' . md5( $map['root'] . ':' . $map['relative'] );
    $history = get_option( $key, array() );
    if ( ! is_array( $history ) || empty( $history[0]['content'] ) ) { return false; }
    $content = $history[0]['content'];
    if ( ! is_string( $content ) || '' === $content || strlen( $content ) > 1048576 ) { return false; }
    // Only undo a recent CodeCanvas change. An old snapshot is the wrong baseline
    // and could silently revert a deliberate fix made later.
    $age = time() - ( isset( $history[0]['time'] ) ? (int) $history[0]['time'] : 0 );
    if ( $age < 0 || $age > DAY_IN_SECONDS ) { return false; }
    // Do not overwrite a file that currently parses and was not touched by this fatal.
    $current = @file_get_contents( $file );
    if ( is_string( $current ) && hash_equals( hash( 'sha256', $current ), hash( 'sha256', $content ) ) ) { return false; }

    $guard = get_option( 'codecanvas_recovery_last', array() );
    $guard = is_array( $guard ) ? $guard : array();
    if ( isset( $guard[ $key ] ) && ( time() - (int) $guard[ $key ] ) < 30 ) { return false; }
    if ( false === @file_put_contents( $file, $content, LOCK_EX ) ) { return false; }
    if ( function_exists( 'opcache_invalidate' ) ) { @opcache_invalidate( $file, true ); }

    $guard[ $key ] = time();
    update_option( 'codecanvas_recovery_last', $guard, false );
    $log = get_option( 'codecanvas_recovery_log', array() );
    $log = is_array( $log ) ? $log : array();
    array_unshift( $log, array(
        'time'    => time(),
        'file'    => $map['root'] . '/' . $map['relative'],
        'bytes'   => strlen( $content ),
        'message' => substr( preg_replace( '/\s+/', ' ', (string) $message ), 0, 300 ),
    ) );
    update_option( 'codecanvas_recovery_log', array_slice( $log, 0, 20 ), false );
    return true;
}

/** Shutdown handler: restore a snapshot when this request died on a fatal error. */
function codecanvas_recovery_handle_fatal() {
    $error = error_get_last();
    if ( ! is_array( $error ) || empty( $error['file'] ) ) { return; }
    $fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
    if ( ! in_array( (int) $error['type'], $fatal, true ) ) { return; }
    codecanvas_recovery_restore_file( $error['file'], isset( $error['message'] ) ? $error['message'] : '' );
}
register_shutdown_function( 'codecanvas_recovery_handle_fatal' );

endif;
