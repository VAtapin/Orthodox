<?php
/* Template Name: About */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_heading(); ?>
<?php echo prihod_theme_about_navigation(); ?>
<div class="prihod-inner-grid">
<article class="prihod-article wp-block-post-content"><?php echo prihod_theme_about_content( apply_filters( 'the_content', get_the_content() ) ); ?></article>
<aside class="prihod-sidebar"><?php echo prihod_theme_sidebar(); ?></aside>
</div>
<?php echo prihod_theme_about_invitation(); ?>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
