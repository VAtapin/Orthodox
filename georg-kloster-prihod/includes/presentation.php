<?php
defined( 'ABSPATH' ) || exit;

/** Shared visual primitives. All service data comes from Core's existing query. */
function prihod_theme_icon( string $name ): string {
    $paths = array(
        'schedule' => '<rect x="4" y="6" width="24" height="22" rx="2"/><path d="M4 12h24M10 3v6m12-6v6M10 17h2m6 0h2m-10 5h2m6 0h2"/>',
        'about' => '<path d="M16 2v8m-3-5h6M16 9c-7 6-6 10 0 11 6-1 7-5 0-11ZM11 20v10h10V20M4 30V17h7m10-3h7v16M2 30h28m-17 0v-5h6v5"/>',
        'group' => '<circle cx="11" cy="9" r="4"/><circle cx="23" cy="10" r="3"/><path d="M3 28v-6a8 8 0 0 1 16 0v6H3Zm17-11a7 7 0 0 1 9 6v5h-7M8 21v7m6-7v7"/>',
        'news' => '<rect x="6" y="3" width="21" height="26" rx="2"/><path d="M6 9H3v18c0 2 1 2 3 2m5-20h11m-11 5h11m-11 5h11m-11 5h7"/>',
        'requests' => '<path d="M4 5h18a6 6 0 0 1 6 6v6a6 6 0 0 1-6 6H12l-7 6v-8a6 6 0 0 1-3-5v-5a6 6 0 0 1 2-6Z"/><path d="M9 12h13m-13 5h9"/>',
        'donations' => '<path d="M16 28S2 19 2 10C2 1 13 0 16 8c3-8 14-7 14 2 0 9-14 18-14 18Z"/>',
        'contacts' => '<path d="M16 30S5 18 5 12a11 11 0 0 1 22 0c0 6-11 18-11 18Z"/><circle cx="16" cy="12" r="4"/>',
        'phone' => '<path d="m8 3 5 7-4 4c3 5 5 7 10 10l4-4 7 5c-1 8-6 7-12 4C8 24 0 14 3 7l5-4Z"/>',
        'email' => '<rect x="2" y="5" width="28" height="22" rx="2"/><path d="m2 6 14 12L30 6"/>',
        'time' => '<circle cx="16" cy="16" r="13"/><path d="M16 7v10l7 4"/>',
        'search' => '<circle cx="13" cy="13" r="9"/><path d="m20 20 9 9"/>',
        'share' => '<circle cx="7" cy="16" r="4"/><circle cx="25" cy="6" r="4"/><circle cx="25" cy="26" r="4"/><path d="m11 14 10-6m-10 10 10 6"/>',
        'support' => '<path d="M2 25l6-6h10c4 0 4 4 0 4h-5m5 0 9-7c3-2 5 1 2 4l-10 9H7l-5-4ZM16 18V8m0 5C8 13 7 5 7 5s10-1 9 8Zm0-5C16 2 25 1 25 1s1 9-9 11"/>',
        'cross' => '<path d="M16 2v28M6 10h20m-16 12 12 5"/>',
    );
    return '<svg class="prihod-icon" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[$name] ?? $paths['about'] ) . '</svg>';
}
function prihod_theme_scheme(): string {
    $settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
    return $settings['custom']['prihod']['scheme'] ?? 'classic';
}
function prihod_theme_preview_url( string $url ): string {
    $preview = prihod_theme_preview_scheme();
    return $preview && wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ? add_query_arg( 'prihod_preview', $preview, $url ) : $url;
}
function prihod_theme_schedule_class(): string {
    if ( class_exists( '\GeorgKlosterPrihodSchedule\Schedule' ) ) {
        return '\GeorgKlosterPrihodSchedule\Schedule';
    }
    if ( class_exists( '\Prihod\Schedule' ) && is_callable( array( '\Prihod\Modules', 'enabled' ) ) && \Prihod\Modules::enabled( 'schedule' ) ) {
        return '\Prihod\Schedule';
    }
    return '';
}

function prihod_theme_compact_services( int $limit = 1 ): string {
    $schedule = prihod_theme_schedule_class();
    if ( ! $schedule ) { return ''; }
    $events = $schedule::next( 0, $limit ); $html = '';
    if ( ! $events ) { return ''; }
    $calendar = substr( $schedule, 0, strrpos( $schedule, '\\' ) ) . '\\Schedule_Calendar';
    $service_class = '\\GeorgKlosterPrihodSchedule\\Schedule' === $schedule ? ' gkpsch-service-compact' : '';
    foreach ( $events as $event ) {
        $date = $event['start'];
        if ( class_exists( '\GeorgKlosterPrihodSchedule\Directory' ) ) {
            if ( method_exists( '\GeorgKlosterPrihodSchedule\Directory', 'public_title' ) ) {
                $place_title = \GeorgKlosterPrihodSchedule\Directory::public_title( $event['location_id'] );
            } else {
                $places = get_posts( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'numberposts' => 2, 'fields' => 'ids' ) );
                $place_title = count( $places ) > 1 ? \GeorgKlosterPrihodSchedule\Directory::title( $event['location_id'] ) : '';
            }
        } else {
            $places = get_posts( array( 'post_type' => 'prihod_location', 'post_status' => 'publish', 'numberposts' => 2, 'fields' => 'ids' ) );
            $place_title = count( $places ) > 1 ? get_the_title( $event['location_id'] ) : '';
        }
        $color = $event['vestment_color'] ?? '';
        $color_data = is_callable( array( $calendar, 'feast_placeholder' ) ) ? $calendar::feast_placeholder( $event, true, true ) : '';
        $html .= '<article class="prihod-service-compact' . $service_class . '"' . ( $color && 'auto' !== $color ? ' data-vestment-color="' . esc_attr( $color ) . '"' : '' ) . '><div class="prihod-service-date"><strong>' . esc_html( $date->format( 'j' ) ) . '</strong><span>' . esc_html( wp_date( 'M', $date->getTimestamp(), $date->getTimezone() ) ) . '</span></div><div class="prihod-service-details' . ( $place_title ? ' has-place' : '' ) . '"><h3>' . esc_html( $event['title'] ) . '</h3><div class="prihod-service-when"><span>' . esc_html( wp_date( 'editorial' === prihod_theme_scheme() ? 'l' : 'l, j F Y', $date->getTimestamp(), $date->getTimezone() ) ) . '</span><time datetime="' . esc_attr( $date->format( DATE_ATOM ) ) . '">' . esc_html( $date->format( 'H:i' ) ) . '</time></div>' . ( $place_title ? '<p>' . prihod_theme_icon( 'contacts' ) . esc_html( $place_title ) . '</p>' : '' ) . '</div>' . ( $color_data ? '<div class="prihod-service-color-data" hidden>' . $color_data . '</div>' : '' ) . '</article>';
    }
    return $html;
}
function prihod_theme_verse( string $text_key = 'quote_text', string $source_key = 'quote_source' ): string {
    $options = georg_kloster_prihod_options();
    return $options[$text_key] ? '<blockquote class="prihod-verse"><p>«' . esc_html( $options[$text_key] ) . '»</p>' . ( $options[$source_key] ? '<cite>' . esc_html( $options[$source_key] ) . '</cite>' : '' ) . '</blockquote>' : '';
}
function prihod_theme_section_title( string $title, string $url = '', string $link = '', string $eyebrow = '' ): string {
    if ( ! $title && ! $eyebrow && ( ! $url || ! $link ) ) { return ''; }
    return '<div class="prihod-section-title"><div>' . ( $eyebrow ? '<p class="prihod-section-eyebrow">' . esc_html( $eyebrow ) . '</p>' : '' ) . ( $title ? '<h2>' . esc_html( $title ) . '</h2>' : '' ) . '</div>' . ( $url && $link ? '<a href="' . esc_url( $url ) . '">' . esc_html( $link ) . ' <span aria-hidden="true">→</span></a>' : '' ) . '</div>';
}
function prihod_theme_contact_panel( string $title = '' ): string {
    $profile = prihod_theme_profile(); $options = georg_kloster_prihod_options();
    if ( ! $profile['address'] && ! $profile['phone'] && ! $profile['email'] ) { return ''; }
    $html = '<section class="prihod-home-contacts">' . prihod_theme_section_title( $title, '', '', prihod_theme_home_eyebrow( 'contacts' ) ) . '<div class="prihod-contact-layout"><div class="prihod-contact-list">';
    foreach ( array( 'address' => array( 'contacts', __( 'Address', 'georg-kloster-prihod' ) ), 'phone' => array( 'phone', __( 'Phone', 'georg-kloster-prihod' ) ), 'email' => array( 'email', __( 'Email', 'georg-kloster-prihod' ) ) ) as $key => $item ) {
        if ( empty( $profile[$key] ) ) { continue; }
        $text = esc_html( $profile[$key] );
        if ( 'phone' === $key ) { $text = '<a href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $profile[$key] ) ) . '">' . $text . '</a>'; }
        if ( 'email' === $key ) { $text = '<a href="' . esc_url( 'mailto:' . $profile[$key] ) . '">' . $text . '</a>'; }
        $html .= '<div>' . prihod_theme_icon( $item[0] ) . '<div><h3>' . esc_html( $item[1] ) . '</h3><p>' . $text . '</p></div></div>';
    }
    $html .= '</div>';
    if ( 'hospitality' === prihod_theme_scheme() ) { $html .= '<aside class="prihod-contact-quote">' . prihod_theme_verse() . '</aside>'; }
    elseif ( 'editorial' === prihod_theme_scheme() ) {
        $html .= '<aside class="prihod-contact-welcome"><div><p>' . esc_html( $profile['description'] ) . '</p>';
        $contact = prihod_theme_url( 'contacts' );
        if ( $contact && prihod_theme_home_copy( 'contacts_button' ) ) { $html .= '<a href="' . esc_url( $contact ) . '">' . esc_html( prihod_theme_home_copy( 'contacts_button' ) ) . ' →</a>'; }
        $html .= '</div>' . prihod_theme_photo( $options['hero_image'], 'medium' ) . '</aside>';
    }
    elseif ( 'tradition' === prihod_theme_scheme() ) { $html .= '<aside class="prihod-contact-motto">' . prihod_theme_verse() . prihod_theme_icon( 'cross' ) . '</aside>'; }
    return $html . '</div></section>';
}
