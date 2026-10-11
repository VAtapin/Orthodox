<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Public_View {
    public static function blocks(): array {
        return array( 'profile' => __( 'Сведения о приходе', 'prihod' ), 'contacts' => __( 'Контакты прихода', 'prihod' ), 'clergy' => __( 'Духовенство', 'prihod' ), 'schedule' => __( 'Расписание богослужений', 'prihod' ), 'next-service' => __( 'Ближайшее богослужение', 'prihod' ), 'next-services' => __( 'Ближайшие службы', 'prihod' ) );
    }
    public static function register(): void {
        wp_register_script( 'prihod-blocks', plugins_url( 'assets/blocks.js', PRIHOD_FILE ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ), PRIHOD_VERSION, true );
        wp_register_style( 'prihod-public', plugins_url( 'assets/public.css', PRIHOD_FILE ), array(), PRIHOD_VERSION );
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
                $view = 'month' === ( $attributes['view'] ?? 'week' ) ? 'month' : 'week';
                $now = new \DateTimeImmutable( 'now', new \DateTimeZone( $profile['timezone'] ) );
                $date = isset( $_GET['prihod_date'] ) && is_string( $_GET['prihod_date'] ) ? sanitize_text_field( wp_unslash( $_GET['prihod_date'] ) ) : $now->format( 'Y-m-d' );
                if ( ! Occurrences::date( $date ) ) { $date = $now->format( 'Y-m-d' ); }
                $anchor = new \DateTimeImmutable( $date, $now->getTimezone() );
                $from = 'month' === $view ? $anchor->modify( 'first day of this month' ) : $anchor->modify( '-' . ( (int) $anchor->format( 'N' ) - 1 ) . ' days' );
                $to = $from->modify( 'month' === $view ? '+1 month' : '+7 days' );
                if ( ! $location && isset( $_GET['prihod_place'] ) && is_scalar( $_GET['prihod_place'] ) ) { $location = absint( $_GET['prihod_place'] ); }
                $html .= '<form method="get" class="prihod-controls"><label>' . esc_html__( 'Дата', 'prihod' ) . ' <input type="date" name="prihod_date" value="' . esc_attr( $date ) . '"></label> ';
                // Preserve WordPress plain-permalink routing without copying arbitrary input.
                foreach ( array( 'page_id', 'p' ) as $query_key ) { if ( isset( $_GET[ $query_key ] ) && is_scalar( $_GET[ $query_key ] ) ) { $html .= '<input type="hidden" name="' . esc_attr( $query_key ) . '" value="' . esc_attr( absint( $_GET[ $query_key ] ) ) . '">'; } }
                if ( ! absint( $attributes['location'] ?? 0 ) ) {
                    $html .= '<label>' . esc_html__( 'Место', 'prihod' ) . ' <select name="prihod_place"><option value="0">' . esc_html__( 'Все места', 'prihod' ) . '</option>';
                    foreach ( get_posts( array( 'post_type' => 'prihod_location', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $place ) { $html .= '<option value="' . esc_attr( $place->ID ) . '"' . selected( $location, $place->ID, false ) . '>' . esc_html( $place->post_title ) . '</option>'; }
                    $html .= '</select></label> ';
                }
                $html .= '<button type="submit">' . esc_html__( 'Показать', 'prihod' ) . '</button> <button type="button" class="prihod-print">' . esc_html__( 'Печать', 'prihod' ) . '</button></form><h3>' . esc_html( $from->format( 'd.m.Y' ) . ' — ' . $to->modify( '-1 day' )->format( 'd.m.Y' ) ) . '</h3>';
                wp_enqueue_script( 'prihod-public', plugins_url( 'assets/public.js', PRIHOD_FILE ), array(), PRIHOD_VERSION, true );
                $events = Schedule::query( $from, $to, $location );
            } else { $events = Schedule::next( $location, 'next-service' === $key ? 1 : absint( $attributes['limit'] ?? 5 ) ); }
            if ( ! $events ) { $html .= '<p>' . esc_html__( 'Богослужения на выбранный период не указаны.', 'prihod' ) . '</p>'; }
            foreach ( $events as $event ) {
                $html .= '<article class="prihod-event' . ( $event['cancelled'] ? ' is-cancelled' : '' ) . '"><h3>' . esc_html( $event['title'] ) . '</h3><p><time datetime="' . esc_attr( $event['start']->format( DATE_ATOM ) ) . '">' . esc_html( $event['start']->format( 'd.m.Y H:i' ) ) . '</time>';
                if ( $event['end'] ) { $html .= ' — ' . esc_html( $event['end']->format( 'd.m.Y H:i' ) ); }
                $html .= ' (' . esc_html( $event['start']->getTimezone()->getName() ) . ')</p><p>' . esc_html( get_the_title( $event['location_id'] ) ) . '</p>';
                if ( $event['cancelled'] ) { $html .= '<strong>' . esc_html__( 'Отменено', 'prihod' ) . '</strong>'; }
                if ( $event['moved'] ) { $html .= '<p>' . esc_html__( 'Изменение службы за', 'prihod' ) . ' ' . esc_html( $event['original_date'] ) . '</p>'; }
                $html .= wpautop( wp_kses_post( $event['description'] ) ) . '</article>';
            }
        }
        return '<section class="prihod-block prihod-' . esc_attr( $key ) . '">' . $html . '</section>';
    }
}
