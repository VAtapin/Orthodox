<?php
namespace Prihod;

defined( 'ABSPATH' ) || exit;

final class Profile {
    public const OPTION = 'prihod_profile';

    public static function defaults(): array {
        $timezone = wp_timezone_string();
        if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
            $timezone = 'UTC';
        }
        return array(
            'type' => 'parish', 'name' => '', 'short_name' => '', 'description' => '',
            'address' => '', 'phone' => '', 'email' => '', 'website' => '',
            'timezone' => $timezone, 'language' => get_locale(),
        );
    }

    public static function get(): array {
        $saved = get_option( self::OPTION, array() );
        return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
    }

    public static function types(): array {
        return array(
            'church' => __( 'Храм', 'prihod' ), 'parish' => __( 'Приход', 'prihod' ),
            'monastery' => __( 'Монастырь', 'prihod' ), 'chapel' => __( 'Часовня', 'prihod' ),
        );
    }

    public static function fields(): array {
        return array(
            'name' => __( 'Полное название', 'prihod' ),
            'short_name' => __( 'Краткое название', 'prihod' ),
            'description' => __( 'Описание', 'prihod' ),
            'address' => __( 'Адрес', 'prihod' ),
            'phone' => __( 'Телефон', 'prihod' ),
            'email' => __( 'Email', 'prihod' ),
            'website' => __( 'Сайт', 'prihod' ),
            'timezone' => __( 'Часовой пояс', 'prihod' ),
            'language' => __( 'Основной язык', 'prihod' ),
        );
    }

    public static function sanitize( $input ): array {
        $saved = self::get();
        if ( ! is_array( $input ) ) {
            add_settings_error( self::OPTION, 'invalid_profile', __( 'Некорректные сведения о приходе.', 'prihod' ) );
            return $saved;
        }
        $result = $saved;
        foreach ( array_keys( self::defaults() ) as $key ) {
            if ( ! array_key_exists( $key, $input ) ) {
                continue;
            }
            if ( ! is_string( $input[ $key ] ) ) {
                add_settings_error( self::OPTION, 'invalid_' . $key, __( 'Некорректное значение поля.', 'prihod' ) );
                continue;
            }
            $value = trim( $input[ $key ] );
            if ( 'type' === $key ) {
                if ( isset( self::types()[ $value ] ) ) {
                    $result[ $key ] = $value;
                } else {
                    add_settings_error( self::OPTION, 'invalid_type', __( 'Выберите тип организации.', 'prihod' ) );
                }
            } elseif ( 'timezone' === $key ) {
                if ( in_array( $value, timezone_identifiers_list(), true ) || 'UTC' === $value ) {
                    $result[ $key ] = $value;
                } else {
                    add_settings_error( self::OPTION, 'invalid_timezone', __( 'Выберите именованный часовой пояс, например Europe/Berlin.', 'prihod' ) );
                }
            } elseif ( 'email' === $key ) {
                if ( '' === $value || is_email( $value ) ) {
                    $result[ $key ] = sanitize_email( $value );
                } else {
                    add_settings_error( self::OPTION, 'invalid_email', __( 'Проверьте email.', 'prihod' ) );
                }
            } elseif ( 'website' === $key ) {
                $url = esc_url_raw( $value, array( 'https', 'http' ) );
                if ( '' === $value || ( $url && wp_parse_url( $url, PHP_URL_HOST ) ) ) {
                    $result[ $key ] = $url;
                } else {
                    add_settings_error( self::OPTION, 'invalid_website', __( 'Укажите адрес сайта с http или https.', 'prihod' ) );
                }
            } elseif ( 'language' === $key ) {
                if ( preg_match( '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/D', $value ) ) {
                    $result[ $key ] = $value;
                } else {
                    add_settings_error( self::OPTION, 'invalid_language', __( 'Укажите код языка, например ru_RU.', 'prihod' ) );
                }
            } else {
                $result[ $key ] = in_array( $key, array( 'description', 'address' ), true )
                    ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
            }
        }
        return $result;
    }
}
