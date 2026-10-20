<?php
/** Disposable Playground only. The integrations panel must not contact external services. */
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
$catalog = prihod_theme_plugin_catalog();
$check( 2 === count( $catalog ), 'Integration catalog changed.' );
$check( isset( $catalog['georg-kloster-connect-hub'] ) && ! isset( $catalog['georg-kloster-prihod-social'] ), 'Connect Hub slug not updated.' );
$check( 'missing' === prihod_theme_plugin_action( 'georg-kloster-prihod-core/georg-kloster-prihod-core.php', array() )['state'], 'Missing integration is shown as available.' );
$html = '';
ob_start(); prihod_theme_plugins_panel(); $html = ob_get_clean();
$check( str_contains( $html, 'Find Georg-Kloster plugins' ), 'Plugin installer search button is missing.' );
$check( str_contains( $html, 's=Georg-Kloster' ) && str_contains( $html, 'tab=search' ) && str_contains( $html, 'type=term' ), 'Plugin installer button has incorrect search parameters.' );
$check( ! str_contains( $html, 'Check WordPress.org' ) && ! str_contains( $html, 'install-plugin' ) && ! str_contains( $html, 'Directory check' ), 'Removed directory checker or installer remains in the panel.' );
$check( 0 === $http_calls, 'Rendering the optional integrations panel made an external request.' );
$source = file_get_contents( get_template_directory() . '/includes/plugins.php' );
$check( ! str_contains( $source, 'plugins_api' ) && ! str_contains( $source, 'wp_remote_get' ) && ! str_contains( $source, 'admin_post_prihod_theme_check_plugins' ), 'Theme source still contains the external plugin-directory checker.' );
$settings = '';
ob_start(); prihod_theme_settings_page(); $settings = ob_get_clean();
$check( str_contains( $settings, 'data-section="plugins"' ), 'Optional integrations section disappeared.' );
$check( $http_calls === 0, 'Rendering theme settings made an external request.' );
wp_set_current_user( 0 );
$check( '' === ( static function (): string { ob_start(); prihod_theme_plugins_panel(); return ob_get_clean(); } )(), 'User without theme permissions can view the panel.' );
file_put_contents( __DIR__ . '/.theme-plugins-result.json', wp_json_encode( array( 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'THEME_PLUGINS_OK' ) ) );
echo 'THEME_PLUGINS_OK ' . $checks;
