<?php
// Disposable fixture only: independent owner words and photo choices must survive.
$home_options = get_option( 'prihod_theme_options' ); $home_get = $_GET; $home_query = $GLOBALS['wp_query']; $home_post = $GLOBALS['post'];
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $first['pages']['home'] ) ); $GLOBALS['post'] = get_post( $first['pages']['home'] );
$options = prihod_theme_options(); $options['hero_quote_text'] = 'Owner hero quotation'; $options['hero_quote_source'] = 'Owner hero source'; $options['header_motto'] = 'Owner header motto'; $options['hero_motto'] = 'Owner decorative words'; $options['quote_text'] = 'Owner general quotation'; $options['quote_source'] = 'Owner general source'; update_option( 'prihod_theme_options', $options );
$_GET['prihod_preview'] = 'blue'; WP_Theme_JSON_Resolver::clean_cached_data(); $home = prihod_theme_home();
$check( str_contains( $home, 'Owner hero quotation' ) && strpos( $home, 'Owner hero quotation' ) > strpos( $home, 'class="prihod-actions"' ) && str_contains( $home, 'Owner hero source' ), 'Blue quotation below buttons uses its independent owner text/source.' );
$check( str_contains( $home, 'Owner general quotation' ) && str_contains( $home, 'Owner general source' ), 'Blue service panel keeps the separate existing general quotation.' );
$_GET['prihod_preview'] = 'editorial'; WP_Theme_JSON_Resolver::clean_cached_data(); $header = prihod_theme_header();
$check( str_contains( $header, 'prihod-header-quote' ) && str_contains( $header, 'Owner general quotation' ) && str_contains( $header, 'Owner general source' ) && str_contains( $header, 'tel:+491714340444' ) && str_contains( $header, 'mailto:info@georg-kloster.de' ), 'Editorial header gains the actual configured quote without removing contacts.' );
$_GET['prihod_preview'] = 'classic'; WP_Theme_JSON_Resolver::clean_cached_data();
$check( str_contains( prihod_theme_header(), 'Owner header motto' ) && str_contains( prihod_theme_home(), 'Owner decorative words' ), 'Classic motto blocks use owner-editable words.' );
$check( str_contains( prihod_theme_home(), 'prihod-feature-photo is-portrait' ) && str_contains( prihod_theme_home_gallery( array( $first['images']['clergy'] ), true ), 'is-portrait wp-image-' ), 'Known clergy photographs receive intact portrait treatment in features and galleries.' );
$_GET['prihod_preview'] = 'tradition'; WP_Theme_JSON_Resolver::clean_cached_data(); $home = prihod_theme_home();
$check( 2 === substr_count( $home, 'prihod-feature-symbol' ) && str_contains( $home, 'prihod-section-eyebrow' ), 'Traditional question/support tiles use native symbols and about gains its eyebrow.' );
$options['feature_requests'] = $first['images']['clergy']; $options['feature_donations'] = $first['images']['monastery']; update_option( 'prihod_theme_options', $options );
$check( ! str_contains( prihod_theme_home_features( 'tradition', array_values( $first['images'] ), 'About', true ), 'prihod-feature-symbol' ), 'Explicit owner photographs override traditional default symbols.' );
$options['hero_quote_text'] = ''; $options['header_motto'] = ''; $options['hero_motto'] = ''; update_option( 'prihod_theme_options', $options );
$_GET['prihod_preview'] = 'classic'; WP_Theme_JSON_Resolver::clean_cached_data();
$check( ! str_contains( prihod_theme_header(), 'prihod-header-motto' ) && ! str_contains( prihod_theme_home(), 'prihod-classic-motto' ) && '' === prihod_theme_verse( 'hero_quote_text', 'hero_quote_source' ), 'Empty owner words remove optional blocks rather than inventing text.' );
$sanitized = prihod_theme_sanitize( array( 'hero_quote_text' => '<script>bad</script>Safe', 'hero_quote_source' => '<b>Source</b>', 'hero_motto' => array( 'invalid' ) ) );
$check( ! str_contains( $sanitized['hero_quote_text'], '<script>' ) && 'Source' === $sanitized['hero_quote_source'] && '' === $sanitized['hero_motto'], 'New editable words reject markup and malformed values.' );
$sanitized = prihod_theme_sanitize( array( 'quote_text' => 'Partial old form' ) );
$check( $options['hero_quote_source'] === $sanitized['hero_quote_source'] && $options['header_motto'] === $sanitized['header_motto'], 'Partial older forms preserve absent independent words.' );
$options['quote_text'] = ''; update_option( 'prihod_theme_options', $options ); $_GET['prihod_preview'] = 'editorial'; WP_Theme_JSON_Resolver::clean_cached_data();
$check( ! str_contains( prihod_theme_header(), 'prihod-header-quote' ), 'Clearing the general quotation removes the editorial header quote wrapper.' );
update_option( 'prihod_theme_options', $home_options ); $_GET = $home_get; $GLOBALS['wp_query'] = $home_query; $GLOBALS['post'] = $home_post; WP_Theme_JSON_Resolver::clean_cached_data();
