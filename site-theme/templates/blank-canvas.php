<?php
/**
 * Template Name: CodeCanvas Blank Canvas
 * Template Post Type: page
 * Description: A clean full-width canvas without the shared theme header/footer. Use the WordPress editor or a CodeCanvas page view for your custom HTML/CSS/JS design.
 *
 * @package CodeCanvas
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) :
    while ( have_posts() ) : the_post(); ?>
        <article <?php post_class( 'cc-blank-content' ); ?>><?php the_content(); wp_link_pages(); ?></article>
    <?php endwhile;
endif;
get_footer();
