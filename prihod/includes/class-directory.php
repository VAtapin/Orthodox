<?php
namespace Prihod;

defined( 'ABSPATH' ) || exit;

final class Directory {
    public static function register(): void {
        foreach ( array(
            'prihod_location' => array( __( 'Места служения', 'prihod' ), __( 'Место служения', 'prihod' ) ),
            'prihod_clergy' => array( __( 'Духовенство', 'prihod' ), __( 'Священнослужитель', 'prihod' ) ),
        ) as $type => $labels ) {
            register_post_type( $type, array(
                'labels' => array(
                    'name' => $labels[0], 'singular_name' => $labels[1],
                    'add_new' => __( 'Добавить', 'prihod' ),
                    'add_new_item' => $labels[1], 'edit_item' => __( 'Редактировать', 'prihod' ),
                    'not_found' => __( 'Записи не найдены.', 'prihod' ),
                ),
                'public' => false, 'show_ui' => true, 'show_in_menu' => 'prihod',
                'show_in_rest' => false, 'publicly_queryable' => false,
                'exclude_from_search' => true, 'show_in_nav_menus' => false,
                'rewrite' => false, 'query_var' => false,
                'supports' => array( 'title', 'editor', 'revisions' ),
                'capabilities' => Capabilities::directory(), 'map_meta_cap' => false,
                'delete_with_user' => false,
            ) );
        }
    }

    public static function fields( string $type ): array {
        if ( 'prihod_location' === $type ) {
            return array(
                'address' => __( 'Адрес', 'prihod' ), 'phone' => __( 'Телефон', 'prihod' ),
                'email' => __( 'Email', 'prihod' ), 'website' => __( 'Сайт', 'prihod' ),
                'timezone' => __( 'Часовой пояс (пусто — использовать часовой пояс прихода)', 'prihod' ),
            );
        }
        if ( 'prihod_clergy' === $type ) {
            return array(
                'rank' => __( 'Сан / должность', 'prihod' ), 'phone' => __( 'Телефон', 'prihod' ),
                'email' => __( 'Email', 'prihod' ),
            );
        }
        return array();
    }

    public static function details( int $id ): array {
        $details = get_post_meta( $id, '_prihod_details', true );
        return is_array( $details ) ? $details : array();
    }

    public static function meta_boxes(): void {
        foreach ( array( 'prihod_location', 'prihod_clergy' ) as $type ) {
            add_meta_box( 'prihod_details', __( 'Сведения', 'prihod' ), array( self::class, 'render' ), $type );
        }
    }

    public static function render( $post ): void {
        wp_nonce_field( 'prihod_directory_' . $post->ID, 'prihod_directory_nonce' );
        $details = self::details( (int) $post->ID );
        foreach ( self::fields( $post->post_type ) as $key => $label ) {
            echo '<p><label for="prihod-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br>';
            echo '<input class="widefat" id="prihod-' . esc_attr( $key ) . '" name="prihod_details[' . esc_attr( $key ) . ']" value="' . esc_attr( $details[ $key ] ?? '' ) . '"></p>';
        }
        if ( 'prihod_clergy' === $post->post_type ) {
            echo '<p><label for="prihod-locations">' . esc_html__( 'Места служения (можно выбрать несколько)', 'prihod' ) . '</label><br>';
            echo '<select class="widefat" multiple size="6" id="prihod-locations" name="prihod_details[location_ids][]">';
            foreach ( get_posts( array( 'post_type' => 'prihod_location', 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $location ) {
                echo '<option value="' . esc_attr( $location->ID ) . '"' . selected( in_array( (int) $location->ID, $details['location_ids'] ?? array(), true ), true, false ) . '>' . esc_html( $location->post_title ) . '</option>';
            }
            echo '</select></p>';
        }
    }

    public static function sanitize( string $type, array $input, array $saved ): array {
        $result = $saved;
        foreach ( array_keys( self::fields( $type ) ) as $key ) {
            if ( ! array_key_exists( $key, $input ) ) {
                continue;
            }
            $value = $input[ $key ];
            if ( ! is_string( $value ) ) {
                self::invalid();
                continue;
            }
            $value = trim( $value );
            if ( 'email' === $key ) {
                if ( '' !== $value && ! is_email( $value ) ) {
                    self::invalid();
                    continue;
                }
                $value = sanitize_email( $value );
            } elseif ( 'website' === $key ) {
                $url = esc_url_raw( $value, array( 'http', 'https' ) );
                if ( '' !== $value && ( ! $url || ! wp_parse_url( $url, PHP_URL_HOST ) ) ) {
                    self::invalid();
                    continue;
                }
                $value = $url;
            } elseif ( 'timezone' === $key ) {
                if ( '' !== $value && ! in_array( $value, timezone_identifiers_list(), true ) && 'UTC' !== $value ) {
                    self::invalid();
                    continue;
                }
            } else {
                $value = 'address' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
            }
            $result[ $key ] = $value;
        }
        if ( 'prihod_clergy' === $type ) {
            $ids = $input['location_ids'] ?? array();
            if ( ! is_array( $ids ) ) {
                self::invalid();
            } else {
                $validated = array();
                $valid = true;
                foreach ( $ids as $id ) {
                    if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 ) {
                        $valid = false;
                        break;
                    }
                    $location = get_post( (int) $id );
                    if ( ! $location || 'prihod_location' !== $location->post_type || in_array( $location->post_status, array( 'trash', 'auto-draft' ), true ) ) {
                        $valid = false;
                        break;
                    }
                    $validated[] = (int) $id;
                }
                if ( $valid ) {
                    $result['location_ids'] = array_values( array_unique( $validated ) );
                } else {
                    self::invalid();
                }
            }
        }
        return $result;
    }

    public static function save( int $id ): void {
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id )
            || wp_is_post_autosave( $id ) || ! current_user_can( 'manage_prihod' ) || ! current_user_can( 'edit_post', $id ) ) {
            return;
        }
        $nonce = $_POST['prihod_directory_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'prihod_directory_' . $id ) ) {
            return;
        }
        $input = $_POST['prihod_details'] ?? null;
        if ( ! is_array( $input ) ) {
            return;
        }
        $type = get_post_type( $id );
        if ( ! in_array( $type, array( 'prihod_location', 'prihod_clergy' ), true ) ) {
            return;
        }
        update_post_meta( $id, '_prihod_details', self::sanitize( $type, wp_unslash( $input ), self::details( $id ) ) );
    }

    private static function invalid(): void {
        set_transient( 'prihod_directory_error_' . get_current_user_id(), true, 60 );
    }

    public static function notice(): void {
        $key = 'prihod_directory_error_' . get_current_user_id();
        if ( current_user_can( 'manage_prihod' ) && get_transient( $key ) ) {
            delete_transient( $key );
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Некоторые сведения некорректны. Для этих полей сохранены предыдущие значения. Проверьте email, сайт, часовой пояс и места служения.', 'prihod' ) . '</p></div>';
        }
    }
}
