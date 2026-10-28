<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Subscriber commands, schedule replies and immediate Telegram broadcasts. */
final class Telegram_Bot {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The subscriber/campaign ledger uses plugin-owned relational tables, joined reads and atomic status transitions; uncached reads must see delivery state written in the same request.
	private const MAX_REPLY_MEDIA = 10;

	private const SCHEMA_OPTION = 'gkch_telegram_schema';
	private const COMMANDS_OPTION = 'gkch_telegram_custom_commands';
	private const SYSTEM_COMMANDS_OPTION = 'gkch_telegram_system_commands';
	private const DIGEST = 'gkch_telegram_daily_digest';

	public static function hooks(): void {
		add_action( 'init', array( self::class, 'install' ), 1 );
		add_action( 'init', array( self::class, 'ensure_digest' ), 2 );
		add_action( 'admin_menu', array( self::class, 'menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'admin_assets' ) );
		add_action( 'admin_post_gkch_telegram_action', array( self::class, 'admin_action' ) );
		add_action( 'admin_post_gkch_telegram_bot_commands_save', array( self::class, 'save_bot_commands' ) );
		add_action( self::DIGEST, array( self::class, 'daily_digest' ) );
		add_action( 'gkps_connector_settings_fields', array( self::class, 'settings_fields' ), 20, 2 );
		add_action( 'gkps_connector_saved', array( self::class, 'settings_saved' ), 20, 2 );
	}

	public static function tables(): array {
		global $wpdb;
		return array(
			'subscribers' => $wpdb->prefix . 'gkch_tg_subscribers',
			'campaigns'   => $wpdb->prefix . 'gkch_tg_campaigns',
			'deliveries'  => $wpdb->prefix . 'gkch_tg_deliveries',
		);
	}

	public static function install(): void {
		if ( '2' === get_option( self::SCHEMA_OPTION ) ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables = self::tables();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$tables['subscribers']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			chat_id varchar(32) NOT NULL,
			language varchar(35) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_id (chat_id),
			KEY status_language (status,language)
		) $charset;" );
		dbDelta( "CREATE TABLE {$tables['campaigns']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dedupe_key char(64) NOT NULL,
			source_type varchar(24) NOT NULL,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			context longtext NOT NULL,
			title varchar(190) NOT NULL,
			payload_ru text NOT NULL,
			payload_de text NOT NULL,
			url text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			total int(10) unsigned NOT NULL DEFAULT 0,
			sent int(10) unsigned NOT NULL DEFAULT 0,
			failed int(10) unsigned NOT NULL DEFAULT 0,
			skipped int(10) unsigned NOT NULL DEFAULT 0,
			error varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_key (dedupe_key),
			KEY status_created (status,created_at)
		) $charset;" );
		dbDelta( "CREATE TABLE {$tables['deliveries']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			subscriber_id bigint(20) unsigned NOT NULL,
			language varchar(35) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			next_attempt datetime NOT NULL,
			remote_id varchar(32) NOT NULL DEFAULT '',
			error varchar(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY campaign_subscriber (campaign_id,subscriber_id),
			KEY due (campaign_id,status,next_attempt)
		) $charset;" );
		update_option( self::SCHEMA_OPTION, '2', false );
	}

	public static function broadcasting_enabled(): bool {
		$settings = self::connector_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['telegram_broadcasts'] ) && is_string( $settings['token'] ) && (bool) preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', $settings['token'] );
	}

	private static function connector_settings(): array {
		$connector = Connector::instance( 'telegram' );
		return $connector ? $connector->settings() : array();
	}

	public static function settings_fields( string $provider, array $settings ): void {
		if ( 'telegram' !== $provider ) { return; }
		$time = is_string( $settings['daily_digest_time'] ?? null ) && preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $settings['daily_digest_time'] ) ? $settings['daily_digest_time'] : '08:00';
		echo '<fieldset><legend><h2>' . esc_html__( 'Public Telegram bot and subscriber broadcasts', 'georg-kloster-connect-hub' ) . '</h2></legend>';
		echo '<p><label><input type="checkbox" name="telegram_broadcasts" value="1"' . checked( ! empty( $settings['telegram_broadcasts'] ), true, false ) . '> ' . esc_html__( 'Enable bot subscriptions and broadcasts to people who pressed Start', 'georg-kloster-connect-hub' ) . '</label></p>';
		echo '<p>' . esc_html__( 'The old global Chat ID is not used for form routing. News and schedule announcements are sent to active bot subscribers; each form uses only its own responsible person’s Chat ID. A person must open the bot and press Start first. /stop removes the subscription.', 'georg-kloster-connect-hub' ) . '</p>';
		echo '<p><label><input type="checkbox" name="daily_digest_enabled" value="1"' . checked( ! empty( $settings['daily_digest_enabled'] ), true, false ) . '> ' . esc_html__( 'Send subscribers a daily schedule digest', 'georg-kloster-connect-hub' ) . '</label> <label>' . esc_html__( 'Time in the WordPress time zone', 'georg-kloster-connect-hub' ) . ' <input type="time" name="daily_digest_time" value="' . esc_attr( $time ) . '"></label></p>';
		echo '<p>' . esc_html__( 'Subscribers can use /today, /week and /lang. Daily digests include the parish services and the calendar day’s main commemorations when the connected calendar is available.', 'georg-kloster-connect-hub' ) . '</p></fieldset>';
	}

	public static function settings_saved( string $provider, array $settings ): void {
		if ( 'telegram' === $provider ) { self::sync_digest( $settings ); }
	}

	private static function sync_digest( array $settings ): void {
		wp_clear_scheduled_hook( self::DIGEST );
		if ( empty( $settings['enabled'] ) || empty( $settings['telegram_broadcasts'] ) || empty( $settings['daily_digest_enabled'] ) ) { return; }
		$time = $settings['daily_digest_time'] ?? '08:00';
		if ( ! is_string( $time ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time ) ) { $time = '08:00'; }
		$now = new \DateTimeImmutable( 'now', wp_timezone() );
		$next = new \DateTimeImmutable( $now->format( 'Y-m-d' ) . ' ' . $time, wp_timezone() );
		if ( $next->getTimestamp() <= time() ) { $next = $next->modify( '+1 day' ); }
		wp_schedule_single_event( $next->getTimestamp(), self::DIGEST );
	}

	public static function ensure_digest(): void {
		$settings = self::connector_settings();
		if ( empty( $settings['daily_digest_enabled'] ) || empty( $settings['telegram_broadcasts'] ) || empty( $settings['enabled'] ) ) {
			wp_clear_scheduled_hook( self::DIGEST );
			return;
		}
		if ( ! wp_next_scheduled( self::DIGEST ) ) { self::sync_digest( $settings ); }
	}

	public static function receive_update( array $update ): bool {
		$message = is_array( $update['message'] ?? null ) ? $update['message'] : array();
		$callback = is_array( $update['callback_query'] ?? null ) ? $update['callback_query'] : array();
		$update_id = is_int( $update['update_id'] ?? null ) ? $update['update_id'] : 0;
		if ( $callback ) {
			if ( $update_id > 0 ) {
				$key = 'gkch_tg_update_' . $update_id;
				if ( get_transient( $key ) ) { return true; }
				set_transient( $key, 1, DAY_IN_SECONDS );
			}
			return self::handle_callback( $callback, $update_id );
		}
		$text = $message['text'] ?? '';
		if ( ! is_string( $text ) || '' === trim( $text ) ) { return false; }
		$system_command = null;
		$custom_command = null;
		$command = '';
		if ( preg_match( '/^\s*\/([a-zA-Z][a-zA-Z0-9_]{0,31})(?:@[a-zA-Z0-9_]+)?(?:\s+([^\r\n]+))?\s*$/u', $text, $match ) ) {
			$command = strtolower( $match[1] );
			$system_command = self::system_command( $command );
			$custom_command = self::custom_command( $command );
			if ( ! $system_command && ! $custom_command ) { return false; }
		} else {
			$custom_command = self::keyword_responder( $text );
			if ( ! $custom_command ) { return false; }
		}
		if ( 'private' !== ( $message['chat']['type'] ?? '' ) || ! is_scalar( $message['chat']['id'] ?? null ) || (string) $message['chat']['id'] !== (string) ( $message['from']['id'] ?? '' ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', (string) ( $message['chat']['id'] ?? '' ) ) || ! empty( $message['from']['is_bot'] ) ) { return true; }
		$chat_id = (string) $message['chat']['id'];
		$language = self::language_from_telegram( $message['from']['language_code'] ?? '' );
		if ( $update_id > 0 ) {
			$key = 'gkch_tg_update_' . $update_id;
			if ( get_transient( $key ) ) { return true; }
			set_transient( $key, 1, DAY_IN_SECONDS );
		}
		$action = $system_command['action'] ?? '';
		if ( 'start' === $action ) {
			if ( ! self::broadcasting_enabled() ) { self::send_direct( $chat_id, self::copy( 'disabled', $language ) ); return true; }
			self::subscribe( $chat_id, $language );
			self::send_system_reply( $chat_id, self::system_command( 'start' ) ?? array( 'command' => 'start', 'action' => 'start' ), $language, self::language_keyboard( $language ) );
			return true;
		}
		if ( 'stop' === $action ) { $language = self::subscriber_language( $chat_id, $language ); self::unsubscribe( $chat_id ); self::send_system_reply( $chat_id, self::system_command( 'stop' ) ?? array( 'command' => 'stop', 'action' => 'stop' ), $language ); return true; }
		if ( ! self::is_active( $chat_id ) ) { self::send_direct( $chat_id, self::copy( 'need_start', $language ) ); return true; }
		$language = self::subscriber_language( $chat_id, $language );
		if ( $custom_command ) { self::send_configured_reply( $chat_id, $custom_command, $language ); return true; }
		if ( $system_command ) { self::run_system_command( $chat_id, $language, $system_command, $update_id ); }
		return true;
	}

	private static function reply_to_schedule_command( string $chat_id, string $language, string $command, string $prefix = '' ): void {
		if ( ! preg_match( '/^[1-9][0-9]{0,19}$/D', $chat_id ) || ! self::valid_language_code( $language ) || ! in_array( $command, array( 'today', 'week' ), true ) || ! self::is_active( $chat_id ) ) { return; }
		$date = new \DateTimeImmutable( 'today', wp_timezone() );
		$content = 'today' === $command ? self::day_content( $date, $language ) : array( 'text' => self::week_message( $date, $language ), 'icon_url' => '' );
		$template = '' === trim( $prefix ) ? '{schedule}' : $prefix;
		$text = str_contains( $template, '{schedule}' ) ? str_replace( '{schedule}', $content['text'], $template ) : $template;
		$system_command = self::system_command( $command ) ?? array();
		self::send_scheduled_output( $chat_id, $text, (string) $content['icon_url'], self::reply_menu_markup( $system_command, $language ) );
	}

	private static function handle_callback( array $callback, int $update_id = 0 ): bool {
		$data = $callback['data'] ?? '';
		if ( ! is_string( $data ) || ! preg_match( '/^(?:lang:[a-z][a-z0-9_-]{1,30}|botcmd:[a-z][a-z0-9_]{0,31}|reply:[a-f0-9]{16})$/D', $data ) ) { return false; }
		$message = $callback['message'] ?? array();
		$chat = $message['chat']['id'] ?? null;
		$from = $callback['from']['id'] ?? null;
		if ( 'private' === ( $message['chat']['type'] ?? '' ) && is_scalar( $chat ) && (string) $chat === (string) $from && preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $chat ) && self::is_active( (string) $chat ) ) {
			$chat_id = (string) $chat;
			$language = self::subscriber_language( $chat_id, self::language_from_telegram( $callback['from']['language_code'] ?? '' ) );
            if ( preg_match( '/^lang:([a-z][a-z0-9_-]{1,30})$/D', $data, $match ) && isset( self::active_languages()[$match[1]] ) ) {
				$language = $match[1];
				self::update_language( $chat_id, $language );
				self::send_direct( $chat_id, self::copy( 'language_saved', $language ), self::menu_keyboard( $language ) );
			} elseif ( preg_match( '/^botcmd:([a-z][a-z0-9_]{0,31})$/D', $data, $match ) ) {
				$system = self::system_command( $match[1] );
				$custom = self::custom_command( $match[1] );
				if ( $system ) { self::run_system_command( $chat_id, $language, $system, $update_id ); }
				elseif ( $custom ) { self::send_configured_reply( $chat_id, $custom, $language ); }
			} elseif ( preg_match( '/^reply:([a-f0-9]{16})$/D', $data, $match ) && ( $responder = self::keyword_responder_by_id( $match[1] ) ) ) {
				self::send_configured_reply( $chat_id, $responder, $language );
			}
		}
		if ( is_string( $callback['id'] ?? null ) ) { self::api( 'answerCallbackQuery', array( 'callback_query_id' => $callback['id'] ) ); }
		return true;
	}

	public static function language_from_telegram( $language ): string {
		$code = is_string( $language ) ? strtolower( str_replace( '_', '-', trim( $language ) ) ) : '';
		$languages = self::active_languages();
		if ( isset( $languages[$code] ) ) { return $code; }
		$base = explode( '-', $code )[0] ?? '';
		if ( isset( $languages[$base] ) ) { return $base; }
		foreach ( array_keys( $languages ) as $active_code ) { if ( str_starts_with( $active_code, $base . '-' ) ) { return $active_code; } }
		if ( ! function_exists( 'icl_get_languages' ) && ! has_filter( 'wpml_active_languages' ) && preg_match( '/^[a-z]{2}$/D', $base ) ) { return $base; }
		return self::default_language_code();
	}

	private static function valid_language_code( string $language ): bool { return (bool) preg_match( '/^[a-z][a-z0-9_-]{1,30}$/D', $language ); }

	private static function active_languages(): array {
		$languages = array();
		if ( function_exists( 'icl_get_languages' ) ) { $raw = icl_get_languages( 'skip_missing=0' ); }
		else {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the exact public WPML filter name.
			$raw = apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
		}
		if ( is_array( $raw ) ) {
			foreach ( $raw as $code => $details ) {
				$code = strtolower( sanitize_key( (string) $code ) );
				if ( ! self::valid_language_code( $code ) ) { continue; }
				$details = is_array( $details ) ? $details : array();
				$languages[$code] = array( 'name' => sanitize_text_field( (string) ( $details['native_name'] ?? $details['translated_name'] ?? $details['display_name'] ?? $code ) ), 'locale' => sanitize_text_field( (string) ( $details['default_locale'] ?? $details['locale'] ?? '' ) ) );
			}
		}
		if ( ! $languages ) {
			$locale = determine_locale();
			$code = strtolower( substr( str_replace( '-', '_', $locale ), 0, 2 ) );
			if ( self::valid_language_code( $code ) ) { $languages[$code] = array( 'name' => $code, 'locale' => $locale ); }
		}
		return $languages;
	}

	public static function menu_languages(): array { return self::active_languages(); }

	private static function default_language_code(): string {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the exact public WPML filter name.
		$default = has_filter( 'wpml_default_language' ) ? apply_filters( 'wpml_default_language', null ) : '';
		$default = is_string( $default ) ? strtolower( sanitize_key( $default ) ) : '';
		$languages = self::active_languages();
		if ( self::valid_language_code( $default ) && isset( $languages[$default] ) ) { return $default; }
		return $languages ? (string) array_key_first( $languages ) : 'en';
	}

	private static function language_locale( string $language ): string {
		$info = self::active_languages()[$language] ?? array();
		if ( is_string( $info['locale'] ?? null ) && '' !== $info['locale'] ) { return $info['locale']; }
		return $language . '_' . strtoupper( $language );
	}

	private static function copy( string $key, string $language ): string {
		$switched = switch_to_locale( self::language_locale( $language ) );
		$messages = array(
			'disabled' => __( 'The Telegram subscription service is currently disabled. Please try again later.', 'georg-kloster-connect-hub' ),
			'welcome' => __( 'You are subscribed to monastery news and announcements. New posts and schedule updates will appear here. Use /menu to open bot commands; press /stop to unsubscribe.', 'georg-kloster-connect-hub' ),
			'stopped' => __( 'You have unsubscribed. The bot will no longer send you broadcasts. Press /start to subscribe again.', 'georg-kloster-connect-hub' ),
			'need_start' => __( 'Press /start first to subscribe and use the bot commands.', 'georg-kloster-connect-hub' ),
			'choose_language' => __( 'Choose the language for messages:', 'georg-kloster-connect-hub' ),
			'language_saved' => __( 'The message language has been saved.', 'georg-kloster-connect-hub' ),
			'russian' => __( 'Russian', 'georg-kloster-connect-hub' ),
			'german' => __( 'German', 'georg-kloster-connect-hub' ),
			'help' => __( 'Available bot commands:', 'georg-kloster-connect-hub' ),
			'menu_intro' => __( 'Choose an action:', 'georg-kloster-connect-hub' ),
			'today' => __( 'Schedule for today', 'georg-kloster-connect-hub' ),
			'week' => __( 'Schedule for this week', 'georg-kloster-connect-hub' ),
			'icon_day' => __( 'Icon of the day', 'georg-kloster-connect-hub' ),
			'services_week' => __( 'Services later this week', 'georg-kloster-connect-hub' ),
			'no_service_today' => __( 'No service is scheduled for today.', 'georg-kloster-connect-hub' ),
			'no_services' => __( 'No services are scheduled for this period.', 'georg-kloster-connect-hub' ),
			'calendar_unavailable' => __( 'Church calendar data is not available right now.', 'georg-kloster-connect-hub' ),
			'feast' => __( 'Commemorations of the day', 'georg-kloster-connect-hub' ),
			'services' => __( 'Services', 'georg-kloster-connect-hub' ),
			'schedule_link' => __( 'Full schedule', 'georg-kloster-connect-hub' ),
			'digest' => __( 'Services and commemorations of the day', 'georg-kloster-connect-hub' ),
			'changed' => __( 'The service schedule has changed', 'georg-kloster-connect-hub' ),
			'post_unavailable' => __( 'This page or news item is no longer available.', 'georg-kloster-connect-hub' ),
			'open_article' => __( 'Read the news', 'georg-kloster-connect-hub' ),
		);
		$message = $messages[$key] ?? '';
		if ( $switched ) { restore_previous_locale(); }
		return $message;
	}

	private static function system_command_defaults(): array {
		$defaults = array();
		$commands = array( 'start', 'stop', 'today', 'week', 'lang', 'help', 'menu' );
		foreach ( self::active_languages() as $language => $details ) {
			$switched = switch_to_locale( self::language_locale( $language ) );
			$descriptions = array(
				'start' => __( 'Subscribe to monastery news', 'georg-kloster-connect-hub' ), 'stop' => __( 'Unsubscribe from messages', 'georg-kloster-connect-hub' ),
				'today' => __( 'Today’s services and commemorations', 'georg-kloster-connect-hub' ), 'week' => __( 'Services this week', 'georg-kloster-connect-hub' ),
				'lang' => __( 'Choose message language', 'georg-kloster-connect-hub' ), 'help' => __( 'Show bot commands', 'georg-kloster-connect-hub' ), 'menu' => __( 'Open the bot menu', 'georg-kloster-connect-hub' ),
			);
			if ( $switched ) { restore_previous_locale(); }
			foreach ( $descriptions as $key => $description ) { $defaults[$key]['description_' . $language] = $description; }
			$defaults['start']['response_' . $language] = self::copy( 'welcome', $language );
			$defaults['stop']['response_' . $language] = self::copy( 'stopped', $language );
			$defaults['today']['response_' . $language] = '{schedule}';
			$defaults['week']['response_' . $language] = '{schedule}';
			$defaults['lang']['response_' . $language] = self::copy( 'choose_language', $language );
			$defaults['help']['response_' . $language] = self::copy( 'help', $language );
			$defaults['menu']['response_' . $language] = self::copy( 'menu_intro', $language );
		}
		foreach ( $commands as $key ) {
			$defaults[$key]['command'] = $key;
			$defaults[$key]['action'] = $key;
			$defaults[$key]['enabled'] = true;
			$defaults[$key]['reply_type'] = in_array( $key, array( 'today', 'week' ), true ) ? 'schedule' : 'text';
			$defaults[$key]['schedule_period'] = in_array( $key, array( 'week' ), true ) ? 'week' : 'today';
		}
		return $defaults;
	}

	private static function system_commands(): array {
		$commands = self::system_command_defaults();
		$saved = get_option( self::SYSTEM_COMMANDS_OPTION, array() );
		if ( ! is_array( $saved ) ) { return $commands; }
		foreach ( $commands as $key => $default ) {
			if ( ! is_array( $saved[$key] ?? null ) ) { continue; }
			$row = $saved[$key];
			foreach ( $row as $field => $value ) {
				if ( 'menu_buttons' === $field ) { $commands[$key]['menu_buttons'] = self::sanitize_menu_buttons( $value ); continue; }
				if ( ! is_scalar( $value ) ) { continue; }
				if ( preg_match( '/^(description|response|media_ids)_(.+)$/', (string) $field, $match ) && self::valid_language_code( $match[2] ) ) {
					$commands[$key][$field] = 'description' === $match[1] ? self::limit_text( sanitize_text_field( (string) $value ), 256 ) : ( 'response' === $match[1] ? self::sanitize_reply( (string) $value ) : self::sanitize_media_ids( (string) $value ) );
				} elseif ( in_array( $field, array( 'reply_type', 'schedule_period' ), true ) ) { $commands[$key][$field] = sanitize_key( (string) $value ); }
				elseif ( 'reply_post_id' === $field ) { $commands[$key][$field] = absint( $value ); }
			}
			if ( ! in_array( $commands[$key]['reply_type'] ?? '', array( 'text', 'html', 'schedule', 'page', 'news' ), true ) ) { $commands[$key]['reply_type'] = $default['reply_type'] ?? 'text'; }
			if ( ! in_array( $commands[$key]['schedule_period'] ?? '', array( 'today', 'week' ), true ) ) { $commands[$key]['schedule_period'] = $default['schedule_period'] ?? 'today'; }
			$commands[$key]['enabled'] = ! empty( $row['enabled'] );
		}
		return $commands;
	}

	private static function system_command( string $name ): ?array {
		$name = 'language' === $name ? 'lang' : $name;
		$commands = self::system_commands();
		$subscription_command = in_array( $name, array( 'start', 'stop' ), true );
		return isset( $commands[$name] ) && ( ! empty( $commands[$name]['enabled'] ) || $subscription_command ) ? $commands[$name] : null;
	}

	private static function system_response( string $key, string $language, bool $fallback = true ): string {
		$commands = self::system_commands();
		$response = trim( (string) ( $commands[$key]['response_' . $language] ?? '' ) );
		$fallback_keys = array( 'start' => 'welcome', 'stop' => 'stopped', 'lang' => 'choose_language', 'help' => 'help', 'menu' => 'menu_intro' );
		return '' !== $response || ! $fallback ? $response : self::copy( $fallback_keys[$key] ?? $key, $language );
	}

	private static function configured_response( array $command, string $language ): string {
		$response = trim( (string) ( $command['response_' . $language] ?? '' ) );
		if ( '' === $response && $language !== self::default_language_code() ) { $response = trim( (string) ( $command['response_' . self::default_language_code()] ?? '' ) ); }
		return $response;
	}

	private static function send_system_reply( string $chat_id, array $command, string $language, array $markup = array() ): void {
		self::send_configured_reply( $chat_id, $command, $language, $markup );
	}

	private static function send_configured_reply( string $chat_id, array $command, string $language, array $markup = array() ): void {
		$markup = self::reply_menu_markup( $command, $language, $markup, ! str_starts_with( $chat_id, '-' ) );
		$type = in_array( $command['reply_type'] ?? 'text', array( 'text', 'html', 'schedule', 'page', 'news' ), true ) ? (string) $command['reply_type'] : 'text';
		$response = self::configured_response( $command, $language );
		if ( in_array( $type, array( 'page', 'news' ), true ) ) {
			$post_id = absint( $command['reply_post_id'] ?? 0 );
			$translated = $post_id ? self::translated_post_id( $post_id, $language ) : 0;
			$source_language = $post_id ? self::post_language( $post_id ) : '';
			$post = get_post( $translated ?: ( $language === $source_language ? $post_id : 0 ) );
			$expected_type = 'page' === $type ? 'page' : 'post';
			if ( $post && 'publish' === $post->post_status && $expected_type === $post->post_type ) {
				$link = get_permalink( $post );
				if ( 'page' === $type ) {
					$old = self::switch_language( $language );
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the core WordPress content filter.
					try { $content = apply_filters( 'the_content', $post->post_content ); }
					finally { self::restore_language( $old ); }
					$content = preg_replace( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', '<b>$2</b><br>', $content );
					$text = '<b>' . esc_html( $post->post_title ) . "</b>\n\n" . $content . "\n\n" . esc_html( $link );
					self::send_direct( $chat_id, $text, $markup, true );
				} else {
					$text = self::post_teaser( $post );
					$markup = self::article_button_markup( $link, $language, $markup );
					$image = self::post_featured_image_url( (int) $post->ID );
					if ( '' !== $image ) { self::send_direct_photo( $chat_id, $image, $text, $markup ); }
					else { self::send_direct_long_html( $chat_id, $text, $markup ); }
				}
			} else { self::send_direct( $chat_id, self::copy( 'post_unavailable', $language ), $markup ); }
			return;
		}
		if ( 'schedule' === $type ) {
			$period = 'week' === ( $command['schedule_period'] ?? '' ) ? 'week' : 'today';
			$date = new \DateTimeImmutable( 'today', wp_timezone() );
			$content = 'week' === $period ? array( 'text' => self::week_message( $date, $language ), 'icon_url' => '' ) : self::day_content( $date, $language );
			$schedule = $content['text'];
			$html = true;
			if ( '' === $response ) { $response = '{schedule}'; }
			if ( str_contains( $response, '{schedule}' ) ) { $text = str_replace( '{schedule}', $schedule, $response ); }
			else { $text = $response . "\n\n" . $schedule; }
			self::send_configured_output( $chat_id, $text, $command, $language, $markup, $html, (string) $content['icon_url'] );
			return;
		}
		if ( '' === $response && isset( $command['action'] ) ) {
			$fallbacks = array( 'start' => 'welcome', 'stop' => 'stopped', 'lang' => 'choose_language', 'help' => 'help', 'menu' => 'menu_intro' );
			$response = self::copy( $fallbacks[$command['action']] ?? $command['action'], $language );
		}
		$html = 'html' === $type || self::has_telegram_formatting( $response );
		self::send_configured_output( $chat_id, $response, $command, $language, $markup, $html );
	}

	private static function send_configured_output( string $chat_id, string $text, array $command, string $language, array $markup, bool $html, string $leading_photo = '' ): void {
		$media = self::configured_media( $command, $language );
		if ( '' !== $leading_photo && self::valid_calendar_icon_url( $leading_photo ) ) { array_unshift( $media, array( 'type' => 'photo', 'url' => $leading_photo ) ); $media = array_slice( $media, 0, self::MAX_REPLY_MEDIA ); }
		if ( ! $media ) { $html ? self::send_direct_long_html( $chat_id, $text, $markup ) : self::send_direct( $chat_id, $text, $markup ); return; }
		$caption_fits = self::text_units( wp_strip_all_tags( $text ) ) <= 1024;
		$single_media_with_markup = 1 === count( $media ) && $caption_fits && $markup;
		$caption = $caption_fits ? $text : '';
		$sent = self::send_media( $chat_id, $media, $caption, $html, $single_media_with_markup ? $markup : array() );
		if ( '' !== trim( $text ) && ( ! $sent || '' === $caption ) ) { $html ? self::send_direct_long_html( $chat_id, $text, $markup ) : self::send_direct( $chat_id, $text, $markup ); }
		elseif ( $sent && $markup && ! $single_media_with_markup ) { self::send_direct( $chat_id, self::copy( 'menu_intro', $language ), $markup ); }
	}

	private static function send_scheduled_output( string $chat_id, string $text, string $icon_url, array $markup = array() ): void {
		if ( '' !== $icon_url && self::valid_calendar_icon_url( $icon_url ) ) { self::send_direct_photo( $chat_id, $icon_url, $text, $markup ); return; }
		self::send_direct_long_html( $chat_id, $text, $markup );
	}

	private static function send_direct_photo( string $chat_id, string $photo, string $text, array $markup = array() ): bool {
		$text = self::telegram_html( $text, true );
		if ( '' === $text || ! self::valid_telegram_photo_url( $photo ) ) { return self::send_direct_long_html( $chat_id, $text, $markup ); }
		list( $caption, $continuation ) = self::photo_caption_parts( $text );
		$body = array( 'chat_id' => $chat_id, 'photo' => $photo, 'caption' => $caption, 'parse_mode' => 'HTML' );
		if ( $markup ) { $body['reply_markup'] = $markup; }
		if ( is_wp_error( self::api( 'sendPhoto', $body ) ) ) { return self::send_direct_long_html( $chat_id, $text, $markup ); }
		return '' === $continuation || self::send_direct_long_html( $chat_id, $continuation );
	}

	private static function photo_caption_parts( string $text ): array {
		$parts = self::split_telegram_text( $text, 1024 );
		$caption = array_shift( $parts ) ?? '';
		return array( $caption, implode( "\n\n", $parts ) );
	}

	/** Send a generated calendar reply in complete Telegram-sized messages. */
	private static function send_direct_long_html( string $chat_id, string $text, array $markup = array() ): bool {
		$text = self::telegram_html( $text, true );
		$parts = self::split_telegram_text( $text, 3900 );
		if ( ! $parts ) { return false; }
		$sent = true;
		foreach ( $parts as $index => $part ) { $sent = self::send_direct( $chat_id, $part, $index ? array() : $markup, true ) && $sent; }
		return $sent;
	}

	/** Split at line and word boundaries while closing and reopening Telegram HTML formatting. */
	private static function split_telegram_text( string $text, int $limit ): array {
		$text = trim( $text );
		if ( '' === $text ) { return array(); }
		$parts = array(); $current = ''; $stack = array();
		$lines = preg_split( '/(?<=\n)/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: array( $text );
		foreach ( $lines as $line ) {
			$line_units = self::text_units( wp_strip_all_tags( $line ) );
			if ( $line_units <= $limit && self::text_units( wp_strip_all_tags( $current . $line ) ) <= $limit ) { $current .= $line; $stack = self::telegram_tag_stack( $line, $stack ); continue; }
			if ( $line_units <= $limit ) {
				if ( '' !== trim( $current ) ) { $parts[] = trim( $current . self::telegram_closing_tags( $stack ) ); }
				$current = self::telegram_opening_tags( $stack ) . $line;
				$stack = self::telegram_tag_stack( $line, $stack );
				continue;
			}
			if ( '' !== trim( $current ) ) { $parts[] = trim( $current . self::telegram_closing_tags( $stack ) ); }
			$current = '';
			$next_stack = self::telegram_tag_stack( $line, $stack );
			foreach ( self::split_plain_telegram_text( wp_strip_all_tags( $line ), $limit ) as $plain_part ) { $parts[] = self::telegram_opening_tags( $stack ) . $plain_part . self::telegram_closing_tags( $stack ); }
			$stack = $next_stack;
		}
		if ( '' !== trim( $current ) ) { $parts[] = trim( $current . self::telegram_closing_tags( $stack ) ); }
		return $parts;
	}

	private static function telegram_tag_stack( string $html, array $stack ): array {
		preg_match_all( '/<\/?([a-z][a-z0-9]*)\b[^>]*>/i', $html, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$name = strtolower( $match[1] );
			if ( str_starts_with( $match[0], '</' ) ) {
				for ( $index = count( $stack ) - 1; $index >= 0; --$index ) {
					if ( preg_match( '/^<' . preg_quote( $name, '/' ) . '\b/i', $stack[$index] ) ) { array_splice( $stack, $index ); break; }
				}
			} elseif ( ! str_ends_with( trim( $match[0] ), '/>' ) ) { $stack[] = $match[0]; }
		}
		return $stack;
	}

	private static function telegram_opening_tags( array $stack ): string { return implode( '', $stack ); }

	private static function telegram_closing_tags( array $stack ): string {
		$closing = '';
		foreach ( array_reverse( $stack ) as $tag ) { if ( preg_match( '/^<([a-z][a-z0-9]*)\b/i', $tag, $match ) ) { $closing .= '</' . strtolower( $match[1] ) . '>'; } }
		return $closing;
	}

	private static function split_plain_telegram_text( string $text, int $limit ): array {
		$parts = array(); $current = '';
		$words = preg_split( '/(\s+)/u', trim( $text ), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: array( trim( $text ) );
		foreach ( $words as $word ) {
			if ( self::text_units( $current . $word ) <= $limit ) { $current .= $word; continue; }
			if ( '' !== trim( $current ) ) { $parts[] = trim( $current ); }
			$current = '';
			$characters = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
			foreach ( $characters as $character ) {
				if ( self::text_units( $current . $character ) > $limit && '' !== $current ) { $parts[] = trim( $current ); $current = ''; }
				$current .= $character;
			}
		}
		if ( '' !== trim( $current ) ) { $parts[] = trim( $current ); }
		return $parts;
	}

	private static function valid_calendar_icon_url( string $url ): bool {
		return (bool) preg_match( '~^https://bible-desktop\.com/storage/calendar-icons/[a-f0-9]{64}\.(?:png|jpe?g)$~D', $url );
	}

	private static function valid_telegram_photo_url( string $url ): bool {
		return self::valid_calendar_icon_url( $url ) || ( 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) && wp_http_validate_url( $url ) );
	}

	private static function configured_media( array $command, string $language ): array {
		$ids = trim( (string) ( $command['media_ids_' . $language] ?? '' ) );
		if ( '' === $ids && $language !== self::default_language_code() ) { $ids = trim( (string) ( $command['media_ids_' . self::default_language_code()] ?? '' ) ); }
		$media = array();
		foreach ( array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', $ids ) ) ) ), 0, self::MAX_REPLY_MEDIA ) as $id ) {
			$type = self::telegram_media_type( $id );
			$url = wp_get_attachment_url( $id );
			if ( ! $type || ! is_string( $url ) || ! wp_http_validate_url( $url ) ) { continue; }
			$media[] = array( 'type' => $type, 'url' => esc_url_raw( $url ) );
		}
		return $media;
	}

	private static function send_media( string $chat_id, array $media, string $caption, bool $html, array $markup = array() ): bool {
		if ( ! $media ) { return false; }
		$caption = trim( $caption );
		$caption_is_html = $html && '' !== $caption;
		if ( 1 === count( $media ) ) {
			$item = $media[0];
			$method = 'photo' === $item['type'] ? 'sendPhoto' : 'sendVideo';
			$body = array( 'chat_id' => $chat_id, $item['type'] => $item['url'] );
			if ( '' !== $caption ) { $body['caption'] = $caption; if ( $caption_is_html ) { $body['parse_mode'] = 'HTML'; } }
			if ( $markup ) { $body['reply_markup'] = $markup; }
			return ! is_wp_error( self::api( $method, $body ) );
		}
		$items = array();
		foreach ( $media as $index => $item ) {
			$entry = array( 'type' => $item['type'], 'media' => $item['url'] );
			if ( 0 === $index && '' !== $caption ) { $entry['caption'] = $caption; if ( $caption_is_html ) { $entry['parse_mode'] = 'HTML'; } }
			$items[] = $entry;
		}
		return ! is_wp_error( self::api( 'sendMediaGroup', array( 'chat_id' => $chat_id, 'media' => $items ) ) );
	}

	private static function text_units( string $text ): int {
		$characters = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$units = 0;
		foreach ( $characters ?: array() as $character ) {
			$encoded = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'UTF-16LE', $character ) : false;
			$units += $encoded ? (int) ( strlen( $encoded ) / 2 ) : 1;
		}
		return $units;
	}

	private static function has_telegram_formatting( string $text ): bool {
		return (bool) preg_match( '/<(?:b|strong|i|em|u|ins|s|strike|del|a|code|pre|blockquote|span)\b/i', $text );
	}

	private static function language_keyboard( string $language ): array {
		$buttons = array();
		foreach ( self::active_languages() as $code => $details ) { $buttons[] = array( 'text' => self::limit_text( $details['name'], 60 ), 'callback_data' => 'lang:' . $code ); }
		return array( 'inline_keyboard' => array_chunk( $buttons ?: array( array( 'text' => $language, 'callback_data' => 'lang:' . $language ) ), 2 ) );
	}

	private static function menu_keyboard( string $language ): array {
		$buttons = array();
		foreach ( self::system_commands() as $key => $command ) {
			if ( ! $command['enabled'] || in_array( $key, array( 'start', 'stop', 'menu' ), true ) ) { continue; }
			$buttons[] = array( 'text' => '/' . $key, 'callback_data' => 'botcmd:' . $key );
		}
		foreach ( self::custom_commands() as $command ) {
			if ( 'command' === $command['type'] ) { $buttons[] = array( 'text' => '/' . $command['command'], 'callback_data' => 'botcmd:' . $command['command'] ); }
			else { $trigger = (string) ( $command['trigger_' . $language] ?? '' ); if ( '' !== $trigger ) { $buttons[] = array( 'text' => self::limit_text( $trigger, 60 ), 'callback_data' => 'reply:' . self::responder_id( $command ) ); } }
		}
		$rows = array();
		foreach ( array_chunk( $buttons, 2 ) as $row ) { $rows[] = $row; }
		if ( ! $rows ) { return self::language_keyboard( $language ); }
		return array( 'inline_keyboard' => $rows );
	}

	/** Add the configured reply buttons after buttons already required by the reply. */
	private static function reply_menu_markup( array $command, string $language, array $markup = array(), bool $private_chat = true ): array {
		$buttons = array();
		$default_language = self::default_language_code();
		$valid_targets = array_fill_keys( array_keys( self::menu_target_options() ), true );
		foreach ( self::sanitize_menu_buttons( $command['menu_buttons'] ?? array() ) as $definition ) {
			if ( 'web_app' === $definition['action'] && ! $private_chat ) { continue; }
			$label = trim( (string) ( $definition['labels'][$language] ?? '' ) );
			if ( '' === $label ) { $label = trim( (string) ( $definition['labels'][$default_language] ?? '' ) ); }
			if ( '' === $label ) { foreach ( $definition['labels'] as $translated_label ) { if ( '' !== trim( (string) $translated_label ) ) { $label = trim( (string) $translated_label ); break; } } }
			if ( '' === $label ) { continue; }
			if ( 'web_app' === $definition['action'] ) {
				$buttons[] = array( 'text' => $label, 'web_app' => array( 'url' => $definition['url'] ) );
			} elseif ( 'link' === $definition['action'] ) {
				$buttons[] = array( 'text' => $label, 'url' => $definition['url'] );
			} elseif ( isset( $valid_targets[$definition['target']] ) ) {
				$buttons[] = array( 'text' => $label, 'callback_data' => $definition['target'] );
			}
		}
		$rows = array_merge( is_array( $markup['inline_keyboard'] ?? null ) ? $markup['inline_keyboard'] : array(), array_chunk( $buttons, 2 ) );
		return $rows ? array_merge( $markup, array( 'inline_keyboard' => $rows ) ) : $markup;
	}

	private static function sanitize_menu_buttons( $raw ): array {
		if ( ! is_array( $raw ) ) { return array(); }
		$buttons = array();
		foreach ( array_slice( $raw, 0, 10 ) as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$action = in_array( $row['action'] ?? 'command', array( 'command', 'link', 'web_app' ), true ) ? (string) ( $row['action'] ?? 'command' ) : 'command';
			$button = array( 'action' => $action, 'labels' => array() );
			foreach ( self::active_languages() as $language => $details ) {
				$label = is_scalar( $row['labels'][$language] ?? null ) ? (string) $row['labels'][$language] : '';
				$button['labels'][$language] = self::limit_text( sanitize_text_field( $label ), 60 );
			}
			if ( ! array_filter( $button['labels'], static fn( $label ) => '' !== trim( (string) $label ) ) ) { continue; }
			if ( in_array( $action, array( 'link', 'web_app' ), true ) ) {
				$allowed_schemes = 'web_app' === $action ? array( 'https' ) : array( 'http', 'https' );
				$url = is_scalar( $row['url'] ?? null ) ? esc_url_raw( trim( (string) $row['url'] ), $allowed_schemes ) : '';
				$parts = $url ? wp_parse_url( $url ) : false;
				$scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';
				/* Links are opened by Telegram, not fetched by this webhook; avoid DNS/network checks here. */
				if ( ! $url || ! in_array( $scheme, $allowed_schemes, true ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { continue; }
				$button['url'] = $url;
			} else {
				$target = is_scalar( $row['target'] ?? null ) ? sanitize_text_field( (string) $row['target'] ) : '';
				if ( ! preg_match( '/^(?:botcmd:[a-z][a-z0-9_]{0,31}|reply:[a-f0-9]{16})$/D', $target ) ) { continue; }
				$button['target'] = $target;
			}
			$buttons[] = $button;
		}
		return $buttons;
	}

	private static function menu_target_options(): array {
		$options = array();
		$language = self::default_language_code();
		foreach ( self::system_commands() as $key => $command ) {
			if ( empty( $command['enabled'] ) || in_array( $key, array( 'start', 'stop' ), true ) ) { continue; }
			$description = trim( (string) ( $command['description_' . $language] ?? '' ) );
			$options['botcmd:' . $key] = '/' . $key . ( '' !== $description ? ' — ' . $description : '' );
		}
		foreach ( self::custom_commands() as $command ) {
			if ( 'command' === $command['type'] ) {
				$description = trim( (string) ( $command['description_' . $language] ?? '' ) );
				$options['botcmd:' . $command['command']] = '/' . $command['command'] . ( '' !== $description ? ' — ' . $description : '' );
			} else {
				$trigger = trim( (string) ( $command['trigger_' . $language] ?? '' ) );
				if ( '' === $trigger ) { $trigger = self::first_localized_trigger( $command ); }
				$options['reply:' . self::responder_id( $command )] = $trigger;
			}
		}
		return $options;
	}

	private static function first_localized_trigger( array $command ): string {
		foreach ( self::active_languages() as $language => $details ) { $trigger = trim( (string) ( $command['trigger_' . $language] ?? '' ) ); if ( '' !== $trigger ) { return $trigger; } }
		return __( 'Text reply', 'georg-kloster-connect-hub' );
	}

	private static function help_message( string $language ): string {
		$lines = array();
		foreach ( self::command_descriptions( $language ) as $command ) { $lines[] = '/' . $command['command'] . ' — ' . $command['description']; }
		foreach ( self::custom_commands() as $responder ) {
			if ( 'text' === $responder['type'] ) {
				$trigger = trim( (string) ( $responder['trigger_' . $language] ?? '' ) );
				$description = trim( (string) ( $responder['description_' . $language] ?? '' ) );
				if ( '' !== $trigger ) { $lines[] = $trigger . ( $description ? ' — ' . $description : '' ); }
			}
		}
		$intro = self::system_response( 'help', $language );
		return $intro . ( $lines ? "\n\n" . implode( "\n", $lines ) : '' );
	}

	private static function run_system_command( string $chat_id, string $language, array $command, int $update_id ): void {
		$action = $command['action'];
		$markup = 'lang' === $action ? self::language_keyboard( $language ) : ( 'menu' === $action ? self::menu_keyboard( $language ) : array() );
		if ( in_array( $action, array( 'today', 'week' ), true ) && 'schedule' === ( $command['reply_type'] ?? 'schedule' ) ) {
			$template = self::configured_response( $command, $language );
			if ( '' === $template ) { $template = '{schedule}'; }
			elseif ( ! str_contains( $template, '{schedule}' ) ) { $template .= "\n\n{schedule}"; }
			self::reply_to_schedule_command( $chat_id, $language, $action, $template );
		} else { self::send_configured_reply( $chat_id, $command, $language, $markup ); }
	}

	public static function command_descriptions( string $language ): array {
		$commands = array();
		foreach ( self::system_commands() as $command ) {
			if ( ! $command['enabled'] ) { continue; }
			$description = trim( (string) ( $command['description_' . $language] ?? '' ) );
			if ( '' === $description ) { $description = trim( (string) ( $command['description_' . self::default_language_code()] ?? '' ) ); }
			if ( '' !== $description ) { $commands[] = array( 'command' => $command['command'], 'description' => $description ); }
		}
		foreach ( self::custom_commands() as $custom ) {
			if ( 'command' !== $custom['type'] ) { continue; }
			$description = trim( (string) ( $custom['description_' . $language] ?? '' ) );
			if ( '' !== $description ) { $commands[] = array( 'command' => $custom['command'], 'description' => $description ); }
		}
		return $commands;
	}

	private static function custom_commands(): array {
		return self::sanitize_custom_commands( get_option( self::COMMANDS_OPTION, array() ) );
	}

	private static function custom_command( string $name ): ?array {
		foreach ( self::custom_commands() as $command ) {
			if ( 'command' === $command['type'] && $name === $command['command'] ) { return $command; }
		}
		return null;
	}

	private static function keyword_responder( string $text ): ?array {
		$normalized = self::normalize_trigger( $text );
		foreach ( self::custom_commands() as $command ) {
			if ( 'text' !== $command['type'] ) { continue; }
			foreach ( self::active_languages() as $language => $details ) {
				$trigger = trim( (string) ( $command['trigger_' . $language] ?? '' ) );
				if ( '' !== $trigger && $normalized === self::normalize_trigger( $trigger ) ) { return $command; }
			}
			// Preserve phrases saved before the language selector became WPML-driven.
			foreach ( array( 'ru', 'de' ) as $legacy_language ) {
				$trigger = trim( (string) ( $command['trigger_' . $legacy_language] ?? '' ) );
				if ( '' !== $trigger && $normalized === self::normalize_trigger( $trigger ) ) { return $command; }
			}
		}
		return null;
	}

	private static function keyword_responder_by_id( string $id ): ?array {
		foreach ( self::custom_commands() as $command ) {
			if ( 'text' === $command['type'] && $id === self::responder_id( $command ) ) { return $command; }
		}
		return null;
	}

	private static function responder_id( array $command ): string {
		if ( preg_match( '/^[a-f0-9]{16}$/D', (string) ( $command['id'] ?? '' ) ) ) { return (string) $command['id']; }
		unset( $command['id'], $command['menu_buttons'] );
		return substr( md5( serialize( $command ) ), 0, 16 );
	}

	private static function normalize_trigger( string $text ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
		if ( function_exists( 'mb_strtolower' ) ) { return mb_strtolower( $text, 'UTF-8' ); }
		$fold = array();
		$decode = static fn( int $codepoint ): string => html_entity_decode( '&#' . $codepoint . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		for ( $codepoint = 0x410; $codepoint <= 0x42f; ++$codepoint ) { $fold[$decode( $codepoint )] = $decode( $codepoint + 32 ); }
		foreach ( array( 0x401 => 0x451, 0xc4 => 0xe4, 0xd6 => 0xf6, 0xdc => 0xfc, 0x1e9e => 0xdf ) as $upper => $lower ) { $fold[$decode( $upper )] = $decode( $lower ); }
		return strtolower( strtr( $text, $fold ) );
	}

	private static function subscribe( string $chat_id, string $language ): void {
		global $wpdb;
		$table = self::tables()['subscribers'];
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i (chat_id,language,status,created_at,updated_at) VALUES (%s,%s,'active',%s,%s) ON DUPLICATE KEY UPDATE language=VALUES(language),status='active',updated_at=VALUES(updated_at)", $table, $chat_id, $language, $now, $now ) );
	}

	private static function unsubscribe( string $chat_id ): void {
		global $wpdb;
		$tables = self::tables();
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE chat_id = %s", $tables['subscribers'], $chat_id ) );
		if ( $id ) { $wpdb->query( $wpdb->prepare( "UPDATE %i SET status='skipped',error='unsubscribed' WHERE subscriber_id=%d AND status='pending'", $tables['deliveries'], $id ) ); }
		$wpdb->delete( $tables['subscribers'], array( 'chat_id' => $chat_id ), array( '%s' ) );
	}

	private static function is_active( string $chat_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE chat_id=%s AND status='active'", self::tables()['subscribers'], $chat_id ) );
	}

	private static function subscriber_language( string $chat_id, string $fallback ): string {
		global $wpdb;
		$lang = $wpdb->get_var( $wpdb->prepare( "SELECT language FROM %i WHERE chat_id=%s AND status='active'", self::tables()['subscribers'], $chat_id ) );
		return is_string( $lang ) && self::valid_language_code( $lang ) ? $lang : $fallback;
	}

	private static function update_language( string $chat_id, string $language ): void {
		global $wpdb;
		$wpdb->update( self::tables()['subscribers'], array( 'language' => $language, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'chat_id' => $chat_id ), array( '%s', '%s' ), array( '%s' ) );
	}

	private static function send_direct( string $chat_id, string $text, array $markup = array(), bool $html = false ): bool {
		if ( '' === trim( $text ) ) { return false; }
		$text = $html ? self::telegram_html( $text ) : wp_strip_all_tags( $text );
		$body = array( 'chat_id' => $chat_id, 'text' => self::limit_text( $text ), 'link_preview_options' => array( 'is_disabled' => true ) );
		if ( $html ) { $body['parse_mode'] = 'HTML'; }
		if ( $markup ) { $body['reply_markup'] = $markup; }
		$result = self::api( 'sendMessage', $body );
		return ! is_wp_error( $result );
	}

	private static function telegram_media_type( int $attachment_id ): string {
		$mime = get_post_mime_type( $attachment_id );
		if ( in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) { return 'photo'; }
		return 'video/mp4' === $mime ? 'video' : '';
	}

	private static function sanitize_media_ids( string $value ): string {
		$ids = array();
		foreach ( preg_split( '/[\s,]+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $raw_id ) {
			$id = absint( $raw_id );
			if ( $id && self::telegram_media_type( $id ) && ! in_array( $id, $ids, true ) ) { $ids[] = $id; }
			if ( count( $ids ) >= self::MAX_REPLY_MEDIA ) { break; }
		}
		return implode( ',', $ids );
	}

	private static function sanitize_reply( string $text, bool $allow_long = false ): string {
		$text = preg_replace( '/<\/?p\b[^>]*>/i', "\n", $text ) ?? $text;
		$text = preg_replace( '/<br\s*\/?\s*>/i', "\n", $text ) ?? $text;
		$text = preg_replace( '/<li\b[^>]*>/i', '• ', $text ) ?? $text;
		$text = preg_replace( '/<\/(?:ul|ol|li)>/i', "\n", $text ) ?? $text;
		$allowed = array(
			'b' => array(), 'strong' => array(), 'i' => array(), 'em' => array(), 'u' => array(), 'ins' => array(),
			's' => array(), 'strike' => array(), 'del' => array(), 'code' => array(), 'pre' => array(),
			'a' => array( 'href' => true ), 'blockquote' => array( 'expandable' => true ), 'span' => array( 'class' => true ),
		);
		$text = preg_replace_callback( '/<span\b([^>]*)>(.*?)<\/span>/is', static function ( array $match ): string {
			return preg_match( '/\bclass\s*=\s*(["\'])tg-spoiler\1/i', $match[1] ) ? '<span class="tg-spoiler">' . $match[2] . '</span>' : $match[2];
		}, $text ) ?? $text;
		$text = wp_kses( $text, $allowed );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? $text;
		return $allow_long ? $text : self::limit_text( $text );
	}

	private static function telegram_html( string $text, bool $allow_long = false ): string {
		$text = self::sanitize_reply( $text, $allow_long );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? $text;
		return trim( $text );
	}

	private static function api( string $method, array $body ) {
		$connector = Connector::instance( 'telegram' );
		$settings = $connector ? $connector->settings() : array();
		if ( ! $connector || empty( $settings['enabled'] ) || ! preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', (string) ( $settings['token'] ?? '' ) ) || ! in_array( $method, array( 'sendMessage', 'sendPhoto', 'sendVideo', 'sendMediaGroup', 'answerCallbackQuery' ), true ) ) { return new \WP_Error( 'telegram_unavailable', 'Telegram is not configured.' ); }
		$response = wp_remote_post( 'https://api.telegram.org/bot' . $settings['token'] . '/' . $method, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'timeout' => 10, 'redirection' => 0 ) );
		$data = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || empty( $data['ok'] ) ) { return new \WP_Error( 'telegram_api', 'Telegram did not accept the request.' ); }
		return $data['result'] ?? true;
	}

	private static function limit_text( string $text, int $limit = 3900 ): string {
		$characters = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $characters ) { return ''; }
		$result = ''; $units = 0;
		foreach ( $characters as $character ) {
			$encoded = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'UTF-16LE', $character ) : false;
			$cost = $encoded ? (int) ( strlen( $encoded ) / 2 ) : 1;
			if ( $units + $cost > $limit ) { break; }
			$result .= $character; $units += $cost;
		}
		return $result;
	}

	public static function queue( string $kind, int $source_id, array $context = array(), string $text = '', string $url = '' ): string {
		global $wpdb;
		if ( ! self::broadcasting_enabled() || ! in_array( $kind, array( 'post', 'automation', 'schedule', 'digest' ), true ) ) { return ''; }
		if ( in_array( $kind, array( 'post', 'automation' ), true ) && ! Publication_Rules::allows( array( 'kind' => $kind, 'source' => $source_id, 'reply_context' => $context ) ) ) { return ''; }
		if ( 'post' === $kind && 'publish' !== get_post_status( $source_id ) ) { return ''; }
		if ( 'schedule' === $kind && 'publish' !== get_post_status( $source_id ) ) { return ''; }
		$messages = self::campaign_messages( $kind, $source_id, $context, $text, $url );
		if ( ! $messages ) { return ''; }
		$translations = is_array( $messages['translations'] ?? null ) ? $messages['translations'] : array();
		$context['translations'] = $translations;
		if ( 'post' === $kind ) {
			$context['links'] = is_array( $messages['links'] ?? null ) ? $messages['links'] : array();
			$context['images'] = is_array( $messages['images'] ?? null ) ? $messages['images'] : array();
		}
		if ( self::valid_calendar_icon_url( (string) ( $messages['icon_url'] ?? '' ) ) ) { $context['icon_url'] = $messages['icon_url']; }
		return self::create_campaign( $kind, $source_id, $context, $messages['title'], (string) ( $translations['ru'] ?? $messages['ru'] ?? '' ), (string) ( $translations['de'] ?? $messages['de'] ?? '' ), $messages['url'], (string) ( $context['configuration'] ?? '' ) );
	}

	private static function campaign_messages( string $kind, int $source_id, array $context, string $text, string $url ): array {
		if ( 'digest' === $kind ) {
			$date = new \DateTimeImmutable( 'today', wp_timezone() );
			$translations = array();
			$icon_url = '';
			foreach ( self::campaign_languages() as $language ) {
				$content = self::day_content( $date, $language, true );
				$translations[$language] = $content['text'];
				if ( '' === $icon_url && self::valid_calendar_icon_url( (string) $content['icon_url'] ) ) { $icon_url = $content['icon_url']; }
			}
			$default = self::default_language_code();
			return array( 'title' => self::copy( 'digest', $default ), 'translations' => $translations, 'url' => '', 'icon_url' => $icon_url );
		}
		if ( 'post' === $kind ) { return self::post_messages( $source_id ); }
		if ( 'automation' === $kind ) {
			$rule = Publication_Rules::get( (int) ( $context['rule_id'] ?? 0 ) );
			if ( ! $rule ) { return array(); }
			$translations = is_array( $rule['translations'] ?? null ) ? $rule['translations'] : array();
			foreach ( array( 'ru', 'de' ) as $legacy_language ) { if ( '' === trim( (string) ( $translations[$legacy_language] ?? '' ) ) && '' !== trim( (string) ( $rule['text_' . $legacy_language] ?? '' ) ) ) { $translations[$legacy_language] = $rule['text_' . $legacy_language]; } }
			if ( ! array_filter( $translations ) ) { $translations[self::default_language_code()] = trim( (string) ( $rule['text'] ?? $text ) ); }
			return array( 'title' => $rule['name'], 'translations' => $translations, 'url' => $rule['url'] ?: $url );
		}
		$default = self::default_language_code();
		$title = 'schedule' === $kind ? self::copy( 'changed', $default ) . ': ' . get_the_title( $source_id ) : $text;
		if ( 'schedule' === $kind ) {
			$translations = array();
			foreach ( self::campaign_languages() as $language ) { $translations[$language] = self::schedule_change_text( $source_id, $language ); }
			return array( 'title' => $title, 'translations' => $translations, 'url' => '' );
		}
		return array( 'title' => $title, 'translations' => array_fill_keys( self::campaign_languages(), $text ), 'url' => $url );
	}

	private static function post_messages( int $source_id ): array {
		$source = get_post( $source_id );
		if ( ! $source || 'publish' !== $source->post_status ) { return array(); }
		$translations = array(); $links = array(); $images = array();
		$source_language = self::post_language( $source_id );
		foreach ( self::campaign_languages() as $language ) {
			$id = self::translated_post_id( $source_id, $language );
			if ( ! $id && $language === $source_language ) { $id = $source_id; }
			$post = $id ? get_post( $id ) : null;
			if ( ! $post || 'publish' !== $post->post_status ) { $translations[$language] = ''; continue; }
			$translations[$language] = self::post_teaser( $post );
			$links[$language] = esc_url_raw( get_permalink( $id ) );
			$images[$language] = self::post_featured_image_url( $id ) ?: self::post_featured_image_url( $source_id );
		}
		return array( 'title' => $source->post_title, 'translations' => $translations, 'links' => $links, 'images' => $images, 'ru' => $translations['ru'] ?? '', 'de' => $translations['de'] ?? '', 'url' => '' );
	}

	private static function post_teaser( \WP_Post $post ): string {
		$excerpt = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$excerpt = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $excerpt ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$excerpt = wp_html_excerpt( $excerpt, 360, '…' );
		$title = html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = wp_html_excerpt( $title, 160, '…' );
		return '<b>' . esc_html( $title ) . '</b>' . ( '' !== trim( $excerpt ) ? "\n\n" . esc_html( $excerpt ) : '' );
	}

	private static function post_featured_image_url( int $post_id ): string {
		$attachment_id = get_post_thumbnail_id( $post_id );
		if ( ! $attachment_id || ! in_array( get_post_mime_type( $attachment_id ), array( 'image/jpeg', 'image/png' ), true ) ) { return ''; }
		$url = wp_get_attachment_image_url( $attachment_id, 'large' ) ?: wp_get_attachment_url( $attachment_id );
		if ( ! is_string( $url ) || 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) { return ''; }
		return esc_url_raw( $url );
	}

	private static function article_button_markup( string $url, string $language, array $markup = array() ): array {
		$url = esc_url_raw( $url );
		if ( '' === $url || ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) || '' === (string) wp_parse_url( $url, PHP_URL_HOST ) ) { return $markup; }
		$rows = is_array( $markup['inline_keyboard'] ?? null ) ? $markup['inline_keyboard'] : array();
		$rows[] = array( array( 'text' => self::copy( 'open_article', $language ), 'url' => $url ) );
		$markup['inline_keyboard'] = $rows;
		return $markup;
	}

	private static function post_language( int $post_id ): string {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the exact public WPML filter name.
		$language = has_filter( 'wpml_element_language_code' ) ? apply_filters( 'wpml_element_language_code', null, $post_id, 'post_post' ) : null;
		if ( ! is_string( $language ) && function_exists( 'pll_get_post_language' ) ) { $language = pll_get_post_language( $post_id, 'slug' ); }
		if ( is_string( $language ) && self::valid_language_code( strtolower( $language ) ) ) { return strtolower( $language ); }
		return self::default_language_code();
	}

	private static function campaign_languages(): array {
		$languages = array_keys( self::active_languages() );
		global $wpdb;
		$stored = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT language FROM %i WHERE status='active'", self::tables()['subscribers'] ) );
		foreach ( (array) $stored as $language ) { if ( is_string( $language ) && self::valid_language_code( $language ) ) { $languages[] = $language; } }
		return array_values( array_unique( $languages ) );
	}

	private static function translated_post_id( int $post_id, string $language ): int {
		if ( has_filter( 'wpml_object_id' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the exact public WPML filter name.
			$id = apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), false, $language );
			if ( $id ) { return absint( $id ); }
		}
		if ( function_exists( 'pll_get_post' ) ) { return absint( pll_get_post( $post_id, $language ) ); }
		return 0;
	}

	private static function translated_title( int $post_id, string $language ): string {
		$id = self::translated_post_id( $post_id, $language );
		return $id ? (string) get_the_title( $id ) : '';
	}

	private static function schedule_change_text( int $service_id, string $language ): string {
		$old = self::switch_language( $language );
		try {
			$title = self::translated_title( $service_id, $language ) ?: (string) get_the_title( $service_id );
			$from = new \DateTimeImmutable( 'today', wp_timezone() );
			$events = array_filter( self::schedule_events( $from, $from->modify( '+366 days' ), $language ), static fn( $event ) => (int) ( $event['service_id'] ?? 0 ) === $service_id );
			$lines = array( '<b>' . esc_html( self::copy( 'changed', $language ) ) . '</b>', '<b>' . esc_html( $title ) . '</b>' );
			$upcoming = array_slice( array_values( $events ), 0, 3 );
			foreach ( $upcoming as $event ) { $lines[] = self::event_line( $event, $language, true ); }
			$places = self::weekly_places( $upcoming, $language );
			if ( $places ) { $lines[] = '━━━━━━━━━━━━━━━━━━'; foreach ( $places as $place ) { $lines[] = self::service_place_line( $place ); } }
			$lines[] = '<a href="' . esc_url( self::schedule_url( $language ) ) . '">' . esc_html( self::copy( 'schedule_link', $language ) ) . '</a>';
			return implode( "\n", $lines );
		} finally { self::restore_language( $old ); }
	}

	private static function create_campaign( string $kind, int $source_id, array $context, string $title, string $ru, string $de, string $url, string $dedupe = '' ): string {
		global $wpdb;
		$tables = self::tables();
		$translation_hash = hash( 'sha256', is_array( $context['translations'] ?? null ) ? wp_json_encode( $context['translations'] ) : '' );
		if ( '' === $dedupe ) { $dedupe = hash( 'sha256', $kind . '|' . $source_id . '|' . $title . '|' . $ru . '|' . $de . '|' . $url . '|' . $translation_hash ); }
		$key = hash( 'sha256', $kind . '|' . $source_id . '|' . $dedupe . '|' . $ru . '|' . $de . '|' . $translation_hash );
		$now = gmdate( 'Y-m-d H:i:s' );
		$inserted = $wpdb->insert( $tables['campaigns'], array( 'dedupe_key' => $key, 'source_type' => $kind, 'source_id' => $source_id, 'context' => wp_json_encode( $context ), 'title' => sanitize_text_field( $title ), 'payload_ru' => sanitize_textarea_field( $ru ), 'payload_de' => sanitize_textarea_field( $de ), 'url' => esc_url_raw( $url ), 'status' => 'queued', 'created_at' => $now, 'updated_at' => $now ), array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		if ( false === $inserted ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE dedupe_key=%s", $tables['campaigns'], $key ) );
			return $existing ? 'tg-broadcast-' . $existing : '';
		}
		$id = (int) $wpdb->insert_id;
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (campaign_id,subscriber_id,language,status,attempts,next_attempt) SELECT %d,id,language,'pending',0,%s FROM %i WHERE status='active'", $tables['deliveries'], $id, $now, $tables['subscribers'] ) );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_id=%d", $tables['deliveries'], $id ) );
		$wpdb->update( $tables['campaigns'], array( 'total' => $total, 'status' => $total ? 'queued' : 'no_subscribers', 'updated_at' => $now ), array( 'id' => $id ), array( '%d', '%s', '%s' ), array( '%d' ) );
		if ( $total ) { self::worker( $id ); }
		return 'tg-broadcast-' . $id;
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'gkch_telegram_broadcast_worker' );
		wp_clear_scheduled_hook( self::DIGEST );
	}

	public static function worker( int $campaign_id ): void {
		global $wpdb;
		$tables = self::tables();
		$lock = Lock::acquire( 'gkch_telegram_campaign_' . $campaign_id, 120 );
		if ( ! $lock ) { return; }
		try {
			$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id=%d", $tables['campaigns'], $campaign_id ), ARRAY_A );
			if ( ! is_array( $campaign ) || ! in_array( $campaign['status'], array( 'queued', 'processing', 'partial' ), true ) ) { return; }
			if ( ! self::broadcasting_enabled() ) {
				$wpdb->update( $tables['deliveries'], array( 'status' => 'failed', 'error' => 'telegram_disabled' ), array( 'campaign_id' => $campaign_id, 'status' => 'pending' ), array( '%s', '%s' ), array( '%d', '%s' ) );
				self::finish_campaign( $campaign_id );
				return;
			}
			$context = json_decode( (string) $campaign['context'], true );
			$context = is_array( $context ) ? $context : array();
			$kind = $campaign['source_type'];
			if ( 'automation' === $kind && ! Publication_Rules::allows( array( 'kind' => 'automation', 'source' => (int) $campaign['source_id'], 'reply_context' => $context ) ) ) { self::set_campaign_status( $campaign_id, 'cancelled', 'The publication rule is no longer active.' ); self::skip_pending( $campaign_id, 'rule_inactive' ); return; }
			if ( 'post' === $kind && ( 'publish' !== get_post_status( (int) $campaign['source_id'] ) || ! Publication_Rules::allows( array( 'kind' => 'post', 'source' => (int) $campaign['source_id'], 'reply_context' => $context ) ) ) ) { self::set_campaign_status( $campaign_id, 'cancelled', 'The post is no longer available for publication.' ); self::skip_pending( $campaign_id, 'post_unavailable' ); return; }
			$batch = $wpdb->get_results( $wpdb->prepare( "SELECT d.*,s.chat_id,s.status AS subscriber_status FROM %i d LEFT JOIN %i s ON s.id=d.subscriber_id WHERE d.campaign_id=%d AND d.status='pending' ORDER BY d.id ASC", $tables['deliveries'], $tables['subscribers'], $campaign_id ), ARRAY_A );
			if ( ! $batch ) { self::finish_campaign( $campaign_id ); return; }
			self::set_campaign_status( $campaign_id, 'processing', '' );
			foreach ( $batch as $index => $delivery ) {
				if ( 'active' !== ( $delivery['subscriber_status'] ?? '' ) || empty( $delivery['chat_id'] ) ) { self::delivery_status( $delivery, 'skipped', 'unsubscribed' ); continue; }
				$language = (string) $delivery['language'];
				$text = is_string( $context['translations'][$language] ?? null ) ? $context['translations'][$language] : ( 'de' === $language ? $campaign['payload_de'] : ( 'ru' === $language ? $campaign['payload_ru'] : '' ) );
				if ( '' === trim( (string) $text ) ) { self::delivery_status( $delivery, 'skipped', 'missing_translation' ); continue; }
				$is_post = 'post' === $kind;
				$article_url = is_string( $context['links'][$language] ?? null ) ? $context['links'][$language] : '';
				$photo = $is_post && is_string( $context['images'][$language] ?? null ) ? $context['images'][$language] : (string) ( $context['icon_url'] ?? '' );
				$markup = $is_post ? self::article_button_markup( $article_url, $language ) : array();
				$result = self::send_campaign_message( (string) $delivery['chat_id'], (string) $text, (string) $campaign['url'], $photo, $is_post || in_array( $kind, array( 'digest', 'schedule' ), true ), $markup );
				$attempt = (int) $delivery['attempts'] + 1;
				if ( ! empty( $result['accepted'] ) ) {
					self::delivery_status( $delivery, 'sent', '', $attempt, (string) ( $result['message_id'] ?? '' ) );
				} else {
					self::delivery_status( $delivery, 'failed', sanitize_key( $result['error'] ?? 'telegram_rejected' ), $attempt );
					if ( 403 === (int) ( $result['error_code'] ?? 0 ) ) { self::block_subscriber( (int) $delivery['subscriber_id'] ); }
					if ( ! empty( $result['rate_limited'] ) ) {
						foreach ( array_slice( $batch, $index + 1 ) as $remaining ) { self::delivery_status( $remaining, 'failed', 'not_sent_rate_limited', (int) $remaining['attempts'] ); }
						break;
					}
				}
			}
			self::finish_campaign( $campaign_id );
		} finally { Lock::release( 'gkch_telegram_campaign_' . $campaign_id, $lock ); }
	}

	private static function send_campaign_message( string $chat_id, string $text, string $url, string $photo = '', bool $html = false, array $reply_markup = array() ): array {
		$connector = Connector::instance( 'telegram' );
		$settings = $connector ? $connector->settings() : array();
		if ( empty( $settings['token'] ) ) { return array( 'accepted' => false, 'retry' => false, 'error' => 'missing_token' ); }
		if ( '' !== $url && ! str_contains( $text, $url ) ) { $text .= $html ? "\n\n<a href=\"" . esc_url( $url ) . "\">" . esc_html( self::copy( 'schedule_link', self::subscriber_language( $chat_id, self::default_language_code() ) ) ) . '</a>' : "\n" . $url; }
		$text = $html ? self::telegram_html( $text, true ) : trim( wp_strip_all_tags( $text ) );
		$use_photo = '' !== $photo && self::valid_telegram_photo_url( $photo ) && $html;
		if ( '' === $text ) { return array( 'accepted' => false, 'retry' => false, 'error' => 'empty_message' ); }
		$parts = self::split_telegram_text( $text, $use_photo ? 1024 : 3900 );
		$first = array_shift( $parts ) ?? '';
		$payload = $use_photo
			? array( 'chat_id' => $chat_id, 'photo' => $photo, 'caption' => $first, 'parse_mode' => 'HTML' )
			: array( 'chat_id' => $chat_id, 'text' => $first, 'link_preview_options' => array( 'is_disabled' => true ) );
		if ( $html ) { $payload['parse_mode'] = 'HTML'; }
		if ( $reply_markup ) { $payload['reply_markup'] = $reply_markup; }
		$send = static function ( string $method, array $body ) use ( $settings ): array {
			$response = wp_remote_post( 'https://api.telegram.org/bot' . $settings['token'] . '/' . $method, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'timeout' => 10, 'redirection' => 0 ) );
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$result = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 === $code && is_array( $result ) && ! empty( $result['ok'] ) ) { return array( 'accepted' => true, 'message_id' => $result['result']['message_id'] ?? '' ); }
			$api_code = is_array( $result ) && is_numeric( $result['error_code'] ?? null ) ? (int) $result['error_code'] : $code;
			return array( 'accepted' => false, 'retry' => is_wp_error( $response ) || 429 === $api_code || $api_code >= 500, 'rate_limited' => 429 === $api_code, 'retry_after' => (int) ( $result['parameters']['retry_after'] ?? 15 ), 'error_code' => $api_code, 'error' => 'api_' . $api_code );
		};
		$accepted = $send( $use_photo ? 'sendPhoto' : 'sendMessage', $payload );
		if ( empty( $accepted['accepted'] ) && $use_photo && 400 === (int) ( $accepted['error_code'] ?? 0 ) ) {
			$parts = self::split_telegram_text( $text, 3900 );
			$first = array_shift( $parts ) ?? '';
			$fallback = array( 'chat_id' => $chat_id, 'text' => $first, 'parse_mode' => 'HTML', 'link_preview_options' => array( 'is_disabled' => true ) );
			if ( $reply_markup ) { $fallback['reply_markup'] = $reply_markup; }
			$accepted = $send( 'sendMessage', $fallback );
		}
		if ( empty( $accepted['accepted'] ) ) { return $accepted; }
		$message_id = $accepted['message_id'] ?? '';
		foreach ( $parts as $part ) {
			$followup = array( 'chat_id' => $chat_id, 'text' => $part, 'link_preview_options' => array( 'is_disabled' => true ) );
			if ( $html ) { $followup['parse_mode'] = 'HTML'; }
			$next = $send( 'sendMessage', $followup );
			if ( empty( $next['accepted'] ) ) { return $next; }
		}
		return array( 'accepted' => true, 'message_id' => $message_id );
	}

	private static function delivery_status( array $delivery, string $status, string $error, int $attempts = 0, string $remote_id = '', int $next = 0 ): void {
		global $wpdb;
		$wpdb->update( self::tables()['deliveries'], array( 'status' => $status, 'error' => $error, 'attempts' => $attempts, 'remote_id' => $remote_id, 'next_attempt' => gmdate( 'Y-m-d H:i:s', $next ?: time() ) ), array( 'id' => (int) $delivery['id'] ), array( '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
	}

	private static function block_subscriber( int $subscriber_id ): void {
		global $wpdb;
		$wpdb->update( self::tables()['subscribers'], array( 'status' => 'blocked', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $subscriber_id ), array( '%s', '%s' ), array( '%d' ) );
	}

	private static function skip_pending( int $campaign_id, string $reason ): void {
		global $wpdb;
		$wpdb->update( self::tables()['deliveries'], array( 'status' => 'skipped', 'error' => $reason ), array( 'campaign_id' => $campaign_id, 'status' => 'pending' ), array( '%s', '%s' ), array( '%d', '%s' ) );
	}

	private static function set_campaign_status( int $id, string $status, string $error ): void {
		global $wpdb;
		$wpdb->update( self::tables()['campaigns'], array( 'status' => $status, 'error' => $error, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
	}

	private static function finish_campaign( int $campaign_id ): void {
		global $wpdb;
		$tables = self::tables();
		$counts = $wpdb->get_results( $wpdb->prepare( "SELECT status,COUNT(*) AS amount,MIN(next_attempt) AS next_at FROM %i WHERE campaign_id=%d GROUP BY status", $tables['deliveries'], $campaign_id ), ARRAY_A );
		$pending = 0; $sent = 0; $failed = 0; $skipped = 0;
		foreach ( (array) $counts as $row ) {
			if ( 'pending' === $row['status'] ) { $pending = (int) $row['amount']; }
			if ( 'sent' === $row['status'] ) { $sent = (int) $row['amount']; }
			if ( 'failed' === $row['status'] ) { $failed = (int) $row['amount']; }
			if ( 'skipped' === $row['status'] ) { $skipped = (int) $row['amount']; }
		}
		$status = $pending || $failed || $skipped ? 'partial' : 'sent';
		$error = $pending ? 'manual_retry_required' : '';
		$wpdb->update( $tables['campaigns'], array( 'status' => $status, 'error' => $error, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $campaign_id ), array( '%s', '%s', '%d', '%d', '%d', '%s' ), array( '%d' ) );
	}

	public static function daily_digest(): void {
		if ( self::broadcasting_enabled() ) { self::queue( 'digest', 0 ); }
		self::sync_digest( self::connector_settings() );
	}

	private static function day_content( \DateTimeImmutable $date, string $language, bool $digest = false ): array {
		$day_end = $date->modify( '+1 day' )->setTime( 0, 0 );
		$today_events = self::schedule_events( $date->setTime( 0, 0 ), $day_end, $language );
		$week_start = $date->modify( 'monday this week' )->setTime( 0, 0 );
		$week_end = $week_start->modify( '+7 days' );
		$week_events = array();
		if ( $day_end < $week_end ) {
			$week_events = self::schedule_events( $day_end, $week_end, $language );
		}
		$calendar = self::calendar_day_data( $date->format( 'Y-m-d' ), $language );
		if ( is_array( $calendar ) && '' !== ( $calendar['color'] ?? '' ) ) {
			foreach ( $today_events as &$event ) { if ( in_array( (string) ( $event['vestment_color'] ?? '' ), array( '', 'auto' ), true ) ) { $event['calendar_color'] = $calendar['color']; } }
			unset( $event );
		}
		$sections = array(
			'<b>' . esc_html( self::copy( $digest ? 'digest' : 'today', $language ) ) . '</b>' . "\n" . '<code>' . esc_html( $date->format( 'd.m.Y' ) ) . '</code>',
		);
		if ( is_array( $calendar ) && ! empty( $calendar['icon_title'] ) ) {
			$sections[] = '<b>' . esc_html( self::copy( 'icon_day', $language ) ) . '</b>' . "\n" . esc_html( $calendar['icon_title'] );
		}
		$today_lines = array( '<b>' . esc_html( self::copy( 'services', $language ) ) . '</b>' );
		if ( $today_events ) {
			foreach ( $today_events as $event ) { $today_lines[] = self::event_line( $event, $language, false ); }
		} else {
			$today_lines[] = esc_html( self::copy( 'no_service_today', $language ) );
		}
		$sections[] = implode( "\n", $today_lines );
		if ( $week_events ) {
			$week_lines = array( '<b>' . esc_html( self::copy( 'services_week', $language ) ) . '</b>' );
			foreach ( $week_events as $event ) { $week_lines[] = self::event_line( $event, $language, true ); }
			$sections[] = implode( "\n", $week_lines );
		}
		$places = self::weekly_places( array_merge( $today_events, $week_events ), $language );
		if ( $places ) {
			$place_lines = array( '━━━━━━━━━━━━━━━━━━' );
			foreach ( $places as $place ) { $place_lines[] = self::service_place_line( $place ); }
			$sections[] = implode( "\n", $place_lines );
		}
		$memory_lines = array( '━━━━━━━━━━━━━━━━━━', '<b>' . esc_html( self::copy( 'feast', $language ) ) . '</b>' );
		if ( is_array( $calendar ) && $calendar['events'] ) {
			foreach ( $calendar['events'] as $event ) { $memory_lines[] = '• ' . esc_html( $event ); }
		} elseif ( null === $calendar ) {
			$memory_lines[] = esc_html( self::copy( 'calendar_unavailable', $language ) );
		}
		$sections[] = implode( "\n", $memory_lines );
		$sections[] = '<a href="' . esc_url( self::schedule_url( $language ) ) . '">' . esc_html( self::copy( 'schedule_link', $language ) ) . '</a>';
		return array( 'text' => implode( "\n\n", $sections ), 'icon_url' => is_array( $calendar ) ? (string) $calendar['icon_url'] : '' );
	}

	private static function week_message( \DateTimeImmutable $today, string $language ): string {
		$from = $today->modify( 'monday this week' )->setTime( 0, 0 );
		$to = $from->modify( '+7 days' );
		$events = self::schedule_events( $from, $to, $language );
		$sections = array( '<b>' . esc_html( self::copy( 'week', $language ) ) . '</b>' . "\n" . '<code>' . esc_html( $from->format( 'd.m.Y' ) . ' — ' . $to->modify( '-1 day' )->format( 'd.m.Y' ) ) . '</code>' );
		if ( $events ) {
			$grouped = array();
			foreach ( $events as $event ) {
				$start = $event['start'] ?? null;
				$key = $start instanceof \DateTimeInterface ? $start->format( 'Y-m-d' ) : '';
				$grouped[$key][] = $event;
			}
			foreach ( $grouped as $day => $day_events ) {
				$day_date = $day ? new \DateTimeImmutable( $day, wp_timezone() ) : null;
				$label = $day_date ? self::localized_weekday( $day_date, $language ) : self::copy( 'services', $language );
				$day_lines = array( '<b>' . esc_html( $label ) . '</b>' );
				foreach ( $day_events as $event ) { $day_lines[] = self::event_line( $event, $language, false ); }
				$sections[] = implode( "\n", $day_lines );
			}
		} else {
			$sections[] = esc_html( self::copy( 'no_services', $language ) );
		}
		$places = self::weekly_places( $events, $language );
		$sections[] = '━━━━━━━━━━━━━━━━━━';
		foreach ( $places as $place ) {
			$sections[] = self::service_place_line( $place );
		}
		$sections[] = '<a href="' . esc_url( self::schedule_url( $language ) ) . '">' . esc_html( self::copy( 'schedule_link', $language ) ) . '</a>';
		return implode( "\n\n", $sections );
	}

	private static function localized_weekday( \DateTimeImmutable $date, string $language ): string {
		$switched = switch_to_locale( self::language_locale( $language ) );
		try { return wp_date( 'l, d.m', $date->getTimestamp(), wp_timezone() ); }
		finally { if ( $switched ) { restore_previous_locale(); } }
	}

	private static function schedule_events( \DateTimeImmutable $from, \DateTimeImmutable $to, string $language ): array {
		$class = '\\GeorgKlosterPrihodSchedule\\Schedule';
		if ( ! class_exists( $class ) ) { return array(); }
		$old = self::switch_language( $language );
		try { return $class::query( $from, $to, 0, false ); }
		catch ( \Throwable $error ) { return array(); }
		finally { self::restore_language( $old ); }
	}

	private static function calendar_day_data( string $date, string $language ): ?array {
		$class = '\\GeorgKlosterPrihodSchedule\\Calendar_Provider';
		if ( ! class_exists( $class ) ) { return null; }
		try { $data = $class::request( 'day', array( 'date' => $date, 'lang' => $language ) ); }
		catch ( \Throwable $error ) { return null; }
		if ( is_wp_error( $data ) || ! is_array( $data['day']['events'] ?? null ) ) { return null; }
		$result = array(); $icon_url = ''; $icon_title = '';
		foreach ( $data['day']['events'] as $event ) {
			if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! is_numeric( $event['typeCode'] ?? null ) || (int) $event['typeCode'] < 0 || ! is_string( $event['title'] ?? null ) ) { continue; }
			$title = sanitize_text_field( $event['title'] );
			if ( $title && ! in_array( $title, $result, true ) ) { $result[] = $title; }
		}
		foreach ( $data['day']['icons'] ?? array() as $icon ) {
			if ( ! is_array( $icon ) ) { continue; }
			$sources = array();
			foreach ( $icon['images'] ?? array() as $image ) { if ( is_array( $image ) && is_string( $image['url'] ?? null ) ) { $sources[] = $image['url']; } }
			if ( is_string( $icon['imageUrl'] ?? null ) ) { $sources[] = $icon['imageUrl']; }
			foreach ( $sources as $source ) {
				if ( self::valid_calendar_icon_url( $source ) ) { $icon_url = $source; $icon_title = sanitize_text_field( (string) ( $icon['title'] ?? '' ) ); break 2; }
			}
		}
		return array( 'events' => $result, 'icon_url' => $icon_url, 'icon_title' => $icon_title, 'color' => self::mapped_calendar_color( $data['day'] ) );
	}

	private static function event_line( array $event, string $language, bool $with_date ): string {
		$start = $event['start'] ?? null;
		$when = $start instanceof \DateTimeInterface ? ( $with_date ? $start->format( 'd.m' ) . ' ' : '' ) . $start->format( 'H:i' ) : '';
		$title = sanitize_text_field( (string) ( $event['title'] ?? '' ) );
		$line = self::vestment_marker( $event, $language ) . ' ┃ ' . ( $when ? '<b>' . esc_html( $when ) . '</b> — ' : '' ) . esc_html( $title );
		return $line;
	}

	private static function service_place_line( array $place ): string {
		$line = '📍 <b>' . esc_html( (string) ( $place['title'] ?? '' ) ) . '</b>';
		$address = trim( (string) ( $place['address'] ?? '' ) );
		if ( '' !== $address ) {
			$maps = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $address );
			$line .= "\n<a href=\"" . esc_url( $maps ) . '">' . esc_html( $address ) . '</a>';
		}
		return $line;
	}

	private static function vestment_marker( array $event, string $language ): string {
		$color = sanitize_key( (string) ( $event['vestment_color'] ?? '' ) );
		if ( 'violet' === $color ) { $color = 'purple'; }
		if ( ! in_array( $color, array( 'white', 'red', 'gold', 'green', 'blue', 'purple', 'black' ), true ) ) { $color = sanitize_key( (string) ( $event['calendar_color'] ?? '' ) ); }
		if ( 'violet' === $color ) { $color = 'purple'; }
		$start = $event['start'] ?? null;
		if ( ! in_array( $color, array( 'white', 'red', 'gold', 'green', 'blue', 'purple', 'black' ), true ) && $start instanceof \DateTimeInterface ) { $color = self::calendar_recommended_color( $start->format( 'Y-m-d' ), $language ); }
		$markers = array( 'white' => '⬜', 'red' => '🟥', 'gold' => '🟨', 'green' => '🟩', 'blue' => '🟦', 'purple' => '🟪', 'black' => '⬛' );
		return $markers[$color] ?? '▫️';
	}

	private static function mapped_calendar_color( array $day ): string {
		$class = '\\GeorgKlosterPrihodSchedule\\Calendar_Colors';
		if ( class_exists( $class ) && method_exists( $class, 'day' ) ) {
			try { $result = $class::day( $day ); if ( is_array( $result ) && in_array( $result['color'] ?? '', array( 'white', 'red', 'gold', 'green', 'blue', 'purple', 'black' ), true ) ) { return (string) $result['color']; } }
			catch ( \Throwable $error ) {}
		}
		$raw = is_array( $day['vestment'] ?? null ) ? sanitize_key( (string) ( $day['vestment']['color'] ?? '' ) ) : '';
		$map = array( 'gold' => 'gold', 'white' => 'white', 'blue' => 'blue', 'red' => 'red', 'green' => 'green', 'violet' => 'purple', 'purple' => 'purple', 'black' => 'black' );
		return $map[$raw] ?? '';
	}

	private static function calendar_recommended_color( string $date, string $language ): string {
		static $cache = array();
		if ( ! preg_match( '/^(\d{4})-(\d{2})-\d{2}$/D', $date, $match ) ) { return ''; }
		$key = $language . ':' . $match[1] . '-' . $match[2];
		if ( ! array_key_exists( $key, $cache ) ) {
			$cache[$key] = array();
			$class = '\\GeorgKlosterPrihodSchedule\\Calendar_Provider';
			if ( class_exists( $class ) ) {
				try {
					$data = $class::request( 'month', array( 'year' => (int) $match[1], 'month' => (int) $match[2], 'lang' => $language ) );
					if ( ! is_wp_error( $data ) && is_array( $data['days'] ?? null ) ) {
						foreach ( $data['days'] as $day ) { if ( is_array( $day ) && is_string( $day['date'] ?? null ) ) { $color = self::mapped_calendar_color( $day ); if ( '' !== $color ) { $cache[$key][$day['date']] = $color; } } }
					}
				} catch ( \Throwable $error ) {}
			}
		}
		return (string) ( $cache[$key][$date] ?? '' );
	}

	private static function service_place_title( int $place_id, string $language ): string {
		$class = '\\GeorgKlosterPrihodSchedule\\Directory';
		$old = self::switch_language( $language );
		try { $title = class_exists( $class ) && method_exists( $class, 'title' ) ? $class::title( $place_id ) : get_the_title( $place_id ); return sanitize_text_field( (string) $title ); }
		finally { self::restore_language( $old ); }
	}

	private static function weekly_places( array $events, string $language ): array {
		$old = self::switch_language( $language );
		try {
			$profile = array();
			$class = '\\GeorgKlosterPrihodCore\\Profile';
			if ( class_exists( $class ) && method_exists( $class, 'get' ) ) { $profile = $class::get(); }
			else { $profile = get_option( 'gkpc_profile', array() ); $profile = is_array( $profile ) ? $profile : array(); }
			$profile_name = sanitize_text_field( (string) ( $profile['name'] ?? '' ) );
			if ( '' === $profile_name ) { $profile_name = sanitize_text_field( (string) ( $profile['short_name'] ?? '' ) ); }
			$profile_address = sanitize_textarea_field( (string) ( $profile['address'] ?? '' ) );
			$places = array();
			foreach ( $events as $event ) {
				$id = absint( $event['location_id'] ?? 0 );
				if ( ! $id || isset( $places[$id] ) ) { continue; }
				$title = self::service_place_title( $id, $language );
				$details_class = '\\GeorgKlosterPrihodCore\\Directory';
				$details = class_exists( $details_class ) && method_exists( $details_class, 'details' ) ? $details_class::details( $id ) : get_post_meta( $id, '_gkpc_details', true );
				if ( ! is_array( $details ) ) { $details = get_post_meta( $id, '_gkpsch_details', true ); }
				$details = is_array( $details ) ? $details : array();
				$address = sanitize_textarea_field( (string) ( $details['address'] ?? '' ) );
				$places[$id] = array( 'title' => $title ?: $profile_name, 'address' => $address ?: $profile_address );
			}
			if ( ! $places && ( $profile_name || $profile_address ) ) { $places[] = array( 'title' => $profile_name, 'address' => $profile_address ); }
			return array_values( array_filter( $places, static fn( $place ) => '' !== $place['title'] || '' !== $place['address'] ) );
		} finally { self::restore_language( $old ); }
	}

	private static function switch_language( string $language ) {
		$old = null;
		if ( has_filter( 'wpml_current_language' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- These are the exact public WPML hook names.
			$old = apply_filters( 'wpml_current_language', null ); do_action( 'wpml_switch_language', $language );
		}
		elseif ( function_exists( 'pll_current_language' ) && function_exists( 'pll_switch_language' ) ) { $old = pll_current_language( 'slug' ); pll_switch_language( $language ); }
		return $old;
	}

	private static function restore_language( $old ): void {
		if ( is_string( $old ) && has_filter( 'wpml_current_language' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the exact public WPML action name.
			do_action( 'wpml_switch_language', $old );
		}
		elseif ( is_string( $old ) && function_exists( 'pll_switch_language' ) ) { pll_switch_language( $old ); }
	}

	private static function schedule_url( string $language = '' ): string {
		$page = get_page_by_path( 'raspisanie-bogosluzheniy' );
		if ( $page && $language && ( $translated = self::translated_post_id( (int) $page->ID, $language ) ) ) { $page = get_post( $translated ); }
		return $page ? get_permalink( $page ) : home_url( '/raspisanie-bogosluzheniy/' );
	}

	public static function menu(): void {
		add_submenu_page( 'gkps', __( 'Telegram bot subscribers', 'georg-kloster-connect-hub' ), __( 'Telegram bot', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkch-telegram', array( self::class, 'render' ) );
		add_submenu_page( 'gkps', __( 'Bot commands and replies', 'georg-kloster-connect-hub' ), __( 'Bot commands', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkch-telegram-commands', array( self::class, 'render_commands' ) );
		add_submenu_page( null, __( 'Edit bot command', 'georg-kloster-connect-hub' ), __( 'Edit bot command', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkch-telegram-command-edit', array( self::class, 'render_command_editor' ) );
	}

	public static function admin_assets( string $hook ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The page query only decides whether to enqueue editor assets.
		$page = is_string( $_GET['page'] ?? null ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'gkch-telegram-command-edit' !== $page ) { return; }
		wp_enqueue_media();
		$script_path = plugin_dir_path( GKPS_FILE ) . 'assets/telegram-bot-admin.js';
		$script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : GKPS_VERSION;
		wp_enqueue_script( 'gkch-telegram-bot-admin', plugins_url( 'assets/telegram-bot-admin.js', GKPS_FILE ), array( 'jquery', 'media-editor' ), $script_version, true );
		wp_localize_script( 'gkch-telegram-bot-admin', 'gkchTelegramMedia', array(
			'title' => __( 'Select images and videos', 'georg-kloster-connect-hub' ),
			'button' => __( 'Use selected media', 'georg-kloster-connect-hub' ),
			'invalid' => __( 'Only JPEG or PNG images and MP4 videos can be sent.', 'georg-kloster-connect-hub' ),
			'limit' => __( 'You can select up to 10 media files.', 'georg-kloster-connect-hub' ),
			'remove' => __( 'Remove', 'georg-kloster-connect-hub' ),
			'photo' => __( 'Photo', 'georg-kloster-connect-hub' ),
			'video' => __( 'Video', 'georg-kloster-connect-hub' ),
			'menuLimit' => __( 'A reply can have up to 10 buttons.', 'georg-kloster-connect-hub' ),
		) );
	}

	private static function sanitize_custom_commands( $rows, &$invalid = false ): array {
		if ( ! is_array( $rows ) ) { return array(); }
		$reserved = array( 'start', 'stop', 'today', 'week', 'lang', 'language', 'help', 'menu' );
		$commands = array();
		$seen = array();
		$seen_triggers = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { $invalid = true; continue; }
			$type = in_array( $row['type'] ?? 'command', array( 'command', 'text' ), true ) ? (string) ( $row['type'] ?? 'command' ) : 'command';
			$raw_command = is_scalar( $row['command'] ?? null ) ? strtolower( trim( (string) $row['command'] ) ) : '';
			$command = 0 === strpos( $raw_command, '/' ) ? substr( $raw_command, 1 ) : $raw_command;
			$reply_type = is_string( $row['reply_type'] ?? null ) && in_array( $row['reply_type'], array( 'text', 'html', 'schedule', 'page', 'news' ), true ) ? $row['reply_type'] : 'text';
			$schedule_period = 'week' === ( $row['schedule_period'] ?? '' ) ? 'week' : 'today';
			$reply_post_id = absint( $row['reply_post_id'] ?? 0 );
			$locales = array_keys( self::active_languages() );
			foreach ( array_keys( $row ) as $field ) { if ( preg_match( '/^(?:trigger|description|response|media_ids)_([a-z][a-z0-9_-]{1,30})$/D', (string) $field, $match ) ) { $locales[] = $match[1]; } }
			$locales = array_values( array_unique( $locales ) );
			$localized = array(); $has_trigger = false; $has_response = false; $has_description = false; $has_media = false;
			foreach ( $locales as $language ) {
				foreach ( array( 'trigger', 'description', 'response', 'media_ids' ) as $field ) {
					$key = $field . '_' . $language;
					$value = is_scalar( $row[$key] ?? null ) ? (string) $row[$key] : '';
					if ( 'trigger' === $field ) { $value = self::limit_text( sanitize_text_field( $value ), 128 ); $has_trigger = $has_trigger || '' !== $value; }
					elseif ( 'description' === $field ) { $value = self::limit_text( sanitize_text_field( $value ), 256 ); $has_description = $has_description || '' !== $value; }
					elseif ( 'response' === $field ) { $value = self::sanitize_reply( $value ); $has_response = $has_response || '' !== trim( wp_strip_all_tags( $value ) ); }
					else { $value = self::sanitize_media_ids( $value ); $has_media = $has_media || '' !== $value; }
					$localized[$key] = $value;
				}
			}
			$menu_buttons = self::sanitize_menu_buttons( $row['menu_buttons'] ?? array() );
			$empty = '' === $command && ! $has_trigger && ! $has_description && ! $has_response && ! $has_media && ! $reply_post_id && ! $menu_buttons;
			if ( $empty ) { continue; }
			$invalid_trigger = 'command' === $type
				? ( ! preg_match( '/^[a-z][a-z0-9_]{0,31}$/D', $command ) || in_array( $command, $reserved, true ) || isset( $seen[$command] ) || ! $has_description )
				: ! $has_trigger;
			foreach ( $locales as $language ) {
				$trigger = $localized['trigger_' . $language] ?? '';
				if ( '' !== $trigger && isset( $seen_triggers[self::normalize_trigger( $trigger )] ) ) { $invalid_trigger = true; }
			}
			if ( $invalid_trigger || ( in_array( $reply_type, array( 'page', 'news' ), true ) ? ! self::valid_reply_post( $reply_post_id, $reply_type ) : ( ! $has_response && ! $has_media ) ) ) {
				$invalid = true;
				continue;
			}
			if ( 'command' === $type ) { $seen[$command] = true; }
			foreach ( $locales as $language ) { $trigger = $localized['trigger_' . $language] ?? ''; if ( '' !== $trigger ) { $seen_triggers[self::normalize_trigger( $trigger )] = true; } }
			$normalized = array_merge( array(
				'type' => $type,
				'command' => $command,
				'reply_type' => $reply_type,
				'schedule_period' => $schedule_period,
				'reply_post_id' => $reply_post_id,
			), $localized );
			$id = preg_match( '/^[a-f0-9]{16}$/D', (string) ( $row['id'] ?? '' ) ) ? (string) $row['id'] : substr( md5( serialize( $normalized ) ), 0, 16 );
			$commands[] = array_merge( $normalized, array( 'id' => $id, 'menu_buttons' => $menu_buttons ) );
		}
		return $commands;
	}

	private static function sync_menu_commands(): ?bool {
		$connector = Connector::instance( 'telegram' );
		if ( ! $connector ) { return null; }
		$settings = $connector->settings();
		if ( ! $connector->valid( $settings ) ) { return null; }
		foreach ( self::active_languages() as $language => $details ) {
			$result = Telegram_Request_Settings::api( 'setMyCommands', array( 'commands' => self::command_descriptions( $language ), 'language_code' => substr( $language, 0, 2 ) ), $settings );
			if ( is_wp_error( $result ) ) { return false; }
		}
		return true;
	}

	public static function save_bot_commands(): void {
		if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'gkch_telegram_bot_command_save' );
		$kind = is_string( $_POST['kind'] ?? null ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$key = is_string( $_POST['command_key'] ?? null ) ? sanitize_key( wp_unslash( $_POST['command_key'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The index is unslashed, checked as numeric and cast to an integer before indexing the command list.
		$raw_index = isset( $_POST['command_index'] ) && is_scalar( $_POST['command_index'] ) ? wp_unslash( $_POST['command_index'] ) : null;
		$index = is_numeric( $raw_index ) ? (int) $raw_index : -1;
		$is_new_command = 'custom' === $kind && $index < 0;
		if ( 'custom' === $kind && ! empty( $_POST['delete_command'] ) ) {
			$commands = self::custom_commands();
			if ( isset( $commands[$index] ) ) { array_splice( $commands, $index, 1 ); update_option( self::COMMANDS_OPTION, $commands, false ); }
			set_transient( 'gkch_telegram_command_notice_' . get_current_user_id(), array( 'status' => 'success', 'message' => __( 'Bot command or responder deleted.', 'georg-kloster-connect-hub' ) ), 120 );
			self::sync_menu_commands();
			wp_safe_redirect( admin_url( 'admin.php?page=gkch-telegram-commands' ) ); exit;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Locale fields are validated individually by sanitize_text_field(), sanitize_reply(), and sanitize_media_ids() before saving.
		$input = isset( $_POST['localized'] ) && is_array( $_POST['localized'] ) ? wp_unslash( $_POST['localized'] ) : array();
		$base = array( 'reply_type' => 'text', 'schedule_period' => 'today', 'reply_post_id' => 0 );
		if ( 'system' === $kind ) {
			$commands = self::system_commands();
			if ( ! isset( $commands[$key] ) ) { wp_die( esc_html__( 'Bot command not found.', 'georg-kloster-connect-hub' ) ); }
			$base = $commands[$key];
			$base['enabled'] = ! empty( $_POST['enabled'] );
		} elseif ( 'custom' === $kind ) {
			$commands = self::custom_commands();
			$base = $index >= 0 && isset( $commands[$index] ) ? $commands[$index] : $base;
			$type = is_string( $_POST['type'] ?? null ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'command';
			$base['type'] = in_array( $type, array( 'command', 'text' ), true ) ? $type : 'command';
			$base['command'] = is_string( $_POST['command'] ?? null ) ? sanitize_key( wp_unslash( $_POST['command'] ) ) : '';
		} else { wp_die( esc_html__( 'Bot command type is invalid.', 'georg-kloster-connect-hub' ) ); }
		$reply_type = is_string( $_POST['reply_type'] ?? null ) ? sanitize_key( wp_unslash( $_POST['reply_type'] ) ) : '';
		$base['reply_type'] = in_array( $reply_type, array( 'text', 'html', 'schedule', 'page', 'news' ), true ) ? $reply_type : 'text';
		$schedule_period = is_string( $_POST['schedule_period'] ?? null ) ? sanitize_key( wp_unslash( $_POST['schedule_period'] ) ) : '';
		$base['schedule_period'] = 'week' === $schedule_period ? 'week' : 'today';
		$base['reply_post_id'] = isset( $_POST['reply_post_id'] ) ? absint( wp_unslash( $_POST['reply_post_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each menu-button field and URL is validated by sanitize_menu_buttons() before persistence.
		$menu_input = isset( $_POST['menu_buttons'] ) && is_array( $_POST['menu_buttons'] ) ? wp_unslash( $_POST['menu_buttons'] ) : array();
		$base['menu_buttons'] = self::sanitize_menu_buttons( $menu_input );
		$valid_targets = array_keys( self::menu_target_options() );
		$base['menu_buttons'] = array_values( array_filter( $base['menu_buttons'], static function ( $button ) use ( $valid_targets ) { return in_array( $button['action'], array( 'link', 'web_app' ), true ) || in_array( $button['target'] ?? '', $valid_targets, true ); } ) );
		if ( in_array( $base['reply_type'], array( 'page', 'news' ), true ) && ! self::valid_reply_post( $base['reply_post_id'], $base['reply_type'] ) ) {
			set_transient( 'gkch_telegram_command_error_' . get_current_user_id(), __( 'Select a published item that matches the selected reply type.', 'georg-kloster-connect-hub' ), 120 );
			wp_safe_redirect( self::command_edit_url( $kind, 'system' === $kind ? $key : ( $is_new_command ? 'new' : (string) $index ) ) ); exit;
		}
		foreach ( $input as $language => $fields ) {
			$language = strtolower( sanitize_key( (string) $language ) );
			if ( ! self::valid_language_code( $language ) || ! is_array( $fields ) ) { continue; }
			foreach ( array( 'trigger', 'description', 'response', 'media_ids' ) as $field ) {
				$value = is_scalar( $fields[$field] ?? null ) ? (string) $fields[$field] : '';
				$base[$field . '_' . $language] = 'trigger' === $field ? self::limit_text( sanitize_text_field( $value ), 128 ) : ( 'description' === $field ? self::limit_text( sanitize_text_field( $value ), 256 ) : ( 'response' === $field ? self::sanitize_reply( $value ) : self::sanitize_media_ids( $value ) ) );
			}
		}
		if ( 'system' === $kind ) {
			$commands[$key] = $base;
			update_option( self::SYSTEM_COMMANDS_OPTION, $commands, false );
		} else {
			if ( 'command' === $base['type'] && ! preg_match( '/^[a-z][a-z0-9_]{0,31}$/D', $base['command'] ) ) { $base['command'] = ''; }
			if ( $index < 0 ) { $commands[] = $base; $index = count( $commands ) - 1; } else { $commands[$index] = $base; }
			$invalid = false;
			$clean = self::sanitize_custom_commands( $commands, $invalid );
			if ( $invalid || ! isset( $clean[$index] ) ) {
				set_transient( 'gkch_telegram_command_error_' . get_current_user_id(), __( 'Check the command name or phrase, its translation, and the reply type before saving.', 'georg-kloster-connect-hub' ), 120 );
				wp_safe_redirect( self::command_edit_url( 'custom', $is_new_command ? 'new' : (string) $index ) ); exit;
			}
			update_option( self::COMMANDS_OPTION, $clean, false );
		}
		$synced = self::sync_menu_commands();
		$message = true === $synced ? __( 'Command saved and the Telegram command menu was updated.', 'georg-kloster-connect-hub' ) : ( false === $synced ? __( 'Command saved, but Telegram could not update its command menu. Check the connection and try again.', 'georg-kloster-connect-hub' ) : __( 'Command saved. Connect Telegram to publish its command menu.', 'georg-kloster-connect-hub' ) );
		set_transient( 'gkch_telegram_command_notice_' . get_current_user_id(), array( 'status' => true === $synced ? 'success' : ( false === $synced ? 'warning' : 'info' ), 'message' => $message ), 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=gkch-telegram-commands' ) );
		exit;
	}

	private static function valid_reply_post( int $post_id, string $reply_type ): bool {
		$post = $post_id ? get_post( $post_id ) : null;
		$expected_type = 'page' === $reply_type ? 'page' : ( 'news' === $reply_type ? 'post' : '' );
		return $post && 'publish' === $post->post_status && $expected_type === $post->post_type;
	}

	private static function command_edit_url( string $kind, string $id ): string { return add_query_arg( array( 'page' => 'gkch-telegram-command-edit', 'kind' => $kind, 'id' => $id ), admin_url( 'admin.php' ) ); }

	public static function render_commands(): void {
		if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
		$notice_key = 'gkch_telegram_command_notice_' . get_current_user_id();
		$notice = get_transient( $notice_key );
		if ( is_array( $notice ) ) { delete_transient( $notice_key ); }
		$error_key = 'gkch_telegram_command_error_' . get_current_user_id();
		$error = get_transient( $error_key );
		if ( $error ) { delete_transient( $error_key ); }
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Bot commands and replies', 'georg-kloster-connect-hub' ) . '</h1> <a class="page-title-action" href="' . esc_url( self::command_edit_url( 'custom', 'new' ) ) . '">' . esc_html__( 'Add command or responder', 'georg-kloster-connect-hub' ) . '</a><hr class="wp-header-end">';
		if ( is_array( $notice ) ) { echo '<div class="notice notice-' . esc_attr( $notice['status'] ?? 'info' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ?? '' ) . '</p></div>'; }
		if ( $error ) { echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
		echo '<p>' . esc_html__( 'Edit each command on its own WordPress screen. Available languages come from WPML; translations are entered separately for each active site language.', 'georg-kloster-connect-hub' ) . '</p>';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Command or phrase', 'georg-kloster-connect-hub' ) . '</th><th scope="col">' . esc_html__( 'Kind', 'georg-kloster-connect-hub' ) . '</th><th scope="col">' . esc_html__( 'Reply type', 'georg-kloster-connect-hub' ) . '</th><th scope="col">' . esc_html__( 'Status', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
		foreach ( self::system_commands() as $key => $command ) {
			$url = self::command_edit_url( 'system', $key );
			$title = '<code>/' . esc_html( $key ) . '</code>';
			self::render_command_list_row( $url, $title, __( 'Built-in command', 'georg-kloster-connect-hub' ), $command['reply_type'] ?? 'text', ! empty( $command['enabled'] ) );
		}
		foreach ( self::custom_commands() as $index => $command ) {
			$label = 'command' === $command['type'] ? '<code>/' . esc_html( $command['command'] ) . '</code>' : esc_html( self::first_trigger( $command ) );
			self::render_command_list_row( self::command_edit_url( 'custom', (string) $index ), $label, 'command' === $command['type'] ? __( 'Slash command', 'georg-kloster-connect-hub' ) : __( 'Text responder', 'georg-kloster-connect-hub' ), $command['reply_type'] ?? 'text', true );
		}
		if ( ! self::custom_commands() ) { echo '<tr class="no-items"><td colspan="4">' . esc_html__( 'No additional commands or responders yet.', 'georg-kloster-connect-hub' ) . '</td></tr>'; }
		echo '</tbody></table></div>';
	}

	private static function render_command_list_row( string $url, string $label, string $kind, string $reply_type, bool $enabled ): void {
		$types = array( 'text' => __( 'Text', 'georg-kloster-connect-hub' ), 'html' => __( 'Telegram HTML', 'georg-kloster-connect-hub' ), 'schedule' => __( 'Parish schedule', 'georg-kloster-connect-hub' ), 'page' => __( 'WordPress page', 'georg-kloster-connect-hub' ), 'news' => __( 'WordPress news post', 'georg-kloster-connect-hub' ) );
		echo '<tr><td><strong><a href="' . esc_url( $url ) . '">' . wp_kses( $label, array( 'code' => array() ) ) . '</a></strong><div class="row-actions"><span class="edit"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit', 'georg-kloster-connect-hub' ) . '</a></span></div></td><td>' . esc_html( $kind ) . '</td><td>' . esc_html( $types[$reply_type] ?? $types['text'] ) . '</td><td>' . esc_html( $enabled ? __( 'Enabled', 'georg-kloster-connect-hub' ) : __( 'Disabled', 'georg-kloster-connect-hub' ) ) . '</td></tr>';
	}

	private static function first_trigger( array $command ): string {
		foreach ( self::active_languages() as $language => $details ) { $trigger = trim( (string) ( $command['trigger_' . $language] ?? '' ) ); if ( '' !== $trigger ) { return $trigger; } }
		return __( 'Untranslated phrase', 'georg-kloster-connect-hub' );
	}

	private static function render_menu_button_editor_row( array $button, string $index, array $languages, array $targets ): void {
		$action = in_array( $button['action'] ?? 'command', array( 'command', 'link', 'web_app' ), true ) ? (string) $button['action'] : 'command';
		echo '<fieldset class="gkch-menu-button" data-menu-button><legend><strong>' . esc_html__( 'Menu button', 'georg-kloster-connect-hub' ) . '</strong></legend><div class="gkch-menu-button-labels">';
		foreach ( $languages as $language => $details ) {
			$value = (string) ( $button['labels'][$language] ?? '' );
			echo '<p><label><strong>' . esc_html( $details['name'] ) . '</strong><br><input class="regular-text" maxlength="60" name="menu_buttons[' . esc_attr( $index ) . '][labels][' . esc_attr( $language ) . ']" value="' . esc_attr( $value ) . '"></label></p>';
		}
		echo '</div><p><label>' . esc_html__( 'When pressed', 'georg-kloster-connect-hub' ) . ' <select class="gkch-menu-action" name="menu_buttons[' . esc_attr( $index ) . '][action]">';
		echo '<option value="command"' . selected( $action, 'command', false ) . '>' . esc_html__( 'Open a bot command or reply', 'georg-kloster-connect-hub' ) . '</option><option value="link"' . selected( $action, 'link', false ) . '>' . esc_html__( 'Open a website link', 'georg-kloster-connect-hub' ) . '</option><option value="web_app"' . selected( $action, 'web_app', false ) . '>' . esc_html__( 'Open a Mini App inside Telegram', 'georg-kloster-connect-hub' ) . '</option></select></label> ';
		echo '<label class="gkch-menu-target-field">' . esc_html__( 'Command or reply', 'georg-kloster-connect-hub' ) . ' <select class="gkch-menu-target" name="menu_buttons[' . esc_attr( $index ) . '][target]" required><option value=""' . selected( empty( $button['target'] ), true, false ) . '>— ' . esc_html__( 'Select a command or reply', 'georg-kloster-connect-hub' ) . ' —</option>';
		foreach ( $targets as $target => $label ) { echo '<option value="' . esc_attr( $target ) . '"' . selected( $button['target'] ?? '', $target, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select></label> <label class="gkch-menu-url-field">' . esc_html__( 'Website URL or Mini App HTTPS URL', 'georg-kloster-connect-hub' ) . ' <input class="large-text gkch-menu-url" type="url" name="menu_buttons[' . esc_attr( $index ) . '][url]" value="' . esc_attr( $button['url'] ?? '' ) . '" placeholder="https://example.org/" required /></label> ';
		echo '<button type="button" class="button-link-delete gkch-menu-button-remove">' . esc_html__( 'Remove button', 'georg-kloster-connect-hub' ) . '</button></p></fieldset>';
	}

	public static function render_command_editor(): void {
		if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET values only choose which editor item to display; saving and deletion use nonce-verified POST forms.
		$kind = is_string( $_GET['kind'] ?? null ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$id = is_string( $_GET['id'] ?? null ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$system = 'system' === $kind;
		$systems = self::system_commands();
		$custom = self::custom_commands();
		$index = 'new' === $id ? -1 : ( ctype_digit( $id ) ? absint( $id ) : -1 );
		if ( $system && isset( $systems[$id] ) ) { $command = $systems[$id]; $command['type'] = 'system'; $command['command'] = $id; }
		elseif ( ! $system && ( 'new' === $id || isset( $custom[$index] ) ) ) { $command = 'new' === $id ? array( 'type' => 'command', 'command' => '', 'reply_type' => 'text', 'schedule_period' => 'today' ) : $custom[$index]; }
		else { wp_die( esc_html__( 'Bot command not found.', 'georg-kloster-connect-hub' ), '', array( 'response' => 404 ) ); }
		$title = $system ? '/' . $id : ( 'new' === $id ? __( 'Add command or responder', 'georg-kloster-connect-hub' ) : ( 'command' === $command['type'] ? '/' . $command['command'] : self::first_trigger( $command ) ) );
		echo '<div class="wrap"><h1>' . esc_html( $system || 'new' !== $id ? __( 'Edit bot command', 'georg-kloster-connect-hub' ) : __( 'Add command or responder', 'georg-kloster-connect-hub' ) ) . ': ' . esc_html( $title ) . '</h1><hr class="wp-header-end"><p><a href="' . esc_url( admin_url( 'admin.php?page=gkch-telegram-commands' ) ) . '">← ' . esc_html__( 'Back to commands', 'georg-kloster-connect-hub' ) . '</a></p>';
		$error_key = 'gkch_telegram_command_error_' . get_current_user_id(); $error = get_transient( $error_key );
		if ( $error ) { delete_transient( $error_key ); echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_telegram_bot_commands_save"><input type="hidden" name="kind" value="' . esc_attr( $system ? 'system' : 'custom' ) . '"><input type="hidden" name="command_key" value="' . esc_attr( $system ? $id : '' ) . '"><input type="hidden" name="command_index" value="' . esc_attr( (string) $index ) . '">';
		wp_nonce_field( 'gkch_telegram_bot_command_save' );
		echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content"><div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Command settings', 'georg-kloster-connect-hub' ) . '</h2></div><div class="inside">';
		if ( ! $system ) {
			echo '<p><label for="gkch-command-type"><strong>' . esc_html__( 'Command type', 'georg-kloster-connect-hub' ) . '</strong></label><br><select id="gkch-command-type" name="type"><option value="command"' . selected( $command['type'], 'command', false ) . '>' . esc_html__( 'Slash command', 'georg-kloster-connect-hub' ) . '</option><option value="text"' . selected( $command['type'], 'text', false ) . '>' . esc_html__( 'Exact text phrase', 'georg-kloster-connect-hub' ) . '</option></select></p>';
			echo '<p><label for="gkch-command-word"><strong>' . esc_html__( 'Command word without the slash', 'georg-kloster-connect-hub' ) . '</strong></label><br><input id="gkch-command-word" class="regular-text" name="command" maxlength="32" pattern="[A-Za-z][A-Za-z0-9_]{0,31}" value="' . esc_attr( $command['command'] ?? '' ) . '"><br><span class="description">' . esc_html__( 'Use a short English command word, for example prayer. Telegram displays it as /prayer.', 'georg-kloster-connect-hub' ) . '</span></p>';
		} else {
			echo '<p><strong>' . esc_html__( 'Telegram command', 'georg-kloster-connect-hub' ) . ':</strong> <code>/' . esc_html( $id ) . '</code></p>';
		}
		$subscription_command = $system && in_array( $id, array( 'start', 'stop' ), true );
		if ( $system ) { echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $command['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable this command', 'georg-kloster-connect-hub' ) . '</label>' . ( $subscription_command ? ' <span class="description">' . esc_html__( 'When hidden from Telegram command lists, /start and /stop still work when entered directly so subscribers can manage their subscription.', 'georg-kloster-connect-hub' ) . '</span>' : '' ) . '</p>'; }
		echo '<p><label for="gkch-reply-type"><strong>' . esc_html__( 'What should the bot send?', 'georg-kloster-connect-hub' ) . '</strong></label><br><select id="gkch-reply-type" name="reply_type">';
		$reply_types = array( 'text' => __( 'Plain text', 'georg-kloster-connect-hub' ), 'html' => __( 'Formatted text (Telegram HTML)', 'georg-kloster-connect-hub' ), 'schedule' => __( 'Parish schedule', 'georg-kloster-connect-hub' ), 'page' => __( 'WordPress page', 'georg-kloster-connect-hub' ), 'news' => __( 'News post', 'georg-kloster-connect-hub' ) );
		foreach ( $reply_types as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $command['reply_type'] ?? 'text', $value, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select></p><p class="gkch-schedule-option"><label for="gkch-schedule-period">' . esc_html__( 'Schedule period', 'georg-kloster-connect-hub' ) . ' <select id="gkch-schedule-period" name="schedule_period"><option value="today"' . selected( $command['schedule_period'] ?? 'today', 'today', false ) . '>' . esc_html__( 'Today', 'georg-kloster-connect-hub' ) . '</option><option value="week"' . selected( $command['schedule_period'] ?? 'today', 'week', false ) . '>' . esc_html__( 'This week', 'georg-kloster-connect-hub' ) . '</option></select></label></p>';
		echo '<p class="gkch-post-option"><label for="gkch-reply-post"><strong>' . esc_html__( 'Page or news post to show', 'georg-kloster-connect-hub' ) . '</strong></label><br><select id="gkch-reply-post" name="reply_post_id"><option value="0">— ' . esc_html__( 'Select a published item', 'georg-kloster-connect-hub' ) . ' —</option>';
		foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ) ) as $post ) { echo '<option data-post-type="' . esc_attr( $post->post_type ) . '" value="' . esc_attr( $post->ID ) . '"' . selected( absint( $command['reply_post_id'] ?? 0 ), $post->ID, false ) . '>' . esc_html( ( 'page' === $post->post_type ? __( 'Page', 'georg-kloster-connect-hub' ) : __( 'Post', 'georg-kloster-connect-hub' ) ) . ': ' . $post->post_title ) . '</option>'; }
		echo '</select><br><span class="description">' . esc_html__( 'The bot sends the translated title, a short excerpt and a link to the selected item.', 'georg-kloster-connect-hub' ) . '</span></p></div></div>';
		echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Reply text and translations', 'georg-kloster-connect-hub' ) . '</h2></div><div class="inside"><p>' . esc_html__( 'These language options follow the active languages configured in WPML. Use the WordPress visual editor for Telegram formatting; unsupported HTML is removed, and lists are converted to bullet lines. The {schedule} code inserts the generated schedule into a reply.', 'georg-kloster-connect-hub' ) . '</p>';
		foreach ( self::active_languages() as $language => $details ) {
			$trigger = (string) ( $command['trigger_' . $language] ?? '' );
			$description = (string) ( $command['description_' . $language] ?? '' );
			$response = (string) ( $command['response_' . $language] ?? '' );
			$media_ids = self::sanitize_media_ids( (string) ( $command['media_ids_' . $language] ?? '' ) );
			echo '<fieldset class="gkch-command-language"><legend><strong>' . esc_html( $details['name'] ) . ' <code>' . esc_html( $language ) . '</code></strong></legend>';
			if ( ! $system && 'text' === ( $command['type'] ?? '' ) ) { echo '<p><label>' . esc_html__( 'Exact text phrase that triggers this reply', 'georg-kloster-connect-hub' ) . '<br><input class="large-text" maxlength="128" name="localized[' . esc_attr( $language ) . '][trigger]" value="' . esc_attr( $trigger ) . '"></label></p>'; }
			if ( ! $system && 'command' === ( $command['type'] ?? '' ) || $system ) { echo '<p><label>' . esc_html__( 'Description in the Telegram command menu', 'georg-kloster-connect-hub' ) . '<br><input class="large-text" maxlength="256" name="localized[' . esc_attr( $language ) . '][description]" value="' . esc_attr( $description ) . '"></label></p>'; }
			echo '<div class="gkch-response-option"><p><strong>' . esc_html__( 'Reply text', 'georg-kloster-connect-hub' ) . '</strong></p>';
			wp_editor( $response, 'gkch-telegram-reply-' . sanitize_key( $language ), array(
				'textarea_name' => 'localized[' . $language . '][response]',
				'textarea_rows' => 8,
				'media_buttons' => false,
				'tinymce' => array(
					'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,link,unlink,undo,redo,removeformat',
					'toolbar2' => '',
					'menubar' => false,
					'valid_elements' => 'p,br,b,strong,i,em,u,ins,s,strike,del,blockquote[expandable],a[href],code,pre,ul,ol,li,span[class]',
				),
				'quicktags' => array( 'buttons' => 'strong,em,link,ul,ol,li,code,close' ),
			) );
			echo '</div><div class="gkch-media-option"><p><strong>' . esc_html__( 'Images and videos', 'georg-kloster-connect-hub' ) . '</strong></p><input class="gkch-telegram-media-ids" type="hidden" name="localized[' . esc_attr( $language ) . '][media_ids]" value="' . esc_attr( $media_ids ) . '"><button type="button" class="button gkch-telegram-media-select">' . esc_html__( 'Choose images or videos', 'georg-kloster-connect-hub' ) . '</button><ul class="gkch-telegram-media-preview">';
			foreach ( array_filter( array_map( 'absint', explode( ',', $media_ids ) ) ) as $media_id ) {
				$attachment = get_post( $media_id );
				if ( ! $attachment ) { continue; }
				$media_label = 'photo' === self::telegram_media_type( $media_id ) ? __( 'Photo', 'georg-kloster-connect-hub' ) : __( 'Video', 'georg-kloster-connect-hub' );
				echo '<li data-attachment-id="' . esc_attr( (string) $media_id ) . '"><span>' . esc_html( $media_label . ': ' . $attachment->post_title ) . '</span> <button type="button" class="button-link-delete gkch-telegram-media-remove">' . esc_html__( 'Remove', 'georg-kloster-connect-hub' ) . '</button></li>';
			}
			echo '</ul><p class="description">' . esc_html__( 'Select up to 10 JPEG or PNG photos and MP4 videos from the Media Library. Telegram fetches them from their public URLs; URL limits are 5 MB per photo and 20 MB per video.', 'georg-kloster-connect-hub' ) . '</p></div></fieldset>';
		}
		echo '</div></div><div class="postbox gkch-reply-menu-postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Menu buttons for this reply', 'georg-kloster-connect-hub' ) . '</h2></div><div class="inside"><p>' . esc_html__( 'Add buttons below this reply. Each button can run another bot command or reply, open a website link, or launch a Mini App inside Telegram. Mini App buttons require an HTTPS URL and work in private chats. Enter labels in the active WPML languages. Telegram displays up to 10 buttons, two per row.', 'georg-kloster-connect-hub' ) . '</p>';
		$menu_buttons = self::sanitize_menu_buttons( $command['menu_buttons'] ?? array() );
		$menu_targets = self::menu_target_options();
		echo '<div class="gkch-menu-buttons" data-next-index="' . esc_attr( (string) count( $menu_buttons ) ) . '">';
		foreach ( $menu_buttons as $button_index => $button ) { self::render_menu_button_editor_row( $button, (string) $button_index, self::active_languages(), $menu_targets ); }
		echo '</div><button type="button" class="button gkch-menu-button-add">' . esc_html__( 'Add a button', 'georg-kloster-connect-hub' ) . '</button><p class="description">' . esc_html__( 'A command button returns its configured answer in the subscriber’s selected language. A website link opens the URL directly in Telegram. A Mini App button needs an HTTPS URL and opens the app inside Telegram in private chats.', 'georg-kloster-connect-hub' ) . '</p>';
		echo '<template id="gkch-menu-button-template">';
		self::render_menu_button_editor_row( array(), '__INDEX__', self::active_languages(), $menu_targets );
		echo '</template></div></div></div><div id="postbox-container-1" class="postbox-container"><div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Save', 'georg-kloster-connect-hub' ) . '</h2></div><div class="inside">';
		submit_button( __( 'Save command', 'georg-kloster-connect-hub' ), 'primary', 'submit', false );
		if ( ! $system && 'new' !== $id ) { echo '<p><button class="button-link-delete" type="submit" name="delete_command" value="1" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this command or responder?', 'georg-kloster-connect-hub' ) ) ) . ');">' . esc_html__( 'Delete', 'georg-kloster-connect-hub' ) . '</button></p>'; }
		echo '</div></div></div></div></div></form></div>';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
		global $wpdb;
		$tables = self::tables();
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status='active'", $tables['subscribers'] ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Telegram bot subscribers', 'georg-kloster-connect-hub' ) . '</h1>';
		echo '<p>' . esc_html__( 'Anyone who presses Start is subscribed to news and schedule announcements. /stop removes their Chat ID from the active subscriber list. The bot sends only to users who initiated the chat.', 'georg-kloster-connect-hub' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=gkch-telegram-commands' ) ) . '">' . esc_html__( 'Manage bot commands and replies', 'georg-kloster-connect-hub' ) . '</a></p>';
		$counts = $wpdb->get_results( $wpdb->prepare( "SELECT language,COUNT(*) AS amount FROM %i WHERE status='active' GROUP BY language", $tables['subscribers'] ), ARRAY_A );
		$available = self::active_languages(); $language_counts = array();
		foreach ( (array) $counts as $row ) { $name = $available[$row['language']]['name'] ?? $row['language']; $language_counts[] = $name . ': ' . (int) $row['amount']; }
		echo '<p><strong>' . esc_html__( 'Active subscribers', 'georg-kloster-connect-hub' ) . ':</strong> ' . esc_html( (string) $total ) . ( $language_counts ? ' · ' . esc_html( implode( ' · ', $language_counts ) ) : '' ) . '</p>';
		self::render_campaigns( $tables );
		self::render_subscribers( $tables );
		echo '</div>';
	}

	private static function render_campaigns( array $tables ): void {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY id DESC LIMIT 25", $tables['campaigns'] ), ARRAY_A );
		echo '<h2>' . esc_html__( 'Recent broadcast status', 'georg-kloster-connect-hub' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Message', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Status', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Recipients', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Action', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			$summary = $row['sent'] . '/' . $row['total'] . ' ' . __( 'sent', 'georg-kloster-connect-hub' ) . ( (int) $row['failed'] ? '; ' . $row['failed'] . ' ' . __( 'failed', 'georg-kloster-connect-hub' ) : '' ) . ( (int) $row['skipped'] ? '; ' . $row['skipped'] . ' ' . __( 'skipped', 'georg-kloster-connect-hub' ) : '' );
			$statuses = array( 'queued' => __( 'Needs attention', 'georg-kloster-connect-hub' ), 'processing' => __( 'Sending', 'georg-kloster-connect-hub' ), 'partial' => __( 'Completed with issues', 'georg-kloster-connect-hub' ), 'sent' => __( 'Completed', 'georg-kloster-connect-hub' ), 'failed' => __( 'Needs attention', 'georg-kloster-connect-hub' ), 'cancelled' => __( 'Cancelled', 'georg-kloster-connect-hub' ), 'no_subscribers' => __( 'No subscribers at send time', 'georg-kloster-connect-hub' ) );
			$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_id=%d AND status='pending'", $tables['deliveries'], $row['id'] ) );
			$reason = '';
			if ( 'cron_schedule_failed' === $row['error'] ) { $reason = __( 'WordPress could not schedule this delivery. Retry it or check that WordPress Cron is running.', 'georg-kloster-connect-hub' ); }
			elseif ( 'telegram_disabled' === $row['error'] ) { $reason = __( 'Telegram sending is disabled or the connection is incomplete. Retry after correcting the settings.', 'georg-kloster-connect-hub' ); }
			elseif ( 'manual_retry_required' === $row['error'] ) { $reason = __( 'Some recipients still need attention. Retry them here; deliveries are not scheduled in the background.', 'georg-kloster-connect-hub' ); }
			elseif ( $pending && in_array( $row['status'], array( 'queued', 'processing' ), true ) ) { $reason = __( 'Some recipients still need attention. Retry them here; deliveries are not scheduled in the background.', 'georg-kloster-connect-hub' ); }
			elseif ( $row['error'] ) { $reason = $row['error']; }
			echo '<tr><td>' . esc_html( $row['title'] ) . '<br><small>' . esc_html( $row['created_at'] ) . '</small>' . ( $reason ? '<br>' . esc_html( $reason ) : '' ) . '</td><td>' . esc_html( $statuses[ $row['status'] ] ?? $row['status'] ) . '</td><td>' . esc_html( $summary ) . '</td><td>';
			if ( ( (int) $row['failed'] > 0 || $pending > 0 ) && in_array( $row['status'], array( 'partial', 'failed', 'processing', 'queued' ), true ) ) { self::action_form( 'retry', (int) $row['id'], __( 'Retry failed recipients', 'georg-kloster-connect-hub' ) ); }
			if ( in_array( $row['status'], array( 'queued', 'processing' ), true ) ) { self::action_form( 'cancel', (int) $row['id'], __( 'Cancel', 'georg-kloster-connect-hub' ) ); }
			echo '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="4">' . esc_html__( 'No broadcasts have been sent yet.', 'georg-kloster-connect-hub' ) . '</td></tr>'; }
		echo '</tbody></table>';
	}

	private static function render_subscribers( array $tables ): void {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This GET value only selects a page of read-only subscriber data.
		$page = max( 1, absint( wp_unslash( $_GET['subscriber_page'] ?? 1 ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$per_page = 50; $offset = ( $page - 1 ) * $per_page;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,chat_id,language,created_at FROM %i WHERE status='active' ORDER BY id DESC LIMIT %d OFFSET %d", $tables['subscribers'], $per_page, $offset ), ARRAY_A );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status='active'", $tables['subscribers'] ) );
		echo '<h2>' . esc_html__( 'Active subscribers', 'georg-kloster-connect-hub' ) . '</h2><table class="widefat striped"><thead><tr><th>Chat ID</th><th>' . esc_html__( 'Language', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Subscribed', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Action', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
		$available = self::active_languages();
		foreach ( (array) $rows as $row ) { $name = $available[$row['language']]['name'] ?? $row['language']; echo '<tr><td>' . esc_html( $row['chat_id'] ) . '</td><td>' . esc_html( $name ) . '</td><td>' . esc_html( $row['created_at'] ) . '</td><td>'; self::action_form( 'remove_subscriber', (int) $row['id'], __( 'Remove subscriber', 'georg-kloster-connect-hub' ) ); echo '</td></tr>'; }
		if ( ! $rows ) { echo '<tr><td colspan="4">' . esc_html__( 'There are no active subscribers.', 'georg-kloster-connect-hub' ) . '</td></tr>'; }
		echo '</tbody></table>';
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total_pages > 1 ) { echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'subscriber_page', '%#%', admin_url( 'admin.php?page=gkch-telegram' ) ), 'format' => '', 'current' => $page, 'total' => $total_pages, 'prev_text' => __( 'Previous', 'georg-kloster-connect-hub' ), 'next_text' => __( 'Next', 'georg-kloster-connect-hub' ) ) ) ) . '</div></div>'; }
	}

	private static function action_form( string $action, int $id, string $label ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_telegram_action"><input type="hidden" name="item_action" value="' . esc_attr( $action ) . '"><input type="hidden" name="item_id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'gkch_telegram_action_' . $action . '_' . $id );
		echo '<button class="button" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	public static function admin_action(): void {
		if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
		$action = is_string( $_POST['item_action'] ?? null ) ? sanitize_key( wp_unslash( $_POST['item_action'] ) ) : '';
		$id = is_scalar( $_POST['item_id'] ?? null ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		check_admin_referer( 'gkch_telegram_action_' . $action . '_' . $id );
		global $wpdb; $tables = self::tables();
		if ( 'remove_subscriber' === $action && $id ) {
			$chat = $wpdb->get_var( $wpdb->prepare( "SELECT chat_id FROM %i WHERE id=%d", $tables['subscribers'], $id ) );
			if ( $chat ) { self::unsubscribe( (string) $chat ); }
		} elseif ( 'retry' === $action && $id ) {
			$campaign_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM %i WHERE id=%d", $tables['campaigns'], $id ) );
			if ( in_array( $campaign_status, array( 'partial', 'failed', 'processing', 'queued' ), true ) ) {
				$now = gmdate( 'Y-m-d H:i:s' );
				$wpdb->query( $wpdb->prepare( "UPDATE %i SET status='pending',attempts=0,error='',next_attempt=%s WHERE campaign_id=%d AND status IN ('failed','pending')", $tables['deliveries'], $now, $id ) );
				$wpdb->update( $tables['campaigns'], array( 'status' => 'queued', 'error' => '', 'updated_at' => $now ), array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
				self::worker( $id );
			}
		} elseif ( 'cancel' === $action && $id ) {
			self::set_campaign_status( $id, 'cancelled', '' ); self::skip_pending( $id, 'cancelled' ); wp_clear_scheduled_hook( 'gkch_telegram_broadcast_worker', array( $id ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=gkch-telegram' ) ); exit;
	}
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
