<?php
/* Template Name: Focused */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_focused_banner(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?>
<div class="prihod-inner-grid is-focused">
<article class="prihod-article wp-block-post-content">
<?php echo prihod_theme_focused_heading(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?>
<?php the_content(); ?>
</article>
<aside class="prihod-sidebar"><?php echo prihod_theme_sidebar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?></aside>
</div>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
