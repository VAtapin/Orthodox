<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Modules {
    public static function enabled( string $module ): bool {
        if ( 'requests' !== $module || get_option( 'gkps_purge_job' ) ) { return false; }
        $settings = get_option( 'gkps_form', array() );
        return is_array( $settings ) && ! empty( $settings['enabled'] );
    }
}
