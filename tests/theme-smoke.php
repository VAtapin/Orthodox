<?php
$theme = wp_get_theme( 'prihod-theme' );
prihod_assert( $theme->exists() && ! $theme->errors() && $theme->is_block_theme(), 'Optional theme is recognized as a valid block theme.' );
$old_theme = get_stylesheet();
switch_theme( 'prihod-theme' );
prihod_assert( wp_theme_has_theme_json(), 'Theme provides structured global styles.' );
$template = get_block_template( 'prihod-theme//page', 'wp_template' );
prihod_assert( $template && str_contains( $template->content, 'wp:post-content' ), 'Page template renders standard WordPress content.' );
prihod_assert( 'Parish' === Prihod\Profile::get()['name'], 'Changing theme does not migrate or replace parish data.' );
$json = WP_Theme_JSON_Resolver::get_theme_data()->get_raw_data();
$palette = $json['settings']['color']['palette'];
prihod_assert( count( $palette['theme'] ?? $palette ) >= 5, 'Theme palette is available to the editor.' );
// Restore baseline for unrelated tests. Standalone theme verification uses a separate request.
switch_theme( $old_theme );
