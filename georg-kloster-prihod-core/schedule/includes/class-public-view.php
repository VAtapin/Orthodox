<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

final class Public_View {
    public static function blocks(): array {
        return array( 'schedule' => __( 'Service schedule', 'georg-kloster-prihod-schedule' ), 'next-service' => __( 'Next service', 'georg-kloster-prihod-schedule' ), 'next-services' => __( 'Upcoming services', 'georg-kloster-prihod-schedule' ) );
    }
    public static function register(): void {
        wp_register_script( 'gkpsch-blocks', plugins_url( 'assets/blocks.js', GKPSCH_FILE ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ), GKPSCH_VERSION, true );
        wp_register_style( 'gkpsch-public', plugins_url( 'assets/public.css', GKPSCH_FILE ), array(), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/public.css' ) );
        wp_set_script_translations( 'gkpsch-blocks', 'georg-kloster-prihod-schedule', dirname( GKPSCH_FILE ) . '/languages' );
        add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'gkpsch-public' ); } );
        $blocks = self::blocks();
        wp_localize_script( 'gkpsch-blocks', 'gkpschBlocks', $blocks );
        foreach ( $blocks as $key => $title ) {
            register_block_type( 'gkpsch/' . $key, array(
                'api_version' => 3, 'title' => $title, 'category' => 'widgets', 'editor_script' => 'gkpsch-blocks', 'style' => 'gkpsch-public',
                'attributes' => array( 'view' => array( 'type' => 'string', 'default' => 'week' ), 'location' => array( 'type' => 'integer', 'default' => 0 ), 'limit' => array( 'type' => 'integer', 'default' => 5 ) ),
                'render_callback' => fn( $attributes ) => self::render( $key, $attributes ),
            ) );
            add_shortcode( 'gkpsch_' . str_replace( '-', '_', $key ), fn( $attributes ) => self::render( $key, is_array( $attributes ) ? $attributes : array() ) );
        }
    }
    public static function render( string $key, array $attributes = array() ): string {
        wp_enqueue_style( 'gkpsch-public' );
        $profile = Profile::get();
        $html = '';
        {
            if ( ! Modules::enabled( 'schedule' ) ) { return ''; }
            $fixed_location = absint( $attributes['location'] ?? 0 );
            $location = $fixed_location;
            if ( 'schedule' === $key ) {
                $places = $fixed_location ? array() : get_posts( array( 'post_type' => \GeorgKlosterPrihodCore\Directory::location_types(), 'post_status' => 'publish', 'numberposts' => -1 ) );
                $show_location_filter = self::location_filter_visible( count( $places ), $fixed_location );
                $requested_view = isset( $_GET['gkpsch_view'] ) && is_string( $_GET['gkpsch_view'] ) ? sanitize_key( $_GET['gkpsch_view'] ) : ( $attributes['view'] ?? 'week' );
                $view = in_array( $requested_view, array( 'week', 'month', 'list' ), true ) ? $requested_view : 'week';
                $now = new \DateTimeImmutable( 'now', new \DateTimeZone( $profile['timezone'] ) );
                $date = isset( $_GET['gkpsch_date'] ) && is_string( $_GET['gkpsch_date'] ) ? sanitize_text_field( wp_unslash( $_GET['gkpsch_date'] ) ) : $now->format( 'Y-m-d' );
                if ( ! Occurrences::date( $date ) ) { $date = $now->format( 'Y-m-d' ); }
                $anchor = new \DateTimeImmutable( $date, $now->getTimezone() );
                $from = 'month' === $view ? $anchor->modify( 'first day of this month' ) : $anchor->modify( '-' . ( (int) $anchor->format( 'N' ) - 1 ) . ' days' );
                $to = $from->modify( 'month' === $view ? '+1 month' : '+7 days' );
                $period = '<nav class="gkpsch-period-nav" aria-label="' . esc_attr__( 'Schedule period', 'georg-kloster-prihod-schedule' ) . '"><a href="' . esc_url( add_query_arg( array( 'gkpsch_date' => $from->modify( 'month' === $view ? '-1 month' : '-7 days' )->format( 'Y-m-d' ), 'gkpsch_view' => $view ) ) ) . '">' . esc_html__( 'Previous period', 'georg-kloster-prihod-schedule' ) . '</a> · <a href="' . esc_url( add_query_arg( array( 'gkpsch_date' => $now->format( 'Y-m-d' ), 'gkpsch_view' => $view ) ) ) . '">' . esc_html__( 'Today', 'georg-kloster-prihod-schedule' ) . '</a> · <a href="' . esc_url( add_query_arg( array( 'gkpsch_date' => $to->format( 'Y-m-d' ), 'gkpsch_view' => $view ) ) ) . '">' . esc_html__( 'Next period', 'georg-kloster-prihod-schedule' ) . '</a></nav>';
                if ( $show_location_filter && isset( $_GET['gkpsch_place'] ) && is_scalar( $_GET['gkpsch_place'] ) ) {
                    $requested_place = absint( $_GET['gkpsch_place'] );
                    foreach ( $places as $place ) { if ( (int) $place->ID === $requested_place ) { $location = $requested_place; break; } }
                }
                $media_place = $location;
                if ( ! $media_place ) {
                    $media_places = \GeorgKlosterPrihodCore\Directory::places( array( 'numberposts' => 2, 'fields' => 'ids' ) );
                    if ( 1 === count( $media_places ) ) { $media_place = (int) $media_places[0]; }
                }
                $html .= \GeorgKlosterPrihodCore\Place_Media::render( $media_place );
                $html .= self::schedule_overview( $location, $from, $to ) . '<div class="gkpsch-schedule-body"><div class="gkpsch-schedule-toolbar"><nav class="gkpsch-view-switch" aria-label="' . esc_attr__( 'Schedule view', 'georg-kloster-prihod-schedule' ) . '">';
                foreach ( array( 'week' => __( 'Week', 'georg-kloster-prihod-schedule' ), 'month' => __( 'Month', 'georg-kloster-prihod-schedule' ), 'list' => __( 'List', 'georg-kloster-prihod-schedule' ) ) as $mode => $label ) { $html .= '<a href="' . esc_url( add_query_arg( 'gkpsch_view', $mode ) ) . '"' . ( $mode === $view ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>'; }
                $html .= '</nav><form method="get" class="gkpsch-controls"><input type="hidden" name="gkpsch_view" value="' . esc_attr( $view ) . '"><label>' . esc_html__( 'Date', 'georg-kloster-prihod-schedule' ) . ' <input type="date" name="gkpsch_date" value="' . esc_attr( $date ) . '"></label> ';
                // Preserve WordPress plain-permalink routing without copying arbitrary input.
                foreach ( array( 'page_id', 'p' ) as $query_key ) { if ( isset( $_GET[ $query_key ] ) && is_scalar( $_GET[ $query_key ] ) ) { $html .= '<input type="hidden" name="' . esc_attr( $query_key ) . '" value="' . esc_attr( absint( $_GET[ $query_key ] ) ) . '">'; } }
                if ( function_exists( 'gkpsch_theme_preview_scheme' ) && gkpsch_theme_preview_scheme() ) { $html .= '<input type="hidden" name="gkpsch_preview" value="' . esc_attr( gkpsch_theme_preview_scheme() ) . '">'; }
                if ( $show_location_filter ) {
                    $html .= '<label>' . esc_html__( 'Place', 'georg-kloster-prihod-schedule' ) . ' <select name="gkpsch_place"><option value="0">' . esc_html__( 'All places', 'georg-kloster-prihod-schedule' ) . '</option>';
                    foreach ( $places as $place ) { $html .= '<option value="' . esc_attr( $place->ID ) . '"' . selected( $location, $place->ID, false ) . '>' . esc_html( $place->post_title ) . '</option>'; }
                    $html .= '</select></label> ';
                }
                $html .= '<button type="submit">' . esc_html__( 'Show', 'georg-kloster-prihod-schedule' ) . '</button> <button type="button" class="gkpsch-print">' . esc_html__( 'Print', 'georg-kloster-prihod-schedule' ) . '</button></form></div>' . $period . '<h3 class="gkpsch-range-title">' . esc_html( $from->format( 'd.m.Y' ) . ' — ' . $to->modify( '-1 day' )->format( 'd.m.Y' ) ) . '</h3>';
                wp_enqueue_script( 'gkpsch-public', plugins_url( 'assets/public.js', GKPSCH_FILE ), array(), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/public.js' ), true );
                $events = Schedule::query( $from, $to, $location );
            } else { $events = Schedule::next( $location, 'next-service' === $key ? 1 : absint( $attributes['limit'] ?? 5 ) ); }
            if ( ! $events ) { $html .= '<p>' . esc_html__( 'No services are listed for the selected period.', 'georg-kloster-prihod-schedule' ) . '</p>'; }
            if ( 'schedule' === $key && 'month' === $view ) { $html .= self::month( $events, $from, $to, $anchor->format( 'Y-m-d' ) ); }
            elseif ( 'schedule' === $key && 'week' === $view ) { $html .= '<div class="gkpsch-schedule-columns">' . self::week( $events, $from, $to ) . self::week_sidebar( $anchor, $location, $from ) . '</div>'; }
            else { foreach ( $events as $event ) { $html .= self::event_html( $event ); } }
            if ( 'schedule' === $key ) { $html .= '</div>'; }
        }
        return '<section class="gkpsch-block gkpsch-' . esc_attr( $key ) . '">' . $html . '</section>';
    }
    private static function location_filter_visible( int $published_places, int $fixed_location ): bool {
        return 0 === $fixed_location && $published_places > 1;
    }
    private static function location_title( int $id ): string {
        return Directory::public_title( $id );
    }
    private static function event_html( array $event, bool $show_date = true, bool $compact = false ): string {
        $html = '<article class="gkpsch-event' . ( $event['cancelled'] ? ' is-cancelled' : ( $event['moved'] ? ' is-moved' : '' ) ) . '"><div class="gkpsch-event-time"><time datetime="' . esc_attr( $event['start']->format( DATE_ATOM ) ) . '">' . esc_html( $event['start']->format( 'H:i' ) ) . '</time>';
        if ( $event['end'] ) { $html .= '<small>— ' . esc_html( $event['end']->format( $event['end']->format( 'Y-m-d' ) === $event['start']->format( 'Y-m-d' ) ? 'H:i' : 'd.m.Y H:i' ) ) . '</small>'; }
        $html .= Schedule_Calendar::icon_placeholder( $event ) . '</div><div class="gkpsch-event-copy"><h3>' . esc_html( $event['title'] ) . '</h3>' . Schedule_Calendar::feast_placeholder( $event, $compact ) . '<p class="gkpsch-event-meta">' . esc_html( ( $show_date ? $event['start']->format( 'd.m.Y' ) . ' · ' : '' ) . $event['start']->getTimezone()->getName() ) . '</p>';
        $place = self::location_title( $event['location_id'] );
        if ( $place ) { $html .= '<p class="gkpsch-event-place">' . esc_html( $place ) . '</p>'; }
        if ( $event['cancelled'] ) { $html .= '<strong>' . esc_html__( 'Cancelled', 'georg-kloster-prihod-schedule' ) . '</strong>'; }
        if ( $event['moved'] ) { $html .= '<p>' . esc_html__( 'Service change for', 'georg-kloster-prihod-schedule' ) . ' ' . esc_html( $event['original_date'] ) . '</p>'; }
        if ( trim( $event['description'] ) ) { $html .= '<details class="gkpsch-event-description"><summary>' . esc_html__( 'Service details', 'georg-kloster-prihod-schedule' ) . '</summary>' . wpautop( wp_kses_post( $event['description'] ) ) . '</details>'; }
        $html .= '</div></article>';
        return $html;
    }
    private static function month( array $events, \DateTimeImmutable $from, \DateTimeImmutable $to, string $selected = '' ): string {
        wp_enqueue_script( 'gkpsch-schedule-icons', plugins_url( 'assets/schedule-icons.js', GKPSCH_FILE ), array(), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/schedule-icons.js' ), true );
        $by_day = array();
        foreach ( $events as $event ) { $by_day[ $event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' ) ][] = $event; }
        $months = array( __( 'January', 'georg-kloster-prihod-schedule' ), __( 'February', 'georg-kloster-prihod-schedule' ), __( 'March', 'georg-kloster-prihod-schedule' ), __( 'April', 'georg-kloster-prihod-schedule' ), __( 'May', 'georg-kloster-prihod-schedule' ), __( 'June', 'georg-kloster-prihod-schedule' ), __( 'July', 'georg-kloster-prihod-schedule' ), __( 'August', 'georg-kloster-prihod-schedule' ), __( 'September', 'georg-kloster-prihod-schedule' ), __( 'October', 'georg-kloster-prihod-schedule' ), __( 'November', 'georg-kloster-prihod-schedule' ), __( 'December', 'georg-kloster-prihod-schedule' ) );
        $calendar_url = add_query_arg( 'date', $from->format( 'Y-m-d' ), rest_url( Plugin::REST_NAMESPACE . '/schedule-month' ) );
        $html = '<table class="gkpsch-month" data-gkpsch-calendar-month="' . esc_url( $calendar_url ) . '"><caption>' . esc_html( $months[ (int) $from->format( 'n' ) - 1 ] . ' ' . $from->format( 'Y' ) ) . '</caption><thead><tr>';
        foreach ( self::weekdays() as $label ) { $html .= '<th scope="col">' . esc_html( $label ) . '</th>'; }
        $html .= '</tr></thead><tbody><tr>'; $offset = (int) $from->format( 'N' ) - 1;
        $panels = '<div class="gkpsch-month-panels">'; $prefix = wp_unique_id( 'gkpsch-month-' );
        for ( $blank = 0; $blank < $offset; $blank++ ) {
            $adjacent = $from->modify( '-' . ( $offset - $blank ) . ' days' );
            $html .= '<td class="is-adjacent"><span aria-hidden="true">' . esc_html( $adjacent->format( 'j' ) ) . '</span></td>';
        }
        $today = ( new \DateTimeImmutable( 'now', $from->getTimezone() ) )->format( 'Y-m-d' );
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) {
            $key = $date->format( 'Y-m-d' ); $items = $by_day[$key] ?? array(); $target = $prefix . $key;
            $count = sprintf( __( 'Services: %d', 'georg-kloster-prihod-schedule' ), count( $items ) );
            $html .= '<td' . ( $key === $today ? ' class="is-today"' : '' ) . '><a class="gkpsch-month-day-choice' . ( $items ? ' has-services' : '' ) . '" href="#' . esc_attr( $target ) . '" data-gkpsch-month-day="' . esc_attr( $key ) . '" aria-label="' . esc_attr( $date->format( 'd.m.Y' ) . ' · ' . $count ) . '"' . ( $key === $selected ? ' aria-current="date"' : '' ) . '><span class="gkpsch-month-date"><time datetime="' . esc_attr( $key ) . '">' . esc_html( $date->format( 'j' ) ) . '</time><small data-gkpsch-old-day></small></span><span class="gkpsch-month-saint" data-gkpsch-month-saint="' . esc_attr( $key ) . '"></span>';
            foreach ( $items as $event ) { $html .= '<span class="gkpsch-month-service"><strong>' . esc_html( $event['start']->format( 'H:i' ) ) . '</strong><span>' . esc_html( $event['title'] ) . '</span></span>'; }
            $html .= '<span class="gkpsch-month-food" data-gkpsch-month-food></span></a>';
            $panels .= '<details class="gkpsch-month-day-panel" id="' . esc_attr( $target ) . '"' . ( $key === $selected ? ' open' : '' ) . '><summary>' . esc_html( $date->format( 'd.m.Y' ) . ' · ' . self::weekdays()[ (int) $date->format( 'N' ) - 1 ] . ' · ' . $count ) . '</summary><div class="gkpsch-month-calendar-detail"><p class="gkpsch-month-saint" data-gkpsch-month-saint="' . esc_attr( $key ) . '"></p><p data-gkpsch-month-description></p><p data-gkpsch-month-fast hidden><strong>' . esc_html__( 'Fasting:', 'georg-kloster-prihod-schedule' ) . '</strong> <span></span></p><h4 data-gkpsch-month-heading hidden>' . esc_html__( 'Commemorations of the day', 'georg-kloster-prihod-schedule' ) . '</h4><ul data-gkpsch-month-commemorations></ul></div>';
            foreach ( $items as $event ) { $panels .= self::event_html( $event, true, true ); }
            if ( ! $items ) { $panels .= '<p>' . esc_html__( 'No services are listed for this date.', 'georg-kloster-prihod-schedule' ) . '</p>'; }
            $panels .= '</details>';
            $html .= '</td>'; ++$offset;
            if ( 0 === $offset % 7 && $date->modify( '+1 day' ) < $to ) { $html .= '</tr><tr>'; }
        }
        $blank = 0;
        while ( 0 !== $offset % 7 ) {
            $adjacent = $to->modify( '+' . $blank . ' days' );
            $html .= '<td class="is-adjacent"><span aria-hidden="true">' . esc_html( $adjacent->format( 'j' ) ) . '</span></td>';
            ++$offset; ++$blank;
        }
        return $html . '</tr></tbody></table>' . $panels . '</div>';
    }
    private static function week( array $events, \DateTimeImmutable $from, \DateTimeImmutable $to ): string {
        $by_day = array();
        foreach ( $events as $event ) { $by_day[ $event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' ) ][] = $event; }
        $html = '<div class="gkpsch-week">';
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) {
            $items = $by_day[ $date->format( 'Y-m-d' ) ] ?? array();
            if ( ! $items ) { continue; }
            $html .= '<div class="gkpsch-week-day"><h4><time datetime="' . esc_attr( $date->format( 'Y-m-d' ) ) . '">' . esc_html( self::weekdays()[ (int) $date->format( 'N' ) - 1 ] ) . '<strong>' . esc_html( $date->format( 'j' ) ) . '</strong></time></h4><div>';
            foreach ( $items as $event ) { $html .= self::event_html( $event ); }
            $html .= '</div></div>';
        }
        return $html . '</div>';
    }
    private static function schedule_overview( int $location, \DateTimeImmutable $from, \DateTimeImmutable $to ): string {
        $html = '<div class="gkpsch-schedule-overview"><div class="gkpsch-upcoming-card"><h3>' . esc_html__( 'Upcoming service', 'georg-kloster-prihod-schedule' ) . '</h3>';
        $next = Schedule::next( $location, 1 );
        if ( $next ) {
            $event = $next[0];
            $html .= Schedule_Calendar::icon_placeholder( $event );
            $html .= '<p class="gkpsch-overview-date"><time datetime="' . esc_attr( $event['start']->format( DATE_ATOM ) ) . '">' . esc_html( wp_date( 'j F · H:i', $event['start']->getTimestamp(), $event['start']->getTimezone() ) ) . '</time></p><strong>' . esc_html( $event['title'] ) . '</strong>' . Schedule_Calendar::feast_placeholder( $event ) . '<p>' . esc_html( self::location_title( $event['location_id'] ) ) . '<small>' . esc_html( $event['start']->getTimezone()->getName() ) . '</small></p>';
            $resources = get_option( 'gkpsch_resources', array() );
            $id = absint( $resources['contacts']['id'] ?? 0 );
            if ( $id && 'publish' === get_post_status( $id ) ) {
                $url = get_permalink( $id );
                if ( function_exists( 'gkpsch_theme_preview_url' ) ) { $url = gkpsch_theme_preview_url( $url ); }
                $html .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Directions', 'georg-kloster-prihod-schedule' ) . ' →</a>';
            }
        } else { $html .= '<p>' . esc_html__( 'No upcoming service is listed yet.', 'georg-kloster-prihod-schedule' ) . '</p>'; }
        $html .= '</div>';
        $changes = Schedule::changes( $from, $to, $location );
        if ( $changes ) {
            $html .= '<div class="gkpsch-changes-card"><h3>' . esc_html__( 'Schedule changes', 'georg-kloster-prihod-schedule' ) . '</h3>';
            $html .= '<ul>';
            foreach ( $changes as $event ) {
                $original = new \DateTimeImmutable( $event['original_date'] );
                $html .= '<li class="' . ( $event['cancelled'] ? 'is-cancelled' : 'is-moved' ) . '"><strong>' . esc_html( $event['cancelled'] ? __( 'Cancelled', 'georg-kloster-prihod-schedule' ) : __( 'Reschedule / change time', 'georg-kloster-prihod-schedule' ) ) . '</strong> · ' . esc_html( $original->format( 'd.m.Y' ) . ' · ' . $event['title'] );
                if ( ! $event['cancelled'] ) { $place = self::location_title( $event['location_id'] ); $html .= '<small>→ ' . esc_html( $event['start']->format( 'd.m.Y H:i' ) . ' · ' . $event['start']->getTimezone()->getName() . ( $place ? ' · ' . $place : '' ) ) . '</small>'; }
                $html .= '</li>';
            }
            $html .= '</ul></div>';
        }
        return $html . '</div>';
    }
    private static function week_sidebar( \DateTimeImmutable $anchor, int $location, \DateTimeImmutable $week_from ): string {
        $from = $anchor->modify( 'first day of this month' ); $to = $from->modify( '+1 month' );
        $events = Schedule::query( $from, $to, $location ); $days = array(); $colors = array();
        foreach ( $events as $event ) { $key = $event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' ); $days[$key][$event['cancelled'] ? 'has-cancellations' : 'has-services'] = true; if ( ! $event['cancelled'] ) { $colors[$key][] = $event['vestment_color'] ?? ''; } }
        foreach ( Schedule::changes( $from, $to, $location ) as $event ) {
            $days[$event['original_date']][$event['cancelled'] ? 'has-cancellations' : 'has-moves'] = true;
            if ( $event['moved'] ) { $days[$event['start']->setTimezone( $from->getTimezone() )->format( 'Y-m-d' )]['has-moves'] = true; }
        }
        $today = ( new \DateTimeImmutable( 'now', $from->getTimezone() ) )->format( 'Y-m-d' );
        $week_to = $week_from->modify( '+7 days' );
        $labels = array( 'has-services' => __( 'Services', 'georg-kloster-prihod-schedule' ), 'has-cancellations' => __( 'Cancellation', 'georg-kloster-prihod-schedule' ), 'has-moves' => __( 'Reschedule / change time', 'georg-kloster-prihod-schedule' ) );
        wp_enqueue_script( 'gkpsch-schedule-icons', plugins_url( 'assets/schedule-icons.js', GKPSCH_FILE ), array(), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/schedule-icons.js' ), true );
        $month_url = add_query_arg( 'date', $from->format( 'Y-m-d' ), rest_url( Plugin::REST_NAMESPACE . '/schedule-month' ) );
        $html = '<aside class="gkpsch-schedule-aside"><div class="gkpsch-mini-month" data-gkpsch-mini-month="' . esc_url( $month_url ) . '"><h3>' . esc_html( wp_date( 'F Y', $from->getTimestamp(), $from->getTimezone() ) ) . '</h3><div class="gkpsch-mini-grid">';
        foreach ( self::weekdays() as $day ) { $html .= '<span>' . esc_html( $day ) . '</span>'; }
        for ( $i = 1; $i < (int) $from->format( 'N' ); ++$i ) { $html .= '<span></span>'; }
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) {
            $key = $date->format( 'Y-m-d' );
            $classes = array_keys( $days[$key] ?? array() );
            $label = wp_date( 'j F Y', $date->getTimestamp(), $date->getTimezone() );
            foreach ( $classes as $class ) { $label .= ' · ' . $labels[$class]; }
            if ( $date >= $week_from && $date < $week_to ) { $classes[] = 'is-selected-week'; $label .= ' · ' . __( 'Selected week', 'georg-kloster-prihod-schedule' ); }
            if ( $key === $today ) { $classes[] = 'is-today'; $label .= ' · ' . __( 'Today', 'georg-kloster-prihod-schedule' ); }
            $html .= '<a class="' . esc_attr( implode( ' ', $classes ) ) . '" data-mini-date="' . esc_attr( $key ) . '" data-service-colors="' . esc_attr( wp_json_encode( array_values( array_unique( $colors[$key] ?? array() ) ) ) ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $key === $today ? ' aria-current="date"' : '' ) . ' href="' . esc_url( add_query_arg( array( 'gkpsch_date' => $key, 'gkpsch_view' => 'week' ) ) ) . '">' . esc_html( $date->format( 'j' ) ) . '</a>';
        }
        $html .= '</div><div class="gkpsch-mini-legend"><span class="is-today">' . esc_html__( 'Today', 'georg-kloster-prihod-schedule' ) . '</span><span class="is-selected-week">' . esc_html__( 'Selected week', 'georg-kloster-prihod-schedule' ) . '</span><span class="has-services">' . esc_html__( 'Services', 'georg-kloster-prihod-schedule' ) . '</span></div></div><div class="gkpsch-visit-card"><h3>' . esc_html__( 'Before your visit', 'georg-kloster-prihod-schedule' ) . '</h3><p>' . esc_html__( 'Check the service date, time and place. Changes and cancellations are marked in the schedule.', 'georg-kloster-prihod-schedule' ) . '</p>';
        $resources = get_option( 'gkpsch_resources', array() );
        foreach ( array( 'first-visit' => __( 'First visit', 'georg-kloster-prihod-schedule' ), 'requests' => __( 'Ask a question', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            $id = absint( $resources[$key]['id'] ?? 0 );
            if ( $id && 'publish' === get_post_status( $id ) ) { $url = get_permalink( $id ); if ( function_exists( 'gkpsch_theme_preview_url' ) ) { $url = gkpsch_theme_preview_url( $url ); } $html .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; }
        }
        return $html . '</div></aside>';
    }
    private static function weekdays(): array {
        return array( __( 'Mon', 'georg-kloster-prihod-schedule' ), __( 'Tue', 'georg-kloster-prihod-schedule' ), __( 'Wed', 'georg-kloster-prihod-schedule' ), __( 'Thu', 'georg-kloster-prihod-schedule' ), __( 'Fri', 'georg-kloster-prihod-schedule' ), __( 'Sat', 'georg-kloster-prihod-schedule' ), __( 'Sun', 'georg-kloster-prihod-schedule' ) );
    }

}
