<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

/** Per-site database lock with bounded lifetime and compare-and-delete release. */
final class Lock {
    public static function acquire( string $name, int $seconds = 60 ): string {
        $token = ( time() + $seconds ) . '|' . wp_generate_uuid4();
        if ( add_option( $name, $token, '', false ) ) { return $token; }
        $old = get_option( $name, '' );
        if ( is_string( $old ) && (int) strtok( $old, '|' ) < time() ) {
            self::release( $name, $old );
            if ( add_option( $name, $token, '', false ) ) { return $token; }
        }
        return '';
    }
    public static function release( string $name, string $token ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $token ) );
        wp_cache_delete( $name, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }
}
