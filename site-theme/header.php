<?php
/** Document head and shared site header. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$codecanvas_layout = codecanvas_site_current_layout();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="cc-skip-link" href="#main-content"><?php esc_html_e( 'Skip to content', 'codecanvas' ); ?></a>
<?php if ( 'blank' !== $codecanvas_layout ) : ?>
        <header class="cc-site-header">
            <div class="cc-container cc-header-inner">
                <div class="cc-brand-area"><?php codecanvas_site_default_brand(); ?></div>
                <nav id="cc-mobile-menu" class="cc-primary-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'codecanvas' ); ?>">
                    <?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'fallback_cb' => 'codecanvas_nav_fallback', 'menu_class' => 'cc-menu' ) ); ?>
                </nav>
                <button class="cc-menu-toggle" type="button" aria-expanded="false" aria-controls="cc-mobile-menu"><span class="screen-reader-text"><?php esc_html_e( 'Toggle menu', 'codecanvas' ); ?></span><span></span><span></span><span></span></button>
            </div>
        </header>
<?php endif; ?>
<?php if ( ! codecanvas_site_has_custom_view_output() ) : ?><main id="main-content" class="cc-main"><?php else : ?><span id="main-content" class="cc-main-anchor" tabindex="-1"></span><?php endif; ?>
