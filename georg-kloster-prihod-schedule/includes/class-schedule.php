<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

final class Schedule {
    public static function hooks(): void {
        if ( ! Modules::enabled( 'schedule' ) ) { return; }
        add_action( 'init', array( self::class, 'register' ) );
        add_action( 'add_meta_boxes', function () {
            add_meta_box( 'gkpsch-service', __( 'Расписание службы', 'georg-kloster-prihod-schedule' ), array( Schedule_Admin::class, 'render' ), 'gkpsch_service' );
        } );
        add_action( 'save_post_gkpsch_service', array( Schedule_Admin::class, 'save' ) );
        add_action( 'save_post_gkpsch_service', static function ( int $id ) {
            if ( 'publish' === get_post_status( $id ) ) {
                do_action( 'wpml_register_single_string', 'georg-kloster-prihod-schedule', 'service-' . $id, get_the_title( $id ) );
            }
        }, 20 );
        add_action( 'admin_enqueue_scripts', array( Schedule_Admin::class, 'assets' ) );
        add_filter( 'manage_gkpsch_service_posts_columns', array( Schedule_Admin::class, 'columns' ) );
        add_action( 'manage_gkpsch_service_posts_custom_column', array( Schedule_Admin::class, 'column' ), 10, 2 );
    }
    public static function register(): void {
        $caps = array_map( fn() => 'manage_gkpsch_schedule', Capabilities::directory() );
        register_post_type( 'gkpsch_service', array(
            'labels' => array( 'name' => __( 'Расписание', 'georg-kloster-prihod-schedule' ), 'singular_name' => __( 'Богослужение', 'georg-kloster-prihod-schedule' ), 'add_new_item' => __( 'Добавить богослужение', 'georg-kloster-prihod-schedule' ) ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'gkpsch', 'show_in_rest' => false,
            'rewrite' => false, 'query_var' => false, 'supports' => array( 'title', 'editor', 'revisions' ),
            'capabilities' => $caps, 'map_meta_cap' => false, 'delete_with_user' => false,
        ) );
    }
    public static function defaults(): array {
        return array( 'kind' => 'single', 'date' => '', 'until' => '', 'time' => '09:00', 'end' => '', 'weekdays' => array(), 'location_id' => 0, 'timezone' => '', 'exceptions' => array(), 'day_icon' => false, 'vestment_color' => '' );
    }
    public static function timezone( int $location, string $explicit = '' ): string {
        $zone = $explicit ?: ( Directory::details( $location )['timezone'] ?? '' );
        $zone = $zone ?: Profile::get()['timezone'];
        return in_array( $zone, timezone_identifiers_list(), true ) ? $zone : 'UTC';
    }
    public static function validate( array $input ): array {
        $rule = array_merge( self::defaults(), array_intersect_key( $input, self::defaults() ) );
        $rule['vestment_color'] = Calendar_Colors::validate( $rule['vestment_color'] );
        if ( ! in_array( $rule['day_icon'], array( true, false, 1, 0, '1', '0', '' ), true ) ) { throw new \InvalidArgumentException( __( 'Некорректные поля расписания.', 'georg-kloster-prihod-schedule' ) ); }
        $rule['day_icon'] = in_array( $rule['day_icon'], array( true, 1, '1' ), true );
        foreach ( array( 'kind', 'date', 'until', 'time', 'end', 'timezone' ) as $key ) {
            if ( ! is_string( $rule[ $key ] ) ) { throw new \InvalidArgumentException( __( 'Некорректные поля расписания.', 'georg-kloster-prihod-schedule' ) ); }
        }
        if ( ! in_array( $rule['kind'], array( 'regular', 'single', 'holiday' ), true ) || ! Occurrences::date( $rule['date'] )
            || ! Occurrences::time( $rule['time'] ) || ( $rule['end'] && ! Occurrences::time( $rule['end'] ) )
            || ( $rule['until'] && ( ! Occurrences::date( $rule['until'] ) || $rule['until'] < $rule['date'] ) ) ) {
            throw new \InvalidArgumentException( __( 'Проверьте тип, даты и время службы.', 'georg-kloster-prihod-schedule' ) );
        }
        $rule['location_id'] = self::location( $rule['location_id'] );
        if ( $rule['timezone'] && ! in_array( $rule['timezone'], timezone_identifiers_list(), true ) ) {
            throw new \InvalidArgumentException( __( 'Укажите именованный часовой пояс.', 'georg-kloster-prihod-schedule' ) );
        }
        if ( ! is_array( $rule['weekdays'] ) || ! is_array( $rule['exceptions'] ) ) { throw new \InvalidArgumentException( __( 'Проверьте повторение и исключения.', 'georg-kloster-prihod-schedule' ) ); }
        $days = array();
        foreach ( $rule['weekdays'] as $day ) {
            if ( ! is_scalar( $day ) || ! preg_match( '/^[1-7]$/D', (string) $day ) ) { throw new \InvalidArgumentException( __( 'Некорректный день недели.', 'georg-kloster-prihod-schedule' ) ); }
            $days[] = (int) $day;
        }
        $rule['weekdays'] = array_values( array_unique( $days ) );
        if ( 'regular' === $rule['kind'] && ! $days ) { throw new \InvalidArgumentException( __( 'Выберите дни повторения.', 'georg-kloster-prihod-schedule' ) ); }
        $exceptions = array();
        if ( count( $rule['exceptions'] ) > 366 ) { throw new \InvalidArgumentException( __( 'Слишком много исключений.', 'georg-kloster-prihod-schedule' ) ); }
        foreach ( $rule['exceptions'] as $original => $change ) {
            if ( ! is_string( $original ) || ! Occurrences::matches( $rule, $original ) || ! is_array( $change ) ) { throw new \InvalidArgumentException( __( 'Исключение должно относиться к существующей дате службы.', 'georg-kloster-prihod-schedule' ) ); }
            if ( 'cancel' === ( $change['action'] ?? '' ) ) { $exceptions[ $original ] = array( 'action' => 'cancel' ); continue; }
            if ( 'color' === ( $change['action'] ?? '' ) ) { $exceptions[$original] = array( 'action' => 'color', 'vestment_color' => Calendar_Colors::validate( $change['vestment_color'] ?? '' ) ); continue; }
            if ( 'replace' !== ( $change['action'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Выберите отмену или перенос.', 'georg-kloster-prihod-schedule' ) ); }
            $replacement = array( 'action' => 'replace', 'date' => $change['date'] ?? $original, 'time' => $change['time'] ?? $rule['time'], 'end' => $change['end'] ?? $rule['end'], 'timezone' => $change['timezone'] ?? $rule['timezone'] );
            foreach ( array( 'date', 'time', 'end', 'timezone' ) as $key ) { if ( ! is_string( $replacement[ $key ] ) ) { throw new \InvalidArgumentException( __( 'Некорректное исключение.', 'georg-kloster-prihod-schedule' ) ); } }
            $replacement['location_id'] = self::location( $change['location_id'] ?? $rule['location_id'] );
            if ( array_key_exists( 'vestment_color', $change ) ) { $replacement['vestment_color'] = Calendar_Colors::validate( $change['vestment_color'] ); }
            if ( $replacement['timezone'] && ! in_array( $replacement['timezone'], timezone_identifiers_list(), true ) ) { throw new \InvalidArgumentException( __( 'Некорректный часовой пояс переноса.', 'georg-kloster-prihod-schedule' ) ); }
            $replacement['timezone'] = self::timezone( $replacement['location_id'], $replacement['timezone'] );
            if ( ! Occurrences::date( $replacement['date'] ) || ! Occurrences::time( $replacement['time'] ) || ( $replacement['end'] && ! Occurrences::time( $replacement['end'] ) )
                || ! Occurrences::instant( $replacement['date'], $replacement['time'], $replacement['timezone'] ) ) {
                throw new \InvalidArgumentException( __( 'Некорректная дата или несуществующее местное время переноса.', 'georg-kloster-prihod-schedule' ) );
            }
            $exceptions[ $original ] = $replacement;
            self::check_interval( $replacement['date'], $replacement['time'], $replacement['end'], $replacement['timezone'] );
        }
        $rule['exceptions'] = $exceptions;
        $zone = self::timezone( $rule['location_id'], $rule['timezone'] );
        if ( 'regular' !== $rule['kind'] && ! Occurrences::instant( $rule['date'], $rule['time'], $zone ) ) {
            throw new \InvalidArgumentException( __( 'Это местное время не существует из-за перевода часов.', 'georg-kloster-prihod-schedule' ) );
        }
        if ( 'regular' !== $rule['kind'] ) { self::check_interval( $rule['date'], $rule['time'], $rule['end'], $zone ); }
        return $rule;
    }
    private static function check_interval( string $date, string $time, string $end, string $zone ): void {
        if ( ! $end ) { return; }
        $end_date = $end <= $time ? ( new \DateTimeImmutable( $date ) )->modify( '+1 day' )->format( 'Y-m-d' ) : $date;
        if ( ! Occurrences::instant( $end_date, $end, $zone ) ) { throw new \InvalidArgumentException( __( 'Время окончания не существует из-за перевода часов.', 'georg-kloster-prihod-schedule' ) ); }
    }
    private static function location( $id ): int {
        if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 || 'gkpsch_location' !== get_post_type( (int) $id ) || in_array( get_post_status( (int) $id ), array( 'trash', 'auto-draft' ), true ) ) {
            throw new \InvalidArgumentException( __( 'Выберите существующее место служения.', 'georg-kloster-prihod-schedule' ) );
        }
        return (int) $id;
    }
    public static function query( \DateTimeImmutable $from, \DateTimeImmutable $to, int $location = 0, bool $cancelled = true ): array {
        if ( ! Modules::enabled( 'schedule' ) ) { return array(); }
        $result = array();
        foreach ( self::published_rules() as $rule ) {
            foreach ( Occurrences::expand( $rule, $from, $to ) as $event ) {
                if ( ( $location && $location !== $event['location_id'] ) || ( ! $cancelled && $event['cancelled'] ) || 'publish' !== get_post_status( $event['location_id'] ) ) { continue; }
                $result[] = $event;
            }
        }
        usort( $result, fn( $a, $b ) => ( $a['start'] <=> $b['start'] ) ?: strcmp( $a['id'], $b['id'] ) );
        return $result;
    }
    private static function published_rules(): \Generator {
        foreach ( get_posts( array( 'post_type' => 'gkpsch_service', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $post ) {
            $data = get_post_meta( $post->ID, '_gkpsch_service', true );
            if ( ! is_array( $data ) || empty( $data['date'] ) ) { continue; }
            $translated = apply_filters( 'wpml_translate_single_string', false, 'georg-kloster-prihod-schedule', 'service-' . $post->ID );
            $title = is_string( $translated ) && '' !== $translated ? $translated : $post->post_title;
            $rule = array_merge( self::defaults(), $data, array( 'id' => $post->ID, 'title' => $title, 'description' => $post->post_content ) );
            $rule['timezone'] = self::timezone( (int) $rule['location_id'], $rule['timezone'] );
            yield $rule;
        }
    }
    /** Include transfers out of the selected period, not just arrivals into it. */
    public static function changes( \DateTimeImmutable $from, \DateTimeImmutable $to, int $location = 0 ): array {
        if ( ! Modules::enabled( 'schedule' ) ) { return array(); }
        $result = array();
        foreach ( self::published_rules() as $rule ) {
            foreach ( array_keys( $rule['exceptions'] ) as $original ) {
                if ( 'color' === $rule['exceptions'][$original]['action'] ) { continue; }
                $event = Occurrences::occurrence( $rule, $original );
                if ( ! $event || 'publish' !== get_post_status( $event['location_id'] ) || 'publish' !== get_post_status( $rule['location_id'] ) ) { continue; }
                $original_start = Occurrences::instant( $original, $rule['time'], $rule['timezone'] );
                $in_original = $original_start && $original_start >= $from && $original_start < $to;
                $in_actual = $event['start'] >= $from && $event['start'] < $to;
                if ( ! $in_original && ! $in_actual ) { continue; }
                if ( $location && $location !== $event['location_id'] && ! ( $in_original && $location === $rule['location_id'] ) ) { continue; }
                $result[] = $event;
            }
        }
        usort( $result, fn( $a, $b ) => ( $a['start'] <=> $b['start'] ) ?: strcmp( $a['id'], $b['id'] ) );
        return $result;
    }
    public static function next( int $location = 0, int $limit = 1 ): array {
        $now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
        return array_slice( self::query( $now, $now->modify( '+366 days' ), $location, false ), 0, max( 1, min( 50, $limit ) ) );
    }
}
