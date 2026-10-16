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
function prihod_theme_compact_services( int $limit = 1 ): string {
    if ( ! class_exists( '\Prihod\Schedule' ) || ! \Prihod\Modules::enabled( 'schedule' ) ) { return ''; }
    $events = \Prihod\Schedule::next( 0, $limit ); $html = '';
    foreach ( $events as $event ) {
        $date = $event['start'];
        $feast = is_callable( array( '\Prihod\Schedule_Calendar', 'feast_placeholder' ) ) ? \Prihod\Schedule_Calendar::feast_placeholder( $event ) : '';
        $html .= '<article class="prihod-service-compact"><div class="prihod-service-date"><strong>' . esc_html( $date->format( 'j' ) ) . '</strong><span>' . esc_html( wp_date( 'M', $date->getTimestamp(), $date->getTimezone() ) ) . '</span></div><div class="prihod-service-details"><h3>' . esc_html( $event['title'] ) . '</h3>' . $feast . '<div class="prihod-service-when"><span>' . esc_html( wp_date( 'editorial' === prihod_theme_scheme() ? 'l' : 'l, j F Y', $date->getTimestamp(), $date->getTimezone() ) ) . '</span><time datetime="' . esc_attr( $date->format( DATE_ATOM ) ) . '">' . esc_html( $date->format( 'H:i' ) ) . '</time></div><p>' . prihod_theme_icon( 'contacts' ) . esc_html( get_the_title( $event['location_id'] ) ) . '</p></div></article>';
    }
    return $html ?: '<p class="prihod-service-empty">' . esc_html__( 'Богослужения на выбранный период не указаны.', 'georg-kloster-prihod' ) . '</p>';
}
function prihod_theme_verse( string $text_key = 'quote_text', string $source_key = 'quote_source' ): string {
    $options = georg_kloster_prihod_options();
    return $options[$text_key] ? '<blockquote class="prihod-verse"><p>«' . esc_html( $options[$text_key] ) . '»</p>' . ( $options[$source_key] ? '<cite>' . esc_html( $options[$source_key] ) . '</cite>' : '' ) . '</blockquote>' : '';
}
function prihod_theme_section_title( string $title, string $url = '', string $link = '' ): string {
    $eyebrow = '';
    if ( 'blue' === prihod_theme_scheme() && is_front_page() ) {
        $labels = array( __( 'О приходе', 'georg-kloster-prihod' ) => __( 'Наш приход', 'georg-kloster-prihod' ), __( 'О монастыре', 'georg-kloster-prihod' ) => __( 'Наша обитель', 'georg-kloster-prihod' ), __( 'Новости прихода', 'georg-kloster-prihod' ) => __( 'Новости', 'georg-kloster-prihod' ), __( 'Новости обители', 'georg-kloster-prihod' ) => __( 'Новости', 'georg-kloster-prihod' ), __( 'Жизнь прихода в фотографиях', 'georg-kloster-prihod' ) => __( 'Фотогалерея', 'georg-kloster-prihod' ), __( 'Жизнь обители в фотографиях', 'georg-kloster-prihod' ) => __( 'Фотогалерея', 'georg-kloster-prihod' ), __( 'Как нас найти', 'georg-kloster-prihod' ) => __( 'Контакты', 'georg-kloster-prihod' ) );
        if ( isset( $labels[$title] ) ) { $eyebrow = '<p class="prihod-section-eyebrow">' . esc_html( $labels[$title] ) . '</p>'; }
    }
    if ( 'editorial' === prihod_theme_scheme() && is_front_page() ) {
        if ( in_array( $title, array( __( 'Новости прихода', 'georg-kloster-prihod' ), __( 'Новости обители', 'georg-kloster-prihod' ) ), true ) ) { $eyebrow = '<p class="prihod-section-eyebrow">' . esc_html__( 'Живая Церковь', 'georg-kloster-prihod' ) . '</p>'; }
        if ( in_array( $title, array( __( 'Жизнь прихода в фотографиях', 'georg-kloster-prihod' ), __( 'Жизнь обители в фотографиях', 'georg-kloster-prihod' ) ), true ) ) { $eyebrow = '<p class="prihod-section-eyebrow">' . esc_html__( 'Фотолетопись', 'georg-kloster-prihod' ) . '</p>'; }
    }
    return '<div class="prihod-section-title"><div>' . $eyebrow . '<h2>' . esc_html( $title ) . '</h2></div>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $link ) . ' <span aria-hidden="true">→</span></a>' : '' ) . '</div>';
}
function prihod_theme_contact_panel(): string {
    $profile = prihod_theme_profile(); $options = georg_kloster_prihod_options();
    $html = '<section class="prihod-home-contacts">' . prihod_theme_section_title( __( 'Как нас найти', 'georg-kloster-prihod' ) ) . '<div class="prihod-contact-layout"><div class="prihod-contact-list">';
    foreach ( array( 'address' => array( 'contacts', __( 'Адрес', 'georg-kloster-prihod' ) ), 'phone' => array( 'phone', __( 'Телефон', 'georg-kloster-prihod' ) ), 'email' => array( 'email', __( 'Email', 'georg-kloster-prihod' ) ) ) as $key => $item ) {
        if ( empty( $profile[$key] ) ) { continue; }
        $text = esc_html( $profile[$key] );
        if ( 'phone' === $key ) { $text = '<a href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $profile[$key] ) ) . '">' . $text . '</a>'; }
        if ( 'email' === $key ) { $text = '<a href="' . esc_url( 'mailto:' . $profile[$key] ) . '">' . $text . '</a>'; }
        $html .= '<div>' . prihod_theme_icon( $item[0] ) . '<div><h3>' . esc_html( $item[1] ) . '</h3><p>' . $text . '</p></div></div>';
    }
    $html .= '</div>';
    $html .= prihod_theme_contact_map();
    if ( 'hospitality' === prihod_theme_scheme() ) { $html .= '<aside class="prihod-contact-quote">' . prihod_theme_verse() . '</aside>'; }
    elseif ( 'editorial' === prihod_theme_scheme() ) {
        $html .= '<aside class="prihod-contact-welcome"><div><h3>' . esc_html__( 'Мы рядом', 'georg-kloster-prihod' ) . '</h3><p>' . esc_html( $profile['description'] ) . '</p>';
        $contact = prihod_theme_url( 'contacts' );
        if ( $contact ) { $html .= '<a href="' . esc_url( $contact ) . '">' . esc_html__( 'Связаться с нами', 'georg-kloster-prihod' ) . ' →</a>'; }
        $html .= '</div>' . prihod_theme_photo( $options['about_image'] ?: $options['hero_image'], 'medium' ) . '</aside>';
    }
    elseif ( 'tradition' === prihod_theme_scheme() ) { $html .= '<aside class="prihod-contact-motto">' . prihod_theme_verse() . prihod_theme_icon( 'cross' ) . '</aside>'; }
    return $html . '</div></section>';
}

function prihod_theme_contact_map(): string {
    $profile = prihod_theme_profile(); $options = georg_kloster_prihod_options(); $html = '';
    if ( $options['map_url'] ) {
        $html .= '<div class="prihod-contact-map">';
        if ( ! empty( $options['map_embed'] ) ) { $html .= '<iframe loading="eager" title="' . esc_attr__( 'Как нас найти', 'georg-kloster-prihod' ) . '" referrerpolicy="no-referrer" src="' . esc_url( $options['map_embed'] ) . '"></iframe>'; }
        else { $html .= '<div class="prihod-map-address">' . prihod_theme_icon( 'contacts' ) . '<p>' . esc_html( $profile['address'] ) . '</p></div>'; }
        $html .= '<a class="prihod-button is-outline" href="' . esc_url( $options['map_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Открыть карту', 'georg-kloster-prihod' ) . ' →</a></div>';
    }
    return $html;
}
