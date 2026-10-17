<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Profile {
    public const OPTION = 'gkpc_profile';

    public static function defaults(): array {
        return array(
            'type' => 'parish', 'name' => '', 'short_name' => '', 'description' => '', 'address' => '', 'phone' => '',
            'email' => '', 'website' => '',
        );
    }

    public static function raw(): array {
        $saved = get_option( self::OPTION, array() );
        return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
    }

    public static function get(): array {
        $profile = self::raw();
        foreach ( array( 'name', 'short_name', 'description', 'address' ) as $key ) {
            if ( '' !== $profile[ $key ] ) {
                $translated = apply_filters( 'wpml_translate_single_string', false, 'georg-kloster-prihod-core', 'profile-' . $key );
                if ( is_string( $translated ) && '' !== $translated ) { $profile[ $key ] = $translated; }
            }
        }
        return $profile;
    }

    public static function register_strings( array $profile ): void {
        foreach ( array( 'name', 'short_name', 'description', 'address' ) as $key ) {
            if ( ! empty( $profile[ $key ] ) && is_string( $profile[ $key ] ) ) {
                do_action( 'wpml_register_single_string', 'georg-kloster-prihod-core', 'profile-' . $key, $profile[ $key ] );
            }
        }
    }

    public static function fields(): array {
        return array(
            'type' => __( 'Organisation type', 'georg-kloster-prihod-core' ),
            'name' => __( 'Name', 'georg-kloster-prihod-core' ),
            'short_name' => __( 'Short name', 'georg-kloster-prihod-core' ),
            'description' => __( 'Description', 'georg-kloster-prihod-core' ),
            'address' => __( 'Address', 'georg-kloster-prihod-core' ),
            'phone' => __( 'Phone', 'georg-kloster-prihod-core' ),
            'email' => __( 'Email', 'georg-kloster-prihod-core' ),
            'website' => __( 'Website', 'georg-kloster-prihod-core' ),
        );
    }

    public static function sanitize( $input ): array {
        $result = self::raw();
        if ( ! is_array( $input ) ) {
            add_settings_error( self::OPTION, 'invalid', __( 'Invalid profile.', 'georg-kloster-prihod-core' ) );
            return $result;
        }
        foreach ( self::defaults() as $key => $default ) {
            if ( ! array_key_exists( $key, $input ) ) { continue; }
            if ( ! is_string( $input[ $key ] ) ) { continue; }
            $value = trim( $input[ $key ] );
            if ( 'type' === $key ) {
                if ( ! in_array( $value, array( 'parish', 'church', 'monastery', 'chapel' ), true ) ) { continue; }
            } elseif ( 'email' === $key ) {
                if ( '' !== $value && ! is_email( $value ) ) { continue; }
                $value = sanitize_email( $value );
            } elseif ( 'website' === $key ) {
                $value = esc_url_raw( $value, array( 'http', 'https' ) );
                if ( '' !== $input[ $key ] && ! wp_parse_url( $value, PHP_URL_HOST ) ) { continue; }
            } elseif ( in_array( $key, array( 'description', 'address' ), true ) ) {
                $value = sanitize_textarea_field( $value );
            } else {
                $value = sanitize_text_field( $value );
            }
            $result[ $key ] = $value;
        }
        return $result;
    }
}
