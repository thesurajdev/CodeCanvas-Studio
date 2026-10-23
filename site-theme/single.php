<?php
/** Singular content fallback (posts and custom post types). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) : while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'cc-container cc-section cc-single' ); ?>><header class="cc-page-heading"><p class="cc-eyebrow"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? __( 'Article', 'codecanvas' ) ); ?></p><h1><?php the_title(); ?></h1><p class="cc-post-meta"><?php echo esc_html( get_the_date() ); ?><?php if ( get_the_author() ) : ?> <span aria-hidden="true">·</span> <?php the_author(); ?><?php endif; ?></p></header><?php if ( has_post_thumbnail() ) : ?><div class="cc-single-cover"><?php the_post_thumbnail( 'full' ); ?></div><?php endif; ?><div class="cc-prose"><?php the_content(); wp_link_pages(); ?></div></article>
<?php endwhile; endif; get_footer();
