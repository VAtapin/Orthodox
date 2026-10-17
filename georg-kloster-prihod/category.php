<?php
get_header();
$category_name = single_cat_title( '', false );
?>
<main id="prihod-main" class="prihod-category-archive">
<div class="prihod-page-heading"><div class="prihod-container">
<a class="prihod-breadcrumb" href="<?php echo esc_url( prihod_theme_preview_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Главная', 'georg-kloster-prihod' ); ?></a>
<h1><?php echo esc_html( $category_name ); ?></h1>
<?php if ( category_description() ) : ?><div class="prihod-category-description"><?php echo wp_kses_post( category_description() ); ?></div><?php endif; ?>
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
<a class="prihod-category-read-more" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Читать далее →', 'georg-kloster-prihod' ); ?></a>
</div></article>
<?php endwhile; ?>
</div>
<?php the_posts_pagination(); ?>
<?php else : ?>
<div class="prihod-category-empty" role="status">
<h2><?php esc_html_e( 'Публикаций пока нет.', 'georg-kloster-prihod' ); ?></h2>
<p><?php esc_html_e( 'В этой рубрике пока нет записей.', 'georg-kloster-prihod' ); ?></p>
<a class="prihod-button is-outline" href="<?php echo esc_url( prihod_theme_url( 'news' ) ?: prihod_theme_preview_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Новости', 'georg-kloster-prihod' ); ?> →</a>
</div>
<?php endif; ?>
</div>
</main>
<?php get_footer(); ?>
