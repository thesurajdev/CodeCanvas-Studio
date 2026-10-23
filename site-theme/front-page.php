<?php
/** Front page. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
if ( ! codecanvas_site_render_view() ) :
    if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <article <?php post_class( 'cc-container cc-section cc-single' ); ?>>
            <?php if ( has_post_thumbnail() ) : ?><div class="cc-single-cover"><?php the_post_thumbnail( 'full' ); ?></div><?php endif; ?>
            <div class="cc-prose"><?php the_content(); ?></div>
        </article>
    <?php endwhile; else : ?>
        <section class="cc-hero"><div class="cc-container cc-hero-inner"><p class="cc-eyebrow">A canvas for your ideas</p><h1><?php esc_html_e( 'Your WordPress. Your code.', 'codecanvas' ); ?></h1><p><?php esc_html_e( 'Build a site that feels like yours. Keep the foundation lightweight, the markup intentional and the possibilities open.', 'codecanvas' ); ?></p><a class="cc-button" href="<?php echo esc_url( admin_url( 'admin.php?page=codecanvas-studio' ) ); ?>"><?php esc_html_e( 'Open Code Studio', 'codecanvas' ); ?></a></div></section>
        <section class="cc-section cc-container"><div class="cc-section-intro"><p class="cc-eyebrow">Made for makers</p><h2><?php esc_html_e( 'A clean foundation. Complete freedom.', 'codecanvas' ); ?></h2><p><?php esc_html_e( 'Use WordPress for content management and your own code for the design.', 'codecanvas' ); ?></p></div><div class="cc-feature-grid"><article class="cc-feature"><span class="cc-feature-number">01</span><h3><?php esc_html_e( 'Code-first pages', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Create layouts using HTML, CSS and JavaScript without a required page builder.', 'codecanvas' ); ?></p></article><article class="cc-feature"><span class="cc-feature-number">02</span><h3><?php esc_html_e( 'Your content system', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Organize projects, galleries and other custom content types from WordPress Admin.', 'codecanvas' ); ?></p></article><article class="cc-feature"><span class="cc-feature-number">03</span><h3><?php esc_html_e( 'Shared or standalone', 'codecanvas' ); ?></h3><p><?php esc_html_e( 'Choose a shared site layout or a blank canvas for a fully custom page.', 'codecanvas' ); ?></p></article></div></section>
    <?php endif;
endif;
get_footer();
