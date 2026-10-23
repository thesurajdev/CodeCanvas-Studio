<?php
/** Generic fallback template. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) :
?>
<section class="cc-section cc-container">
    <header class="cc-page-heading"><p class="cc-eyebrow"><?php esc_html_e( 'CodeCanvas', 'codecanvas' ); ?></p><h1><?php esc_html_e( 'Latest content', 'codecanvas' ); ?></h1></header>
    <?php if ( have_posts() ) : ?><div class="cc-card-grid"><?php while ( have_posts() ) : the_post(); codecanvas_site_render_default_card( get_the_ID() ); endwhile; ?></div><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => __( 'Previous', 'codecanvas' ), 'next_text' => __( 'Next', 'codecanvas' ) ) ); ?><?php else : ?><p><?php esc_html_e( 'Nothing published here yet.', 'codecanvas' ); ?></p><?php endif; ?>
</section>
<?php endif; get_footer();
