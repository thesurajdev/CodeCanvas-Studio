<?php
/** Comments template. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( post_password_required() ) { return; }
if ( have_comments() ) : ?>
<section class="cc-comments" id="comments"><h2><?php comments_number( esc_html__( 'No comments', 'codecanvas' ), esc_html__( 'One comment', 'codecanvas' ), esc_html__( '% comments', 'codecanvas' ) ); ?></h2><ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true ) ); ?></ol><?php the_comments_navigation(); ?></section>
<?php endif; if ( comments_open() ) { comment_form(); }
