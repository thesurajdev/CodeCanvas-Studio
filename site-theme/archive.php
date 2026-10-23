<?php
/** Archive fallback. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) : ?>
<section class="cc-section cc-container"><header class="cc-page-heading"><p class="cc-eyebrow"><?php esc_html_e( 'Explore', 'codecanvas' ); ?></p><?php the_archive_title( '<h1>', '</h1>' ); ?><?php the_archive_description( '<div class="cc-archive-description">', '</div>' ); ?></header>
<?php if ( have_posts() ) : ?><div class="cc-card-grid"><?php while ( have_posts() ) : the_post(); codecanvas_site_render_default_card( get_the_ID() ); endwhile; ?></div><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => __( 'Previous', 'codecanvas' ), 'next_text' => __( 'Next', 'codecanvas' ) ) ); ?><?php else : ?><p><?php esc_html_e( 'No content found.', 'codecanvas' ); ?></p><?php endif; ?></section>
<?php endif; get_footer();
