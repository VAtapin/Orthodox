<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Public_View {
    public static function blocks(): array {
        return array( 'profile' => __( 'Сведения о приходе', 'prihod' ), 'contacts' => __( 'Контакты прихода', 'prihod' ), 'clergy' => __( 'Духовенство', 'prihod' ), 'schedule' => __( 'Расписание богослужений', 'prihod' ), 'next-service' => __( 'Ближайшее богослужение', 'prihod' ), 'next-services' => __( 'Ближайшие службы', 'prihod' ), 'requests' => __( 'Вопрос священнику', 'prihod' ) );
    }
    public static function register(): void {
        wp_register_script( 'prihod-blocks', plugins_url( 'assets/blocks.js', PRIHOD_FILE ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ), PRIHOD_VERSION, true );
        wp_register_style( 'prihod-public', plugins_url( 'assets/public.css', PRIHOD_FILE ), array(), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/public.css' ) );
        wp_set_script_translations( 'prihod-blocks', 'prihod', dirname( PRIHOD_FILE ) . '/languages' );
        add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'prihod-public' ); } );
        $blocks = self::blocks();
        wp_localize_script( 'prihod-blocks', 'prihodBlocks', $blocks );
        foreach ( $blocks as $key => $title ) {
            register_block_type( 'prihod/' . $key, array(
                'api_version' => 3, 'title' => $title, 'category' => 'widgets', 'editor_script' => 'prihod-blocks', 'style' => 'prihod-public',
                'attributes' => array( 'view' => array( 'type' => 'string', 'default' => 'week' ), 'location' => array( 'type' => 'integer', 'default' => 0 ), 'limit' => array( 'type' => 'integer', 'default' => 5 ) ),
                'render_callback' => fn( $attributes ) => self::render( $key, $attributes ),
            ) );
            add_shortcode( 'prihod_' . str_replace( '-', '_', $key ), fn( $attributes ) => self::render( $key, is_array( $attributes ) ? $attributes : array() ) );
        }
    }
    public static function render( string $key, array $attributes = array() ): string {
        wp_enqueue_style( 'prihod-public' );
        if ( 'requests' === $key ) { return Request_Form::render(); }
        $profile = Profile::get();
        $html = '';
        if ( 'profile' === $key ) {
            $html = '<h2>' . esc_html( $profile['name'] ) . '</h2><p>' . nl2br( esc_html( $profile['description'] ) ) . '</p>';
        } elseif ( 'contacts' === $key ) {
            foreach ( array( 'address', 'phone', 'email', 'website' ) as $field ) {
                if ( ! $profile[ $field ] ) { continue; }
                $value = esc_html( $profile[ $field ] );
                if ( 'email' === $field ) { $value = '<a href="' . esc_url( 'mailto:' . $profile[ $field ] ) . '">' . $value . '</a>'; }
                if ( 'website' === $field ) { $value = '<a href="' . esc_url( $profile[ $field ] ) . '">' . $value . '</a>'; }
                $html .= '<p>' . esc_html( Profile::fields()[ $field ] ) . ': ' . $value . '</p>';
            }
        } elseif ( 'clergy' === $key ) {
            foreach ( get_posts( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $post ) {
                $details = Directory::details( $post->ID );
                $html .= '<article><h3>' . esc_html( $post->post_title ) . '</h3><p>' . esc_html( $details['rank'] ?? '' ) . '</p>' . wpautop( wp_kses_post( $post->post_content ) ) . '</article>';
            }
        } else {
            if ( ! Modules::enabled( 'schedule' ) ) { return ''; }
            $location = absint( $attributes['location'] ?? 0 );
            if ( 'schedule' === $key ) {
                $requested_view = isset( $_GET['prihod_view'] ) && is_string( $_GET['prihod_view'] ) ? sanitize_key( $_GET['prihod_view'] ) : ( $attributes['view'] ?? 'week' );
                $view = in_array( $requested_view, array( 'week', 'month', 'list' ), true ) ? $requested_view : 'week';
                $now = new \DateTimeImmutable( 'now', new \DateTimeZone( $profile['timezone'] ) );
                $date = isset( $_GET['prihod_date'] ) && is_string( $_GET['prihod_date'] ) ? sanitize_text_field( wp_unslash( $_GET['prihod_date'] ) ) : $now->format( 'Y-m-d' );
                if ( ! Occurrences::date( $date ) ) { $date = $now->format( 'Y-m-d' ); }
                $anchor = new \DateTimeImmutable( $date, $now->getTimezone() );
                $from = 'month' === $view ? $anchor->modify( 'first day of this month' ) : $anchor->modify( '-' . ( (int) $anchor->format( 'N' ) - 1 ) . ' days' );
                $to = $from->modify( 'month' === $view ? '+1 month' : '+7 days' );
                $period = '<nav class="prihod-period-nav" aria-label="' . esc_attr__( 'Период расписания', 'prihod' ) . '"><a href="' . esc_url( add_query_arg( array( 'prihod_date' => $from->modify( 'month' === $view ? '-1 month' : '-7 days' )->format( 'Y-m-d' ), 'prihod_view' => $view ) ) ) . '">' . esc_html__( 'Предыдущий период', 'prihod' ) . '</a> · <a href="' . esc_url( add_query_arg( array( 'prihod_date' => $now->format( 'Y-m-d' ), 'prihod_view' => $view ) ) ) . '">' . esc_html__( 'Сегодня', 'prihod' ) . '</a> · <a href="' . esc_url( add_query_arg( array( 'prihod_date' => $to->format( 'Y-m-d' ), 'prihod_view' => $view ) ) ) . '">' . esc_html__( 'Следующий период', 'prihod' ) . '</a></nav>';
                if ( ! $location && isset( $_GET['prihod_place'] ) && is_scalar( $_GET['prihod_place'] ) ) { $location = absint( $_GET['prihod_place'] ); }
                $html .= self::schedule_overview( $location, $from, $to ) . '<div class="prihod-schedule-body"><div class="prihod-schedule-toolbar"><nav class="prihod-view-switch" aria-label="' . esc_attr__( 'Вид расписания', 'prihod' ) . '">';
                foreach ( array( 'week' => __( 'Неделя', 'prihod' ), 'month' => __( 'Месяц', 'prihod' ), 'list' => __( 'Список', 'prihod' ) ) as $mode => $label ) { $html .= '<a href="' . esc_url( add_query_arg( 'prihod_view', $mode ) ) . '"' . ( $mode === $view ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>'; }
                $html .= '</nav><form method="get" class="prihod-controls"><input type="hidden" name="prihod_view" value="' . esc_attr( $view ) . '"><label>' . esc_html__( 'Дата', 'prihod' ) . ' <input type="date" name="prihod_date" value="' . esc_attr( $date ) . '"></label> ';
                // Preserve WordPress plain-permalink routing without copying arbitrary input.
                foreach ( array( 'page_id', 'p' ) as $query_key ) { if ( isset( $_GET[ $query_key ] ) && is_scalar( $_GET[ $query_key ] ) ) { $html .= '<input type="hidden" name="' . esc_attr( $query_key ) . '" value="' . esc_attr( absint( $_GET[ $query_key ] ) ) . '">'; } }
                if ( function_exists( 'prihod_theme_preview_scheme' ) && prihod_theme_preview_scheme() ) { $html .= '<input type="hidden" name="prihod_preview" value="' . esc_attr( prihod_theme_preview_scheme() ) . '">'; }
                if ( ! absint( $attributes['location'] ?? 0 ) ) {
                    $html .= '<label>' . esc_html__( 'Место', 'prihod' ) . ' <select name="prihod_place"><option value="0">' . esc_html__( 'Все места', 'prihod' ) . '</option>';
                    foreach ( get_posts( array( 'post_type' => 'prihod_location', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $place ) { $html .= '<option value="' . esc_attr( $place->ID ) . '"' . selected( $location, $place->ID, false ) . '>' . esc_html( $place->post_title ) . '</option>'; }
                    $html .= '</select></label> ';
                }
                $html .= '<button type="submit">' . esc_html__( 'Показать', 'prihod' ) . '</button> <button type="button" class="prihod-print">' . esc_html__( 'Печать', 'prihod' ) . '</button></form></div>' . $period . '<h3 class="prihod-range-title">' . esc_html( $from->format( 'd.m.Y' ) . ' — ' . $to->modify( '-1 day' )->format( 'd.m.Y' ) ) . '</h3>';
                wp_enqueue_script( 'prihod-public', plugins_url( 'assets/public.js', PRIHOD_FILE ), array(), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/public.js' ), true );
                $events = Schedule::query( $from, $to, $location );
            } else { $events = Schedule::next( $location, 'next-service' === $key ? 1 : absint( $attributes['limit'] ?? 5 ) ); }
            if ( ! $events ) { $html .= '<p>' . esc_html__( 'Богослужения на выбранный период не указаны.', 'prihod' ) . '</p>'; }
            if ( 'schedule' === $key && 'month' === $view ) { $html .= self::month( $events, $from, $to, $anchor->format( 'Y-m-d' ) ); }
            elseif ( 'schedule' === $key && 'week' === $view ) { $html .= '<div class="prihod-schedule-columns">' . self::week( $events, $from, $to ) . self::week_sidebar( $anchor, $location, $from ) . '</div>'; }
            else { foreach ( $events as $event ) { $html .= self::event_html( $event ); } }
            if ( 'schedule' === $key ) { $html .= '</div>'; }
        }
        return '<section class="prihod-block prihod-' . esc_attr( $key ) . '">' . $html . '</section>';
    }
    private static function event_html( array $event ): string {
        $html = '<article class="prihod-event' . ( $event['cancelled'] ? ' is-cancelled' : ( $event['moved'] ? ' is-moved' : '' ) ) . '"><div class="prihod-event-time"><time datetime="' . esc_attr( $event['start']->format( DATE_ATOM ) ) . '">' . esc_html( $event['start']->format( 'H:i' ) ) . '</time>';
        if ( $event['end'] ) { $html .= '<small>— ' . esc_html( $event['end']->format( $event['end']->format( 'Y-m-d' ) === $event['start']->format( 'Y-m-d' ) ? 'H:i' : 'd.m.Y H:i' ) ) . '</small>'; }
        $html .= Schedule_Calendar::icon_placeholder( $event ) . '</div><div class="prihod-event-copy"><h3>' . esc_html( $event['title'] ) . '</h3>' . Schedule_Calendar::feast_placeholder( $event ) . '<p class="prihod-event-meta">' . esc_html( $event['start']->format( 'd.m.Y' ) . ' · ' . $event['start']->getTimezone()->getName() ) . '</p><p>' . esc_html( get_the_title( $event['location_id'] ) ) . '</p>';
        if ( $event['cancelled'] ) { $html .= '<strong>' . esc_html__( 'Отменено', 'prihod' ) . '</strong>'; }
        if ( $event['moved'] ) { $html .= '<p>' . esc_html__( 'Изменение службы за', 'prihod' ) . ' ' . esc_html( $event['original_date'] ) . '</p>'; }
        if ( trim( $event['description'] ) ) { $html .= '<details class="prihod-event-description"><summary>' . esc_html__( 'Подробнее о богослужении', 'prihod' ) . '</summary>' . wpautop( wp_kses_post( $event['description'] ) ) . '</details>'; }
        $html .= '</div></article>';
        return $html;
    }
    private static function month( array $events, \DateTimeImmutable $from, \DateTimeImmutable $to, string $selected = '' ): string {
        $by_day = array();
        foreach ( $events as $event ) { $by_day[ $event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' ) ][] = $event; }
        $months = array( __( 'Январь', 'prihod' ), __( 'Февраль', 'prihod' ), __( 'Март', 'prihod' ), __( 'Апрель', 'prihod' ), __( 'Май', 'prihod' ), __( 'Июнь', 'prihod' ), __( 'Июль', 'prihod' ), __( 'Август', 'prihod' ), __( 'Сентябрь', 'prihod' ), __( 'Октябрь', 'prihod' ), __( 'Ноябрь', 'prihod' ), __( 'Декабрь', 'prihod' ) );
        $html = '<table class="prihod-month"><caption>' . esc_html( $months[ (int) $from->format( 'n' ) - 1 ] . ' ' . $from->format( 'Y' ) ) . '</caption><thead><tr>';
        foreach ( self::weekdays() as $label ) { $html .= '<th scope="col">' . esc_html( $label ) . '</th>'; }
        $html .= '</tr></thead><tbody><tr>'; $offset = (int) $from->format( 'N' ) - 1;
        $panels = '<div class="prihod-month-panels">'; $prefix = wp_unique_id( 'prihod-month-' );
        for ( $blank = 0; $blank < $offset; $blank++ ) { $html .= '<td></td>'; }
        $today = ( new \DateTimeImmutable( 'now', $from->getTimezone() ) )->format( 'Y-m-d' );
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) {
            $key = $date->format( 'Y-m-d' ); $items = $by_day[$key] ?? array(); $target = $prefix . $key;
            $count = sprintf( __( 'Богослужений: %d', 'prihod' ), count( $items ) );
            $html .= '<td' . ( $key === $today ? ' class="is-today"' : '' ) . '><time datetime="' . esc_attr( $key ) . '">' . esc_html( $date->format( 'j' ) ) . '</time><a class="prihod-month-day-choice' . ( $items ? ' has-services' : '' ) . '" href="#' . esc_attr( $target ) . '" data-prihod-month-day="' . esc_attr( $key ) . '" aria-label="' . esc_attr( $date->format( 'd.m.Y' ) . ' · ' . $count ) . '"' . ( $key === $selected ? ' aria-current="date"' : '' ) . '>' . esc_html( $date->format( 'j' ) ) . ( $items ? '<small>' . esc_html( count( $items ) ) . '</small>' : '' ) . '</a>';
            $panels .= '<details class="prihod-month-day-panel" id="' . esc_attr( $target ) . '"' . ( $key === $selected ? ' open' : '' ) . '><summary>' . esc_html( $date->format( 'd.m.Y' ) . ' · ' . self::weekdays()[ (int) $date->format( 'N' ) - 1 ] . ' · ' . $count ) . '</summary>';
            foreach ( $items as $event ) { $panels .= self::event_html( $event ); }
            if ( ! $items ) { $panels .= '<p>' . esc_html__( 'Богослужения на эту дату не указаны.', 'prihod' ) . '</p>'; }
            $panels .= '</details>';
            foreach ( $by_day[ $key ] ?? array() as $event ) { $html .= self::event_html( $event ); }
            $html .= '</td>'; ++$offset;
            if ( 0 === $offset % 7 && $date->modify( '+1 day' ) < $to ) { $html .= '</tr><tr>'; }
        }
        while ( 0 !== $offset % 7 ) { $html .= '<td></td>'; ++$offset; }
        return $html . '</tr></tbody></table>' . $panels . '</div>';
    }
    private static function week( array $events, \DateTimeImmutable $from, \DateTimeImmutable $to ): string {
        $by_day = array();
        foreach ( $events as $event ) { $by_day[ $event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' ) ][] = $event; }
        $html = '<div class="prihod-week">';
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) {
            $items = $by_day[ $date->format( 'Y-m-d' ) ] ?? array();
            if ( ! $items ) { continue; }
            $html .= '<div class="prihod-week-day"><h4><time datetime="' . esc_attr( $date->format( 'Y-m-d' ) ) . '">' . esc_html( self::weekdays()[ (int) $date->format( 'N' ) - 1 ] ) . '<strong>' . esc_html( $date->format( 'j' ) ) . '</strong></time></h4><div>';
            foreach ( $items as $event ) { $html .= self::event_html( $event ); }
            $html .= '</div></div>';
        }
        return $html . '</div>';
    }
    private static function schedule_overview( int $location, \DateTimeImmutable $from, \DateTimeImmutable $to ): string {
        $html = '<div class="prihod-schedule-overview"><div class="prihod-upcoming-card"><h3>' . esc_html__( 'Ближайшая служба', 'prihod' ) . '</h3>';
        $next = Schedule::next( $location, 1 );
        if ( $next ) {
            $event = $next[0];
            $html .= Schedule_Calendar::icon_placeholder( $event );
            $html .= '<p class="prihod-overview-date"><time datetime="' . esc_attr( $event['start']->format( DATE_ATOM ) ) . '">' . esc_html( wp_date( 'j F · H:i', $event['start']->getTimestamp(), $event['start']->getTimezone() ) ) . '</time></p><strong>' . esc_html( $event['title'] ) . '</strong>' . Schedule_Calendar::feast_placeholder( $event ) . '<p>' . esc_html( get_the_title( $event['location_id'] ) ) . '<small>' . esc_html( $event['start']->getTimezone()->getName() ) . '</small></p>';
            $resources = get_option( 'prihod_resources', array() );
            $id = absint( $resources['contacts']['id'] ?? 0 );
            if ( $id && 'publish' === get_post_status( $id ) ) {
                $url = get_permalink( $id );
                if ( function_exists( 'prihod_theme_preview_url' ) ) { $url = prihod_theme_preview_url( $url ); }
                $html .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Как добраться', 'prihod' ) . ' →</a>';
            }
        } else { $html .= '<p>' . esc_html__( 'Ближайшая служба пока не указана.', 'prihod' ) . '</p>'; }
        $html .= '</div>';
        $changes = Schedule::changes( $from, $to, $location );
        if ( $changes ) {
            $html .= '<div class="prihod-changes-card"><h3>' . esc_html__( 'Изменения в расписании', 'prihod' ) . '</h3>';
            $html .= '<ul>';
            foreach ( $changes as $event ) {
                $original = new \DateTimeImmutable( $event['original_date'] );
                $html .= '<li class="' . ( $event['cancelled'] ? 'is-cancelled' : 'is-moved' ) . '"><strong>' . esc_html( $event['cancelled'] ? __( 'Отменено', 'prihod' ) : __( 'Перенос / изменение времени', 'prihod' ) ) . '</strong> · ' . esc_html( $original->format( 'd.m.Y' ) . ' · ' . $event['title'] );
                if ( ! $event['cancelled'] ) { $html .= '<small>→ ' . esc_html( $event['start']->format( 'd.m.Y H:i' ) . ' · ' . $event['start']->getTimezone()->getName() . ' · ' . get_the_title( $event['location_id'] ) ) . '</small>'; }
                $html .= '</li>';
            }
            $html .= '</ul></div>';
        }
        return $html . '</div>';
    }
    private static function week_sidebar( \DateTimeImmutable $anchor, int $location, \DateTimeImmutable $week_from ): string {
        $from = $anchor->modify( 'first day of this month' ); $to = $from->modify( '+1 month' );
        $events = Schedule::query( $from, $to, $location ); $days = array();
        foreach ( $events as $event ) { $key = $event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' ); $days[$key][$event['cancelled'] ? 'has-cancellations' : 'has-services'] = true; }
        foreach ( Schedule::changes( $from, $to, $location ) as $event ) {
            $days[$event['original_date']][$event['cancelled'] ? 'has-cancellations' : 'has-moves'] = true;
            if ( $event['moved'] ) { $days[$event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' )]['has-moves'] = true; }
        }
        $today = ( new \DateTimeImmutable( 'now', $from->getTimezone() ) )->format( 'Y-m-d' );
        $week_to = $week_from->modify( '+7 days' );
        $labels = array( 'has-services' => __( 'Богослужения', 'prihod' ), 'has-cancellations' => __( 'Отмена', 'prihod' ), 'has-moves' => __( 'Перенос / изменение времени', 'prihod' ) );
        $html = '<aside class="prihod-schedule-aside"><div class="prihod-mini-month"><h3>' . esc_html( wp_date( 'F Y', $from->getTimestamp(), $from->getTimezone() ) ) . '</h3><div class="prihod-mini-grid">';
        foreach ( self::weekdays() as $day ) { $html .= '<span>' . esc_html( $day ) . '</span>'; }
        for ( $i = 1; $i < (int) $from->format( 'N' ); ++$i ) { $html .= '<span></span>'; }
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) {
            $key = $date->format( 'Y-m-d' );
            $classes = array_keys( $days[$key] ?? array() );
            $label = wp_date( 'j F Y', $date->getTimestamp(), $date->getTimezone() );
            foreach ( $classes as $class ) { $label .= ' · ' . $labels[$class]; }
            if ( $date >= $week_from && $date < $week_to ) { $classes[] = 'is-selected-week'; $label .= ' · ' . __( 'Выбранная неделя', 'prihod' ); }
            if ( $key === $today ) { $classes[] = 'is-today'; $label .= ' · ' . __( 'Сегодня', 'prihod' ); }
            $html .= '<a class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $key === $today ? ' aria-current="date"' : '' ) . ' href="' . esc_url( add_query_arg( array( 'prihod_date' => $key, 'prihod_view' => 'week' ) ) ) . '">' . esc_html( $date->format( 'j' ) ) . '</a>';
        }
        $html .= '</div><div class="prihod-mini-legend"><span class="is-today">' . esc_html__( 'Сегодня', 'prihod' ) . '</span><span class="is-selected-week">' . esc_html__( 'Выбранная неделя', 'prihod' ) . '</span><span class="has-services">' . esc_html__( 'Богослужения', 'prihod' ) . '</span></div></div><div class="prihod-visit-card"><h3>' . esc_html__( 'Перед посещением', 'prihod' ) . '</h3><p>' . esc_html__( 'Проверьте дату, время и место богослужения. Изменения и отмены отмечены в расписании.', 'prihod' ) . '</p>';
        $resources = get_option( 'prihod_resources', array() );
        foreach ( array( 'first-visit' => __( 'Первый визит', 'prihod' ), 'requests' => __( 'Задать вопрос', 'prihod' ) ) as $key => $label ) {
            $id = absint( $resources[$key]['id'] ?? 0 );
            if ( $id && 'publish' === get_post_status( $id ) ) { $url = get_permalink( $id ); if ( function_exists( 'prihod_theme_preview_url' ) ) { $url = prihod_theme_preview_url( $url ); } $html .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; }
        }
        return $html . '</div></aside>';
    }
    private static function weekdays(): array {
        return array( __( 'Пн', 'prihod' ), __( 'Вт', 'prihod' ), __( 'Ср', 'prihod' ), __( 'Чт', 'prihod' ), __( 'Пт', 'prihod' ), __( 'Сб', 'prihod' ), __( 'Вс', 'prihod' ) );
    }

}
