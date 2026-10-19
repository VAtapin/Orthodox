<?php
/* Template Name: Pilgrim */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_pilgrim_heading(); ?>
<div class="prihod-container prihod-wide prihod-pilgrim wp-block-post-content"><?php echo prihod_theme_pilgrim_content( apply_filters( 'the_content', get_the_content() ) ); ?></div>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
