<?php
/* Template Name: Wide */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_heading(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?>
<div class="prihod-container prihod-wide wp-block-post-content"><?php the_content(); ?></div>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
