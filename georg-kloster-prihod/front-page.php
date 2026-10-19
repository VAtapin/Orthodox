<?php
if ( 'posts' === get_option( 'show_on_front' ) ) {
    get_template_part( 'index' );
    return;
}
get_header();
?>
<main id="prihod-main">
<?php
if ( have_posts() ) {
    the_post();
}
echo prihod_theme_home();
?>
</main>
<?php get_footer(); ?>
