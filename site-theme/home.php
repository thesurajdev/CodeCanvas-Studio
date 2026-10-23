<?php
/** Blog posts index. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) : ?>
<section class="cc-section cc-container"><header class="cc-page-heading"><p class="cc-eyebrow"><?php esc_html_e( 'The journal', 'codecanvas' ); ?></p><h1><?php echo esc_html( single_post_title( '', false ) ?: __( 'Latest articles', 'codecanvas' ) ); ?></h1></header>
<?php if ( have_posts() ) : ?><div class="cc-card-grid"><?php while ( have_posts() ) : the_post(); codecanvas_site_render_default_card( get_the_ID() ); endwhile; ?></div><?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?><?php else : ?><p><?php esc_html_e( 'No articles have been published yet.', 'codecanvas' ); ?></p><?php endif; ?></section>
<?php endif; get_footer();
