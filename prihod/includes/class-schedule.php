<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Schedule {
    public static function hooks(): void {
        if ( ! Modules::enabled( 'schedule' ) ) { return; }
        add_action( 'init', array( self::class, 'register' ) );
        add_action( 'add_meta_boxes', function () {
            add_meta_box( 'prihod-service', __( 'Расписание службы', 'prihod' ), array( Schedule_Admin::class, 'render' ), 'prihod_service' );
        } );
        add_action( 'save_post_prihod_service', array( Schedule_Admin::class, 'save' ) );
    }
    public static function register(): void {
        $caps = array_map( fn() => 'manage_prihod_schedule', Capabilities::directory() );
        register_post_type( 'prihod_service', array(
            'labels' => array( 'name' => __( 'Расписание', 'prihod' ), 'singular_name' => __( 'Богослужение', 'prihod' ), 'add_new_item' => __( 'Добавить богослужение', 'prihod' ) ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'prihod', 'show_in_rest' => false,
            'rewrite' => false, 'query_var' => false, 'supports' => array( 'title', 'editor', 'revisions' ),
            'capabilities' => $caps, 'map_meta_cap' => false, 'delete_with_user' => false,
        ) );
    }
    public static function defaults(): array {
        return array( 'kind' => 'single', 'date' => '', 'until' => '', 'time' => '09:00', 'end' => '', 'weekdays' => array(), 'location_id' => 0, 'timezone' => '', 'exceptions' => array() );
    }
    public static function timezone( int $location, string $explicit = '' ): string {
        $zone = $explicit ?: ( Directory::details( $location )['timezone'] ?? '' );
        $zone = $zone ?: Profile::get()['timezone'];
        return in_array( $zone, timezone_identifiers_list(), true ) ? $zone : 'UTC';
    }
    public static function validate( array $input ): array {
        $rule = array_merge( self::defaults(), array_intersect_key( $input, self::defaults() ) );
        foreach ( array( 'kind', 'date', 'until', 'time', 'end', 'timezone' ) as $key ) {
            if ( ! is_string( $rule[ $key ] ) ) { throw new \InvalidArgumentException( __( 'Некорректные поля расписания.', 'prihod' ) ); }
        }
        if ( ! in_array( $rule['kind'], array( 'regular', 'single', 'holiday' ), true ) || ! Occurrences::date( $rule['date'] )
            || ! Occurrences::time( $rule['time'] ) || ( $rule['end'] && ! Occurrences::time( $rule['end'] ) )
            || ( $rule['until'] && ( ! Occurrences::date( $rule['until'] ) || $rule['until'] < $rule['date'] ) ) ) {
            throw new \InvalidArgumentException( __( 'Проверьте тип, даты и время службы.', 'prihod' ) );
        }
        $rule['location_id'] = self::location( $rule['location_id'] );
        if ( $rule['timezone'] && ! in_array( $rule['timezone'], timezone_identifiers_list(), true ) ) {
            throw new \InvalidArgumentException( __( 'Укажите именованный часовой пояс.', 'prihod' ) );
        }
        if ( ! is_array( $rule['weekdays'] ) || ! is_array( $rule['exceptions'] ) ) { throw new \InvalidArgumentException( __( 'Проверьте повторение и исключения.', 'prihod' ) ); }
        $days = array();
        foreach ( $rule['weekdays'] as $day ) {
            if ( ! is_scalar( $day ) || ! preg_match( '/^[1-7]$/D', (string) $day ) ) { throw new \InvalidArgumentException( __( 'Некорректный день недели.', 'prihod' ) ); }
            $days[] = (int) $day;
        }
        $rule['weekdays'] = array_values( array_unique( $days ) );
        if ( 'regular' === $rule['kind'] && ! $days ) { throw new \InvalidArgumentException( __( 'Выберите дни повторения.', 'prihod' ) ); }
        $exceptions = array();
        if ( count( $rule['exceptions'] ) > 366 ) { throw new \InvalidArgumentException( __( 'Слишком много исключений.', 'prihod' ) ); }
        foreach ( $rule['exceptions'] as $original => $change ) {
            if ( ! is_string( $original ) || ! Occurrences::matches( $rule, $original ) || ! is_array( $change ) ) { throw new \InvalidArgumentException( __( 'Исключение должно относиться к существующей дате службы.', 'prihod' ) ); }
            if ( 'cancel' === ( $change['action'] ?? '' ) ) { $exceptions[ $original ] = array( 'action' => 'cancel' ); continue; }
            if ( 'replace' !== ( $change['action'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Выберите отмену или перенос.', 'prihod' ) ); }
            $replacement = array( 'action' => 'replace', 'date' => $change['date'] ?? $original, 'time' => $change['time'] ?? $rule['time'], 'end' => $change['end'] ?? $rule['end'], 'timezone' => $change['timezone'] ?? $rule['timezone'] );
            foreach ( array( 'date', 'time', 'end', 'timezone' ) as $key ) { if ( ! is_string( $replacement[ $key ] ) ) { throw new \InvalidArgumentException( __( 'Некорректное исключение.', 'prihod' ) ); } }
            $replacement['location_id'] = self::location( $change['location_id'] ?? $rule['location_id'] );
            $replacement['timezone'] = self::timezone( $replacement['location_id'], $replacement['timezone'] );
            if ( ! Occurrences::date( $replacement['date'] ) || ! Occurrences::time( $replacement['time'] ) || ( $replacement['end'] && ! Occurrences::time( $replacement['end'] ) )
                || ! Occurrences::instant( $replacement['date'], $replacement['time'], $replacement['timezone'] ) ) {
                throw new \InvalidArgumentException( __( 'Некорректная дата или несуществующее местное время переноса.', 'prihod' ) );
            }
            $exceptions[ $original ] = $replacement;
        }
        $rule['exceptions'] = $exceptions;
        $zone = self::timezone( $rule['location_id'], $rule['timezone'] );
        if ( 'regular' !== $rule['kind'] && ! Occurrences::instant( $rule['date'], $rule['time'], $zone ) ) {
            throw new \InvalidArgumentException( __( 'Это местное время не существует из-за перевода часов.', 'prihod' ) );
        }
        return $rule;
    }
    private static function location( $id ): int {
        if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 || 'prihod_location' !== get_post_type( (int) $id ) || in_array( get_post_status( (int) $id ), array( 'trash', 'auto-draft' ), true ) ) {
            throw new \InvalidArgumentException( __( 'Выберите существующее место служения.', 'prihod' ) );
        }
        return (int) $id;
    }
    public static function query( \DateTimeImmutable $from, \DateTimeImmutable $to, int $location = 0, bool $cancelled = true ): array {
        if ( ! Modules::enabled( 'schedule' ) ) { return array(); }
        $result = array();
        foreach ( get_posts( array( 'post_type' => 'prihod_service', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $post ) {
            $data = get_post_meta( $post->ID, '_prihod_service', true );
            if ( ! is_array( $data ) || empty( $data['date'] ) ) { continue; }
            $rule = array_merge( self::defaults(), $data, array( 'id' => $post->ID, 'title' => $post->post_title, 'description' => $post->post_content ) );
            $rule['timezone'] = self::timezone( (int) $rule['location_id'], $rule['timezone'] );
            foreach ( Occurrences::expand( $rule, $from, $to ) as $event ) {
                if ( ( $location && $location !== $event['location_id'] ) || ( ! $cancelled && $event['cancelled'] ) || 'publish' !== get_post_status( $event['location_id'] ) ) { continue; }
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
