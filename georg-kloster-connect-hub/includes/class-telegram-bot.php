<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Subscriber commands, schedule replies and queued Telegram broadcasts. */
final class Telegram_Bot {
	private const SCHEMA_OPTION = 'gkch_telegram_schema';
	private const COMMANDS_OPTION = 'gkch_telegram_custom_commands';
	private const WORKER = 'gkch_telegram_broadcast_worker';
	private const DIGEST = 'gkch_telegram_daily_digest';
	private const COMMAND = 'gkch_telegram_command';
	private const BATCH_SIZE = 10;

	public static function hooks(): void {
		add_action( 'init', array( self::class, 'install' ), 1 );
		add_action( 'init', array( self::class, 'ensure_digest' ), 2 );
		add_action( 'init', array( self::class, 'ensure_workers' ), 3 );
		add_action( 'admin_menu', array( self::class, 'menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'admin_assets' ) );
		add_action( 'admin_post_gkch_telegram_action', array( self::class, 'admin_action' ) );
		add_action( 'admin_post_gkch_telegram_bot_commands_save', array( self::class, 'save_bot_commands' ) );
		add_action( self::WORKER, array( self::class, 'worker' ) );
		add_action( self::DIGEST, array( self::class, 'daily_digest' ) );
		add_action( self::COMMAND, array( self::class, 'command_worker' ), 10, 4 );
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
		if ( self::SCHEMA_OPTION === get_option( self::SCHEMA_OPTION ) ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables = self::tables();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$tables['subscribers']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			chat_id varchar(32) NOT NULL,
			language varchar(2) NOT NULL DEFAULT 'ru',
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
			language varchar(2) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			next_attempt datetime NOT NULL,
			remote_id varchar(32) NOT NULL DEFAULT '',
			error varchar(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY campaign_subscriber (campaign_id,subscriber_id),
			KEY due (campaign_id,status,next_attempt)
		) $charset;" );
		update_option( self::SCHEMA_OPTION, '1', false );
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
			return self::handle_callback( $callback );
		}
		$text = $message['text'] ?? '';
		if ( ! is_string( $text ) || ! preg_match( '/^\s*\/([a-zA-Z][a-zA-Z0-9_]{0,31})(?:@[a-zA-Z0-9_]+)?(?:\s+([^\r\n]+))?\s*$/u', $text, $match ) ) { return false; }
		$command = strtolower( $match[1] );
		$built_in = in_array( $command, array( 'start', 'stop', 'today', 'week', 'lang', 'language', 'help' ), true );
		$custom_command = self::custom_command( $command );
		if ( ! $built_in && ! $custom_command ) { return false; }
		if ( 'private' !== ( $message['chat']['type'] ?? '' ) || ! is_scalar( $message['chat']['id'] ?? null ) || (string) $message['chat']['id'] !== (string) ( $message['from']['id'] ?? '' ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', (string) ( $message['chat']['id'] ?? '' ) ) || ! empty( $message['from']['is_bot'] ) ) { return true; }
		$chat_id = (string) $message['chat']['id'];
		$language = self::language_from_telegram( $message['from']['language_code'] ?? '' );
		if ( $update_id > 0 ) {
			$key = 'gkch_tg_update_' . $update_id;
			if ( get_transient( $key ) ) { return true; }
			set_transient( $key, 1, DAY_IN_SECONDS );
		}
		if ( 'start' === $command ) {
			if ( ! self::broadcasting_enabled() ) { self::send_direct( $chat_id, self::copy( 'disabled', $language ) ); return true; }
			self::subscribe( $chat_id, $language );
			self::send_direct( $chat_id, self::message_with_custom_commands( 'welcome', $language ), array( 'inline_keyboard' => array( array( array( 'text' => self::copy( 'russian', $language ), 'callback_data' => 'lang:ru' ), array( 'text' => self::copy( 'german', $language ), 'callback_data' => 'lang:de' ) ) ) ) );
			return true;
		}
		if ( 'stop' === $command ) { self::unsubscribe( $chat_id ); self::send_direct( $chat_id, self::copy( 'stopped', $language ) ); return true; }
		if ( ! self::is_active( $chat_id ) ) { self::send_direct( $chat_id, self::copy( 'need_start', $language ) ); return true; }
		$language = self::subscriber_language( $chat_id, $language );
		if ( in_array( $command, array( 'lang', 'language' ), true ) ) {
			self::send_direct( $chat_id, self::copy( 'choose_language', $language ), array( 'inline_keyboard' => array( array( array( 'text' => self::copy( 'russian', $language ), 'callback_data' => 'lang:ru' ), array( 'text' => self::copy( 'german', $language ), 'callback_data' => 'lang:de' ) ) ) ) );
		} elseif ( 'today' === $command ) {
			self::queue_command( $chat_id, $language, $command, $update_id );
		} elseif ( 'week' === $command ) {
			self::queue_command( $chat_id, $language, $command, $update_id );
		} elseif ( $custom_command ) {
			self::send_direct( $chat_id, $custom_command['response_' . $language] );
		} else {
			self::send_direct( $chat_id, self::message_with_custom_commands( 'help', $language ) );
		}
		return true;
	}

	private static function queue_command( string $chat_id, string $language, string $command, int $update_id ): void {
		$queued = wp_schedule_single_event( time() + 1, self::COMMAND, array( $chat_id, $language, $command, $update_id ), true );
		if ( is_wp_error( $queued ) || ! $queued ) { self::send_direct( $chat_id, self::copy( 'command_busy', $language ) ); }
	}

	public static function command_worker( string $chat_id, string $language, string $command, int $update_id ): void {
		if ( ! preg_match( '/^[1-9][0-9]{0,19}$/D', $chat_id ) || ! in_array( $language, array( 'ru', 'de' ), true ) || ! in_array( $command, array( 'today', 'week' ), true ) || ! self::is_active( $chat_id ) ) { return; }
		$date = new \DateTimeImmutable( 'today', wp_timezone() );
		$text = 'today' === $command ? self::day_message( $date, $language ) : self::week_message( $date, $language );
		self::send_direct( $chat_id, $text );
	}

	private static function handle_callback( array $callback ): bool {
		$data = $callback['data'] ?? '';
		if ( ! is_string( $data ) || ! preg_match( '/^lang:(ru|de)$/D', $data ) ) { return false; }
		$message = $callback['message'] ?? array();
		$chat = $message['chat']['id'] ?? null;
		if ( 'private' === ( $message['chat']['type'] ?? '' ) && is_scalar( $chat ) && preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $chat ) && self::is_active( (string) $chat ) ) {
			self::update_language( (string) $chat, $data === 'lang:de' ? 'de' : 'ru' );
			self::send_direct( (string) $chat, self::copy( 'language_saved', $data === 'lang:de' ? 'de' : 'ru' ) );
		}
		if ( is_string( $callback['id'] ?? null ) ) { self::api( 'answerCallbackQuery', array( 'callback_query_id' => $callback['id'] ) ); }
		return true;
	}

	public static function language_from_telegram( $language ): string { return is_string( $language ) && 0 === strpos( strtolower( $language ), 'de' ) ? 'de' : 'ru'; }

	private static function copy( string $key, string $language ): string {
		$locale = 'de' === $language ? 'de_DE' : 'ru_RU';
		$switched = switch_to_locale( $locale );
		$messages = array(
			'disabled' => __( 'The Telegram subscription service is currently disabled. Please try again later.', 'georg-kloster-connect-hub' ),
			'welcome' => __( 'You are subscribed to monastery news and announcements. New posts and schedule updates will appear here. Commands: /today — services and commemorations, /week — weekly schedule, /lang — language, /stop — unsubscribe.', 'georg-kloster-connect-hub' ),
			'stopped' => __( 'You have unsubscribed. The bot will no longer send you broadcasts. Press /start to subscribe again.', 'georg-kloster-connect-hub' ),
			'need_start' => __( 'Press /start first to subscribe and use the bot commands.', 'georg-kloster-connect-hub' ),
			'choose_language' => __( 'Choose the language for messages:', 'georg-kloster-connect-hub' ),
			'language_saved' => __( 'The message language has been saved.', 'georg-kloster-connect-hub' ),
			'russian' => __( 'Russian', 'georg-kloster-connect-hub' ),
			'german' => __( 'German', 'georg-kloster-connect-hub' ),
			'help' => __( 'Commands: /today — services and commemorations, /week — weekly schedule, /lang — choose message language, /stop — unsubscribe.', 'georg-kloster-connect-hub' ),
			'today' => __( 'Schedule for today', 'georg-kloster-connect-hub' ),
			'week' => __( 'Schedule for this week', 'georg-kloster-connect-hub' ),
			'no_services' => __( 'No services are scheduled for this period.', 'georg-kloster-connect-hub' ),
			'calendar_unavailable' => __( 'Church calendar data is not available right now.', 'georg-kloster-connect-hub' ),
			'command_busy' => __( 'The request could not be queued. Please try again in a minute.', 'georg-kloster-connect-hub' ),
			'feast' => __( 'Commemorations of the day', 'georg-kloster-connect-hub' ),
			'services' => __( 'Services', 'georg-kloster-connect-hub' ),
			'schedule_link' => __( 'Full schedule', 'georg-kloster-connect-hub' ),
			'digest' => __( 'Services and commemorations of the day', 'georg-kloster-connect-hub' ),
			'changed' => __( 'The service schedule has changed', 'georg-kloster-connect-hub' ),
			'additional_commands' => __( 'Additional commands', 'georg-kloster-connect-hub' ),
		);
		$message = $messages[$key] ?? '';
		if ( $switched ) { restore_previous_locale(); }
		return $message;
	}

	private static function message_with_custom_commands( string $key, string $language ): string {
		$text = self::copy( $key, $language );
		$commands = self::custom_commands();
		$lines = array();
		foreach ( $commands as $command ) {
			$description = trim( (string) ( $command['description_' . $language] ?? '' ) );
			if ( '' !== $description ) { $lines[] = '/' . $command['command'] . ' — ' . $description; }
		}
		if ( $lines ) { $text .= "\n\n" . self::copy( 'additional_commands', $language ) . ":\n" . implode( "\n", $lines ); }
		return $text;
	}

	public static function command_descriptions( string $language ): array {
		$language = in_array( $language, array( 'en', 'ru', 'de' ), true ) ? $language : 'en';
		$locale = array( 'ru' => 'ru_RU', 'de' => 'de_DE', 'en' => 'en_US' )[ $language ];
		$switched = switch_to_locale( $locale );
		$descriptions = array(
			'start' => __( 'Subscribe to monastery news', 'georg-kloster-connect-hub' ),
			'today' => __( 'Today’s services and commemorations', 'georg-kloster-connect-hub' ),
			'week' => __( 'Services this week', 'georg-kloster-connect-hub' ),
			'lang' => __( 'Choose message language', 'georg-kloster-connect-hub' ),
			'stop' => __( 'Unsubscribe from messages', 'georg-kloster-connect-hub' ),
			'help' => __( 'Show bot commands', 'georg-kloster-connect-hub' ),
		);
		if ( $switched ) { restore_previous_locale(); }
		$commands = array();
		foreach ( array( 'start', 'today', 'week', 'lang', 'stop', 'help' ) as $command ) {
			if ( is_string( $descriptions[$command] ?? null ) ) { $commands[] = array( 'command' => $command, 'description' => $descriptions[$command] ); }
		}
		if ( in_array( $language, array( 'ru', 'de' ), true ) ) {
			foreach ( self::custom_commands() as $custom ) {
				$description = trim( (string) ( $custom['description_' . $language] ?? '' ) );
				if ( '' !== $description ) { $commands[] = array( 'command' => $custom['command'], 'description' => $description ); }
			}
		}
		return $commands;
	}

	private static function custom_commands(): array {
		return self::sanitize_custom_commands( get_option( self::COMMANDS_OPTION, array() ) );
	}

	private static function custom_command( string $name ): ?array {
		foreach ( self::custom_commands() as $command ) {
			if ( $name === $command['command'] ) { return $command; }
		}
		return null;
	}

	private static function subscribe( string $chat_id, string $language ): void {
		global $wpdb;
		$table = self::tables()['subscribers'];
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( "INSERT INTO $table (chat_id,language,status,created_at,updated_at) VALUES (%s,%s,'active',%s,%s) ON DUPLICATE KEY UPDATE language=VALUES(language),status='active',updated_at=VALUES(updated_at)", $chat_id, $language, $now, $now ) );
	}

	private static function unsubscribe( string $chat_id ): void {
		global $wpdb;
		$tables = self::tables();
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['subscribers']} WHERE chat_id = %s", $chat_id ) );
		if ( $id ) { $wpdb->query( $wpdb->prepare( "UPDATE {$tables['deliveries']} SET status='skipped',error='unsubscribed' WHERE subscriber_id=%d AND status='pending'", $id ) ); }
		$wpdb->delete( $tables['subscribers'], array( 'chat_id' => $chat_id ), array( '%s' ) );
	}

	private static function is_active( string $chat_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tables()['subscribers'] . " WHERE chat_id=%s AND status='active'", $chat_id ) );
	}

	private static function subscriber_language( string $chat_id, string $fallback ): string {
		global $wpdb;
		$lang = $wpdb->get_var( $wpdb->prepare( 'SELECT language FROM ' . self::tables()['subscribers'] . " WHERE chat_id=%s AND status='active'", $chat_id ) );
		return in_array( $lang, array( 'ru', 'de' ), true ) ? $lang : $fallback;
	}

	private static function update_language( string $chat_id, string $language ): void {
		global $wpdb;
		$wpdb->update( self::tables()['subscribers'], array( 'language' => $language, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'chat_id' => $chat_id ), array( '%s', '%s' ), array( '%s' ) );
	}

	private static function send_direct( string $chat_id, string $text, array $markup = array() ): bool {
		if ( '' === trim( $text ) ) { return false; }
		$body = array( 'chat_id' => $chat_id, 'text' => self::limit_text( $text ), 'link_preview_options' => array( 'is_disabled' => true ) );
		if ( $markup ) { $body['reply_markup'] = $markup; }
		$result = self::api( 'sendMessage', $body );
		return ! is_wp_error( $result );
	}

	private static function api( string $method, array $body ) {
		$connector = Connector::instance( 'telegram' );
		$settings = $connector ? $connector->settings() : array();
		if ( ! $connector || empty( $settings['enabled'] ) || ! preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', (string) ( $settings['token'] ?? '' ) ) || ! in_array( $method, array( 'sendMessage', 'answerCallbackQuery' ), true ) ) { return new \WP_Error( 'telegram_unavailable', 'Telegram is not configured.' ); }
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
		return self::create_campaign( $kind, $source_id, $context, $messages['title'], $messages['ru'], $messages['de'], $messages['url'], (string) ( $context['configuration'] ?? '' ) );
	}

	private static function campaign_messages( string $kind, int $source_id, array $context, string $text, string $url ): array {
		if ( 'digest' === $kind ) {
			$date = new \DateTimeImmutable( 'today', wp_timezone() );
			return array( 'title' => self::copy( 'digest', 'ru' ), 'ru' => self::day_message( $date, 'ru', true ), 'de' => self::day_message( $date, 'de', true ), 'url' => '' );
		}
		if ( 'post' === $kind ) { return self::post_messages( $source_id ); }
		if ( 'automation' === $kind ) {
			$rule = Publication_Rules::get( (int) ( $context['rule_id'] ?? 0 ) );
			if ( ! $rule ) { return array(); }
			$ru = trim( (string) ( $rule['text_ru'] ?? '' ) ); $de = trim( (string) ( $rule['text_de'] ?? '' ) );
			if ( '' === $ru && '' === $de ) {
				$old = trim( (string) ( $rule['text'] ?? $text ) );
				$lang = 'de' === substr( determine_locale(), 0, 2 ) ? 'de' : 'ru';
				if ( 'de' === $lang ) { $de = $old; } else { $ru = $old; }
			}
			return array( 'title' => $rule['name'], 'ru' => $ru, 'de' => $de, 'url' => $rule['url'] ?: $url );
		}
		$title = 'schedule' === $kind ? self::copy( 'changed', 'ru' ) . ': ' . get_the_title( $source_id ) : $text;
		if ( 'schedule' === $kind ) {
			$ru = self::schedule_change_text( $source_id, 'ru' );
			$de = self::schedule_change_text( $source_id, 'de' );
			return array( 'title' => $title, 'ru' => $ru, 'de' => $de, 'url' => '' );
		}
		return array( 'title' => $title, 'ru' => $text, 'de' => $text, 'url' => $url );
	}

	private static function post_messages( int $source_id ): array {
		$source = get_post( $source_id );
		if ( ! $source || 'publish' !== $source->post_status ) { return array(); }
		$messages = array();
		$source_language = self::post_language( $source_id );
		foreach ( array( 'ru', 'de' ) as $language ) {
			$id = self::translated_post_id( $source_id, $language );
			if ( ! $id && $language === $source_language ) { $id = $source_id; }
			$post = $id ? get_post( $id ) : null;
			if ( ! $post || 'publish' !== $post->post_status ) { $messages[$language] = ''; continue; }
			$excerpt = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
			$messages[$language] = $post->post_title . "\n" . wp_html_excerpt( wp_strip_all_tags( strip_shortcodes( $excerpt ) ), 700, '…' );
			if ( ! isset( $messages['url_' . $language] ) ) { $messages['url_' . $language] = get_permalink( $id ); }
		}
		$messages['title'] = $source->post_title;
		$messages['url'] = '';
		// Each locale gets its own translated permalink as a final line.
		foreach ( array( 'ru', 'de' ) as $language ) { if ( $messages[$language] ) { $messages[$language] .= "\n" . ( $messages['url_' . $language] ?? get_permalink( $source_id ) ); } }
		return $messages;
	}

	private static function post_language( int $post_id ): string {
		$language = has_filter( 'wpml_element_language_code' ) ? apply_filters( 'wpml_element_language_code', null, $post_id, 'post_post' ) : null;
		if ( ! is_string( $language ) && function_exists( 'pll_get_post_language' ) ) { $language = pll_get_post_language( $post_id, 'slug' ); }
		if ( is_string( $language ) && in_array( substr( strtolower( $language ), 0, 2 ), array( 'ru', 'de' ), true ) ) { return substr( strtolower( $language ), 0, 2 ); }
		return 'de' === substr( determine_locale(), 0, 2 ) ? 'de' : 'ru';
	}

	private static function translated_post_id( int $post_id, string $language ): int {
		if ( has_filter( 'wpml_object_id' ) ) {
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
			$lines = array( self::copy( 'changed', $language ) . ':', $title );
			foreach ( array_slice( array_values( $events ), 0, 3 ) as $event ) { $lines[] = self::event_line( $event, $language, true ); }
			$lines[] = self::copy( 'schedule_link', $language ) . ': ' . self::schedule_url( $language );
			return implode( "\n", $lines );
		} finally { self::restore_language( $old ); }
	}

	private static function create_campaign( string $kind, int $source_id, array $context, string $title, string $ru, string $de, string $url, string $dedupe = '' ): string {
		global $wpdb;
		$tables = self::tables();
		if ( '' === $dedupe ) { $dedupe = hash( 'sha256', $kind . '|' . $source_id . '|' . $title . '|' . $ru . '|' . $de . '|' . $url ); }
		$key = hash( 'sha256', $kind . '|' . $source_id . '|' . $dedupe . '|' . $ru . '|' . $de );
		$now = gmdate( 'Y-m-d H:i:s' );
		$inserted = $wpdb->insert( $tables['campaigns'], array( 'dedupe_key' => $key, 'source_type' => $kind, 'source_id' => $source_id, 'context' => wp_json_encode( $context ), 'title' => sanitize_text_field( $title ), 'payload_ru' => sanitize_textarea_field( $ru ), 'payload_de' => sanitize_textarea_field( $de ), 'url' => esc_url_raw( $url ), 'status' => 'queued', 'created_at' => $now, 'updated_at' => $now ), array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		if ( false === $inserted ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['campaigns']} WHERE dedupe_key=%s", $key ) );
			return $existing ? 'tg-broadcast-' . $existing : '';
		}
		$id = (int) $wpdb->insert_id;
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$tables['deliveries']} (campaign_id,subscriber_id,language,status,attempts,next_attempt) SELECT %d,id,language,'pending',0,%s FROM {$tables['subscribers']} WHERE status='active'", $id, $now ) );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['deliveries']} WHERE campaign_id=%d", $id ) );
		$wpdb->update( $tables['campaigns'], array( 'total' => $total, 'status' => $total ? 'queued' : 'no_subscribers', 'updated_at' => $now ), array( 'id' => $id ), array( '%d', '%s', '%s' ), array( '%d' ) );
		if ( $total ) {
			if ( ! self::schedule_worker( $id, time() + 5 ) ) { $wpdb->update( $tables['campaigns'], array( 'status' => 'failed', 'error' => 'cron_schedule_failed', 'updated_at' => $now ), array( 'id' => $id ) ); }
		}
		return 'tg-broadcast-' . $id;
	}

	private static function schedule_worker( int $campaign_id, int $timestamp ): bool {
		if ( wp_next_scheduled( self::WORKER, array( $campaign_id ) ) ) { return true; }
		$scheduled = wp_schedule_single_event( max( time() + 1, $timestamp ), self::WORKER, array( $campaign_id ), true );
		return ! is_wp_error( $scheduled ) && (bool) $scheduled;
	}

	public static function ensure_workers(): void {
		if ( ! self::broadcasting_enabled() ) { return; }
		global $wpdb;
		$table = self::tables()['campaigns'];
		$deliveries = self::tables()['deliveries'];
		$ids = $wpdb->get_col( "SELECT c.id FROM $table c WHERE (c.status IN ('queued','processing','partial') OR (c.status='failed' AND c.error IN ('cron_schedule_failed','telegram_disabled'))) AND EXISTS (SELECT 1 FROM $deliveries d WHERE d.campaign_id=c.id AND d.status='pending') ORDER BY c.id ASC LIMIT 100" );
		foreach ( (array) $ids as $id ) {
			$campaign_id = absint( $id );
			if ( ! $campaign_id ) { continue; }
			self::schedule_worker( $campaign_id, time() + 5 );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::WORKER );
		wp_clear_scheduled_hook( self::DIGEST );
		wp_clear_scheduled_hook( self::COMMAND );
	}

	public static function worker( int $campaign_id ): void {
		global $wpdb;
		$tables = self::tables();
		$lock = Lock::acquire( 'gkch_telegram_campaign_' . $campaign_id, 120 );
		if ( ! $lock ) { return; }
		try {
			$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['campaigns']} WHERE id=%d", $campaign_id ), ARRAY_A );
			if ( ! is_array( $campaign ) || ! in_array( $campaign['status'], array( 'queued', 'processing', 'partial' ), true ) ) { return; }
			if ( ! self::broadcasting_enabled() ) { self::set_campaign_status( $campaign_id, 'failed', 'telegram_disabled' ); return; }
			$context = json_decode( (string) $campaign['context'], true );
			$context = is_array( $context ) ? $context : array();
			$kind = $campaign['source_type'];
			if ( 'automation' === $kind && ! Publication_Rules::allows( array( 'kind' => 'automation', 'source' => (int) $campaign['source_id'], 'reply_context' => $context ) ) ) { self::set_campaign_status( $campaign_id, 'cancelled', 'The publication rule is no longer active.' ); self::skip_pending( $campaign_id, 'rule_inactive' ); return; }
			if ( 'post' === $kind && ( 'publish' !== get_post_status( (int) $campaign['source_id'] ) || ! Publication_Rules::allows( array( 'kind' => 'post', 'source' => (int) $campaign['source_id'], 'reply_context' => $context ) ) ) ) { self::set_campaign_status( $campaign_id, 'cancelled', 'The post is no longer available for publication.' ); self::skip_pending( $campaign_id, 'post_unavailable' ); return; }
			$batch = $wpdb->get_results( $wpdb->prepare( "SELECT d.*,s.chat_id,s.status AS subscriber_status FROM {$tables['deliveries']} d LEFT JOIN {$tables['subscribers']} s ON s.id=d.subscriber_id WHERE d.campaign_id=%d AND d.status='pending' AND d.next_attempt<=%s ORDER BY d.id ASC LIMIT %d", $campaign_id, gmdate( 'Y-m-d H:i:s' ), self::BATCH_SIZE ), ARRAY_A );
			if ( ! $batch ) { self::finish_or_reschedule( $campaign_id ); return; }
			self::set_campaign_status( $campaign_id, 'processing', '' );
			foreach ( $batch as $index => $delivery ) {
				if ( 'active' !== ( $delivery['subscriber_status'] ?? '' ) || empty( $delivery['chat_id'] ) ) { self::delivery_status( $delivery, 'skipped', 'unsubscribed' ); continue; }
				$language = 'de' === $delivery['language'] ? 'de' : 'ru';
				$text = ( 'de' === $language ? $campaign['payload_de'] : $campaign['payload_ru'] );
				if ( '' === trim( (string) $text ) ) { self::delivery_status( $delivery, 'skipped', 'missing_translation' ); continue; }
				$result = self::send_campaign_message( (string) $delivery['chat_id'], (string) $text, (string) $campaign['url'] );
				$attempt = (int) $delivery['attempts'] + 1;
				if ( ! empty( $result['accepted'] ) ) {
					self::delivery_status( $delivery, 'sent', '', $attempt, (string) ( $result['message_id'] ?? '' ) );
				} elseif ( ! empty( $result['retry'] ) && $attempt < 5 ) {
					$delay = max( 5, min( 3600, (int) ( $result['retry_after'] ?? 15 ) ) );
					self::delivery_status( $delivery, 'pending', sanitize_key( $result['error'] ?? 'temporary_error' ), $attempt, '', time() + $delay );
					if ( ! empty( $result['rate_limited'] ) ) {
						foreach ( array_slice( $batch, $index + 1 ) as $remaining ) { self::delivery_status( $remaining, 'pending', '', (int) $remaining['attempts'], '', time() + $delay ); }
						break;
					}
				} else {
					self::delivery_status( $delivery, 'failed', sanitize_key( $result['error'] ?? 'telegram_rejected' ), $attempt );
					if ( 403 === (int) ( $result['error_code'] ?? 0 ) ) { self::block_subscriber( (int) $delivery['subscriber_id'] ); }
				}
			}
			self::finish_or_reschedule( $campaign_id );
		} finally { Lock::release( 'gkch_telegram_campaign_' . $campaign_id, $lock ); }
	}

	private static function send_campaign_message( string $chat_id, string $text, string $url ): array {
		$connector = Connector::instance( 'telegram' );
		$settings = $connector ? $connector->settings() : array();
		if ( empty( $settings['token'] ) ) { return array( 'accepted' => false, 'retry' => false, 'error' => 'missing_token' ); }
		if ( '' !== $url && ! str_contains( $text, $url ) ) { $text .= "\n" . $url; }
		$text = self::limit_text( trim( $text ) );
		$response = wp_remote_post( 'https://api.telegram.org/bot' . $settings['token'] . '/sendMessage', array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'chat_id' => $chat_id, 'text' => $text, 'link_preview_options' => array( 'is_disabled' => true ) ) ), 'timeout' => 10, 'redirection' => 0 ) );
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 === $code && is_array( $body ) && ! empty( $body['ok'] ) ) { return array( 'accepted' => true, 'message_id' => $body['result']['message_id'] ?? '' ); }
		$api_code = is_array( $body ) && is_numeric( $body['error_code'] ?? null ) ? (int) $body['error_code'] : $code;
		$retry_after = is_array( $body ) ? (int) ( $body['parameters']['retry_after'] ?? 0 ) : 0;
		$retry = is_wp_error( $response ) || 429 === $api_code || $api_code >= 500;
		return array( 'accepted' => false, 'retry' => $retry, 'rate_limited' => 429 === $api_code, 'retry_after' => $retry_after ?: 15, 'error_code' => $api_code, 'error' => 'api_' . $api_code );
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

	private static function finish_or_reschedule( int $campaign_id ): void {
		global $wpdb;
		$tables = self::tables();
		$counts = $wpdb->get_results( $wpdb->prepare( "SELECT status,COUNT(*) AS amount,MIN(next_attempt) AS next_at FROM {$tables['deliveries']} WHERE campaign_id=%d GROUP BY status", $campaign_id ), ARRAY_A );
		$pending = 0; $sent = 0; $failed = 0; $skipped = 0; $next = '';
		foreach ( (array) $counts as $row ) {
			if ( 'pending' === $row['status'] ) { $pending = (int) $row['amount']; $next = $row['next_at']; }
			if ( 'sent' === $row['status'] ) { $sent = (int) $row['amount']; }
			if ( 'failed' === $row['status'] ) { $failed = (int) $row['amount']; }
			if ( 'skipped' === $row['status'] ) { $skipped = (int) $row['amount']; }
		}
		$status = $pending ? 'processing' : ( $failed || $skipped ? 'partial' : 'sent' );
		$wpdb->update( $tables['campaigns'], array( 'status' => $status, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $campaign_id ), array( '%s', '%d', '%d', '%d', '%s' ), array( '%d' ) );
		if ( $pending ) {
			$next_time = $next ? strtotime( $next . ' UTC' ) : time() + 5;
			if ( ! self::schedule_worker( $campaign_id, max( time() + 2, $next_time ) ) ) { self::set_campaign_status( $campaign_id, 'failed', 'cron_schedule_failed' ); }
		}
	}

	public static function daily_digest(): void {
		if ( self::broadcasting_enabled() ) { self::queue( 'digest', 0 ); }
		self::sync_digest( self::connector_settings() );
	}

	private static function day_message( \DateTimeImmutable $date, string $language, bool $digest = false ): string {
		$language = 'de' === $language ? 'de' : 'ru';
		$events = self::schedule_events( $date, $date->modify( '+1 day' ), $language );
		$calendar = self::calendar_events( $date->format( 'Y-m-d' ), $language );
		$lines = array( self::copy( $digest ? 'digest' : 'today', $language ) . ' · ' . $date->format( 'd.m.Y' ) );
		if ( $calendar ) { $lines[] = self::copy( 'feast', $language ) . ': ' . implode( '; ', $calendar ); }
		if ( $events ) {
			$lines[] = self::copy( 'services', $language ) . ':';
			foreach ( $events as $event ) { $lines[] = self::event_line( $event, $language, false ); }
		} else { $lines[] = self::copy( 'no_services', $language ); }
		if ( null === $calendar ) { $lines[] = self::copy( 'calendar_unavailable', $language ); }
		$lines[] = self::copy( 'schedule_link', $language ) . ': ' . self::schedule_url( $language );
		return implode( "\n", $lines );
	}

	private static function week_message( \DateTimeImmutable $today, string $language ): string {
		$from = $today->modify( 'monday this week' )->setTime( 0, 0 );
		$to = $from->modify( '+7 days' );
		$events = self::schedule_events( $from, $to, $language );
		$lines = array( self::copy( 'week', $language ) . ' · ' . $from->format( 'd.m.Y' ) . ' — ' . $to->modify( '-1 day' )->format( 'd.m.Y' ) );
		if ( $events ) { foreach ( $events as $event ) { $lines[] = self::event_line( $event, $language, true ); } }
		else { $lines[] = self::copy( 'no_services', $language ); }
		$lines[] = self::copy( 'schedule_link', $language ) . ': ' . self::schedule_url( $language );
		return implode( "\n", $lines );
	}

	private static function schedule_events( \DateTimeImmutable $from, \DateTimeImmutable $to, string $language ): array {
		$class = '\\GeorgKlosterPrihodSchedule\\Schedule';
		if ( ! class_exists( $class ) ) { return array(); }
		$old = self::switch_language( $language );
		try { return $class::query( $from, $to, 0, false ); }
		catch ( \Throwable $error ) { return array(); }
		finally { self::restore_language( $old ); }
	}

	private static function calendar_events( string $date, string $language ): ?array {
		$class = '\\GeorgKlosterPrihodSchedule\\Calendar_Provider';
		if ( ! class_exists( $class ) ) { return null; }
		try { $data = $class::request( 'day', array( 'date' => $date, 'lang' => $language ) ); }
		catch ( \Throwable $error ) { return null; }
		if ( is_wp_error( $data ) || ! is_array( $data['day']['events'] ?? null ) ) { return null; }
		$result = array();
		foreach ( $data['day']['events'] as $event ) {
			if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! is_numeric( $event['typeCode'] ?? null ) || (int) $event['typeCode'] < 0 || ! is_string( $event['title'] ?? null ) ) { continue; }
			$title = sanitize_text_field( $event['title'] );
			if ( $title && ! in_array( $title, $result, true ) ) { $result[] = $title; }
			if ( count( $result ) >= 4 ) { break; }
		}
		return $result;
	}

	private static function event_line( array $event, string $language, bool $with_date ): string {
		$start = $event['start'] ?? null;
		$when = $start instanceof \DateTimeInterface ? ( $with_date ? $start->format( 'd.m' ) . ' ' : '' ) . $start->format( 'H:i' ) : '';
		$location = get_the_title( (int) ( $event['location_id'] ?? 0 ) );
		$title = sanitize_text_field( (string) ( $event['title'] ?? '' ) );
		return '• ' . ( $when ? $when . ' — ' : '' ) . $title . ( $location ? ' · ' . sanitize_text_field( $location ) : '' );
	}

	private static function switch_language( string $language ) {
		$old = null;
		if ( has_filter( 'wpml_current_language' ) ) { $old = apply_filters( 'wpml_current_language', null ); do_action( 'wpml_switch_language', $language ); }
		elseif ( function_exists( 'pll_current_language' ) && function_exists( 'pll_switch_language' ) ) { $old = pll_current_language( 'slug' ); pll_switch_language( $language ); }
		return $old;
	}

	private static function restore_language( $old ): void {
		if ( is_string( $old ) && has_filter( 'wpml_current_language' ) ) { do_action( 'wpml_switch_language', $old ); }
		elseif ( is_string( $old ) && function_exists( 'pll_switch_language' ) ) { pll_switch_language( $old ); }
	}

	private static function schedule_url( string $language = '' ): string {
		$page = get_page_by_path( 'raspisanie-bogosluzheniy' );
		if ( $page && $language && ( $translated = self::translated_post_id( (int) $page->ID, $language ) ) ) { $page = get_post( $translated ); }
		return $page ? get_permalink( $page ) : home_url( '/raspisanie-bogosluzheniy/' );
	}

	public static function menu(): void {
		add_submenu_page( 'gkps', __( 'Telegram bot subscribers', 'georg-kloster-connect-hub' ), __( 'Telegram bot', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkch-telegram', array( self::class, 'render' ) );
	}

	public static function admin_assets( string $hook ): void {
		if ( 'gkps_page_gkch-telegram' !== $hook ) { return; }
		wp_enqueue_script( 'gkch-telegram-bot-admin', plugins_url( 'assets/telegram-bot-admin.js', GKPS_FILE ), array(), GKPS_VERSION, true );
	}

	private static function sanitize_custom_commands( $rows, &$invalid = false ): array {
		if ( ! is_array( $rows ) ) { return array(); }
		$reserved = array( 'start', 'stop', 'today', 'week', 'lang', 'language', 'help' );
		$commands = array();
		$seen = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { $invalid = true; continue; }
			$raw_command = is_scalar( $row['command'] ?? null ) ? strtolower( trim( (string) $row['command'] ) ) : '';
			$command = 0 === strpos( $raw_command, '/' ) ? substr( $raw_command, 1 ) : $raw_command;
			$description_ru = is_scalar( $row['description_ru'] ?? null ) ? self::limit_text( sanitize_text_field( (string) $row['description_ru'] ), 256 ) : '';
			$description_de = is_scalar( $row['description_de'] ?? null ) ? self::limit_text( sanitize_text_field( (string) $row['description_de'] ), 256 ) : '';
			$response_ru = is_scalar( $row['response_ru'] ?? null ) ? self::limit_text( sanitize_textarea_field( (string) $row['response_ru'] ) ) : '';
			$response_de = is_scalar( $row['response_de'] ?? null ) ? self::limit_text( sanitize_textarea_field( (string) $row['response_de'] ) ) : '';
			if ( '' === $command && '' === $description_ru && '' === $description_de && '' === $response_ru && '' === $response_de ) { continue; }
			if ( ! preg_match( '/^[a-z][a-z0-9_]{0,31}$/D', $command ) || in_array( $command, $reserved, true ) || isset( $seen[$command] ) || '' === $description_ru || '' === $description_de || '' === $response_ru || '' === $response_de ) {
				$invalid = true;
				continue;
			}
			$seen[$command] = true;
			$commands[] = array(
				'command' => $command,
				'description_ru' => $description_ru,
				'description_de' => $description_de,
				'response_ru' => $response_ru,
				'response_de' => $response_de,
			);
		}
		return $commands;
	}

	private static function sync_menu_commands(): ?bool {
		$connector = Connector::instance( 'telegram' );
		if ( ! $connector ) { return null; }
		$settings = $connector->settings();
		if ( ! $connector->valid( $settings ) ) { return null; }
		foreach ( array( 'en', 'ru', 'de' ) as $language ) {
			$result = Telegram_Request_Settings::api( 'setMyCommands', array( 'commands' => self::command_descriptions( $language ), 'language_code' => $language ), $settings );
			if ( is_wp_error( $result ) ) { return false; }
		}
		return true;
	}

	public static function save_bot_commands(): void {
		if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'gkch_telegram_bot_commands_save' );
		$input = isset( $_POST['bot_commands'] ) && is_array( $_POST['bot_commands'] ) ? wp_unslash( $_POST['bot_commands'] ) : array();
		$invalid_commands = false;
		update_option( self::COMMANDS_OPTION, self::sanitize_custom_commands( $input, $invalid_commands ), false );
		$synced = self::sync_menu_commands();
		$notice = array(
			'status' => true === $synced ? 'success' : ( false === $synced ? 'warning' : 'info' ),
			'message' => true === $synced
				? __( 'Custom commands were saved and the Telegram menu was updated.', 'georg-kloster-connect-hub' )
				: ( false === $synced
					? __( 'Custom commands were saved, but Telegram could not update the menu. Check the bot connection and save again.', 'georg-kloster-connect-hub' )
					: __( 'Custom commands were saved. Connect the Telegram bot to publish them in its menu.', 'georg-kloster-connect-hub' ) ),
		);
		if ( $invalid_commands ) { $notice['invalid_commands'] = __( 'Some command rows were skipped. Use a unique lowercase English command word without spaces, and provide a menu description and reply in both languages.', 'georg-kloster-connect-hub' ); }
		set_transient( 'gkch_telegram_command_notice_' . get_current_user_id(), $notice, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=gkch-telegram#custom-commands' ) );
		exit;
	}

	private static function render_bot_command_settings(): void {
		$notice_key = 'gkch_telegram_command_notice_' . get_current_user_id();
		$notice = get_transient( $notice_key );
		if ( is_array( $notice ) ) {
			delete_transient( $notice_key );
			echo '<div class="notice notice-' . esc_attr( $notice['status'] ?? 'info' ) . '"><p>' . esc_html( $notice['message'] ?? '' ) . '</p>';
			if ( ! empty( $notice['invalid_commands'] ) ) { echo '<p>' . esc_html( $notice['invalid_commands'] ) . '</p>'; }
			echo '</div>';
		}
		echo '<section id="custom-commands"><h2>' . esc_html__( 'Custom Telegram commands', 'georg-kloster-connect-hub' ) . '</h2>';
		echo '<p>' . esc_html__( 'Add a command word and the answer the bot should send. Subscribers choose the command from Telegram’s menu; the bot replies in the subscriber’s selected language. Built-in bot messages and their translations are part of the plugin.', 'georg-kloster-connect-hub' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_telegram_bot_commands_save">';
		wp_nonce_field( 'gkch_telegram_bot_commands_save' );
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Command word', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Russian menu description', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'German menu description', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Reply in Russian', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Reply in German', 'georg-kloster-connect-hub' ) . '</th><th></th></tr></thead><tbody id="gkch-telegram-command-rows">';
		$commands = self::custom_commands();
		foreach ( $commands as $index => $command ) { self::render_command_row( $index, $command ); }
		self::render_command_row( count( $commands ), array() );
		echo '</tbody></table><p><button type="button" class="button" id="gkch-add-telegram-command">' . esc_html__( 'Add another command', 'georg-kloster-connect-hub' ) . '</button></p>';
		echo '<template id="gkch-telegram-command-template">';
		self::render_command_row( '__index__', array() );
		echo '</template>';
		submit_button( __( 'Save custom commands and update Telegram menu', 'georg-kloster-connect-hub' ) );
		echo '</form></section><hr>';
	}

	private static function render_command_row( $index, array $command ): void {
		$prefix = 'bot_commands[' . $index . ']';
		echo '<tr class="gkch-telegram-command-row"><td><input class="regular-text" maxlength="32" pattern="[A-Za-z][A-Za-z0-9_]{0,31}" name="' . esc_attr( $prefix . '[command]' ) . '" value="' . esc_attr( $command['command'] ?? '' ) . '" placeholder="' . esc_attr__( 'e.g. prayer', 'georg-kloster-connect-hub' ) . '"></td>';
		foreach ( array( 'description_ru', 'description_de' ) as $key ) { echo '<td><input class="large-text" maxlength="256" name="' . esc_attr( $prefix . '[' . $key . ']' ) . '" value="' . esc_attr( $command[$key] ?? '' ) . '"></td>'; }
		foreach ( array( 'response_ru', 'response_de' ) as $key ) { echo '<td><textarea class="large-text" rows="3" name="' . esc_attr( $prefix . '[' . $key . ']' ) . '">' . esc_textarea( $command[$key] ?? '' ) . '</textarea></td>'; }
		echo '<td><button type="button" class="button-link-delete gkch-remove-telegram-command">' . esc_html__( 'Remove', 'georg-kloster-connect-hub' ) . '</button></td></tr>';
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
		global $wpdb;
		$tables = self::tables();
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['subscribers']} WHERE status='active'" );
		$ru = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['subscribers']} WHERE status='active' AND language='ru'" );
		$de = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['subscribers']} WHERE status='active' AND language='de'" );
		echo '<div class="wrap"><h1>' . esc_html__( 'Telegram bot subscribers', 'georg-kloster-connect-hub' ) . '</h1>';
		echo '<p>' . esc_html__( 'Anyone who presses Start is subscribed to news and schedule announcements. /stop removes their Chat ID from the active subscriber list. The bot sends only to users who initiated the chat.', 'georg-kloster-connect-hub' ) . '</p>';
		self::render_bot_command_settings();
		echo '<p><strong>' . esc_html__( 'Active subscribers', 'georg-kloster-connect-hub' ) . ':</strong> ' . esc_html( (string) $total ) . ' · ' . esc_html__( 'Russian', 'georg-kloster-connect-hub' ) . ': ' . esc_html( (string) $ru ) . ' · ' . esc_html__( 'German', 'georg-kloster-connect-hub' ) . ': ' . esc_html( (string) $de ) . '</p>';
		self::render_campaigns( $tables );
		self::render_subscribers( $tables );
		echo '</div>';
	}

	private static function render_campaigns( array $tables ): void {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$tables['campaigns']} ORDER BY id DESC LIMIT 25", ARRAY_A );
		echo '<h2>' . esc_html__( 'Recent broadcast status', 'georg-kloster-connect-hub' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Message', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Status', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Recipients', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Action', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			$summary = $row['sent'] . '/' . $row['total'] . ' ' . __( 'sent', 'georg-kloster-connect-hub' ) . ( (int) $row['failed'] ? '; ' . $row['failed'] . ' ' . __( 'failed', 'georg-kloster-connect-hub' ) : '' ) . ( (int) $row['skipped'] ? '; ' . $row['skipped'] . ' ' . __( 'skipped', 'georg-kloster-connect-hub' ) : '' );
			$statuses = array( 'queued' => __( 'Queued', 'georg-kloster-connect-hub' ), 'processing' => __( 'Sending', 'georg-kloster-connect-hub' ), 'partial' => __( 'Completed with issues', 'georg-kloster-connect-hub' ), 'sent' => __( 'Completed', 'georg-kloster-connect-hub' ), 'failed' => __( 'Needs attention', 'georg-kloster-connect-hub' ), 'cancelled' => __( 'Cancelled', 'georg-kloster-connect-hub' ), 'no_subscribers' => __( 'No subscribers at send time', 'georg-kloster-connect-hub' ) );
			$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['deliveries']} WHERE campaign_id=%d AND status='pending'", $row['id'] ) );
			$reason = '';
			if ( 'cron_schedule_failed' === $row['error'] ) { $reason = __( 'WordPress could not schedule this delivery. Retry it or check that WordPress Cron is running.', 'georg-kloster-connect-hub' ); }
			elseif ( 'telegram_disabled' === $row['error'] ) { $reason = __( 'Telegram sending is disabled or the connection is incomplete. Pending deliveries remain available.', 'georg-kloster-connect-hub' ); }
			elseif ( $row['error'] ) { $reason = $row['error']; }
			echo '<tr><td>' . esc_html( $row['title'] ) . '<br><small>' . esc_html( $row['created_at'] ) . '</small>' . ( $reason ? '<br>' . esc_html( $reason ) : '' ) . '</td><td>' . esc_html( $statuses[ $row['status'] ] ?? $row['status'] ) . '</td><td>' . esc_html( $summary ) . '</td><td>';
			if ( ( (int) $row['failed'] > 0 || ( 'failed' === $row['status'] && $pending > 0 ) ) && in_array( $row['status'], array( 'partial', 'failed' ), true ) ) { self::action_form( 'retry', (int) $row['id'], __( 'Retry failed recipients', 'georg-kloster-connect-hub' ) ); }
			if ( in_array( $row['status'], array( 'queued', 'processing' ), true ) ) { self::action_form( 'cancel', (int) $row['id'], __( 'Cancel', 'georg-kloster-connect-hub' ) ); }
			echo '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="4">' . esc_html__( 'No broadcasts have been sent yet.', 'georg-kloster-connect-hub' ) . '</td></tr>'; }
		echo '</tbody></table>';
	}

	private static function render_subscribers( array $tables ): void {
		global $wpdb;
		$page = max( 1, absint( $_GET['subscriber_page'] ?? 1 ) );
		$per_page = 50; $offset = ( $page - 1 ) * $per_page;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,chat_id,language,created_at FROM {$tables['subscribers']} WHERE status='active' ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['subscribers']} WHERE status='active'" );
		echo '<h2>' . esc_html__( 'Active subscribers', 'georg-kloster-connect-hub' ) . '</h2><table class="widefat striped"><thead><tr><th>Chat ID</th><th>' . esc_html__( 'Language', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Subscribed', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Action', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) { echo '<tr><td>' . esc_html( $row['chat_id'] ) . '</td><td>' . esc_html( 'de' === $row['language'] ? __( 'German', 'georg-kloster-connect-hub' ) : __( 'Russian', 'georg-kloster-connect-hub' ) ) . '</td><td>' . esc_html( $row['created_at'] ) . '</td><td>'; self::action_form( 'remove_subscriber', (int) $row['id'], __( 'Remove subscriber', 'georg-kloster-connect-hub' ) ); echo '</td></tr>'; }
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
		$id = is_scalar( $_POST['item_id'] ?? null ) ? absint( $_POST['item_id'] ) : 0;
		check_admin_referer( 'gkch_telegram_action_' . $action . '_' . $id );
		global $wpdb; $tables = self::tables();
		if ( 'remove_subscriber' === $action && $id ) {
			$chat = $wpdb->get_var( $wpdb->prepare( "SELECT chat_id FROM {$tables['subscribers']} WHERE id=%d", $id ) );
			if ( $chat ) { self::unsubscribe( (string) $chat ); }
		} elseif ( 'retry' === $action && $id ) {
			$campaign_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$tables['campaigns']} WHERE id=%d", $id ) );
			if ( in_array( $campaign_status, array( 'partial', 'failed' ), true ) ) {
				$now = gmdate( 'Y-m-d H:i:s' );
				$wpdb->query( $wpdb->prepare( "UPDATE {$tables['deliveries']} SET status='pending',attempts=0,error='',next_attempt=%s WHERE campaign_id=%d AND status='failed'", $now, $id ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$tables['deliveries']} SET next_attempt=%s WHERE campaign_id=%d AND status='pending'", $now, $id ) );
				$wpdb->update( $tables['campaigns'], array( 'status' => 'queued', 'error' => '', 'updated_at' => $now ), array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
				if ( ! self::schedule_worker( $id, time() + 1 ) ) { self::set_campaign_status( $id, 'failed', 'cron_schedule_failed' ); }
			}
		} elseif ( 'cancel' === $action && $id ) {
			self::set_campaign_status( $id, 'cancelled', '' ); self::skip_pending( $id, 'cancelled' ); wp_clear_scheduled_hook( self::WORKER, array( $id ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=gkch-telegram' ) ); exit;
	}
}
