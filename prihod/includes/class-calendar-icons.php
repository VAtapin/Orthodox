<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

/** Local fallback images, distinct from the calendar's actual icon of the day. */
final class Calendar_Icons {
    public const OPTION = 'prihod_calendar_icons';
    public static function hooks(): void {
        add_action( 'admin_init', static function () { register_setting( self::OPTION, self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( self::class, 'sanitize' ), 'show_in_rest' => false ) ); } );
        add_filter( 'option_page_capability_' . self::OPTION, static fn() => 'manage_prihod_settings' );
        add_action( 'admin_enqueue_scripts', static function () {
            if ( 'prihod-settings' !== ( $_GET['page'] ?? '' ) || ! current_user_can( 'manage_prihod_settings' ) ) { return; }
            wp_enqueue_media();
            wp_enqueue_script( 'prihod-calendar-icons', plugins_url( 'assets/calendar-icons.js', PRIHOD_FILE ), array( 'media-views' ), PRIHOD_VERSION, true );
        } );
    }
    public static function options(): array { return wp_parse_args( get_option( self::OPTION, array() ), array( 'enabled' => true, 'weekday' => 0, 'sunday' => 0 ) ); }
    public static function sanitize( $input ): array {
        if ( ! is_array( $input ) ) { add_settings_error( self::OPTION, 'calendar_icon', __( 'Выберите изображение из медиатеки.', 'prihod' ) ); return self::options(); }
        $result = array( 'enabled' => ! empty( $input['enabled'] ), 'weekday' => 0, 'sunday' => 0 );
        foreach ( array( 'weekday', 'sunday' ) as $slot ) {
            if ( ! is_array( $input ) || ! is_scalar( $input[$slot] ?? null ) || ! preg_match( '/^\d+$/D', (string) $input[$slot] ) || ( $input[$slot] && ! wp_attachment_is_image( (int) $input[$slot] ) ) ) {
                add_settings_error( self::OPTION, 'calendar_icon', __( 'Выберите изображение из медиатеки.', 'prihod' ) ); return self::options();
            }
            $result[$slot] = (int) $input[$slot];
        }
        return $result;
    }
    public static function fallback( string $date ): ?array {
        if ( ! Occurrences::date( $date ) || ! self::options()['enabled'] ) { return null; }
        $sunday = '7' === ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->format( 'N' );
        $id = absint( self::options()[$sunday ? 'sunday' : 'weekday'] );
        $url = $id && wp_attachment_is_image( $id ) ? wp_get_attachment_image_url( $id, 'medium' ) : false;
        $alt = $url ? ( get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title( $id ) ) : ( $sunday ? __( 'Воскресение Христово — Сошествие во ад', 'prihod' ) : __( 'Спаситель — Христос Вседержитель', 'prihod' ) );
        $builtin = plugins_url( 'assets/icons/' . ( $sunday ? 'resurrection.jpg' : 'christ.jpg' ), PRIHOD_FILE );
        $full = plugins_url( 'assets/icons/' . ( $sunday ? 'resurrection-full.jpg' : 'christ-full.jpg' ), PRIHOD_FILE );
        return array( 'url' => $url ?: $builtin, 'fullUrl' => $url ? wp_get_attachment_image_url( $id, 'full' ) : $full, 'alt' => sanitize_text_field( $alt ), 'attribution' => '', 'media' => '', 'fallback' => true );
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_prihod_settings' ) ) { return; }
        $options = self::options();
        echo '<h2>' . esc_html__( 'Запасные иконы расписания', 'prihod' ) . '</h2><p>' . esc_html__( 'Икона дня имеет приоритет. Если изображения нет, используется воскресный или будничный образ. Встроенные образы хранятся на сайте; их можно заменить своей храмовой или месяцесловной иконой.', 'prihod' ) . '</p>';
        settings_errors( self::OPTION );
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">'; settings_fields( self::OPTION );
        echo '<p><label><input type="checkbox" name="' . self::OPTION . '[enabled]" value="1"' . checked( $options['enabled'], true, false ) . '> ' . esc_html__( 'Показывать запасной образ, если иконы дня нет', 'prihod' ) . '</label></p>';
        foreach ( array( 'sunday' => __( 'Воскресенье: Воскресение Христово', 'prihod' ), 'weekday' => __( 'Будни: храмовый или месяцесловный образ', 'prihod' ) ) as $slot => $label ) {
            $builtin = plugins_url( 'assets/icons/' . ( 'sunday' === $slot ? 'resurrection.jpg' : 'christ.jpg' ), PRIHOD_FILE );
            $url = wp_get_attachment_image_url( absint( $options[$slot] ), 'medium' ) ?: $builtin;
            echo '<fieldset class="prihod-service-section" data-prihod-icon-choice><legend>' . esc_html( $label ) . '</legend><input type="hidden" name="' . self::OPTION . '[' . $slot . ']" value="' . absint( $options[$slot] ) . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $label ) . '" data-default="' . esc_url( $builtin ) . '" style="display:block;width:90px;height:130px;object-fit:contain;margin-bottom:12px"><button type="button" class="button" data-prihod-icon-select>' . esc_html__( 'Выбрать икону', 'prihod' ) . '</button> <button type="button" class="button" data-prihod-icon-reset>' . esc_html__( 'Встроенный образ', 'prihod' ) . '</button></fieldset>';
        }
        submit_button(); echo '</form>';
    }
}
