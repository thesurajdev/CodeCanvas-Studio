<?php
/** Not found. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) : ?>
<section class="cc-container cc-section cc-error-page"><p class="cc-eyebrow">404</p><h1><?php esc_html_e( 'This page took a different path.', 'codecanvas' ); ?></h1><p><?php esc_html_e( 'The page may have moved, or the link might be out of date.', 'codecanvas' ); ?></p><a class="cc-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'codecanvas' ); ?></a></section>
<?php endif; get_footer();
