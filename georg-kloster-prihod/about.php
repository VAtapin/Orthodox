<?php
/* Template Name: About */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_heading(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?>
<?php echo prihod_theme_about_navigation(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?>
<div class="prihod-inner-grid">
<article class="prihod-article wp-block-post-content"><?php echo prihod_theme_about_content( apply_filters( 'the_content', get_the_content() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?></article>
<aside class="prihod-sidebar"><?php echo prihod_theme_sidebar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?></aside>
</div>
<?php echo prihod_theme_about_invitation(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
