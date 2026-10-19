<?php
/* Template Name: Catalog */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main" class="prihod-container prihod-catalog prihod-wide">
<article class="prihod-article wp-block-post-content">
<?php echo prihod_theme_heading(); ?>
<?php the_content(); ?>
</article>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
