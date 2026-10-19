<?php
/** Disposable Playground only. No plugin is downloaded and no real API is contacted. */
if ( ! is_file( '/wordpress/wp-load.php' ) ) { throw new RuntimeException( 'Requires disposable WordPress Playground.' ); }
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void {
    ++$checks;
    if ( ! $ok ) { throw new RuntimeException( $message ); }
};
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
set_current_screen( 'themes' );
$http_calls = 0;
add_filter( 'pre_http_request', static function () use ( &$http_calls ) { ++$http_calls; return new WP_Error( 'fixture', 'No external HTTP.' ); } );
$options_before = get_option( 'georg_kloster_prihod_options' );
$active_before = get_option( 'active_plugins' );
global $wpdb;
$posts_before = $wpdb->get_results( "SELECT ID,post_content,post_title,post_status FROM {$wpdb->posts} ORDER BY ID", ARRAY_A );
$cache_key = prihod_theme_plugin_cache_key();
delete_transient( $cache_key );
$catalog = prihod_theme_plugin_catalog();
$check( 2 === count( $catalog ), 'Integration catalog changed.' );
$check( isset( $catalog['georg-kloster-connect-hub'] ) && ! isset( $catalog['georg-kloster-prihod-social'] ), 'Connect Hub slug not updated.' );
$slug = 'georg-kloster-prihod-core';
$file = $slug . '/' . $slug . '.php';
$valid = (object) array( 'slug' => $slug, 'version' => '1.2.3', 'requires' => '6.5', 'requires_php' => '8.0', 'download_link' => 'https://downloads.wordpress.org/plugin/' . $slug . '.1.2.3.zip' );
$record = prihod_theme_plugin_directory_record( $valid, $slug );
$check( '1.2.3' === $record['version'], 'Installable official API record rejected.' );
foreach ( array( new WP_Error( 'plugin_not_found', 'Plugin not found.' ), null, array(), (object) array( 'slug' => $slug ), (object) array_merge( (array) $valid, array( 'slug' => 'another-plugin' ) ) ) as $bad ) {
    $check( array() === prihod_theme_plugin_directory_record( $bad, $slug ), 'Unconfirmed record became a recommendation.' );
}
foreach ( array( 'https://example.org/plugin.zip', 'http://downloads.wordpress.org/plugin/' . $slug . '.zip', 'https://downloads.wordpress.org.evil.test/plugin/' . $slug . '.zip', 'https://downloads.wordpress.org/plugin/other.zip', 'https://downloads.wordpress.org/plugin/' . $slug . '.php' ) as $url ) {
    $bad = clone $valid; $bad->download_link = $url;
    $check( array() === prihod_theme_plugin_directory_record( $bad, $slug ), 'Non-directory package accepted: ' . $url );
}
$action = prihod_theme_plugin_action( $slug, $file, array(), array() );
$check( 'unavailable' === $action['state'] && ! isset( $action['url'] ), 'Unpublished plugin has an install button.' );
$action = prihod_theme_plugin_action( $slug, $file, array(), $record );
parse_str( wp_parse_url( html_entity_decode( $action['url'] ), PHP_URL_QUERY ), $args );
$check( 'install-plugin' === $args['action'] && $slug === $args['plugin'] && wp_verify_nonce( $args['_wpnonce'], 'install-plugin_' . $slug ), 'Install action bypasses the core nonce flow.' );
$check( str_contains( $action['url'], '/wp-admin/update.php?' ), 'Install action does not use the standard core installer.' );
$incompatible = array_merge( $record, array( 'requires' => '99.0' ) );
$action = prihod_theme_plugin_action( $slug, $file, array(), $incompatible );
$check( 'incompatible' === $action['state'] && ! isset( $action['url'] ), 'Incompatible directory plugin can be installed.' );
$action = prihod_theme_plugin_action( $slug, $file, array( $file => array( 'Version' => '1.0', 'RequiresPHP' => '99.0' ) ), array() );
$check( 'incompatible' === $action['state'] && ! isset( $action['url'] ), 'Incompatible installed plugin can be activated.' );
$action = prihod_theme_plugin_action( $slug, $file, get_plugins(), array() );
parse_str( wp_parse_url( html_entity_decode( $action['url'] ), PHP_URL_QUERY ), $args );
$check( 'inactive' === $action['state'] && 'activate' === $args['action'] && $file === $args['plugin'] && wp_verify_nonce( $args['_wpnonce'], 'activate-plugin_' . $file ), 'Installed plugin activation is not a standard nonced action.' );
$render = static function (): string { ob_start(); prihod_theme_plugins_panel(); return ob_get_clean(); };
$html = $render();
$installed_count = count( array_filter( array_keys( $catalog ), static fn( $key ) => isset( get_plugins()[ $key . '/' . $key . '.php' ] ) ) );
$check( $installed_count === substr_count( $html, '<article class="prihod-plugin-card"' ) && str_contains( $html, 'Installed, inactive' ), 'Installed integrations are not documented when absent from the directory.' );
$check( 0 === $http_calls && ! str_contains( $html, 'install-plugin' ), 'Merely viewing plugins performs a lookup or advertises an unconfirmed install.' );
foreach ( $catalog as $plugin ) { $check( is_file( get_template_directory() . '/assets/plugins/' . $plugin['preview'] . '.png' ), 'Actual plugin screenshot missing.' ); }
ob_start(); prihod_theme_settings_page(); $settings = ob_get_clean();
$check( str_contains( $settings, 'data-section="plugins"' ) && strpos( $settings, '</form>' ) < strpos( $settings, 'id="prihod-option-plugins"' ), 'Plugin check form is nested inside the theme settings form.' );
$check( (bool) preg_match( '/name="_wpnonce" value="([^"]+)"/', $html, $nonce ) && wp_verify_nonce( $nonce[1], 'prihod_theme_check_plugins' ), 'Explicit directory check lacks CSRF protection.' );

// A clean installation sees no unpublished recommendations, including their names/screenshots.
$plugin_cache = wp_cache_get( 'plugins', 'plugins' );
wp_cache_set( 'plugins', array( '' => array() ), 'plugins' );
$empty = $render();
$check( ! str_contains( $empty, 'prihod-plugin-card"' ) && ! str_contains( $empty, $catalog[$slug]['name'] ), 'Unpublished missing plugins are still recommended.' );
$api_calls = 0;
$api = static function ( $result, $action, $args ) use ( &$api_calls, $valid ) {
    ++$api_calls;
    if ( 'georg-kloster-prihod-core' === $args->slug ) { return clone $valid; }
    return new WP_Error( 'fixture', 'Unavailable or unpublished.' );
};
add_filter( 'plugins_api', $api, 10, 3 );
$records = prihod_theme_refresh_plugin_directory();
$check( 2 === $api_calls && array( $slug ) === array_keys( $records ), 'Failed API entries were advertised or other plugins were requested.' );
$html = $render();
$check( 1 === substr_count( $html, '<article class="prihod-plugin-card"' ) && str_contains( $html, 'install-plugin' ) && str_contains( $html, 'Details on WordPress.org' ), 'Confirmed recommendation is missing.' );
$check( 0 === $http_calls && 2 === $api_calls, 'Rendering does not reuse the explicit directory check cache.' );
remove_filter( 'plugins_api', $api, 10 );
$api_error = static fn() => new WP_Error( 'fixture', 'Directory unavailable.' );
add_filter( 'plugins_api', $api_error );
$check( array() === prihod_theme_refresh_plugin_directory(), 'Directory outage creates recommendations.' );
$check( ! str_contains( $render(), 'install-plugin' ), 'A failed refresh leaves stale install links.' );
remove_filter( 'plugins_api', $api_error );
wp_cache_set( 'plugins', $plugin_cache, 'plugins' );
activate_plugin( $file );
wp_set_current_user( 0 ); wp_set_current_user( 1 );
$html = $render();
$check( str_contains( $html, '>Active</span>' ) && str_contains( $html, 'page=gkpc' ), 'Active integration has no settings link.' );
$action = prihod_theme_plugin_action( $slug, $file, get_plugins(), array() );
$check( 'active' === $action['state'] && ! isset( $action['url'] ), 'Active plugin is offered for activation again.' );
deactivate_plugins( $file );

// Theme editors may read this panel but cannot install, activate, or initiate API checks.
$limited = wp_insert_user( array( 'user_login' => 'theme-panel-editor', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$user = get_user_by( 'id', $limited ); $user->add_cap( 'edit_theme_options' ); wp_set_current_user( $limited );
set_transient( $cache_key, array( $slug => $record ), HOUR_IN_SECONDS );
$html = $render();
$check( ! str_contains( $html, 'name="action"' ) && ! str_contains( $html, 'action=activate' ) && ! str_contains( $html, 'action=install-plugin' ) && ! str_contains( $html, 'page=gkpc' ), 'Restricted user sees privileged plugin actions.' );
$check( str_contains( $html, 'Ask an administrator' ), 'Restricted user lacks a useful explanation.' );
$die_handler = static fn() => static function () { throw new RuntimeException( 'denied' ); };
add_filter( 'wp_die_handler', $die_handler );
try { prihod_theme_check_plugins_action(); $check( false, 'Unauthorized directory request was accepted.' ); } catch ( RuntimeException $error ) { $check( 'denied' === $error->getMessage(), 'Unexpected authorization response.' ); }
$user->remove_cap( 'edit_theme_options' );
wp_set_current_user( 0 ); wp_set_current_user( $limited );
$check( '' === $render(), 'User without theme permissions can view the panel.' );
wp_set_current_user( 1 );
$_REQUEST['_wpnonce'] = 'invalid';
ob_start();
try { prihod_theme_check_plugins_action(); $check( false, 'Invalid directory-check nonce accepted.' ); } catch ( RuntimeException $error ) { $check( 'denied' === $error->getMessage(), 'Unexpected nonce failure.' ); }
ob_end_clean();
remove_filter( 'wp_die_handler', $die_handler ); unset( $_REQUEST['_wpnonce'] );
delete_transient( $cache_key );
$check( $options_before === get_option( 'georg_kloster_prihod_options' ), 'Browsing/checking integrations changed theme content/settings.' );
$check( $active_before === get_option( 'active_plugins' ), 'Directory check changed active plugins.' );
$check( $posts_before === $wpdb->get_results( "SELECT ID,post_content,post_title,post_status FROM {$wpdb->posts} ORDER BY ID", ARRAY_A ), 'Plugin panel changed pages/posts.' );
file_put_contents( __DIR__ . '/.theme-plugins-result.json', wp_json_encode( array( 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'THEME_PLUGINS_OK' ) ) );
echo 'THEME_PLUGINS_OK ' . $checks;
