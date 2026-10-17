<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

/** Local fallback images, distinct from the calendar's actual icon of the day. */
final class Calendar_Icons {
    public const OPTION = 'gkpsch_calendar_icons';
    public static function hooks(): void {
        add_action( 'admin_init', static function () { register_setting( self::OPTION, self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( self::class, 'sanitize' ), 'show_in_rest' => false ) ); } );
        add_filter( 'option_page_capability_' . self::OPTION, static fn() => 'manage_gkpsch_settings' );
        add_action( 'admin_enqueue_scripts', static function () {
            if ( 'gkpsch-settings' !== ( $_GET['page'] ?? '' ) || ! current_user_can( 'manage_gkpsch_settings' ) ) { return; }
            wp_enqueue_media();
            wp_enqueue_script( 'gkpsch-calendar-icons', plugins_url( 'assets/calendar-icons.js', GKPSCH_FILE ), array( 'media-views' ), GKPSCH_VERSION, true );
        } );
    }
    public static function options(): array { return wp_parse_args( get_option( self::OPTION, array() ), array( 'enabled' => true, 'weekday' => 0, 'sunday' => 0 ) ); }
    public static function sanitize( $input ): array {
        if ( ! is_array( $input ) ) { add_settings_error( self::OPTION, 'calendar_icon', __( 'Выберите изображение из медиатеки.', 'georg-kloster-prihod-schedule' ) ); return self::options(); }
        $result = array( 'enabled' => ! empty( $input['enabled'] ), 'weekday' => 0, 'sunday' => 0 );
        foreach ( array( 'weekday', 'sunday' ) as $slot ) {
            if ( ! is_array( $input ) || ! is_scalar( $input[$slot] ?? null ) || ! preg_match( '/^\d+$/D', (string) $input[$slot] ) || ( $input[$slot] && ! wp_attachment_is_image( (int) $input[$slot] ) ) ) {
                add_settings_error( self::OPTION, 'calendar_icon', __( 'Выберите изображение из медиатеки.', 'georg-kloster-prihod-schedule' ) ); return self::options();
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
        $alt = $url ? ( get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title( $id ) ) : ( $sunday ? __( 'Воскресение Христово — Сошествие во ад', 'georg-kloster-prihod-schedule' ) : __( 'Спаситель — Христос Вседержитель', 'georg-kloster-prihod-schedule' ) );
        $builtin = plugins_url( 'assets/icons/' . ( $sunday ? 'resurrection.jpg' : 'christ.jpg' ), GKPSCH_FILE );
        $full = plugins_url( 'assets/icons/' . ( $sunday ? 'resurrection-full.jpg' : 'christ-full.jpg' ), GKPSCH_FILE );
        return array( 'url' => $url ?: $builtin, 'fullUrl' => $url ? wp_get_attachment_image_url( $id, 'full' ) : $full, 'alt' => sanitize_text_field( $alt ), 'attribution' => '', 'media' => '', 'fallback' => true );
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_gkpsch_settings' ) ) { return; }
        $options = self::options();
        echo '<h2>' . esc_html__( 'Запасные иконы расписания', 'georg-kloster-prihod-schedule' ) . '</h2><p>' . esc_html__( 'Икона дня имеет приоритет. Если изображения нет, используется воскресный или будничный образ. Встроенные образы хранятся на сайте; их можно заменить своей храмовой или месяцесловной иконой.', 'georg-kloster-prihod-schedule' ) . '</p>';
        settings_errors( self::OPTION );
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">'; settings_fields( self::OPTION );
        echo '<p><label><input type="checkbox" name="' . self::OPTION . '[enabled]" value="1"' . checked( $options['enabled'], true, false ) . '> ' . esc_html__( 'Показывать запасной образ, если иконы дня нет', 'georg-kloster-prihod-schedule' ) . '</label></p>';
        foreach ( array( 'sunday' => __( 'Воскресенье: Воскресение Христово', 'georg-kloster-prihod-schedule' ), 'weekday' => __( 'Будни: храмовый или месяцесловный образ', 'georg-kloster-prihod-schedule' ) ) as $slot => $label ) {
            $builtin = plugins_url( 'assets/icons/' . ( 'sunday' === $slot ? 'resurrection.jpg' : 'christ.jpg' ), GKPSCH_FILE );
            $url = wp_get_attachment_image_url( absint( $options[$slot] ), 'medium' ) ?: $builtin;
            echo '<fieldset class="gkpsch-service-section" data-gkpsch-icon-choice><legend>' . esc_html( $label ) . '</legend><input type="hidden" name="' . self::OPTION . '[' . $slot . ']" value="' . absint( $options[$slot] ) . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $label ) . '" data-default="' . esc_url( $builtin ) . '" style="display:block;width:90px;height:130px;object-fit:contain;margin-bottom:12px"><button type="button" class="button" data-gkpsch-icon-select>' . esc_html__( 'Выбрать икону', 'georg-kloster-prihod-schedule' ) . '</button> <button type="button" class="button" data-gkpsch-icon-reset>' . esc_html__( 'Встроенный образ', 'georg-kloster-prihod-schedule' ) . '</button></fieldset>';
        }
        submit_button(); echo '</form>';
    }
}
