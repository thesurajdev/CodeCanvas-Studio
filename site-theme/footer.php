<?php
/** Shared footer and document close. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$codecanvas_layout = codecanvas_site_current_layout();
?>
<?php if ( ! codecanvas_site_has_custom_view_output() ) : ?></main><?php endif; ?>
<?php if ( 'blank' !== $codecanvas_layout ) : ?>
        <footer class="cc-site-footer">
            <div class="cc-container cc-footer-main">
                <div class="cc-footer-brand"><a class="cc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a><p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p></div>
                <nav aria-label="<?php esc_attr_e( 'Footer navigation', 'codecanvas' ); ?>"><?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'fallback_cb' => '__return_empty_string', 'menu_class' => 'cc-menu cc-footer-menu' ) ); ?></nav>
            </div>
            <div class="cc-container cc-footer-bottom">
                <p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>.</p>
                <p class="cc-attribution"><?php esc_html_e( 'Theme by', 'codecanvas' ); ?> <a href="https://surajdev.com/" target="_blank" rel="noopener noreferrer">Suraj Dev</a> <span aria-hidden="true">·</span> <?php esc_html_e( 'Credit to', 'codecanvas' ); ?> <a href="https://webot.surajdev.com/" target="_blank" rel="noopener noreferrer">Webot</a></p>
            </div>
        </footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
