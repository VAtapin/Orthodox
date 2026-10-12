<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Wizard_Interview {
    public static function resume( array $state ): array {
        if ( ! isset( $state['stage'] ) ) {
            $step = (int) ( $state['step'] ?? 0 );
            $started = ! empty( $state['started'] ) || $step > 0 || ( $state['revision'] ?? 0 ) > 0;
            $state['stage'] = ! $started ? 0 : ( $step <= 5 ? 1 : ( $step <= 10 ? 2 : ( 11 === $step ? 3 : ( 12 === $step ? 4 : 5 ) ) ) );
        }
        $state['stage'] = max( 0, min( 5, (int) $state['stage'] ) );
        return $state;
    }
    public static function features( array $answers ): array {
        return array_merge( array( 'schedule' => ! empty( $answers['modules']['schedule'] ), 'requests' => ! empty( $answers['modules']['requests'] ), 'publications' => ! empty( $answers['publications'] ), 'clergy' => true, 'locations' => true, 'integrations' => false ), $answers['features'] ?? array() );
    }
    public static function plan_answers( array $answers ): array {
        $features = self::features( $answers );
        foreach ( array( 'clergy', 'locations' ) as $key ) { if ( ! $features[$key] ) { $answers[$key] = array(); } }
        foreach ( array( 'schedule', 'requests', 'clergy', 'news', 'sermons' ) as $key ) {
            $feature = in_array( $key, array( 'news', 'sermons' ), true ) ? 'publications' : $key;
            if ( ! $features[$feature] ) { unset( $answers['pages'][$key] ); }
        }
        if ( ! $features['requests'] ) { $answers['responsible'] = 0; }
        return $answers;
    }
    public static function advance( array $state, string $operation, array $input ): array {
        $state = self::resume( $state ); $stage = $state['stage'];
        if ( 'start' === $operation || 'restart' === $operation ) { $state['stage'] = 1; $state['started'] = true; unset( $state['plan'] ); }
        elseif ( 'apply' === $operation ) {
            if ( 4 !== $stage || '1' !== ( $input['confirm'] ?? '' ) || empty( $state['plan'] ) ) { throw new \RuntimeException( __( 'Сначала подтвердите план.', 'prihod' ) ); }
            Site_Plan::apply( $state['plan'] ); $state['stage'] = 5;
        } elseif ( 'publish' === $operation ) {
            if ( 5 !== $stage || '1' !== ( $input['confirm'] ?? '' ) || ! is_array( $input['publish_keys'] ?? null ) ) { throw new \RuntimeException( __( 'Подтвердите публикацию.', 'prihod' ) ); }
            $location = is_string( $input['menu_location'] ?? null ) ? sanitize_key( $input['menu_location'] ) : '';
            Site_Plan::publish( $input['publish_keys'], ! empty( $input['set_front'] ), $location, ! empty( $input['navigation'] ) );
        } elseif ( in_array( $operation, array( 'next', 'skip', 'back' ), true ) ) {
            if ( 'back' !== $operation && ! in_array( $stage, array( 1, 2, 3 ), true ) ) { throw new \RuntimeException( __( 'Требуется подтверждение.', 'prihod' ) ); }
            if ( 'back' === $operation && 1 === $stage ) {
                if ( is_array( $input['profile'] ?? null ) ) { $state['profile_input'] = array_intersect_key( array_filter( $input['profile'], 'is_string' ), Profile::defaults() ); }
            } elseif ( 'skip' !== $operation ) { $state['answers'] = self::answers( $state['answers'], $stage, $input ); }
            if ( 'next' === $operation && 1 === $stage ) { unset( $state['profile_input'] ); }
            $state['stage'] = 'back' === $operation ? max( 0, $stage - 1 ) : $stage + 1;
            if ( 4 === $state['stage'] ) { $state['plan'] = Site_Plan::plan( self::plan_answers( $state['answers'] ) ); }
            else { unset( $state['plan'] ); }
        } else { throw new \RuntimeException( __( 'Некорректное действие.', 'prihod' ) ); }
        $state['step'] = array( 0, 0, 6, 11, 12, 13 )[$state['stage']];
        return $state;
    }
    private static function answers( array $answers, int $stage, array $input ): array {
        if ( 1 === $stage && isset( $input['profile'] ) ) {
            if ( ! is_array( $input['profile'] ) ) { throw new \InvalidArgumentException( __( 'Некорректные сведения о приходе.', 'prihod' ) ); }
            $errors_before = count( get_settings_errors( Profile::OPTION ) );
            $answers['profile'] = Profile::sanitize( array_merge( $answers['profile'], $input['profile'] ) );
            $errors = get_settings_errors( Profile::OPTION );
            if ( count( $errors ) > $errors_before ) { throw new \InvalidArgumentException( $errors[$errors_before]['message'] ); }
        }
        if ( 2 === $stage && isset( $input['features_present'] ) ) {
            $features = is_array( $input['features'] ?? null ) ? $input['features'] : array();
            foreach ( array_keys( self::features( $answers ) ) as $key ) { $answers['features'][$key] = ! empty( $features[$key] ); }
            $answers['modules'] = array( 'schedule' => $answers['features']['schedule'], 'requests' => $answers['features']['requests'] );
            $answers['publications'] = $answers['features']['publications'];
            foreach ( array( 'locations', 'clergy' ) as $key ) {
                if ( $answers['features'][$key] ) {
                    $rows = is_array( $input[$key] ?? null ) ? array_slice( $input[$key], 0, 100 ) : array();
                    foreach ( $rows as &$row ) {
                        if ( ! is_array( $row ) ) { throw new \InvalidArgumentException( __( 'Некорректное действие.', 'prihod' ) ); }
                        foreach ( array( 'name', 'locations' === $key ? 'address' : 'rank' ) as $field ) {
                            if ( ! is_string( $row[$field] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Некорректное действие.', 'prihod' ) ); }
                            $row[$field] = 'address' === $field ? sanitize_textarea_field( $row[$field] ?? '' ) : sanitize_text_field( $row[$field] ?? '' );
                        }
                    }
                    unset( $row ); $answers[$key] = $rows;
                }
            }
            if ( $answers['features']['schedule'] ) {
                $answers['service'] = array();
                foreach ( array( 'title', 'date', 'time', 'location_id' ) as $key ) {
                    if ( ! is_string( $input['service'][$key] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Некорректные сведения о службе.', 'prihod' ) ); }
                    $answers['service'][$key] = sanitize_text_field( $input['service'][$key] ?? '' );
                }
                if ( $answers['service']['title'] ) {
                    if ( ! Occurrences::date( $answers['service']['date'] ) || ! Occurrences::time( $answers['service']['time'] ) ) { throw new \InvalidArgumentException( __( 'Проверьте тип, даты и время службы.', 'prihod' ) ); }
                    if ( absint( $answers['service']['location_id'] ) ) { Schedule::validate( array( 'date' => $answers['service']['date'], 'time' => $answers['service']['time'], 'location_id' => absint( $answers['service']['location_id'] ) ) ); }
                }
            }
            if ( $answers['features']['requests'] ) { $answers['responsible'] = is_scalar( $input['responsible'] ?? null ) ? absint( $input['responsible'] ) : 0; }
            $answers['places_mode'] = 'multiple' === ( $input['places_mode'] ?? '' ) ? 'multiple' : 'one';
        }
        if ( 3 === $stage && isset( $input['pages_present'] ) ) {
            $answers['pages'] = is_array( $input['pages'] ?? null ) ? $input['pages'] : array();
            $answers['reuse'] = is_array( $input['reuse'] ?? null ) ? $input['reuse'] : array();
            foreach ( array( 'theme_choice', 'scheme_choice' ) as $key ) {
                if ( isset( $input[$key] ) ) { if ( ! is_string( $input[$key] ) ) { throw new \InvalidArgumentException( __( 'Некорректное действие.', 'prihod' ) ); } $answers[$key] = sanitize_key( $input[$key] ); }
            }
        }
        return $answers;
    }
}
