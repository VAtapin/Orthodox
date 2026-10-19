<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Profile {
    public static function get(): array {
        $profile = class_exists( '\\GeorgKlosterPrihodCore\\Profile' ) ? \GeorgKlosterPrihodCore\Profile::get() : array();
        $fallback = array( 'name' => get_bloginfo( 'name' ), 'email' => get_option( 'admin_email' ), 'address' => '', 'phone' => '', 'language' => get_locale() );
        return array_merge( $fallback, array_filter( $profile, static fn( $value ) => is_string( $value ) && '' !== $value ) );
    }
}
