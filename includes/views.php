<?php
/**
 * Coded-page resolution and rendering.
 *
 * CodeCanvas renders exactly one thing on the front end: a Page saved through
 * the CodeCanvas editor. Its HTML lives in post_content, its CSS, JavaScript and
 * layout in post meta, and it is identified by the
 * _codecanvas_page_code_enabled flag. Every other request is left entirely to
 * the active theme's PHP templates, so there is no database layer that can
 * silently override a template file.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Code stored in the views option by versions before 2.0.
 *
 * Only kept to migrate pages authored before the editor moved page code into
 * post content and post meta. Nothing can write new entries here.
 */
function codecanvas_get_views() {
    $views = get_option( 'codecanvas_views', array() );
    return is_array( $views ) ? $views : array();
}

/** Return the Page ID when the current request is a CodeCanvas coded page. */
function codecanvas_coded_page_id() {
    if ( ! is_singular( 'page' ) ) { return 0; }
    $post_id = absint( get_queried_object_id() );
    if ( ! $post_id ) { return 0; }
    return '1' === (string) get_post_meta( $post_id, '_codecanvas_page_code_enabled', true ) ? $post_id : 0;
}

/**
 * The code that renders the current request, or an empty array.
 *
 * The post is the authoritative source: a coded page that has been saved with
 * empty HTML still resolves, so its blank canvas renders instead of falling
 * back to the theme's default page template.
 */
function codecanvas_get_active_view() {
    static $resolved = null;
    if ( null !== $resolved ) { return $resolved; }

    $post_id = codecanvas_coded_page_id();
    if ( ! $post_id ) {
        $resolved = array( '_key' => '' );
        return $resolved;
    }

    $page = get_post( $post_id );
    if ( ! $page || 'page' !== $page->post_type ) {
        $resolved = array( '_key' => '' );
        return $resolved;
    }

    $resolved = array(
        '_key'   => 'page_' . $post_id,
        'label'  => $page->post_title,
        'html'   => (string) $page->post_content,
        'css'    => (string) get_post_meta( $post_id, '_codecanvas_page_css', true ),
        'js'     => (string) get_post_meta( $post_id, '_codecanvas_page_js', true ),
        'layout' => 'blank' === get_post_meta( $post_id, '_codecanvas_page_layout', true ) ? 'blank' : 'global',
    );
    return $resolved;
}

/** Whether the current request is rendered from coded page HTML. */
function codecanvas_has_custom_view_output() {
    return 0 !== codecanvas_coded_page_id();
}

/** Replace one dynamic token with the value for the current request. */
function codecanvas_token_value( $name, $post = null ) {
    if ( $post ) {
        $post = get_post( $post );
    } elseif ( is_singular() ) {
        $post = get_post( get_queried_object_id() );
    } else {
        $post = null;
    }
    if ( 'site.name' === $name ) { return esc_html( get_bloginfo( 'name' ) ); }
    if ( 'site.description' === $name ) { return esc_html( get_bloginfo( 'description' ) ); }
    if ( 'site.url' === $name ) { return esc_url( home_url( '/' ) ); }
    if ( 'archive.title' === $name ) { return esc_html( wp_strip_all_tags( get_the_archive_title() ) ); }
    if ( 0 === strpos( $name, 'term.' ) ) {
        $term = get_queried_object();
        if ( ! $term || is_wp_error( $term ) || empty( $term->term_id ) ) { return ''; }
        if ( 'term.name' === $name ) { return esc_html( $term->name ); }
        if ( 'term.description' === $name ) { return wp_kses_post( term_description( $term->term_id ) ); }
        if ( 'term.link' === $name ) { $link = get_term_link( $term ); return is_wp_error( $link ) ? '' : esc_url( $link ); }
        if ( 'term.count' === $name ) { return absint( $term->count ); }
    }
    if ( ! $post ) { return ''; }
    if ( 'post.title' === $name ) { return esc_html( get_the_title( $post ) ); }
    if ( 'post.content' === $name ) { return apply_filters( 'the_content', $post->post_content ); } // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
    if ( 'post.excerpt' === $name ) { return esc_html( codecanvas_excerpt( $post, 35 ) ); }
    if ( 'post.url' === $name ) { return esc_url( get_permalink( $post ) ); }
    if ( 'post.featured_image' === $name ) { return codecanvas_post_thumbnail_markup( $post->ID, 'large', 'cc-dynamic-image' ); }
    if ( 'post.date' === $name ) { return esc_html( get_the_date( get_option( 'date_format' ), $post ) ); }
    if ( 'post.author' === $name ) { return esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); }
    if ( 0 === strpos( $name, 'post.meta:' ) ) { $meta_key = sanitize_key( substr( $name, 10 ) ); if ( '' === $meta_key || '_' === substr( $meta_key, 0, 1 ) ) { return ''; } $value = get_post_meta( $post->ID, $meta_key, true ); return is_scalar( $value ) ? esc_html( (string) $value ) : ''; }
    return '';
}

/** Replace one repeater token inside a {{#items}} block. */
function codecanvas_item_token_value( $name, $post ) {
    $post = get_post( $post );
    if ( ! $post ) { return ''; }
    $map = array(
        'item.title' => esc_html( get_the_title( $post ) ),
        'item.url' => esc_url( get_permalink( $post ) ),
        'item.excerpt' => esc_html( codecanvas_excerpt( $post, 24 ) ),
        'item.content' => apply_filters( 'the_content', $post->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
        'item.featured_image' => codecanvas_post_thumbnail_markup( $post->ID, 'medium_large', 'cc-card-image' ),
        'item.date' => esc_html( get_the_date( get_option( 'date_format' ), $post ) ),
        'item.author' => esc_html( get_the_author_meta( 'display_name', $post->post_author ) ),
    );
    if ( isset( $map[ $name ] ) ) { return $map[ $name ]; }
    if ( 0 === strpos( $name, 'item.meta:' ) ) { $meta_key = sanitize_key( substr( $name, 10 ) ); if ( '' === $meta_key || '_' === substr( $meta_key, 0, 1 ) ) { return ''; } $value = get_post_meta( $post->ID, $meta_key, true ); return is_scalar( $value ) ? esc_html( (string) $value ) : ''; }
    if ( 'item.terms' === $name ) {
        $taxonomies = get_object_taxonomies( $post->post_type, 'names' );
        $names = array();
        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_the_terms( $post, $taxonomy );
            if ( is_array( $terms ) ) { foreach ( $terms as $term ) { $names[] = $term->name; } }
        }
        return esc_html( implode( ', ', array_unique( $names ) ) );
    }
    return '';
}

/** Render coded page HTML through the small, non-evaluating token language. */
function codecanvas_render_template_markup( $html ) {
    global $wp_query;
    if ( ! is_string( $html ) || '' === trim( $html ) ) { return ''; }

    // Repeater blocks: {{#items}}...{{/items}} or {{#items:project}}...{{/items:project}}.
    // This is a small, non-evaluating template language; it never evaluates PHP.
    $html = preg_replace_callback( '/\{\{#items(?::([a-z0-9_-]+))?\}\}(.*?)\{\{\/items(?::[a-z0-9_-]+)?\}\}/s', function ( $matches ) use ( $wp_query ) {
        $post_type = ! empty( $matches[1] ) ? sanitize_key( $matches[1] ) : '';
        $inner = $matches[2];
        if ( $post_type && post_type_exists( $post_type ) ) {
            $post_type_object = get_post_type_object( $post_type );
            $posts = ( $post_type_object && $post_type_object->public && $post_type_object->publicly_queryable ) ? get_posts( array( 'numberposts' => 12, 'post_status' => 'publish', 'post_type' => $post_type, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
        } elseif ( ! $post_type && ( is_archive() || is_home() || is_search() ) && $wp_query instanceof WP_Query && ! empty( $wp_query->posts ) ) {
            $posts = $wp_query->posts;
        } else {
            $posts = get_posts( array( 'numberposts' => 6, 'post_status' => 'publish', 'post_type' => 'post', 'orderby' => 'date', 'order' => 'DESC' ) );
        }
        $output = '';
        foreach ( $posts as $item ) {
            $output .= preg_replace_callback( '/\{\{\s*(item\.[a-z_]+(?::[a-zA-Z0-9_-]+)?)\s*\}\}/', function ( $token ) use ( $item ) {
                return codecanvas_item_token_value( $token[1], $item );
            }, $inner );
        }
        return $output;
    }, $html );

    $html = preg_replace_callback( '/\{\{\s*(site\.(?:name|description|url)|post\.(?:title|content|excerpt|url|featured_image|date|author|meta:[a-zA-Z0-9_-]+)|term\.(?:name|description|link|count)|archive\.title)\s*\}\}/', function ( $matches ) {
        return codecanvas_token_value( $matches[1] );
    }, $html );

    return $html;
}

/** Print the coded page body. Returns false when this is an ordinary request. */
function codecanvas_render_active_view() {
    if ( ! codecanvas_coded_page_id() ) { return false; }
    $view = codecanvas_get_active_view();
    echo codecanvas_render_template_markup( isset( $view['html'] ) ? $view['html'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted administrator-authored page code is the intended feature.
    return true;
}

/**
 * Render a coded page through the plugin's bundled template.
 *
 * The plugin's page.php renders the stored HTML. A theme that ships its own
 * page-{slug}.php would otherwise win the template hierarchy and hide the coded
 * page, so the coded page is routed to the plugin scaffold explicitly.
 */
function codecanvas_prefer_coded_page_template( $template ) {
    if ( ! codecanvas_has_custom_view_output() || ! is_page() ) {
        return $template;
    }

    $theme_dir = trailingslashit( CODECANVAS_DIR ) . 'site-theme/';

    // A coded page that is also the static front page must not fall through to
    // a theme front-page.php that knows nothing about stored page code.
    if ( is_front_page() ) {
        $front_template = $theme_dir . 'front-page.php';
        return is_readable( $front_template ) ? $front_template : $template;
    }

    if ( is_page_template( 'templates/blank-canvas.php' ) ) {
        $blank_template = $theme_dir . 'templates/blank-canvas.php';
        return is_readable( $blank_template ) ? $blank_template : $template;
    }

    $page_template = $theme_dir . 'page.php';
    return is_readable( $page_template ) ? $page_template : $template;
}
add_filter( 'template_include', 'codecanvas_prefer_coded_page_template', 99 );

function codecanvas_render_default_card( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) { return; }
    echo '<article class="cc-card">';
    echo '<a class="cc-card-media" href="' . esc_url( get_permalink( $post_id ) ) . '">' . codecanvas_post_thumbnail_markup( $post_id, 'large' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup built by core image functions.
    echo '<div class="cc-card-body"><p class="cc-card-meta">' . esc_html( get_the_date( '', $post_id ) ) . '</p>';
    echo '<h2><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h2>';
    echo '<p>' . esc_html( codecanvas_excerpt( $post_id, 24 ) ) . '</p></div></article>';
}

/*
 * Theme-facing render wrappers.
 *
 * The generated website theme calls these prefixed functions so its templates
 * never collide with the plugin's implementation and still render (with safe
 * fallbacks) if CodeCanvas is deactivated.
 */
if ( ! function_exists( 'codecanvas_site_render_view' ) ) {
    function codecanvas_site_has_custom_view_output() {
        return codecanvas_has_custom_view_output();
    }

    function codecanvas_site_render_view() {
        return codecanvas_render_active_view();
    }

    function codecanvas_site_render_markup( $html ) {
        return codecanvas_render_template_markup( $html );
    }

    function codecanvas_site_render_default_card( $post_id ) {
        codecanvas_render_default_card( $post_id );
    }

    /*
     * Alias kept for themes generated before 2.0, whose archive, index, home and
     * search templates call the shorter name. Without it those listing pages
     * fatal until the theme is regenerated.
     */
    function codecanvas_site_default_card( $post_id ) {
        codecanvas_render_default_card( $post_id );
    }

    function codecanvas_site_current_layout() {
        return codecanvas_current_layout();
    }

    /*
     * Kept for already-generated themes that still ask for database-saved header
     * and footer markup. Code now lives in the theme files, so both return an
     * empty string and the template's own markup renders.
     */
    function codecanvas_site_header_markup() {
        return '';
    }

    function codecanvas_site_footer_markup() {
        return '';
    }

    function codecanvas_site_default_brand() {
        codecanvas_default_brand();
    }

    function codecanvas_site_nav_fallback() {
        codecanvas_nav_fallback();
    }
}
