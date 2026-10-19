<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Modules {
    public static function enabled( string $module ): bool {
        if ( 'requests' !== $module || get_option( 'gkps_purge_job' ) ) { return false; }
        if ( ! Hub_Settings::get()['enabled'] ) { return false; }
        foreach ( Forms::all() as $form ) { if ( $form['enabled'] ) { return true; } }
        return false;
    }
}
