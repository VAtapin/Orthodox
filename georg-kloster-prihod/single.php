<?php get_header(); ?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_heading(); ?>
<div class="prihod-inner-grid">
<article <?php post_class( 'prihod-article wp-block-post-content' ); ?>>
<?php echo prihod_theme_article_meta(); ?>
<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large' ); } ?>
<?php the_content(); wp_link_pages(); ?>
<?php the_tags( '<p class="prihod-post-tags">', ', ', '</p>' ); ?>
<?php echo prihod_theme_article_return(); ?>
</article>
<aside class="prihod-sidebar"><?php echo prihod_theme_sidebar(); ?></aside>
</div>
<div class="prihod-container prihod-related-wrap"><?php echo prihod_theme_related_posts(); ?></div>
<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
