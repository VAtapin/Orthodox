<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Modules {
    public static function enabled( string $id ): bool {
        if ( get_option( 'prihod_purge_job' ) ) { return false; }
        $settings = get_option( 'prihod_modules', array() );
        return ! isset( $settings[ $id ] ) || (bool) $settings[ $id ];
    }
    public static function sanitize( $input ): array {
        $input = is_array( $input ) ? $input : array();
        return array( 'schedule' => ! empty( $input['schedule'] ), 'requests' => ! empty( $input['requests'] ) );
    }
}
