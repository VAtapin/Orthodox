<?php get_header(); ?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main">
<?php echo prihod_theme_heading(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?>
<div class="prihod-inner-grid">
<article <?php post_class( 'prihod-article wp-block-post-content' ); ?>><?php the_content(); wp_link_pages(); ?></article>
<aside class="prihod-sidebar"><?php echo prihod_theme_sidebar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?></aside>
</div>
<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
