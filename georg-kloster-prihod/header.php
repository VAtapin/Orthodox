<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#prihod-main"><?php esc_html_e( 'Перейти к содержимому', 'georg-kloster-prihod' ); ?></a>
<div class="wp-site-blocks">
<header><?php echo prihod_theme_header(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by renderer. ?></header>
