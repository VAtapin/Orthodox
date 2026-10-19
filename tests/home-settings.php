<?php
/** Disposable Playground database only. Never run this fixture on a real site. */
if ( ! is_file( '/wordpress/wp-load.php' ) ) { throw new RuntimeException( 'Requires disposable WordPress Playground.' ); }
register_shutdown_function( static function () { $error = error_get_last(); if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE ), true ) ) { file_put_contents( __DIR__ . '/.home-settings-result.json', json_encode( $error ) ); } } );
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
add_filter( 'pre_http_request', static fn() => new WP_Error( 'fixture', 'No external requests in this fixture.' ) );
$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { file_put_contents( __DIR__ . '/.home-settings-result.json', json_encode( array( 'failed' => $message, 'checks' => $checks ) ) ); throw new RuntimeException( $message ); } };
$check( ! class_exists( '\GeorgKlosterPrihodCore\Profile' ) && ! class_exists( '\Prihod\Profile' ), 'Standalone phase must not load Core.' );
$home_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Fixture home', 'post_content' => '<!-- wp:paragraph --><p>Owner page content</p><!-- /wp:paragraph -->' ) );
$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Linked page', 'post_content' => 'Keep this page untouched' ) );
$draft_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Draft page' ) );
$category = wp_insert_term( 'Fixture news', 'category' )['term_id'];
$other_category = wp_insert_term( 'Other fixture news', 'category' )['term_id'];
foreach ( range( 1, 5 ) as $index ) {
    wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Fixture news ' . $index, 'post_content' => 'One two three four five six seven eight nine ten.', 'post_date' => '2020-01-0' . $index . ' 10:00:00', 'post_category' => array( $category ) ) );
}
wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Private fixture news', 'post_category' => array( $category ) ) );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $home_id );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $home_id ) ); $GLOBALS['wp_query']->the_post();
$set = static function ( array $home, string $scheme = 'classic' ): void {
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $scheme, 'home' => prihod_theme_home_sanitize( $home, $scheme ) ) );
    WP_Theme_JSON_Resolver::clean_cached_data();
};
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $scheme ) ); WP_Theme_JSON_Resolver::clean_cached_data();
    $html = prihod_theme_home();
    $check( str_contains( $html, 'prihod-home-hero' ) && str_contains( $html, 'Owner page content' ), 'Default layout/content disappeared: ' . $scheme );
    $check( ! str_contains( $html, 'prihod-hero-service' ) && ! str_contains( $html, 'prihod-home-contacts' ), 'Unavailable plugin data creates empty panels: ' . $scheme );
    $check( ( 'editorial' !== $scheme ) === str_contains( $html, 'prihod-about-section' ), 'Default scheme composition changed: ' . $scheme );
}
$bad = prihod_theme_home_sanitize( array( 'order' => array( 'news', 'news', '../../invalid', array(), 'hero' ), 'news_count' => 900, 'services_count' => array(), 'news_columns' => -4, 'news_category' => 9999999, 'pages' => array( 'about' => $draft_id, 'news' => $page_id ), 'sections' => array( 'hero' => array( 'title' => '<script>bad</script>Heading' ) ), 'unknown' => 'discard' ), 'classic' );
$check( $bad['order'][0] === 'news' && $bad['order'][1] === 'hero' && count( $bad['order'] ) === 8, 'Order must be unique, bounded and include missing sections.' );
$check( $bad['news_count'] === 12 && $bad['services_count'] === 0 && $bad['news_columns'] === 0 && $bad['news_category'] === 0, 'Bounds or malformed option handling failed.' );
$check( $bad['pages']['about'] === 0 && $bad['pages']['news'] === $page_id && ! isset( $bad['unknown'] ), 'Only published linked pages and known fields are accepted.' );
$check( ! str_contains( $bad['sections']['hero']['title'], '<' ), 'Heading accepts executable markup.' );
$config = array( 'order' => array( 'news', 'features', 'hero', 'content' ), 'pages' => array( 'news' => $page_id, 'donations' => $page_id, 'about' => $page_id ), 'card_order' => array( 'donations', 'news' ), 'cards' => array( 'donations' => array( 'title' => 'Owner donation card' ), 'news' => array( 'title' => 'Owner news card' ) ), 'sections' => array( 'hero' => array( 'title' => 'Owner hero heading' ), 'about' => array( 'enabled' => false ), 'services' => array( 'enabled' => false ), 'topics' => array( 'enabled' => false ), 'contacts' => array( 'enabled' => false ), 'news' => array( 'title' => 'Owner news heading' ) ), 'news_category' => $category, 'news_count' => 2, 'news_columns' => 2, 'news_show_date' => false, 'news_show_excerpt' => false, 'news_show_images' => false );
global $wpdb;
$post_snapshot = hash( 'sha256', serialize( $wpdb->get_results( "SELECT ID,post_content,post_title,post_status FROM {$wpdb->posts} ORDER BY ID", ARRAY_A ) ) );
$set( $config ); $html = prihod_theme_home();
$check( strpos( $html, 'Owner news heading' ) < strpos( $html, 'Owner donation card' ) && strpos( $html, 'Owner donation card' ) < strpos( $html, 'Owner hero heading' ), 'Saved section order does not control the page.' );
$check( strpos( $html, 'Owner donation card' ) < strpos( $html, 'Owner news card' ), 'Card order was ignored.' );
$check( substr_count( $html, '<article class="prihod-news-card' ) === 2 && ! str_contains( $html, 'Private fixture news' ), 'News limit or publication status is ignored.' );
$check( ! str_contains( $html, '<time' ) && ! str_contains( $html, 'One two three' ) && str_contains( $html, 'data-home-columns="2"' ), 'News appearance options were ignored.' );
$check( str_contains( $html, get_permalink( $page_id ) ) && str_contains( $html, 'Owner donation card' ), 'Linked cards need a companion plugin.' );
$check( ! str_contains( $html, 'prihod-about-section' ) && str_contains( $html, 'Owner page content' ), 'Section visibility hides unrelated owner content.' );
$check( $post_snapshot === hash( 'sha256', serialize( $wpdb->get_results( "SELECT ID,post_content,post_title,post_status FROM {$wpdb->posts} ORDER BY ID", ARRAY_A ) ) ), 'Saving homepage settings rewrote page/post content.' );
$partial = prihod_theme_sanitize( array( 'width' => 1300 ) );
$check( $partial['home'] === get_option( 'georg_kloster_prihod_options' )['home'], 'Saving old/partial theme forms discards home settings.' );
$config['news_category'] = $other_category; $set( $config ); $html = prihod_theme_home_news( 'classic', false );
$check( ! str_contains( $html, 'Fixture news 1' ) && str_contains( $html, __( 'There are no posts yet.', 'georg-kloster-prihod' ) ), 'An explicitly selected empty category silently falls back to unrelated news.' );
$config['news_category'] = $category; $config['news_show_excerpt'] = true; $config['news_excerpt_words'] = 3; $set( $config );
$check( str_contains( prihod_theme_home_news( 'classic', false ), 'One two three' ) && ! str_contains( prihod_theme_home_news( 'classic', false ), 'four five' ), 'Excerpt length was ignored.' );
$config['sections']['content']['enabled'] = false; $set( $config );
$check( ! str_contains( prihod_theme_home(), 'Owner page content' ) && get_post( $home_id )->post_content === '<!-- wp:paragraph --><p>Owner page content</p><!-- /wp:paragraph -->', 'Disabling page content edits or still renders it.' );
wp_set_current_user( 1 ); ob_start(); prihod_theme_settings_page(); $panel = ob_get_clean();
$check( str_contains( $panel, 'prihod-option-home' ) && str_contains( $panel, 'name="_wpnonce"' ) && substr_count( $panel, '[home][order][]' ) === 8, 'Protected native theme settings panel is incomplete.' );
$translation = static fn( $value, $context, $name ) => 'georg-kloster-prihod' === $context && 'home-hero-title' === $name ? 'Translated owner heading' : $value;
add_filter( 'wpml_translate_single_string', $translation, 10, 3 );
$check( str_contains( prihod_theme_home(), 'Translated owner heading' ), 'Custom headings do not use WPML string translation.' );
remove_filter( 'wpml_translate_single_string', $translation, 10 );

// All site presentation data must also work with no active plugins.
$check( get_option( 'active_plugins', array() ) === array(), 'Standalone phase unexpectedly loads a plugin.' );
$set( array( 'pages' => array( 'contacts' => $page_id, 'about' => $page_id, 'requests' => $page_id, 'donations' => $page_id, 'schedule' => $page_id ) ) );
$check( prihod_theme_resource_id( 'contacts' ) === $page_id && prihod_theme_home_resource_id( 'contacts' ) === $page_id, 'Theme page links are restricted to the homepage.' );
$check( prihod_theme_resource_id( 'home' ) === $home_id, 'Standalone footer loses the native WordPress homepage.' );
$check( str_contains( prihod_theme_footer(), get_permalink( $page_id ) ) && str_contains( prihod_theme_sidebar(), get_permalink( $page_id ) ), 'Standalone footer/sidebar cannot use selected pages.' );
$own_profile = array( 'name' => 'Owner community', 'short_name' => 'Owner short name', 'description' => 'Owner description', 'type' => 'monastery', 'address' => "Owner street\nOwner town", 'phone' => '+49 123 456', 'email' => 'owner@example.org' );
$options = prihod_theme_sanitize( array( 'profile' => $own_profile, 'header_contacts' => true ) ); update_option( 'georg_kloster_prihod_options', $options );
$check( prihod_theme_profile() == $own_profile, 'Theme profile requires Core or loses owner fields.' );
$check( str_contains( prihod_theme_header(), 'tel:+49123456' ) && str_contains( prihod_theme_header(), 'mailto:owner@example.org' ) && str_contains( prihod_theme_brand(), 'Owner short name' ), 'Standalone header ignores theme contacts/name.' );
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    $options['scheme'] = $scheme; update_option( 'georg_kloster_prihod_options', $options ); WP_Theme_JSON_Resolver::clean_cached_data();
    $html = prihod_theme_home();
    $check( str_contains( $html, 'Owner street' ) && str_contains( $html, 'mailto:owner@example.org' ) && str_contains( $html, 'Owner description' ), 'Standalone data missing in scheme: ' . $scheme );
    $check( ! str_contains( $html, 'prihod-hero-service' ) && str_contains( $html, get_permalink( $page_id ) ), 'Optional Schedule hides ordinary page links or creates an empty panel: ' . $scheme );
}
$invalid_profile = prihod_theme_profile_sanitize( array( 'name' => '<b>Safe name</b>', 'phone' => array(), 'email' => 'invalid', 'address' => '<script>x</script>Street', 'type' => '../../invalid', 'unknown' => 'discard' ) );
$check( $invalid_profile['name'] === 'Safe name' && $invalid_profile['email'] === '' && $invalid_profile['phone'] === '' && $invalid_profile['type'] === '' && ! isset( $invalid_profile['unknown'] ) && ! str_contains( $invalid_profile['address'], '<' ), 'Profile validation accepts invalid fields/markup.' );
$check( prihod_theme_sanitize( array( 'home' => array() ) )['profile'] === $options['profile'], 'Partial settings discard the owner profile.' );
$translate_profile = static fn( $value, $context, $name ) => 'profile-description' === $name ? 'Translated owner description' : $value;
add_filter( 'wpml_translate_single_string', $translate_profile, 10, 3 );
$check( prihod_theme_profile()['description'] === 'Translated owner description', 'Theme profile text ignores optional translations.' );
remove_filter( 'wpml_translate_single_string', $translate_profile, 10 );
ob_start(); prihod_theme_settings_page(); $panel = ob_get_clean();
$check( str_contains( $panel, 'prihod-option-profile' ) && str_contains( $panel, '[profile][address]' ) && str_contains( $panel, '[profile][type]' ), 'Theme has no standalone profile controls.' );
$check( $post_snapshot === hash( 'sha256', serialize( $wpdb->get_results( "SELECT ID,post_content,post_title,post_status FROM {$wpdb->posts} ORDER BY ID", ARRAY_A ) ) ), 'Standalone theme settings rewrote content.' );
$set( array() );
$check( prihod_theme_profile()['name'] === get_bloginfo( 'name' ) && prihod_theme_profile()['description'] === get_bloginfo( 'description' ), 'Empty theme settings lose standard WordPress identity.' );

// Site copy is owner data, never a theme fallback, even after explicitly clearing it.
foreach ( array( 'header_motto', 'hero_motto', 'hero_quote_text', 'hero_quote_source', 'quote_text', 'quote_source' ) as $key ) {
    $check( georg_kloster_prihod_options()[$key] === '', 'A content default is embedded in theme options: ' . $key );
}
$copy_config = array( 'hero_schedule_button' => 'Owner schedule action', 'hero_secondary_button' => 'Owner second action', 'about_button' => 'Owner about action', 'news_button' => 'Owner news action', 'services_button' => 'Owner services action', 'sections' => array( 'hero' => array( 'title' => 'Owner welcome', 'eyebrow' => 'Owner eyebrow' ), 'about' => array( 'title' => 'Owner section', 'eyebrow' => 'Owner section eyebrow' ) ), 'pages' => array( 'schedule' => $page_id, 'contacts' => $page_id, 'about' => $page_id, 'news' => $page_id ) );
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    $set( $copy_config, $scheme ); $html = prihod_theme_home();
    $check( str_contains( $html, 'Owner welcome' ) && str_contains( $html, 'Owner eyebrow' ), 'Saved hero copy was ignored: ' . $scheme );
    $check( 'editorial' === $scheme || str_contains( $html, 'Owner section eyebrow' ), 'Saved section eyebrow was ignored: ' . $scheme );
    $set( array(), $scheme ); $html = prihod_theme_home();
    $check( ! str_contains( $html, '<h1>' ) && ! str_contains( $html, 'prihod-verse' ) && ! str_contains( $html, 'prihod-classic-motto' ) && ! str_contains( $html, 'prihod-tradition-motto' ), 'Clearing copy restores ready-made content: ' . $scheme );
    $check( ! str_contains( $html, 'Owner welcome' ) && ! str_contains( $html, 'Owner section eyebrow' ), 'Cleared owner text survives: ' . $scheme );
}
$copy_config['sections']['hero']['eyebrow'] = '<script>bad</script>Owner';
$check( ! str_contains( prihod_theme_home_sanitize( $copy_config, 'classic' )['sections']['hero']['eyebrow'], '<' ), 'Eyebrow accepts markup.' );

// Second phase: activate Parish and its bundled Schedule without external API calls.
$activated = activate_plugin( 'georg-kloster-prihod-core/georg-kloster-prihod-core.php' );
if ( is_wp_error( $activated ) ) { throw new RuntimeException( $activated->get_error_message() ); }
\GeorgKlosterPrihodCore\Schedule_Module::load();
\GeorgKlosterPrihodCore\Directory::register();
\GeorgKlosterPrihodCore\Directory::register_legacy();
\GeorgKlosterPrihodSchedule\Schedule::register();
$location = wp_insert_post( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'post_title' => 'Fixture church' ) );
foreach ( range( 1, 3 ) as $index ) {
    $id = wp_insert_post( array( 'post_type' => 'gkpsch_service', 'post_status' => 'publish', 'post_title' => 'Fixture service ' . $index ) );
    update_post_meta( $id, '_gkpsch_service', array_merge( \GeorgKlosterPrihodSchedule\Schedule::defaults(), array( 'date' => gmdate( 'Y-m-d', time() + $index * DAY_IN_SECONDS ), 'location_id' => $location, 'timezone' => 'UTC', 'time' => '10:00', 'vestment_color' => 'gold' ) ) );
}
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    $set( array( 'services_count' => 2, 'sections' => array( 'services' => array( 'eyebrow' => 'Owner service eyebrow' ) ) ), $scheme ); $html = prihod_theme_home();
    $check( substr_count( $html, '<article class="prihod-service-compact' ) === 2 && str_contains( $html, 'Owner service eyebrow' ), 'Configured service count or owner eyebrow failed: ' . $scheme );
    $check( substr_count( $html, 'class="prihod-hero-service ' ) === 1, 'Services are duplicated: ' . $scheme );
    $set( array( 'order' => array( 'news', 'services', 'hero' ) ), $scheme ); $html = prihod_theme_home();
    $check( strpos( $html, 'prihod-news-section' ) < strpos( $html, 'prihod-hero-service' ) && strpos( $html, 'prihod-hero-service' ) < strpos( $html, 'prihod-home-hero' ), 'Reordered services remain embedded in hero: ' . $scheme );
    $set( array( 'sections' => array( 'hero' => array( 'enabled' => false ) ) ), $scheme ); $html = prihod_theme_home();
    $check( ! str_contains( $html, 'prihod-home-hero' ) && str_contains( $html, 'prihod-hero-service' ), 'Hiding hero hides independent services: ' . $scheme );
}
file_put_contents( __DIR__ . '/.home-settings-result.json', wp_json_encode( array( 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'HOME_SETTINGS_OK' ) ) );
echo 'HOME_SETTINGS_OK ' . $checks;
