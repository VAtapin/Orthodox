<?php
/** Disposable WordPress: Core demo with no Parish theme or other plugin. */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
use GeorgKlosterPrihodCore\Demo_Importer;
wp_set_current_user( 1 );
$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } };
$check( 'georg-kloster-prihod' !== get_template() && ! function_exists( 'prihod_theme_sanitize' ), 'Standalone demo test loaded the Parish theme.' );
add_filter( 'pre_http_request', static function () { throw new RuntimeException( 'Demo made an external HTTP request.' ); } );
add_filter( 'pre_wp_mail', static function () { throw new RuntimeException( 'Demo sent a message.' ); } );
$result = Demo_Importer::run( 'en_US', true );
$check( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
$check( 10 === count( $result['pages'] ) && 4 === count( $result['posts'] ) && 5 === count( $result['media'] ), 'Standalone demo is incomplete.' );
$check( ! get_option( 'georg_kloster_prihod_options' ) && ! post_type_exists( 'gkpsch_service' ), 'Demo assumes theme settings or Schedule is present.' );
$check( (int) get_option( 'page_on_front' ) === $result['pages']['home'], 'Standalone demo homepage was not selected.' );
$pack = Demo_Importer::pack( 'en_US' );
$check( $pack['profile']['name'] === get_option( 'gkpc_profile' )['name'], 'Fictional profile was not saved.' );
$content = get_post_field( 'post_content', $result['pages']['home'] );
$walk = static function ( array $blocks ) use ( &$walk, $check ): void {
    foreach ( $blocks as $block ) {
        $check( empty( $block['blockName'] ) || str_starts_with( $block['blockName'], 'core/' ), 'Demo content depends on a plugin block.' );
        if ( $block['innerBlocks'] ) { $walk( $block['innerBlocks'] ); }
    }
};
$walk( parse_blocks( $content ) );
deactivate_plugins( 'georg-kloster-prihod-core/georg-kloster-prihod-core.php' );
$check( str_contains( do_blocks( $content ), 'wp-block-gallery' ) && str_contains( do_blocks( $content ), 'fictional' ), 'Imported page content disappears after disabling Core.' );
echo 'DEMO_STANDALONE_OK ' . $checks;
