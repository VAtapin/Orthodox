<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

/** Explicit, resumable content import. No activation-time writes or external downloads. */
final class Demo_Importer {
    public const OPTION = 'gkpc_demo_import';

    public static function allowed(): bool {
        foreach ( array( 'manage_options', 'manage_gkpc_profile', 'publish_pages', 'publish_posts', 'upload_files' ) as $capability ) {
            if ( ! current_user_can( $capability ) ) { return false; }
        }
        return true;
    }

    public static function state(): array {
        $state = get_option( self::OPTION, array() );
        return is_array( $state ) ? $state : array();
    }

    public static function pack( string $locale ): array {
        if ( ! in_array( $locale, array( 'ru_RU', 'en_US' ), true ) ) { throw new \InvalidArgumentException( 'Unsupported demo locale.' ); }
        $pack = json_decode( file_get_contents( dirname( GKPC_FILE ) . '/demo/' . $locale . '.json' ), true, 32, JSON_THROW_ON_ERROR );
        if ( ! is_array( $pack ) || ( $pack['id'] ?? '' ) !== 'birch-haven-v1' || ( $pack['locale'] ?? '' ) !== $locale ) { throw new \RuntimeException( 'Invalid demo pack.' ); }
        return $pack;
    }

    public static function schedule_available(): bool {
        return class_exists( '\GeorgKlosterPrihodSchedule\Schedule' ) && post_type_exists( 'gkpsch_service' ) && post_type_exists( 'gkpsch_location' ) && current_user_can( 'manage_gkpsch_schedule' );
    }

    private static function key( array $pack, string $key ): string { return $pack['id'] . ':' . $pack['locale'] . ':' . $key; }

    private static function find( array $pack, string $key, string $type ): int {
        $found = get_posts( array( 'post_type' => $type, 'post_status' => array_values( get_post_stati() ), 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_gkpc_demo_key', 'meta_value' => self::key( $pack, $key ), 'suppress_filters' => true ) );
        return $found ? (int) $found[0] : 0;
    }

    private static function save( array $state ): void { update_option( self::OPTION, $state, false ); }

    private static function media( array $pack, string $key, array $row ): int {
        $existing = self::find( $pack, 'image-' . $key, 'attachment' );
        if ( $existing ) { return $existing; }
        $folder = realpath( dirname( GKPC_FILE ) . '/demo/media' );
        $source = realpath( $folder . '/' . $row['file'] );
        if ( ! $source || dirname( $source ) !== $folder || 'jpg' !== strtolower( pathinfo( $source, PATHINFO_EXTENSION ) ) ) { throw new \RuntimeException( 'Invalid bundled demo image.' ); }
        $temporary = wp_tempnam( basename( $source ) );
        if ( ! $temporary || ! copy( $source, $temporary ) ) { throw new \RuntimeException( 'Could not prepare demo image.' ); }
        $id = media_handle_sideload( array( 'name' => basename( $source ), 'tmp_name' => $temporary ), 0, $row['caption'], array( 'post_title' => $row['title'], 'post_excerpt' => sanitize_textarea_field( $row['caption'] ), 'meta_input' => array( '_gkpc_demo_key' => self::key( $pack, 'image-' . $key ) ) ) );
        if ( is_file( $temporary ) ) { wp_delete_file( $temporary ); }
        if ( is_wp_error( $id ) ) { throw new \RuntimeException( $id->get_error_message() ); }
        update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $row['alt'] ) );
        return (int) $id;
    }

    private static function post( array $pack, string $key, array $row, string $type ): int {
        $existing = self::find( $pack, $key, $type );
        if ( $existing ) { return $existing; }
        $id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $row['title'], 'post_name' => 'gkpc-demo-' . $key, 'post_content' => '', 'meta_input' => array( '_gkpc_demo_key' => self::key( $pack, $key ) ) ) ), true );
        if ( is_wp_error( $id ) ) { throw new \RuntimeException( $id->get_error_message() ); }
        return (int) $id;
    }

    /** Native blocks keep imported content editable and usable after deactivating Core. */
    public static function content( array $blocks, array $state, array $pack ): string {
        $html = '';
        foreach ( $blocks as $block ) {
            $text = esc_html( $block['text'] ?? '' );
            switch ( $block['type'] ) {
                case 'heading': $html .= '<!-- wp:heading --><h2 class="wp-block-heading">' . $text . '</h2><!-- /wp:heading -->'; break;
                case 'paragraph': $html .= '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->'; break;
                case 'link':
                    $id = $state['pages'][ $block['page'] ] ?? 0;
                    if ( $id && 'publish' === get_post_status( $id ) ) { $html .= '<!-- wp:paragraph --><p><a href="' . esc_url( get_permalink( $id ) ) . '">' . $text . '</a></p><!-- /wp:paragraph -->'; }
                    break;
                case 'image':
                    $id = $state['media'][ $block['media'] ] ?? 0;
                    if ( $id ) {
                        $caption = false !== ( $block['caption'] ?? true ) ? '<figcaption class="wp-element-caption">' . esc_html( $pack['media'][ $block['media'] ]['caption'] ) . '</figcaption>' : '';
                        $html .= '<!-- wp:image ' . wp_json_encode( array( 'id' => $id, 'sizeSlug' => 'large', 'linkDestination' => 'none', 'lightbox' => array( 'enabled' => true ) ) ) . ' --><figure class="wp-block-image size-large"><img src="' . esc_url( wp_get_attachment_image_url( $id, 'large' ) ) . '" alt="' . esc_attr( $pack['media'][ $block['media'] ]['alt'] ) . '" class="wp-image-' . $id . '"/>' . $caption . '</figure><!-- /wp:image -->';
                    }
                    break;
                case 'gallery':
                    $images = self::content( array_map( static fn( $key ) => array( 'type' => 'image', 'media' => $key, 'caption' => false ), $block['media'] ), $state, $pack );
                    $html .= '<!-- wp:group {"className":"prihod-home-gallery"} --><div class="wp-block-group prihod-home-gallery"><!-- wp:heading --><h2 class="wp-block-heading">' . $text . '</h2><!-- /wp:heading --><!-- wp:gallery {"columns":5,"linkTo":"none","className":"prihod-photo-strip"} --><figure class="wp-block-gallery has-nested-images columns-5 is-cropped prihod-photo-strip">' . $images . '</figure><!-- /wp:gallery --></div><!-- /wp:group -->';
                    break;
                case 'schedule':
                    $fallback = self::content( array( array( 'type' => 'paragraph', 'text' => $block['text'] ) ), $state, $pack );
                    $html .= ! empty( $state['include_schedule'] ) && self::schedule_available() ? '<!-- wp:gkpsch/schedule {"view":"month"} -->' . $fallback . '<!-- /wp:gkpsch/schedule -->' : $fallback;
                    break;
            }
        }
        return $html;
    }

    private static function complete_post( int $id, array $row, array $state, array $pack ): void {
        if ( get_post_meta( $id, '_gkpc_demo_ready', true ) || 'trash' === get_post_status( $id ) ) { return; }
        // An owner edit during an interrupted import always wins.
        if ( '' === get_post_field( 'post_content', $id ) ) {
            $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => self::content( $row['blocks'], $state, $pack ), 'post_excerpt' => sanitize_textarea_field( $row['excerpt'] ?? '' ) ) ), true );
            if ( is_wp_error( $result ) ) { throw new \RuntimeException( $result->get_error_message() ); }
            if ( isset( $row['image'], $state['media'][ $row['image'] ] ) ) { set_post_thumbnail( $id, $state['media'][ $row['image'] ] ); }
            if ( isset( $row['category'], $state['categories'][ $row['category'] ] ) ) { wp_set_post_terms( $id, array( $state['categories'][ $row['category'] ] ), 'category' ); }
        }
        update_post_meta( $id, '_gkpc_demo_ready', 1 );
    }

    private static function menu( array $pack, array $state ): int {
        $key = self::key( $pack, 'menu' );
        $found = get_terms( array( 'taxonomy' => 'nav_menu', 'hide_empty' => false, 'meta_key' => '_gkpc_demo_key', 'meta_value' => $key ) );
        $menu = $found && ! is_wp_error( $found ) ? (int) $found[0]->term_id : 0;
        if ( ! $menu ) {
            $menu = wp_create_nav_menu( $pack['menu_title'] );
            if ( is_wp_error( $menu ) ) { throw new \RuntimeException( $menu->get_error_message() ); }
            update_term_meta( $menu, '_gkpc_demo_key', $key );
        }
        $items = wp_get_nav_menu_items( $menu ) ?: array();
        $linked = array_map( static fn( $item ) => (int) $item->object_id, $items );
        foreach ( $pack['navigation'] as $page_key ) {
            $id = $state['pages'][ $page_key ];
            if ( in_array( $id, $linked, true ) || 'publish' !== get_post_status( $id ) ) { continue; }
            $result = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
            if ( is_wp_error( $result ) ) { throw new \RuntimeException( $result->get_error_message() ); }
        }
        return (int) $menu;
    }

    private static function category( array $pack, string $key, string $name ): int {
        $tag = self::key( $pack, 'category-' . $key );
        $found = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'meta_key' => '_gkpc_demo_key', 'meta_value' => $tag ) );
        if ( $found && ! is_wp_error( $found ) ) { return (int) $found[0]->term_id; }
        $term = wp_insert_term( $name, 'category', array( 'slug' => wp_unique_term_slug( 'gkpc-demo-' . $key, (object) array( 'taxonomy' => 'category', 'parent' => 0 ) ) ) );
        if ( is_wp_error( $term ) ) { throw new \RuntimeException( $term->get_error_message() ); }
        update_term_meta( $term['term_id'], '_gkpc_demo_key', $tag );
        return (int) $term['term_id'];
    }

    private static function fill_empty( array $current, array $demo ): array {
        foreach ( $demo as $key => $value ) {
            if ( ! array_key_exists( $key, $current ) || '' === $current[$key] || null === $current[$key] || 0 === $current[$key] ) { $current[$key] = $value; }
            elseif ( is_array( $value ) && is_array( $current[$key] ) ) { $current[$key] = self::fill_empty( $current[$key], $value ); }
        }
        return $current;
    }

    private static function apply_layout( array $pack, array &$state ): void {
        if ( ! current_user_can( 'edit_theme_options' ) ) { throw new \RuntimeException( 'Theme editing permission required.' ); }
        $home = $state['pages']['home'] ?? 0;
        if ( 'publish' !== get_post_status( $home ) ) { throw new \RuntimeException( 'Demo homepage is not published.' ); }
        if ( empty( $state['previous_front'] ) ) { $state['previous_front'] = array( 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => get_option( 'page_on_front' ) ); self::save( $state ); }
        update_option( Profile::OPTION, Profile::sanitize( self::fill_empty( Profile::raw(), $pack['profile'] ) ) );
        $links = array(); foreach ( $state['pages'] as $key => $id ) { if ( 'publish' === get_post_status( $id ) ) { $links[$key] = array( 'id' => $id ); } }
        update_option( Page_Links::OPTION, Page_Links::sanitize( self::fill_empty( Page_Links::get(), $links ) ) );
        if ( 'georg-kloster-prihod' === get_template() ) {
            $options = get_option( 'georg_kloster_prihod_options', array() );
            $demo = $pack['theme']; $demo['profile'] = Profile::raw(); $demo['hero_image'] = $state['media']['monastery']; $demo['home']['pages'] = $state['pages'];
            if ( ! empty( $state['categories']['news'] ) ) { $demo['home']['news_category'] = $state['categories']['news']; }
            $options = self::fill_empty( is_array( $options ) ? $options : array(), $demo );
            // Use the theme's existing validation and translation registration.
            update_option( 'georg_kloster_prihod_options', prihod_theme_sanitize( $options ) );
        }
        $locations = get_theme_mod( 'nav_menu_locations', array() );
        if ( empty( $locations['primary'] ) && has_nav_menu( 'primary' ) === false && isset( get_registered_nav_menus()['primary'] ) ) { $locations['primary'] = $state['menu']; set_theme_mod( 'nav_menu_locations', $locations ); }
        update_option( 'page_on_front', $home ); update_option( 'show_on_front', 'page' );
        $state['applied'] = true; self::save( $state );
    }

    private static function schedule( array $pack, array &$state ): void {
        $place = self::post( $pack, 'schedule-place', $pack['location'], 'gkpsch_location' );
        if ( 'publish' !== get_post_status( $place ) ) { throw new \RuntimeException( 'Demo service place is no longer published.' ); }
        $timezone = \GeorgKlosterPrihodSchedule\Schedule::timezone( $place );
        $today = current_datetime()->setTimezone( new \DateTimeZone( $timezone ) );
        foreach ( $pack['services'] as $key => $row ) {
            $id = self::post( $pack, 'service-' . $key, $row, 'gkpsch_service' );
            $state['services'][$key] = $id; self::save( $state );
            if ( get_post_meta( $id, '_gkpc_demo_ready', true ) || 'trash' === get_post_status( $id ) ) { continue; }
            $rule = \GeorgKlosterPrihodSchedule\Schedule::validate( array_merge( \GeorgKlosterPrihodSchedule\Schedule::defaults(), array( 'date' => $today->modify( '+' . absint( $row['offset_days'] ) . ' days' )->format( 'Y-m-d' ), 'time' => $row['time'], 'timezone' => $timezone, 'location_id' => $place, 'vestment_color' => 'gold' ) ) );
            update_post_meta( $id, '_gkpsch_service', $rule ); update_post_meta( $id, '_gkpc_demo_ready', 1 );
            $state['services'][$key] = $id; self::save( $state );
        }
    }

    public static function run( string $locale, bool $apply = false, bool $with_schedule = false ) {
        if ( ! self::allowed() || ( $apply && ! current_user_can( 'edit_theme_options' ) ) || ( $with_schedule && ! self::schedule_available() ) ) { return new \WP_Error( 'gkpc_demo_forbidden', __( 'You do not have permission to import this demo.', 'georg-kloster-prihod-core' ) ); }
        $lock = Lock::acquire( 'gkpc_demo_lock', 900 );
        if ( ! $lock ) { return new \WP_Error( 'gkpc_demo_busy', __( 'A demo import is already running. Please try again later.', 'georg-kloster-prihod-core' ) ); }
        try {
            $state = self::state();
            if ( ! empty( $state['locale'] ) && $state['locale'] !== $locale ) { throw new \RuntimeException( 'Resume the original demo language.' ); }
            $pack = self::pack( $locale );
            $state['locale'] = $locale; self::save( $state );
            if ( empty( $state['complete'] ) ) {
                $state['include_schedule'] = $with_schedule;
                require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
                foreach ( $pack['media'] as $key => $row ) { $state['media'][$key] = self::media( $pack, $key, $row ); self::save( $state ); }
                foreach ( $pack['categories'] as $key => $name ) { $state['categories'][$key] = self::category( $pack, $key, $name ); self::save( $state ); }
                foreach ( $pack['pages'] as $key => $row ) { $state['pages'][$key] = self::post( $pack, 'page-' . $key, $row, 'page' ); self::save( $state ); }
                foreach ( $pack['pages'] as $key => $row ) { self::complete_post( $state['pages'][$key], $row, $state, $pack ); }
                foreach ( $pack['posts'] as $key => $row ) {
                    $id = self::post( $pack, 'post-' . $key, $row, 'post' );
                    self::complete_post( $id, $row, $state, $pack ); $state['posts'][$key] = $id; self::save( $state );
                }
                $state['menu'] = self::menu( $pack, $state );
                $state['complete'] = true; self::save( $state );
            }
            if ( $with_schedule ) { self::schedule( $pack, $state ); }
            if ( $apply && empty( $state['applied'] ) ) { self::apply_layout( $pack, $state ); }
            return $state;
        } catch ( \Throwable $error ) {
            return new \WP_Error( 'gkpc_demo_failed', $error->getMessage() );
        } finally { Lock::release( 'gkpc_demo_lock', $lock ); }
    }
}
