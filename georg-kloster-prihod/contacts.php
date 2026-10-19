<?php
/* Template Name: Contacts */
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<main id="prihod-main" class="prihod-container prihod-wide prihod-contacts-page">
<?php echo prihod_theme_heading(); ?>
<div class="wp-block-post-content"><?php echo prihod_theme_contacts_content( apply_filters( 'the_content', get_the_content() ) ); ?></div>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
