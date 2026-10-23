<?php
/**
 * OAuth 2.1 authorization server for the CodeCanvas MCP endpoint.
 *
 * Browser-based MCP clients (claude.ai, Claude Desktop's connector UI and
 * others) cannot send a fixed Authorization header, so they expect an OAuth
 * flow instead of the static bearer token. This module adds the minimum
 * standards-based surface those clients probe:
 *
 * - RFC 9728 protected-resource metadata
 * - RFC 8414 authorization-server metadata
 * - RFC 7591 dynamic client registration
 * - RFC 7636 PKCE (S256 only) on the authorization-code grant
 * - RFC 7009 token revocation
 * - Client ID Metadata Documents (an https client_id is fetched and validated)
 *
 * Every grant is bound to a WordPress administrator who explicitly approved
 * the request on the consent screen, and every access token is stored as an
 * HMAC hash. The static bearer token keeps working exactly as before.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The single scope exposed by this server. */
function codecanvas_oauth_scope() {
    return 'mcp';
}

/** Scheme and host of the site, without a path. */
function codecanvas_oauth_origin() {
    $parts  = wp_parse_url( home_url( '/' ) );
    $scheme = ( is_ssl() || ( ! empty( $parts['scheme'] ) && 'https' === $parts['scheme'] ) ) ? 'https' : 'http';
    $host   = ! empty( $parts['host'] ) ? $parts['host'] : 'localhost';
    $port   = ! empty( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
    return $scheme . '://' . $host . $port;
}

/** Issuer identifier advertised in the metadata documents. */
function codecanvas_oauth_issuer() {
    return untrailingslashit( home_url() );
}

/** Public URL of one OAuth endpoint. */
function codecanvas_oauth_endpoint( $name ) {
    return home_url( '/codecanvas-oauth/' . $name );
}

/**
 * Public URL of a discovery document.
 *
 * A root installation serves these from the domain root, which is where AI
 * clients look. A subdirectory installation cannot own the root path, so the
 * document is advertised under the install path instead.
 */
function codecanvas_oauth_well_known_url( $name ) {
    $path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( '' === $path ) {
        return codecanvas_oauth_origin() . '/.well-known/' . $name;
    }
    return untrailingslashit( home_url() ) . '/.well-known/' . $name;
}

/** Protected-resource metadata URL, used in WWW-Authenticate. */
function codecanvas_oauth_resource_metadata_url() {
    return codecanvas_oauth_well_known_url( 'oauth-protected-resource' );
}

/** Absolute URL of the MCP endpoint itself. */
function codecanvas_oauth_resource_url() {
    return home_url( '/wp-json/codecanvas/v1/mcp' );
}

/**
 * Protected-resource metadata (RFC 9728).
 *
 * Claude fetches this after the MCP endpoint answers 401 so it can discover
 * which authorization server protects the resource.
 */
function codecanvas_oauth_resource_metadata() {
    return array(
        'resource'                 => codecanvas_oauth_resource_url(),
        'authorization_servers'    => array( codecanvas_oauth_issuer() ),
        'scopes_supported'         => array( codecanvas_oauth_scope() ),
        'bearer_methods_supported' => array( 'header' ),
        'resource_name'            => get_bloginfo( 'name' ) . ' (CodeCanvas MCP)',
        'resource_documentation'   => home_url( '/wp-content/themes/CodeCanvas/docs/11-mcp-integration.md' ),
    );
}

/** Authorization-server metadata (RFC 8414). */
function codecanvas_oauth_server_metadata() {
    return array(
        'issuer'                                => codecanvas_oauth_issuer(),
        'authorization_endpoint'                => codecanvas_oauth_endpoint( 'authorize' ),
        'token_endpoint'                        => codecanvas_oauth_endpoint( 'token' ),
        'registration_endpoint'                 => codecanvas_oauth_endpoint( 'register' ),
        'revocation_endpoint'                   => codecanvas_oauth_endpoint( 'revoke' ),
        'scopes_supported'                      => array( codecanvas_oauth_scope() ),
        'response_types_supported'              => array( 'code' ),
        'response_modes_supported'              => array( 'query' ),
        'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
        'token_endpoint_auth_methods_supported' => array( 'none' ),
        'code_challenge_methods_supported'       => array( 'S256' ),
        'service_documentation'                 => home_url( '/wp-content/themes/CodeCanvas/docs/11-mcp-integration.md' ),
    );
}

// ---------------------------------------------------------------------------
// Storage
// ---------------------------------------------------------------------------

/** Registered dynamic clients and cached Client ID Metadata Documents. */
function codecanvas_oauth_clients() {
    $clients = get_option( 'codecanvas_oauth_clients', array() );
    return is_array( $clients ) ? $clients : array();
}

/** Persist the client registry, dropping the oldest entries past the cap. */
function codecanvas_oauth_save_client( $client ) {
    $clients = codecanvas_oauth_clients();
    $clients[ $client['client_id'] ] = $client;
    while ( count( $clients ) > 100 ) { array_shift( $clients ); }
    update_option( 'codecanvas_oauth_clients', $clients, false );
}

/** Stored grants, keyed by the HMAC hash of the secret. */
function codecanvas_oauth_tokens() {
    $tokens = get_option( 'codecanvas_oauth_tokens', array() );
    return is_array( $tokens ) ? $tokens : array();
}

/** Persist grants, pruning expired entries and enforcing a hard cap. */
function codecanvas_oauth_save_tokens( $tokens ) {
    $now = time();
    foreach ( $tokens as $hash => $row ) {
        if ( ! is_array( $row ) || empty( $row['expires'] ) || (int) $row['expires'] <= $now ) {
            unset( $tokens[ $hash ] );
        }
    }
    while ( count( $tokens ) > 200 ) { array_shift( $tokens ); }
    update_option( 'codecanvas_oauth_tokens', $tokens, false );
}

/** Count active grants, split by type, for the settings screen. */
function codecanvas_oauth_token_counts() {
    $now    = time();
    $counts = array( 'access' => 0, 'refresh' => 0, 'clients' => 0 );
    foreach ( codecanvas_oauth_tokens() as $row ) {
        if ( ! is_array( $row ) || empty( $row['expires'] ) || (int) $row['expires'] <= $now ) { continue; }
        if ( isset( $counts[ $row['type'] ] ) ) { $counts[ $row['type'] ]++; }
    }
    $counts['clients'] = count( codecanvas_oauth_clients() );
    return $counts;
}

/** Issue one access or refresh token and return the plaintext secret once. */
function codecanvas_oauth_issue_token( $type, $client_id, $user_id, $ttl, $family ) {
    $secret = 'cco_' . wp_generate_password( 48, false, false );
    $hash   = codecanvas_mcp_hash_token( $secret );
    $tokens = codecanvas_oauth_tokens();
    $tokens[ $hash ] = array(
        'type'      => ( 'refresh' === $type ) ? 'refresh' : 'access',
        'client_id' => (string) $client_id,
        'user_id'   => (int) $user_id,
        'family'    => (string) $family,
        'scope'     => codecanvas_oauth_scope(),
        'created'   => time(),
        'expires'   => time() + max( 60, (int) $ttl ),
    );
    codecanvas_oauth_save_tokens( $tokens );
    return $secret;
}

/** Look up a stored grant by its plaintext secret. */
function codecanvas_oauth_find_token( $secret ) {
    if ( ! is_string( $secret ) || '' === $secret ) { return null; }
    $tokens = codecanvas_oauth_tokens();
    $hash   = codecanvas_mcp_hash_token( $secret );
    if ( empty( $tokens[ $hash ] ) || ! is_array( $tokens[ $hash ] ) ) { return null; }
    $row = $tokens[ $hash ];
    if ( empty( $row['expires'] ) || (int) $row['expires'] <= time() ) { return null; }
    return $row;
}

/**
 * Map an OAuth access token to a WordPress user ID for MCP authorization.
 *
 * Returns 0 when the secret is unknown, expired, the wrong type, or the
 * granting administrator can no longer manage the site.
 */
function codecanvas_oauth_access_user( $secret ) {
    $row = codecanvas_oauth_find_token( $secret );
    if ( ! $row || 'access' !== $row['type'] ) { return 0; }
    $user_id = (int) $row['user_id'];
    $user    = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user || ! user_can( $user, 'manage_options' ) ) { return 0; }
    return $user_id;
}

/**
 * Revoke a grant.
 *
 * Revoking an access token (or the app revoking itself) deletes the whole
 * grant, refresh token included. Refresh rotation passes $with_family = false
 * so the previous access token keeps working until it expires.
 */
function codecanvas_oauth_revoke( $secret, $with_family = true ) {
    if ( ! is_string( $secret ) || '' === $secret ) { return false; }
    $hash   = codecanvas_mcp_hash_token( $secret );
    $tokens = codecanvas_oauth_tokens();
    if ( empty( $tokens[ $hash ] ) ) { return false; }
    $family = isset( $tokens[ $hash ]['family'] ) ? (string) $tokens[ $hash ]['family'] : '';
    unset( $tokens[ $hash ] );
    if ( $with_family && '' !== $family ) {
        foreach ( $tokens as $key => $row ) {
            if ( isset( $row['family'] ) && $family === (string) $row['family'] ) { unset( $tokens[ $key ] ); }
        }
    }
    update_option( 'codecanvas_oauth_tokens', $tokens, false );
    return true;
}

/** Delete every OAuth grant without touching the static token. */
function codecanvas_oauth_revoke_all() {
    update_option( 'codecanvas_oauth_tokens', array(), false );
    update_option( 'codecanvas_oauth_clients', array(), false );
}

// ---------------------------------------------------------------------------
// Clients
// ---------------------------------------------------------------------------

/** A redirect URI must be absolute, HTTPS, and free of fragments or userinfo. */
function codecanvas_oauth_valid_redirect_uri( $uri ) {
    if ( ! is_string( $uri ) || '' === $uri || strlen( $uri ) > 500 ) { return false; }
    $parts = wp_parse_url( $uri );
    if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) { return false; }
    if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) { return false; }
    $scheme = strtolower( $parts['scheme'] );
    if ( 'https' === $scheme ) { return true; }
    $loopback = in_array( strtolower( $parts['host'] ), array( 'localhost', '127.0.0.1', '::1' ), true );
    return ( 'http' === $scheme && $loopback );
}

/**
 * Resolve a client_id to client metadata.
 *
 * Registered clients are read from the option. An HTTPS URL client_id is
 * treated as a Client ID Metadata Document, fetched with the safe HTTP API
 * (private networks rejected) and cached for an hour.
 */
function codecanvas_oauth_get_client( $client_id ) {
    if ( ! is_string( $client_id ) || '' === $client_id || strlen( $client_id ) > 300 ) {
        return new WP_Error( 'invalid_client', __( 'Missing client identifier.', 'codecanvas' ) );
    }
    $clients = codecanvas_oauth_clients();
    if ( isset( $clients[ $client_id ] ) && is_array( $clients[ $client_id ] ) ) {
        return $clients[ $client_id ];
    }
    if ( 0 !== stripos( $client_id, 'https://' ) ) {
        return new WP_Error( 'invalid_client', __( 'Unknown client. Register the client or use a Client ID Metadata Document URL.', 'codecanvas' ) );
    }
    $cache_key = 'cc_oauth_cimd_' . md5( $client_id );
    $cached    = get_transient( $cache_key );
    if ( is_array( $cached ) ) { return $cached; }

    $response = wp_safe_remote_get( $client_id, array( 'timeout' => 5, 'redirection' => 2, 'limit_response_size' => 102400 ) );
    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'invalid_client', __( 'Could not read the client metadata document.', 'codecanvas' ) );
    }
    $doc = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $doc ) || empty( $doc['redirect_uris'] ) || ! is_array( $doc['redirect_uris'] ) ) {
        return new WP_Error( 'invalid_client', __( 'The client metadata document is invalid.', 'codecanvas' ) );
    }
    if ( ! empty( $doc['client_id'] ) && $doc['client_id'] !== $client_id ) {
        return new WP_Error( 'invalid_client', __( 'The client metadata document does not match its URL.', 'codecanvas' ) );
    }
    $uris = array();
    foreach ( $doc['redirect_uris'] as $uri ) {
        if ( codecanvas_oauth_valid_redirect_uri( $uri ) ) { $uris[] = $uri; }
    }
    if ( empty( $uris ) ) {
        return new WP_Error( 'invalid_client', __( 'The client metadata document has no usable redirect URI.', 'codecanvas' ) );
    }
    $client = array(
        'client_id'     => $client_id,
        'client_name'   => isset( $doc['client_name'] ) && is_string( $doc['client_name'] ) ? substr( sanitize_text_field( $doc['client_name'] ), 0, 120 ) : '',
        'redirect_uris' => $uris,
        'created'       => time(),
        'cimd'          => true,
    );
    set_transient( $cache_key, $client, HOUR_IN_SECONDS );
    return $client;
}

/** Validate a dynamic client registration request and store the client. */
function codecanvas_oauth_register_client( $payload ) {
    if ( ! is_array( $payload ) ) {
        return new WP_Error( 'invalid_client_metadata', __( 'Expected a JSON or form-encoded registration body.', 'codecanvas' ) );
    }
    $method = isset( $payload['token_endpoint_auth_method'] ) && is_string( $payload['token_endpoint_auth_method'] )
        ? strtolower( $payload['token_endpoint_auth_method'] ) : 'none';
    if ( 'none' !== $method ) {
        return new WP_Error( 'invalid_client_metadata', __( 'Only public clients using token_endpoint_auth_method "none" are supported.', 'codecanvas' ) );
    }
    if ( ! empty( $payload['grant_types'] ) ) {
        if ( ! is_array( $payload['grant_types'] ) ) {
            return new WP_Error( 'invalid_client_metadata', __( 'grant_types must be an array.', 'codecanvas' ) );
        }
        foreach ( $payload['grant_types'] as $grant ) {
            if ( ! in_array( $grant, array( 'authorization_code', 'refresh_token' ), true ) ) {
                return new WP_Error( 'invalid_client_metadata', __( 'Unsupported grant type.', 'codecanvas' ) );
            }
        }
    }
    $uris = isset( $payload['redirect_uris'] ) ? $payload['redirect_uris'] : null;
    if ( is_string( $uris ) ) { $uris = array( $uris ); }
    if ( ! is_array( $uris ) || empty( $uris ) || count( $uris ) > 10 ) {
        return new WP_Error( 'invalid_redirect_uri', __( 'redirect_uris must contain between 1 and 10 entries.', 'codecanvas' ) );
    }
    $valid = array();
    foreach ( $uris as $uri ) {
        if ( ! codecanvas_oauth_valid_redirect_uri( $uri ) ) {
            return new WP_Error( 'invalid_redirect_uri', __( 'Every redirect URI must be an absolute HTTPS URL without a fragment. Plain HTTP is accepted only for localhost.', 'codecanvas' ) );
        }
        $valid[] = $uri;
    }
    $client = array(
        'client_id'     => 'ccc_' . wp_generate_password( 32, false, false ),
        'client_name'   => isset( $payload['client_name'] ) && is_string( $payload['client_name'] ) ? substr( sanitize_text_field( $payload['client_name'] ), 0, 120 ) : '',
        'redirect_uris' => array_values( array_unique( $valid ) ),
        'created'       => time(),
        'cimd'          => false,
    );
    codecanvas_oauth_save_client( $client );
    return array(
        'client_id'                  => $client['client_id'],
        'client_id_issued_at'        => $client['created'],
        'client_name'                => $client['client_name'],
        'redirect_uris'              => $client['redirect_uris'],
        'grant_types'                => array( 'authorization_code', 'refresh_token' ),
        'response_types'             => array( 'code' ),
        'token_endpoint_auth_method' => 'none',
        'scope'                      => codecanvas_oauth_scope(),
    );
}

// ---------------------------------------------------------------------------
// Authorization code grant
// ---------------------------------------------------------------------------

/** Create a single-use authorization code. Only its hash is stored. */
function codecanvas_oauth_create_code( $client_id, $user_id, $redirect_uri, $challenge, $scope = 'mcp' ) {
    $code = 'ccc_' . wp_generate_password( 48, false, false );
    set_transient(
        'cc_oauth_code_' . hash( 'sha256', $code ),
        array(
            'client_id'  => (string) $client_id,
            'user_id'    => (int) $user_id,
            'redirect_uri' => (string) $redirect_uri,
            'challenge'  => (string) $challenge,
            'scope'      => (string) $scope,
            'created'    => time(),
        ),
        300
    );
    return $code;
}

/** Verify an S256 PKCE code verifier against the stored challenge. */
function codecanvas_oauth_verify_pkce( $verifier, $challenge ) {
    if ( ! is_string( $verifier ) || ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) ) { return false; }
    if ( ! is_string( $challenge ) || '' === $challenge ) { return false; }
    $calculated = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
    return hash_equals( $challenge, $calculated );
}

/** Exchange an authorization code for an access/refresh token pair. */
function codecanvas_oauth_exchange_code( $code, $client_id, $redirect_uri, $verifier ) {
    if ( ! is_string( $code ) || '' === $code ) {
        return new WP_Error( 'invalid_request', __( 'Missing authorization code.', 'codecanvas' ) );
    }
    $key = 'cc_oauth_code_' . hash( 'sha256', $code );
    $row = get_transient( $key );
    if ( ! is_array( $row ) ) {
        return new WP_Error( 'invalid_grant', __( 'The authorization code is unknown, expired or already used.', 'codecanvas' ) );
    }
    if ( ! isset( $row['client_id'] ) || $row['client_id'] !== (string) $client_id ) {
        return new WP_Error( 'invalid_grant', __( 'The authorization code was issued to a different client.', 'codecanvas' ) );
    }
    if ( ! isset( $row['redirect_uri'] ) || $row['redirect_uri'] !== (string) $redirect_uri ) {
        return new WP_Error( 'invalid_grant', __( 'The redirect URI does not match the authorization request.', 'codecanvas' ) );
    }
    if ( ! codecanvas_oauth_verify_pkce( $verifier, isset( $row['challenge'] ) ? $row['challenge'] : '' ) ) {
        return new WP_Error( 'invalid_grant', __( 'The PKCE code verifier does not match the code challenge.', 'codecanvas' ) );
    }
    delete_transient( $key );

    $family  = wp_generate_password( 24, false, false );
    $access  = codecanvas_oauth_issue_token( 'access', $client_id, (int) $row['user_id'], HOUR_IN_SECONDS, $family );
    $refresh = codecanvas_oauth_issue_token( 'refresh', $client_id, (int) $row['user_id'], 30 * DAY_IN_SECONDS, $family );
    return array(
        'access_token'  => $access,
        'token_type'    => 'Bearer',
        'expires_in'    => HOUR_IN_SECONDS,
        'refresh_token' => $refresh,
        'scope'         => codecanvas_oauth_scope(),
    );
}

/** Rotate a refresh token into a fresh token pair. */
function codecanvas_oauth_refresh( $refresh_token, $client_id ) {
    $row = codecanvas_oauth_find_token( $refresh_token );
    if ( ! $row || 'refresh' !== $row['type'] ) {
        return new WP_Error( 'invalid_grant', __( 'The refresh token is unknown or expired.', 'codecanvas' ) );
    }
    if ( (string) $row['client_id'] !== (string) $client_id ) {
        return new WP_Error( 'invalid_grant', __( 'The refresh token was issued to a different client.', 'codecanvas' ) );
    }
    $user_id = (int) $row['user_id'];
    $user    = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
        return new WP_Error( 'invalid_grant', __( 'The administrator who granted access is unavailable.', 'codecanvas' ) );
    }
    $family = isset( $row['family'] ) ? (string) $row['family'] : wp_generate_password( 24, false, false );
    codecanvas_oauth_revoke( $refresh_token, false );

    $access = codecanvas_oauth_issue_token( 'access', $client_id, $user_id, HOUR_IN_SECONDS, $family );
    $next   = codecanvas_oauth_issue_token( 'refresh', $client_id, $user_id, 30 * DAY_IN_SECONDS, $family );
    return array(
        'access_token'  => $access,
        'token_type'    => 'Bearer',
        'expires_in'    => HOUR_IN_SECONDS,
        'refresh_token' => $next,
        'scope'         => codecanvas_oauth_scope(),
    );
}

// ---------------------------------------------------------------------------
// HTTP plumbing
// ---------------------------------------------------------------------------

/** Emit a JSON response and stop. */
function codecanvas_oauth_json( $data, $status = 200, $extra_headers = array() ) {
    status_header( $status );
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Cache-Control: no-store' );
    header( 'Pragma: no-cache' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: no-referrer' );
    foreach ( (array) $extra_headers as $header ) { header( $header ); }
    echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
    exit;
}

/** OAuth error response. */
function codecanvas_oauth_json_error( $error, $description, $status = 400 ) {
    $headers = ( 401 === (int) $status ) ? array( 'WWW-Authenticate: Basic realm="CodeCanvas OAuth"' ) : array();
    codecanvas_oauth_json(
        array( 'error' => (string) $error, 'error_description' => (string) $description ),
        $status,
        $headers
    );
}

/** Normalized HTTP request method for the OAuth endpoints. */
function codecanvas_oauth_request_method() {
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
    return strtoupper( $method );
}

/** Read request parameters from JSON or form-encoded bodies. */
function codecanvas_oauth_params() {
    $type = isset( $_SERVER['CONTENT_TYPE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) ) : '';
    if ( false !== strpos( $type, 'application/json' ) ) {
        $raw  = file_get_contents( 'php://input' );
        $data = json_decode( (string) $raw, true );
        return is_array( $data ) ? $data : array();
    }
    $post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- OAuth protocol request; credentials are validated by the endpoint handlers.
    return is_array( $post ) ? $post : array();
}

/** Read one scalar parameter with a length cap. */
function codecanvas_oauth_param( $params, $key, $max = 500 ) {
    if ( ! is_array( $params ) || ! isset( $params[ $key ] ) || ! is_scalar( $params[ $key ] ) ) { return ''; }
    return substr( (string) $params[ $key ], 0, (int) $max );
}

/** Append OAuth response parameters to a validated redirect URI. */
function codecanvas_oauth_redirect( $uri, $args ) {
    $url  = add_query_arg( $args, $uri );
    $host = wp_parse_url( $url, PHP_URL_HOST );
    nocache_headers();
    // The client redirect URI was validated before this point; allow only that host.
    if ( $host ) {
        add_filter( 'allowed_redirect_hosts', function ( $hosts ) use ( $host ) {
            $hosts[] = $host;
            return $hosts;
        } );
    }
    wp_safe_redirect( $url );
    exit;
}

/** Build an OAuth error redirect for an already-validated redirect URI. */
function codecanvas_oauth_redirect_error( $uri, $state, $error, $description ) {
    $args = array( 'error' => $error, 'error_description' => $description );
    if ( '' !== $state ) { $args['state'] = $state; }
    codecanvas_oauth_redirect( $uri, $args );
}

/**
 * Render a standalone HTML page for errors and consent.
 *
 * A self-contained document keeps theme scripts from running on a page that
 * grants source-file access.
 */
function codecanvas_oauth_render_page( $title, $body, $status = 200, $headers = array() ) {
    status_header( $status );
    nocache_headers();
    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'X-Frame-Options: DENY' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: no-referrer' );
    header( 'X-Robots-Tag: noindex, nofollow' );
    foreach ( (array) $headers as $header ) { header( $header ); }
    $site = get_bloginfo( 'name' );
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title . ' · ' . $site ); ?></title>
<style>
:root{color-scheme:light dark}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 20px;background:#f4f5f7;color:#1d2327;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.cc-oauth-card{width:100%;max-width:560px;background:#fff;border:1px solid #dcdcde;border-radius:14px;box-shadow:0 12px 32px rgba(15,23,42,.08);padding:32px}
.cc-oauth-brand{display:flex;align-items:center;gap:10px;margin:0 0 18px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#646970}
.cc-oauth-card h1{margin:0 0 12px;font-size:24px;line-height:1.25}
.cc-oauth-card p{margin:0 0 14px}
.cc-oauth-detail{margin:18px 0;padding:14px 16px;border:1px solid #e2e4e7;border-radius:10px;background:#f8f9fa;font-size:14px}
.cc-oauth-detail dt{font-weight:600;margin-top:10px}
.cc-oauth-detail dt:first-child{margin-top:0}
.cc-oauth-detail dd{margin:2px 0 0;word-break:break-word}
.cc-oauth-detail code{font-size:13px;background:#fff;border:1px solid #e2e4e7;border-radius:4px;padding:1px 5px}
.cc-oauth-scopes{margin:0;padding-left:20px;font-size:14px}
.cc-oauth-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
.cc-oauth-actions button{font:inherit;font-weight:600;cursor:pointer;border-radius:8px;padding:11px 20px;border:1px solid transparent}
.cc-oauth-approve{background:#1d4ed8;color:#fff}
.cc-oauth-approve:hover{background:#1e40af}
.cc-oauth-deny{background:#fff;color:#b32d2e;border-color:#dcdcde}
.cc-oauth-deny:hover{background:#fcf0f1}
.cc-oauth-note{margin-top:20px;font-size:13px;color:#646970}
@media (prefers-color-scheme:dark){
body{background:#14161a;color:#e8eaed}
.cc-oauth-card{background:#1c1f24;border-color:#2f333a;box-shadow:none}
.cc-oauth-brand,.cc-oauth-note{color:#9aa0a6}
.cc-oauth-detail{background:#22262c;border-color:#2f333a}
.cc-oauth-detail code{background:#14161a;border-color:#2f333a;color:#e8eaed}
.cc-oauth-deny{background:#1c1f24;border-color:#3a3f46;color:#f19a9a}
.cc-oauth-deny:hover{background:#2a1e1f}
}
</style>
</head>
<body>
<main class="cc-oauth-card">
<div class="cc-oauth-brand"><?php echo esc_html( $site ); ?> · CodeCanvas</div>
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped fragments below. ?>
</main>
</body>
</html>
    <?php
    exit;
}

/** Render an error page that never redirects to an unverified URI. */
function codecanvas_oauth_render_error( $message, $status = 400 ) {
    codecanvas_oauth_render_page(
        __( 'Authorization error', 'codecanvas' ),
        '<h1>' . esc_html__( 'This sign-in request could not be completed', 'codecanvas' ) . '</h1><p>' . esc_html( $message ) . '</p><p class="cc-oauth-note">' . esc_html__( 'Close this window and start the connection again from your MCP client.', 'codecanvas' ) . '</p>',
        $status
    );
}

/** Human-readable summary of what the connected client may do. */
function codecanvas_oauth_consent_scopes() {
    $options = codecanvas_mcp_options();
    $items   = array( __( 'Read theme and plugin files in the projects enabled below.', 'codecanvas' ) );
    $names   = array();
    $all     = codecanvas_mcp_tools();
    foreach ( codecanvas_mcp_enabled_tools() as $name => $tool ) {
        if ( isset( $all[ $name ]['title'] ) ) { $names[] = $all[ $name ]['title']; }
    }
    /* translators: %s: comma-separated list of tool names. */
    $items[] = sprintf( __( 'Call these tools only: %s.', 'codecanvas' ), implode( ', ', $names ) );
    $projects = array();
    foreach ( $options['projects'] as $key ) {
        $project = codecanvas_mcp_project( $key );
        if ( ! is_wp_error( $project ) && ! empty( $project['label'] ) ) { $projects[] = $project['label']; }
    }
    if ( $projects ) {
        /* translators: %s: comma-separated list of project keys. */
        $items[] = sprintf( __( 'Projects in scope: %s.', 'codecanvas' ), implode( ', ', $projects ) );
    }
    if ( ! empty( $options['allow_writes'] ) ) {
        $items[] = __( 'Create, overwrite and delete files in those projects.', 'codecanvas' );
    }
    if ( ! empty( $options['allow_content_writes'] ) ) {
        $items[] = __( 'Create and update WordPress posts and pages.', 'codecanvas' );
    }
    return $items;
}

/** Render the approval screen for an authorization request. */
function codecanvas_oauth_render_consent( $client, $params ) {
    $client_name  = ! empty( $client['client_name'] ) ? $client['client_name'] : __( 'An MCP client', 'codecanvas' );
    $redirect     = codecanvas_oauth_param( $params, 'redirect_uri', 500 );
    $redirect_host = (string) wp_parse_url( $redirect, PHP_URL_HOST );
    $state        = codecanvas_oauth_param( $params, 'state', 500 );
    $challenge    = codecanvas_oauth_param( $params, 'code_challenge', 200 );
    $method       = codecanvas_oauth_param( $params, 'code_challenge_method', 20 );
    $scope        = codecanvas_oauth_param( $params, 'scope', 100 );
    $user         = wp_get_current_user();

    $fields = array(
        'response_type'         => 'code',
        'client_id'             => $client['client_id'],
        'redirect_uri'          => $redirect,
        'state'                 => $state,
        'code_challenge'        => $challenge,
        'code_challenge_method' => $method,
        'scope'                 => $scope,
    );
    $hidden = '';
    foreach ( $fields as $name => $value ) {
        $hidden .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    }
    $hidden .= wp_nonce_field( 'codecanvas_oauth_consent_' . $client['client_id'], '_wpnonce', true, false );

    $scopes = '';
    foreach ( codecanvas_oauth_consent_scopes() as $item ) {
        $scopes .= '<li>' . esc_html( $item ) . '</li>';
    }

    $body = '<h1>' . esc_html__( 'Connect an AI client to this site?', 'codecanvas' ) . '</h1>'
        /* translators: %1$s: OAuth client name, %2$s: site name. */
        . '<p>' . sprintf( esc_html__( '%1$s is asking for access to %2$s through CodeCanvas MCP.', 'codecanvas' ), '<strong>' . esc_html( $client_name ) . '</strong>', '<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>' ) . '</p>'
        . '<dl class="cc-oauth-detail">'
        . '<dt>' . esc_html__( 'Client', 'codecanvas' ) . '</dt><dd>' . esc_html( $client_name ) . '</dd>'
        . '<dt>' . esc_html__( 'Returns to', 'codecanvas' ) . '</dt><dd><code>' . esc_html( $redirect_host ) . '</code></dd>'
        . '<dt>' . esc_html__( 'Signed in as', 'codecanvas' ) . '</dt><dd>' . esc_html( $user->user_login ) . ' (' . esc_html( (string) $user->user_email ) . ')</dd>'
        . '</dl>'
        . '<p><strong>' . esc_html__( 'If you approve, this client can:', 'codecanvas' ) . '</strong></p>'
        . '<ul class="cc-oauth-scopes">' . $scopes . '</ul>'
        . '<form method="post" action="' . esc_url( codecanvas_oauth_endpoint( 'authorize' ) ) . '">'
        . $hidden
        . '<div class="cc-oauth-actions">'
        . '<button type="submit" class="cc-oauth-approve" name="codecanvas_oauth_action" value="approve">' . esc_html__( 'Approve and connect', 'codecanvas' ) . '</button>'
        . '<button type="submit" class="cc-oauth-deny" name="codecanvas_oauth_action" value="deny" formnovalidate>' . esc_html__( 'Cancel', 'codecanvas' ) . '</button>'
        . '</div></form>'
        . '<p class="cc-oauth-note">' . esc_html__( 'Access uses your administrator account and stays active until you revoke it under CodeCanvas → MCP & Settings. Only approve clients you trust.', 'codecanvas' ) . '</p>';

    codecanvas_oauth_render_page( __( 'Approve connection', 'codecanvas' ), $body );
}

// ---------------------------------------------------------------------------
// Endpoint handlers
// ---------------------------------------------------------------------------

/** GET/POST /codecanvas-oauth/authorize */
function codecanvas_oauth_handle_authorize() {
    $method = codecanvas_oauth_request_method();
    if ( ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
        codecanvas_oauth_render_error( __( 'This endpoint accepts GET and POST only.', 'codecanvas' ), 405 );
    }
    $params = ( 'POST' === $method ) ? codecanvas_oauth_params() : wp_unslash( $_GET );
    if ( ! is_array( $params ) ) { $params = array(); }

    $client_id     = codecanvas_oauth_param( $params, 'client_id', 300 );
    $redirect_uri  = codecanvas_oauth_param( $params, 'redirect_uri', 500 );
    $state         = codecanvas_oauth_param( $params, 'state', 500 );
    $challenge     = codecanvas_oauth_param( $params, 'code_challenge', 200 );
    $challenge_alg = codecanvas_oauth_param( $params, 'code_challenge_method', 20 );
    $response_type = codecanvas_oauth_param( $params, 'response_type', 40 );
    $scope_in      = codecanvas_oauth_param( $params, 'scope', 100 );

    if ( '' === $client_id || '' === $redirect_uri ) {
        codecanvas_oauth_render_error( __( 'The request is missing the client identifier or redirect URI.', 'codecanvas' ) );
    }
    $client = codecanvas_oauth_get_client( $client_id );
    if ( is_wp_error( $client ) ) {
        codecanvas_oauth_render_error( $client->get_error_message() );
    }
    if ( ! in_array( $redirect_uri, $client['redirect_uris'], true ) ) {
        codecanvas_oauth_render_error( __( 'The redirect URI is not registered for this client.', 'codecanvas' ) );
    }

    $options = codecanvas_mcp_options();
    if ( ! $options['enabled'] ) {
        codecanvas_oauth_render_error( __( 'The CodeCanvas MCP server is disabled. Enable it in CodeCanvas → MCP & Settings, then try again.', 'codecanvas' ), 503 );
    }
    if ( 'code' !== $response_type ) {
        codecanvas_oauth_redirect_error( $redirect_uri, $state, 'unsupported_response_type', 'Only the authorization code flow is supported.' );
    }
    if ( 'S256' !== $challenge_alg || '' === $challenge ) {
        codecanvas_oauth_redirect_error( $redirect_uri, $state, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.' );
    }

    // The browser must be signed in to WordPress before it can approve anything.
    // is_user_logged_in() (the front-end cookie) is the right check here;
    // auth_redirect() would demand the wp-admin cookie and bounce signed-in users.
    if ( ! is_user_logged_in() ) {
        // Keep the raw query string so percent-encoded OAuth parameters reach the login round trip intact.
        $query  = isset( $_SERVER['QUERY_STRING'] ) ? wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only appended to the local login URL; wp_safe_redirect() validates it.
        $target = codecanvas_oauth_endpoint( 'authorize' ) . ( '' !== $query ? '?' . $query : '' );
        nocache_headers();
        wp_safe_redirect( wp_login_url( $target ) );
        exit;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        codecanvas_oauth_render_error( __( 'Only a site administrator can approve an MCP connection.', 'codecanvas' ), 403 );
    }

    $decision = codecanvas_oauth_param( $params, 'codecanvas_oauth_action', 20 );
    if ( '' !== $decision ) {
        $nonce = isset( $params['_wpnonce'] ) ? (string) $params['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'codecanvas_oauth_consent_' . $client['client_id'] ) ) {
            codecanvas_oauth_render_error( __( 'The approval form expired. Close this window and start the connection again.', 'codecanvas' ), 403 );
        }
        if ( 'approve' !== $decision ) {
            codecanvas_oauth_redirect_error( $redirect_uri, $state, 'access_denied', 'The site owner declined the request.' );
        }
        $scope = '' !== $scope_in ? $scope_in : codecanvas_oauth_scope();
        $code  = codecanvas_oauth_create_code( $client['client_id'], get_current_user_id(), $redirect_uri, $challenge, $scope );
        codecanvas_oauth_redirect( $redirect_uri, array( 'code' => $code, 'state' => $state ) );
    }

    codecanvas_oauth_render_consent( $client, $params );
}

/** POST /codecanvas-oauth/token */
function codecanvas_oauth_handle_token() {
    if ( 'POST' !== codecanvas_oauth_request_method() ) {
        codecanvas_oauth_json_error( 'invalid_request', 'Use POST for the token endpoint.', 405 );
    }
    $options = codecanvas_mcp_options();
    if ( ! $options['enabled'] ) {
        codecanvas_oauth_json_error( 'temporarily_unavailable', 'The CodeCanvas MCP server is disabled.', 503 );
    }
    $params = codecanvas_oauth_params();
    $grant  = codecanvas_oauth_param( $params, 'grant_type', 40 );
    $client_id = codecanvas_oauth_param( $params, 'client_id', 300 );

    if ( '' === $client_id ) {
        codecanvas_oauth_json_error( 'invalid_client', 'Missing client_id.', 401 );
    }
    $client = codecanvas_oauth_get_client( $client_id );
    if ( is_wp_error( $client ) ) {
        codecanvas_oauth_json_error( 'invalid_client', $client->get_error_message(), 401 );
    }

    if ( 'authorization_code' === $grant ) {
        $result = codecanvas_oauth_exchange_code(
            codecanvas_oauth_param( $params, 'code', 300 ),
            $client_id,
            codecanvas_oauth_param( $params, 'redirect_uri', 500 ),
            codecanvas_oauth_param( $params, 'code_verifier', 200 )
        );
    } elseif ( 'refresh_token' === $grant ) {
        $result = codecanvas_oauth_refresh( codecanvas_oauth_param( $params, 'refresh_token', 300 ), $client_id );
    } else {
        codecanvas_oauth_json_error( 'unsupported_grant_type', 'Supported grants: authorization_code, refresh_token.' );
    }

    if ( is_wp_error( $result ) ) {
        codecanvas_oauth_json_error( $result->get_error_code(), $result->get_error_message() );
    }
    codecanvas_oauth_json( $result );
}

/** POST /codecanvas-oauth/register (RFC 7591 dynamic client registration). */
function codecanvas_oauth_handle_register() {
    if ( 'POST' !== codecanvas_oauth_request_method() ) {
        codecanvas_oauth_json_error( 'invalid_request', 'Use POST for client registration.', 405 );
    }
    // Bound registration volume; the endpoint is public by design.
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    $key = 'cc_oauth_reg_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 20 );
    $hit = (int) get_transient( $key );
    if ( $hit >= 20 ) {
        codecanvas_oauth_json_error( 'too_many_requests', 'Too many client registrations from this address. Try again later.', 429 );
    }
    set_transient( $key, $hit + 1, HOUR_IN_SECONDS );

    $result = codecanvas_oauth_register_client( codecanvas_oauth_params() );
    if ( is_wp_error( $result ) ) {
        codecanvas_oauth_json_error( $result->get_error_code(), $result->get_error_message() );
    }
    codecanvas_oauth_json( $result, 201 );
}

/** POST /codecanvas-oauth/revoke (RFC 7009). */
function codecanvas_oauth_handle_revoke() {
    if ( 'POST' !== codecanvas_oauth_request_method() ) {
        codecanvas_oauth_json_error( 'invalid_request', 'Use POST for the revocation endpoint.', 405 );
    }
    $params = codecanvas_oauth_params();
    $token  = codecanvas_oauth_param( $params, 'token', 300 );
    codecanvas_oauth_revoke( $token );
    // RFC 7009: unknown tokens still answer 200 so clients cannot probe validity.
    codecanvas_oauth_json( array() );
}

/** Route the rewritten OAuth endpoints and metadata documents. */
function codecanvas_oauth_dispatch() {
    $route = get_query_var( 'codecanvas_oauth' );
    if ( ! is_string( $route ) || '' === $route ) { return; }
    switch ( $route ) {
        case 'resource-metadata':
            codecanvas_oauth_json( codecanvas_oauth_resource_metadata() );
            break;
        case 'server-metadata':
            codecanvas_oauth_json( codecanvas_oauth_server_metadata() );
            break;
        case 'authorize':
            codecanvas_oauth_handle_authorize();
            break;
        case 'token':
            codecanvas_oauth_handle_token();
            break;
        case 'register':
            codecanvas_oauth_handle_register();
            break;
        case 'revoke':
            codecanvas_oauth_handle_revoke();
            break;
        default:
            codecanvas_oauth_json( array( 'error' => 'not_found' ), 404 );
    }
    exit;
}
add_action( 'template_redirect', 'codecanvas_oauth_dispatch', 0 );

/** Register the metadata and endpoint rewrite rules. */
function codecanvas_oauth_rewrite_rules() {
    add_rewrite_rule( '^\.well-known/oauth-protected-resource/?$', 'index.php?codecanvas_oauth=resource-metadata', 'top' );
    add_rewrite_rule( '^\.well-known/oauth-authorization-server/?$', 'index.php?codecanvas_oauth=server-metadata', 'top' );
    add_rewrite_rule( '^codecanvas-oauth/(authorize|token|register|revoke)/?$', 'index.php?codecanvas_oauth=$matches[1]', 'top' );
}
add_action( 'init', 'codecanvas_oauth_rewrite_rules' );

/** Make the routing variable readable. */
function codecanvas_oauth_query_vars( $vars ) {
    $vars[] = 'codecanvas_oauth';
    return $vars;
}
add_filter( 'query_vars', 'codecanvas_oauth_query_vars' );

/**
 * Flush rewrite rules once per theme version.
 *
 * The metadata documents live at fixed root paths, so the rules must exist
 * before an AI client probes them. Flushed on the first request after an
 * update rather than on every request.
 */
function codecanvas_oauth_maybe_flush_rewrites() {
    $stamp  = 'codecanvas_oauth_rewrites';
    $stored = get_option( $stamp );
    if ( CODECANVAS_VERSION === $stored ) { return; }
    flush_rewrite_rules( false );
    update_option( $stamp, CODECANVAS_VERSION, false );
}
add_action( 'init', 'codecanvas_oauth_maybe_flush_rewrites', 99 );

/** Admin action: revoke every OAuth grant at once. */
function codecanvas_oauth_handle_revoke_all() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do that.', 'codecanvas' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'codecanvas_oauth_revoke_all' );
    codecanvas_oauth_revoke_all();
    wp_safe_redirect( add_query_arg( array( 'page' => 'codecanvas-settings', 'cc_mcp_notice' => 'oauth-revoked' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_codecanvas_oauth_revoke_all', 'codecanvas_oauth_handle_revoke_all' );
