<?php
/* Template Name: Focused */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_focused_banner(); ?>
<div class="prihod-inner-grid is-focused">
<article class="prihod-article wp-block-post-content">
<?php echo prihod_theme_focused_heading(); ?>
<?php the_content(); ?>
</article>
<aside class="prihod-sidebar"><?php echo prihod_theme_sidebar(); ?></aside>
</div>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
