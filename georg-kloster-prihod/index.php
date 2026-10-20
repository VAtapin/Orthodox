<?php get_header(); ?>
<main id="prihod-main" class="prihod-container prihod-wide">
<?php
$posts_page_id = absint( get_option( 'page_for_posts' ) );
if ( $posts_page_id ) {
    $posts_page_id = absint( apply_filters( 'wpml_object_id', $posts_page_id, 'page', true ) );
    if ( function_exists( 'pll_get_post' ) ) { $posts_page_id = absint( pll_get_post( $posts_page_id ) ?: $posts_page_id ); }
}
$archive_title = is_home() && ! is_front_page()
    ? ( $posts_page_id ? get_the_title( $posts_page_id ) : prihod_theme_home_title( 'news', __( 'News', 'georg-kloster-prihod' ) ) )
	: get_the_archive_title();
?>
<h1><?php echo esc_html( $archive_title ?: get_bloginfo( 'name' ) ); ?></h1>
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
