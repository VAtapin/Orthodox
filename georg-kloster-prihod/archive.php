<?php
get_header();
$queried = get_queried_object();
$archive_title = $queried instanceof WP_Term ? $queried->name : wp_strip_all_tags( get_the_archive_title() );
$archive_description = get_the_archive_description();
?>
<main id="prihod-main" class="prihod-category-archive">
<div class="prihod-page-heading"><div class="prihod-container">
<a class="prihod-breadcrumb" href="<?php echo esc_url( prihod_theme_preview_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Home', 'georg-kloster-prihod' ); ?></a>
<h1><?php echo esc_html( $archive_title ); ?></h1>
<?php if ( $archive_description ) : ?><div class="prihod-category-description"><?php echo wp_kses_post( $archive_description ); ?></div><?php endif; ?>
</div></div>
<div class="prihod-container prihod-category-content">
<?php if ( have_posts() ) : ?>
<div class="prihod-category-grid">
<?php while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'prihod-category-card' ); ?>>
<?php if ( has_post_thumbnail() ) : ?><a class="prihod-category-card-image" href="<?php echo esc_url( get_permalink() ); ?>"><?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?></a><?php endif; ?>
<div class="prihod-category-card-copy">
<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
<h2><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></h2>
<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
<a class="prihod-category-read-more" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Read more →', 'georg-kloster-prihod' ); ?></a>
</div></article>
<?php endwhile; ?>
</div>
<?php the_posts_pagination(); ?>
<?php else : ?>
<div class="prihod-category-empty" role="status">
<h2><?php esc_html_e( 'There are no posts yet.', 'georg-kloster-prihod' ); ?></h2>
<?php if ( is_category() ) : ?><p><?php esc_html_e( 'There are no posts in this category yet.', 'georg-kloster-prihod' ); ?></p><?php endif; ?>
<a class="prihod-button is-outline" href="<?php echo esc_url( prihod_theme_url( 'news' ) ?: prihod_theme_preview_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'News', 'georg-kloster-prihod' ); ?> →</a>
</div>
<?php endif; ?>
</div>
</main>
<?php get_footer(); ?>
