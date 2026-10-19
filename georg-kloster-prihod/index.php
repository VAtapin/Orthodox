<?php get_header(); ?>
<main id="prihod-main" class="prihod-container prihod-wide">
<h1><?php echo esc_html( get_the_archive_title() ?: get_bloginfo( 'name' ) ); ?></h1>
<div class="prihod-catalog-grid">
<?php while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'prihod-news-card' ); ?>>
<?php echo prihod_theme_post_photo( get_post(), 'medium_large', get_permalink() ); ?>
<a class="prihod-news-copy" href="<?php the_permalink(); ?>"><time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><h2><?php the_title(); ?></h2><p><?php echo esc_html( get_the_excerpt() ); ?></p></a>
</article>
<?php endwhile; ?>
</div>
<?php the_posts_pagination(); ?>
</main>
<?php get_footer(); ?>
