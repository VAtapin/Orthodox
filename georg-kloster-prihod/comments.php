<?php
defined( 'ABSPATH' ) || exit;
if ( post_password_required() ) { return; }
?>
<section id="comments" class="prihod-container prihod-comments">
<?php if ( have_comments() ) : ?>
<h2><?php echo esc_html( get_comments_number_text() ); ?></h2>
<ol class="comment-list"><?php wp_list_comments( array( 'avatar_size' => 48, 'style' => 'ol' ) ); ?></ol>
<?php the_comments_pagination(); ?>
<?php endif; ?>
<?php if ( comments_open() ) { comment_form(); } ?>
</section>
