<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Opt-in rules and local-calendar scheduling; all transport stays in Connector. */
final class Publication_Rules {
    private const TYPE = 'gkch_pub_rule';
    public const HOOK = 'gkch_publication_due';

    public static function boot(): void {
        $caps = array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts' ), 'manage_gkps_settings' );
        $caps['create_posts'] = 'do_not_allow';
        register_post_type( self::TYPE, array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'can_export' => false, 'rewrite' => false, 'supports' => array(), 'map_meta_cap' => false, 'capabilities' => $caps ) );
        add_action( 'wp_after_insert_post', array( self::class, 'published' ), 20, 4 );
        add_action( self::HOOK, array( self::class, 'run' ), 10, 3 );
        add_action( 'admin_post_gkch_rule_save', array( Publication_Rules_Admin::class, 'save' ) );
        add_action( 'admin_post_gkch_rule_delete', array( Publication_Rules_Admin::class, 'delete' ) );
        add_action( 'admin_enqueue_scripts', array( Publication_Rules_Admin::class, 'assets' ) );
        add_action( 'update_option_timezone_string', array( self::class, 'reschedule' ) );
        add_action( 'update_option_gmt_offset', array( self::class, 'reschedule' ) );
        self::ensure_scheduled();
    }

    public static function can_manage(): bool { return current_user_can( 'manage_gkps_settings' ) && current_user_can( 'publish_posts' ); }

    public static function defaults(): array {
        return array( 'id' => 0, 'name' => '', 'enabled' => false, 'type' => 'new_posts', 'channels' => array(), 'category' => 0, 'text' => '', 'text_ru' => '', 'text_de' => '', 'translations' => array(), 'url' => '', 'image' => 0, 'frequency' => 'daily', 'time' => '09:00', 'weekday' => 1, 'date' => '', 'next' => 0, 'revision' => '', 'owner' => 0 );
    }

    public static function get( int $id ): ?array {
        $post = get_post( $id );
        $data = get_post_meta( $id, '_gkch_rule', true );
        return $post && self::TYPE === $post->post_type && 'private' === $post->post_status && is_array( $data ) ? array_merge( self::defaults(), $data, array( 'id' => $id, 'name' => $post->post_title ) ) : null;
    }

    public static function all(): array {
        $result = array();
        foreach ( get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'numberposts' => 100, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
            if ( $rule = self::get( $post->ID ) ) { $result[$post->ID] = $rule; }
        }
        return $result;
    }

    public static function store( int $id, array $input ): int {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! self::can_manage() ) { throw new \RuntimeException( __( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        $owner = Lock::acquire( 'gkch_rules_edit', 60 );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $owner ) { throw new \RuntimeException( __( 'Publication is busy. Try again shortly.', 'georg-kloster-connect-hub' ) ); }
        try {
            $old = $id ? self::get( $id ) : null;
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ( $id && ! $old ) || ( ! $id && count( self::all() ) >= 100 ) ) { throw new \InvalidArgumentException( __( 'Rule not found or rule limit reached.', 'georg-kloster-connect-hub' ) ); }
            $rule = self::defaults();
            foreach ( array( 'name', 'type', 'text', 'text_ru', 'text_de', 'url', 'frequency', 'time', 'date' ) as $field ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( isset( $input[$field] ) && ! is_string( $input[$field] ) ) { throw new \InvalidArgumentException( __( 'Invalid publication rule.', 'georg-kloster-connect-hub' ) ); }
                if ( isset( $input[$field] ) ) { $rule[$field] = 'text' === $field ? sanitize_textarea_field( $input[$field] ) : sanitize_text_field( $input[$field] ); }
            }
            $active_languages = Telegram_Bot::menu_languages();
            $translations = is_array( $old['translations'] ?? null ) ? $old['translations'] : array();
            foreach ( array( 'ru', 'de' ) as $legacy_language ) {
                if ( '' === trim( (string) ( $translations[$legacy_language] ?? '' ) ) && '' !== trim( (string) ( $old['text_' . $legacy_language] ?? '' ) ) ) { $translations[$legacy_language] = $old['text_' . $legacy_language]; }
                if ( isset( $input['text_' . $legacy_language] ) && is_string( $input['text_' . $legacy_language] ) ) { $translations[$legacy_language] = sanitize_textarea_field( $input['text_' . $legacy_language] ); }
            }
            if ( isset( $input['translations'] ) && is_array( $input['translations'] ) ) {
                foreach ( $active_languages as $language => $details ) {
                    $value = is_scalar( $input['translations'][$language] ?? null ) ? (string) $input['translations'][$language] : '';
                    $translations[$language] = sanitize_textarea_field( $value );
                }
            }
            $rule['translations'] = $translations;
            $rule['text_ru'] = (string) ( $translations['ru'] ?? '' );
            $rule['text_de'] = (string) ( $translations['de'] ?? '' );
            foreach ( array( 'category', 'image', 'weekday' ) as $field ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( isset( $input[$field] ) && ! is_scalar( $input[$field] ) ) { throw new \InvalidArgumentException( __( 'Invalid publication rule.', 'georg-kloster-connect-hub' ) ); }
                if ( isset( $input[$field] ) ) { $rule[$field] = absint( $input[$field] ); }
            }
            $rule['enabled'] = ! empty( $input['enabled'] );
            if ( ! array_filter( $rule['translations'] ) && '' !== $rule['text'] ) {
                $default_language = has_filter( 'wpml_default_language' ) ? apply_filters( 'wpml_default_language', null ) : '';
                $default_language = is_string( $default_language ) && isset( $active_languages[$default_language] ) ? $default_language : (string) array_key_first( $active_languages );
                if ( '' !== $default_language ) { $rule['translations'][$default_language] = $rule['text']; }
                $rule['text_ru'] = (string) ( $rule['translations']['ru'] ?? '' );
                $rule['text_de'] = (string) ( $rule['translations']['de'] ?? '' );
            }
            $channels = isset( $input['channels'] ) && is_array( $input['channels'] ) ? array_filter( $input['channels'], 'is_string' ) : array();
            $rule['channels'] = array_values( array_intersect( array_keys( Publications::titles() ), $channels ) );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( '' === $rule['name'] || mb_strlen( $rule['name'] ) > 150 || ! in_array( $rule['type'], array( 'new_posts', 'message' ), true ) || ! $rule['channels'] ) { throw new \InvalidArgumentException( __( 'Enter a rule name and choose at least one channel.', 'georg-kloster-connect-hub' ) ); }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( $rule['category'] && ! term_exists( $rule['category'], 'category' ) ) { throw new \InvalidArgumentException( __( 'Choose an existing category.', 'georg-kloster-connect-hub' ) ); }
            if ( 'message' === $rule['type'] ) {
                $needs_general_text = (bool) array_diff( $rule['channels'], array( 'telegram' ) );
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( ( $needs_general_text && '' === $rule['text'] ) || mb_strlen( $rule['text'] ) > 900 || array_filter( $rule['translations'], static fn( $value ) => ! is_string( $value ) || mb_strlen( $value ) > 900 ) || ! in_array( $rule['frequency'], array( 'once', 'daily', 'weekly' ), true ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $rule['time'] ) || $rule['weekday'] > 6 ) { throw new \InvalidArgumentException( __( 'Enter a message of up to 900 characters and a valid schedule.', 'georg-kloster-connect-hub' ) ); }
                if ( $rule['enabled'] && in_array( 'telegram', $rule['channels'], true ) ) {
                    foreach ( $active_languages as $language => $details ) { if ( '' === trim( (string) ( $rule['translations'][$language] ?? '' ) ) ) { throw new \InvalidArgumentException( __( 'Enter Telegram reminder text for every active WPML language before enabling this rule.', 'georg-kloster-connect-hub' ) ); } }
                }
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( 'once' === $rule['frequency'] && ( ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $rule['date'], $date ) || ! checkdate( (int) $date[2], (int) $date[3], (int) $date[1] ) ) ) { throw new \InvalidArgumentException( __( 'Choose a valid reminder date.', 'georg-kloster-connect-hub' ) ); }
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( '' !== $rule['url'] && ( ! wp_http_validate_url( $rule['url'] ) || ! in_array( wp_parse_url( $rule['url'], PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) ) { throw new \InvalidArgumentException( __( 'Enter a public HTTP or HTTPS link.', 'georg-kloster-connect-hub' ) ); }
                $rule['url'] = esc_url_raw( $rule['url'] );
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( in_array( 'instagram', $rule['channels'], true ) && ! Social_Providers::attachment_image( $rule['image'] ) ) { throw new \InvalidArgumentException( __( 'Instagram requires a public HTTPS JPEG from the media library.', 'georg-kloster-connect-hub' ) ); }
            }
            if ( $rule['enabled'] ) {
                foreach ( $rule['channels'] as $channel ) {
                    $connector = Connector::instance( $channel );
                    if ( 'telegram' === $channel && ! Telegram_Bot::broadcasting_enabled() ) { throw new \InvalidArgumentException( __( 'Enable Telegram subscriber broadcasts on the Telegram settings tab before selecting it for publications.', 'georg-kloster-connect-hub' ) ); }
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                    if ( ! $connector || ! $connector->valid( $connector->settings() ) ) { throw new \InvalidArgumentException( __( 'Configure and enable each selected channel first.', 'georg-kloster-connect-hub' ) ); }
                }
            }
            $rule['owner'] = get_current_user_id();
            $rule['revision'] = wp_generate_uuid4();
            $rule['next'] = $rule['enabled'] && 'message' === $rule['type'] ? self::next_time( $rule, time() ) : 0;
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( $rule['enabled'] && 'message' === $rule['type'] && ! $rule['next'] ) { throw new \InvalidArgumentException( __( 'Choose a future reminder date and time.', 'georg-kloster-connect-hub' ) ); }
            $post = array( 'post_type' => self::TYPE, 'post_status' => 'private', 'post_title' => $rule['name'], 'post_author' => $rule['owner'] );
            if ( $id ) { $post['ID'] = $id; }
            $result = wp_insert_post( wp_slash( $post ), true );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( is_wp_error( $result ) ) { throw new \RuntimeException( __( 'Could not save the publication rule.', 'georg-kloster-connect-hub' ) ); }
            $id = (int) $result; $rule['id'] = $id;
            if ( $old ) { self::clear_event( $old ); }
            update_post_meta( $id, '_gkch_rule', wp_slash( $rule ) );
            delete_post_meta( $id, '_gkch_rule_error' );
            self::schedule( $rule );
            return $id;
        } finally { Lock::release( 'gkch_rules_edit', $owner ); }
    }

    public static function remove( int $id ): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! self::can_manage() ) { throw new \RuntimeException( __( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        $rule = self::get( $id );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $rule ) { throw new \InvalidArgumentException( __( 'Invalid publication rule.', 'georg-kloster-connect-hub' ) ); }
        // Keep queued history; the worker cancels jobs whose rule no longer exists.
        $owner = Lock::acquire( 'gkch_rules_edit', 60 );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $owner ) { throw new \RuntimeException( __( 'Publication is busy. Try again shortly.', 'georg-kloster-connect-hub' ) ); }
        try { self::clear_event( $rule ); wp_trash_post( $id ); }
        finally { Lock::release( 'gkch_rules_edit', $owner ); }
    }

    public static function next_time( array $rule, int $after ): int {
        $now = ( new \DateTimeImmutable( '@' . $after ) )->setTimezone( wp_timezone() );
        if ( 'once' === $rule['frequency'] ) {
            $date = new \DateTimeImmutable( $rule['date'] . ' ' . $rule['time'], wp_timezone() );
            return $date->getTimestamp() > $after ? $date->getTimestamp() : 0;
        }
        for ( $day = 0; $day <= 7; ++$day ) {
            $local = $now->modify( '+' . $day . ' days' );
            if ( 'weekly' === $rule['frequency'] && (int) $local->format( 'w' ) !== $rule['weekday'] ) { continue; }
            $date = new \DateTimeImmutable( $local->format( 'Y-m-d' ) . ' ' . $rule['time'], wp_timezone() );
            if ( $date->getTimestamp() > $after ) { return $date->getTimestamp(); }
        }
        return 0;
    }

    private static function clear_event( array $rule ): void { wp_clear_scheduled_hook( self::HOOK, array( $rule['id'], $rule['revision'], $rule['next'] ) ); }

    private static function schedule( array $rule ): void {
        if ( ! $rule['enabled'] || 'message' !== $rule['type'] || ! $rule['next'] ) { return; }
        $args = array( $rule['id'], $rule['revision'], $rule['next'] );
        if ( wp_next_scheduled( self::HOOK, $args ) ) { return; }
        $result = wp_schedule_single_event( $rule['next'], self::HOOK, $args, true );
        if ( ! $result || is_wp_error( $result ) ) { update_post_meta( $rule['id'], '_gkch_rule_error', __( 'Could not schedule publication. Check WordPress Cron.', 'georg-kloster-connect-hub' ) ); }
    }

    public static function ensure_scheduled(): void { foreach ( self::all() as $rule ) { self::schedule( $rule ); } }
    public static function reschedule(): void {
        $owner = Lock::acquire( 'gkch_rules_edit', 60 );
        if ( ! $owner ) { return; }
        try {
            foreach ( self::all() as $rule ) {
                if ( ! $rule['enabled'] || 'message' !== $rule['type'] ) { continue; }
                self::clear_event( $rule ); $rule['next'] = self::next_time( $rule, time() );
                update_post_meta( $rule['id'], '_gkch_rule', wp_slash( $rule ) ); self::schedule( $rule );
            }
        } finally { Lock::release( 'gkch_rules_edit', $owner ); }
    }
    public static function deactivate(): void { foreach ( self::all() as $rule ) { self::clear_event( $rule ); } }

    public static function image( int $id ): string { $rule = self::get( $id ); return $rule ? Social_Providers::attachment_image( $rule['image'] ) : ''; }

    public static function allows( array $job ): bool {
        $context = $job['reply_context'] ?? array();
        if ( empty( $context['rule_id'] ) ) { return 'automation' !== $job['kind']; }
        $rule = self::get( (int) $context['rule_id'] );
        if ( ! $rule || ! $rule['enabled'] || ! hash_equals( $rule['revision'], (string) ( $context['revision'] ?? '' ) ) || ! user_can( $rule['owner'], 'publish_posts' ) || ! user_can( $rule['owner'], 'manage_gkps_settings' ) ) { return false; }
        return 'automation' === $job['kind'] ? 'message' === $rule['type'] && $job['source'] === $rule['id'] : 'post' === $job['kind'] && 'new_posts' === $rule['type'] && user_can( $rule['owner'], 'edit_post', $job['source'] );
    }

    public static function published( int $id, \WP_Post $post, bool $update, ?\WP_Post $before ): void {
        if ( 'post' === $post->post_type && $before && 'publish' === $before->post_status ) { update_post_meta( $id, '_gkch_first_publication', true ); }
        if ( 'post' !== $post->post_type || 'publish' !== $post->post_status || ( $before && 'publish' === $before->post_status ) || wp_is_post_revision( $id ) || get_post_meta( $id, '_gkch_first_publication', true ) ) { return; }
        // No backfill, update reposts or unpublish/republish duplicates.
        update_post_meta( $id, '_gkch_first_publication', true );
        foreach ( self::all() as $rule ) {
            if ( ! $rule['enabled'] || 'new_posts' !== $rule['type'] || ( $rule['category'] && ! has_category( $rule['category'], $id ) ) ) { continue; }
            self::dispatch( $rule, 'post:' . $id, 'post', $id, $post->post_title . "\n" . wp_html_excerpt( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 700, '…' ), get_permalink( $id ) );
        }
    }

    public static function run( int $id, string $revision, int $slot ): void {
        $owner = Lock::acquire( 'gkch_rules_edit', 60 );
        if ( ! $owner ) { return; }
        try {
            $rule = self::get( $id );
            if ( ! $rule || ! $rule['enabled'] || 'message' !== $rule['type'] || $rule['revision'] !== $revision || $rule['next'] !== $slot || $slot > time() ) { return; }
            self::dispatch( $rule, (string) $slot, 'automation', $id, $rule['text'], $rule['url'] );
            self::clear_event( $rule );
            $rule['next'] = self::next_time( $rule, max( time(), $slot ) );
            update_post_meta( $id, '_gkch_rule', wp_slash( $rule ) );
            self::schedule( $rule );
        } finally { Lock::release( 'gkch_rules_edit', $owner ); }
    }

    private static function dispatch( array $rule, string $slot, string $kind, int $source, string $text, string $url ): void {
        $context = array( 'rule_id' => $rule['id'], 'revision' => $rule['revision'], 'rule_name' => $rule['name'], 'configuration' => $rule['id'] . ':' . $rule['revision'] . ':' . $slot );
        $job = array( 'kind' => $kind, 'source' => $source, 'reply_context' => $context );
        if ( ! self::allows( $job ) ) { update_post_meta( $rule['id'], '_gkch_rule_error', __( 'Rule paused: its owner no longer has publication and settings permissions.', 'georg-kloster-connect-hub' ) ); return; }
        $errors = array();
        foreach ( $rule['channels'] as $channel ) {
            $connector = Connector::instance( $channel );
            if ( ! $connector || ! $connector->queue( $text, $url, $kind, $source, $context ) ) { $errors[] = Publications::titles()[$channel]; }
        }
        if ( $errors ) { update_post_meta( $rule['id'], '_gkch_rule_error', __( 'Not queued. Check the connection, required image and WordPress Cron:', 'georg-kloster-connect-hub' ) . ' ' . implode( ', ', $errors ) ); }
        else { delete_post_meta( $rule['id'], '_gkch_rule_error' ); }
        update_post_meta( $rule['id'], '_gkch_rule_last', time() );
    }
}
