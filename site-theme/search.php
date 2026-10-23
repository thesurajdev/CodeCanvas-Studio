<?php
/** Search results. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) : ?>
<section class="cc-section cc-container"><header class="cc-page-heading"><p class="cc-eyebrow"><?php esc_html_e( 'Search', 'codecanvas' ); ?></p><h1><?php
    /* translators: %s: search query. */
    printf( esc_html__( 'Results for: %s', 'codecanvas' ), esc_html( get_search_query() ) );
?></h1><?php get_search_form(); ?></header>
<?php if ( have_posts() ) : ?><div class="cc-card-grid"><?php while ( have_posts() ) : the_post(); codecanvas_site_render_default_card( get_the_ID() ); endwhile; ?></div><?php the_posts_pagination(); ?><?php else : ?><p><?php esc_html_e( 'No results found. Try another search.', 'codecanvas' ); ?></p><?php endif; ?></section>
<?php endif; get_footer();
