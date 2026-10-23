<?php
/** Static pages. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) :
    while ( have_posts() ) : the_post(); ?>
        <article <?php post_class( 'cc-container cc-section cc-single' ); ?>>
            <header class="cc-page-heading"><h1><?php the_title(); ?></h1></header>
            <?php if ( has_post_thumbnail() ) : ?><div class="cc-single-cover"><?php the_post_thumbnail( 'full' ); ?></div><?php endif; ?>
            <div class="cc-prose"><?php the_content(); wp_link_pages(); ?></div>
        </article>
    <?php endwhile;
endif;
get_footer();
