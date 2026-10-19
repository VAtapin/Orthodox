<?php
namespace GeorgKlosterPrihodSchedule;

defined( 'ABSPATH' ) || exit;

final class Profile {
    public static function get(): array {
        $timezone = wp_timezone_string();
        if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) { $timezone = 'UTC'; }
        return array( 'name' => get_bloginfo( 'name' ), 'timezone' => $timezone, 'email' => get_option( 'admin_email' ) );
    }
}
