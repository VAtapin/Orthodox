<?php
/** Synthetic data in a disposable WordPress database only. No live sends. */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
use GeorgKlosterPrihodSocial\Forms;
use GeorgKlosterPrihodSocial\Admin;
use GeorgKlosterPrihodSocial\Request_Form;
use GeorgKlosterPrihodSocial\Requests;
use GeorgKlosterPrihodSocial\Connector;
use GeorgKlosterPrihodSocial\Publications;
use GeorgKlosterPrihodSocial\Telegram_Bot;
use GeorgKlosterPrihodSocial\Hub_Settings;
use GeorgKlosterPrihodSocial\Social_Providers;

if ( ! class_exists( '\\GeorgKlosterPrihodSchedule\\Calendar_Provider' ) ) {
    $calendar_provider_fixture = new class {
        public static function request( string $path, array $query ): array {
            if ( 'month' === $path ) {
                $year = absint( $query['year'] ?? gmdate( 'Y' ) ); $month = absint( $query['month'] ?? gmdate( 'n' ) );
                $first = new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ) ); $days = array();
                for ( $index = 0, $count = (int) $first->format( 't' ); $index < $count; ++$index ) { $days[] = array( 'date' => $first->modify( '+' . $index . ' days' )->format( 'Y-m-d' ), 'vestment' => array( 'color' => 'green' ) ); }
                return array( 'days' => $days );
            }
            return array( 'day' => array(
                'date' => (string) ( $query['date'] ?? '' ),
                'events' => array(
                    array( 'title' => 'Память святого Георгия, великомученика', 'veryShortTitle' => 'Святой Георгий', 'category' => 'commemoration', 'typeCode' => 2 ),
                    array( 'title' => 'Память святого Михаила', 'veryShortTitle' => 'Святой Михаил', 'category' => 'commemoration', 'typeCode' => 2 ),
                    array( 'title' => 'Память преподобной Марии', 'veryShortTitle' => 'Преподобная Мария', 'category' => 'commemoration', 'typeCode' => 2 ),
                    array( 'title' => 'Память святителя Николая', 'veryShortTitle' => 'Святитель Николай', 'category' => 'commemoration', 'typeCode' => 2 ),
                    array( 'title' => 'Память апостола Павла', 'veryShortTitle' => 'Апостол Павел', 'category' => 'commemoration', 'typeCode' => 2 ),
                    array( 'title' => 'Память мученицы Ирины', 'veryShortTitle' => 'Мученица Ирина', 'category' => 'commemoration', 'typeCode' => 2 ),
                    array( 'title' => 'Память праведного Иоанна', 'veryShortTitle' => 'Праведный Иоанн', 'category' => 'commemoration', 'typeCode' => 2 ),
                ),
                'icons' => array( array( 'title' => 'Икона святого Георгия', 'imageUrl' => 'https://bible-desktop.com/storage/calendar-icons/' . str_repeat( 'a', 64 ) . '.jpg' ) ),
                'vestment' => array( 'color' => 'green' ),
            ) );
        }
    };
    class_alias( get_class( $calendar_provider_fixture ), 'GeorgKlosterPrihodSchedule\\Calendar_Provider' );
}
if ( ! class_exists( '\\GeorgKlosterPrihodSchedule\\Schedule' ) ) {
    $schedule_fixture = new class {
        public static function query( DateTimeImmutable $from, DateTimeImmutable $to, int $location = 0, bool $cancelled = true ): array {
            $places = array_values( array_filter( (array) ( $GLOBALS['gkch_schedule_fixture_places'] ?? array() ) ) ); $events = array();
            if ( ! $places ) { return $events; }
            for ( $day = $from; $day < $to; $day = $day->modify( '+1 day' ) ) {
                for ( $index = 1; $index <= 6; ++$index ) {
                    $place = (int) $places[( $index - 1 ) % count( $places )];
                    if ( $location && $location !== $place ) { continue; }
                    $events[] = array( 'id' => $day->format( 'Y-m-d' ) . ':' . $index, 'start' => $day->setTime( 10, 0 )->modify( '+' . $index . ' minutes' ), 'title' => 'Божественная литургия ' . $index, 'location_id' => $place, 'vestment_color' => 'auto', 'cancelled' => false );
                }
            }
            return $events;
        }
    };
    class_alias( get_class( $schedule_fixture ), 'GeorgKlosterPrihodSchedule\\Schedule' );
}

$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } };
$throws = static function ( callable $operation, string $message ) use ( $check ): void {
    $failed = false; try { $operation(); } catch ( Throwable $error ) { $failed = true; } $check( $failed, $message );
};
$admin_id = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID;
wp_set_current_user( $admin_id ); set_current_screen( 'dashboard' );
$check( '0.4.6' === GKPS_VERSION, 'Wrong Connect Hub version.' );
$check( ! is_plugin_active( 'georg-kloster-prihod-social/georg-kloster-prihod-social.php' ), 'Old development package remains active.' );
$check( 'Existing development form' === Admin::get()['title'], 'Development form settings were lost.' );
$check( '12345' === Connector::instance( 'telegram' )->settings()['target'], 'Channel settings were lost.' );
$check( null === Connector::instance( 'whatsapp' ) && ! isset( Publications::titles()['whatsapp'] ), 'WhatsApp is still registered as a Connect Hub channel.' );
$old_messages = get_posts( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'numberposts' => -1 ) );
$check( 1 === count( $old_messages ) && 'Synthetic development content' === $old_messages[0]->post_content, 'Synthetic upgrade fixture was lost.' );
$mail = array();
add_filter( 'pre_wp_mail', static function ( $result, $args ) use ( &$mail ) { $mail[] = $args; return true; }, 10, 2 );
$http = array(); $mode = 'success'; $ig_status = 'IN_PROGRESS'; $telegram_webhook_url = 'https://old-test.example/telegram-webhook'; $telegram_set_webhook = array();
$telegram_message_valid = static function ( array $call ): bool {
    $body = json_decode( $call['args']['body'] ?? '{}', true );
    $text = (string) ( $body['caption'] ?? $body['text'] ?? '' );
    $plain = wp_strip_all_tags( $text ); $encoded = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'UTF-16LE', $plain ) : false;
    $units = $encoded ? (int) ( strlen( $encoded ) / 2 ) : preg_match_all( '/./us', $plain );
    $limit = isset( $body['photo'] ) || isset( $body['video'] ) ? 1024 : 3900;
    if ( $units > $limit ) { return false; }
    if ( 'HTML' === ( $body['parse_mode'] ?? '' ) ) {
        preg_match_all( '/<([a-z][a-z0-9]*)\b[^>]*>/i', $text, $open ); preg_match_all( '/<\/([a-z][a-z0-9]*)>/i', $text, $close );
        $open_counts = array_count_values( array_map( 'strtolower', $open[1] ?? array() ) ); $close_counts = array_count_values( array_map( 'strtolower', $close[1] ?? array() ) );
        ksort( $open_counts ); ksort( $close_counts );
        if ( $open_counts !== $close_counts ) { return false; }
    }
    return true;
};
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$http, &$mode, &$ig_status, &$telegram_webhook_url, &$telegram_set_webhook ) {
    $http[] = array( 'url' => $url, 'args' => $args );
    $body = array(); $code = 200;
    if ( str_contains( $url, 'api.vk.com/method/wall.post' ) ) {
        if ( 'timeout' === $mode ) { return new WP_Error( 'timeout', 'Synthetic timeout.' ); }
        if ( 'vk_denied' === $mode ) { $body = array( 'error' => array( 'error_code' => 5 ) ); }
        elseif ( 'throttle' === $mode ) { $code = 429; }
        else { $body = array( 'response' => array( 'post_id' => 73 ) ); }
    } elseif ( str_contains( $url, 'api.ok.ru/fb.do' ) ) {
        if ( 'timeout' === $mode ) { return new WP_Error( 'timeout', 'Synthetic timeout.' ); }
        $body = '987654';
    } elseif ( str_contains( $url, '/media_publish' ) ) { $body = array( 'id' => '88001' ); }
    elseif ( str_contains( $url, '/media' ) ) { $body = array( 'id' => '77001' ); }
    elseif ( str_contains( $url, '/77001?' ) ) { $body = array( 'status_code' => $ig_status ); }
    elseif ( str_contains( $url, '/feed' ) ) { $body = array( 'id' => 'page_71' ); }
    elseif ( str_contains( $url, 'api.telegram.org' ) ) {
        $method = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ); $payload = json_decode( $args['body'] ?? '{}', true );
        if ( 'getWebhookInfo' === $method ) { $body = array( 'ok' => true, 'result' => array( 'url' => $telegram_webhook_url ) ); }
        elseif ( 'getMe' === $method ) { $body = array( 'ok' => true, 'result' => array( 'id' => 123456, 'is_bot' => true ) ); }
        elseif ( 'getChat' === $method ) { $body = array( 'ok' => true, 'result' => array( 'id' => 12345, 'type' => 'private' ) ); }
        elseif ( 'setWebhook' === $method ) { $telegram_set_webhook = $payload; $telegram_webhook_url = $payload['url'] ?? ''; $body = array( 'ok' => true, 'result' => true ); }
        else { $body = array( 'ok' => true, 'result' => array( 'message_id' => 71 ) ); }
    }
    else { return new WP_Error( 'fixture', 'External request blocked by disposable test.' ); }
    return array( 'headers' => array(), 'response' => array( 'code' => $code ), 'body' => wp_json_encode( $body ) );
}, 10, 3 );

do_action( 'admin_init' );
foreach ( array( 'ru_RU' => array( 'Коммуникации', 'Формы', 'Сообщения', 'Публикации', 'Телеграм-бот', 'Команды бота', 'Журнал', 'Настройки' ), 'de_DE' => array( 'Kommunikation', 'Formulare', 'Nachrichten', 'Veröffentlichungen', 'Telegram-Bot', 'Bot-Befehle', 'Protokoll', 'Einstellungen' ), 'en_US' => array( 'Connect Hub', 'Forms', 'Messages', 'Publications', 'Telegram bot', 'Bot commands', 'Message log', 'Settings' ) ) as $test_locale => $labels ) {
    $switched = switch_to_locale( $test_locale );
    global $menu, $submenu; $menu = array(); $submenu = array(); do_action( 'admin_menu' );
    $main = array_values( array_filter( $menu, static fn( $item ) => 'gkps' === $item[2] ) );
    $check( 1 === count( $main ) && $labels[0] === $main[0][0], 'Main menu locale: ' . $test_locale . ' ' . wp_json_encode( array( $switched, get_locale(), determine_locale(), $main ) ) );
    $check( array( 'gkps', 'gkps-requests', 'gkch-publications', 'gkch-telegram', 'gkch-telegram-commands', 'gkch-log', 'gkch-settings' ) === array_column( $submenu['gkps'], 2 ), 'Seven menu sections/order: ' . $test_locale );
    $check( array( $labels[1], $labels[2], $labels[3], $labels[4], $labels[5], $labels[6], $labels[7] ) === array_column( $submenu['gkps'], 0 ), 'Submenu locale: ' . $test_locale );
    if ( $switched ) { restore_previous_locale(); }
}

$milk = Forms::store( 0, array( 'name' => 'Milk supply', 'enabled' => true, 'title' => 'Order milk', 'intro' => 'Local dairy', 'submit' => 'Send milk order', 'topics' => "Milk\nCream", 'recipient' => 'milk@example.org' ) );
$coal = Forms::store( 0, array( 'name' => 'Coal supply', 'enabled' => true, 'title' => 'Order coal', 'submit' => 'Send coal order', 'topics' => 'Coal', 'recipient' => 'coal@example.org' ) );
$church = Forms::store( 0, array( 'name' => 'Church questions', 'enabled' => true, 'title' => 'Write a question', 'submit' => 'Send', 'topics' => 'Pilgrimage', 'recipient' => 'church@example.org' ) );
$check( 4 === count( Forms::all() ), 'Forms were not independently stored.' );
$check( 'milk@example.org' === Forms::get( $milk )['recipient'] && 'coal@example.org' === Forms::get( $coal )['recipient'], 'Form recipients were mixed.' );
ob_start(); Forms::render(); $forms_list = ob_get_clean();
$check( str_contains( $forms_list, 'wp-list-table widefat fixed striped' ) && str_contains( $forms_list, 'page-title-action' ) && str_contains( $forms_list, 'Milk supply' ) && ! str_contains( $forms_list, 'name="telegram_chat_id"' ), 'Forms screen did not render as a separate WordPress-style list.' );
$saved_get = $_GET; $_GET = array( 'action' => 'add' ); ob_start(); Forms::render_editor(); $form_editor = ob_get_clean(); $_GET = $saved_get;
$check( str_contains( $form_editor, 'name="telegram_chat_id"' ) && str_contains( $form_editor, 'name="name"' ) && ! str_contains( $form_editor, 'wp-list-table widefat fixed striped' ), 'Form add/edit screen was not separated from the forms list.' );
$check( '' === Request_Form::render( array( 'id' => 0 ) ) && '' === Request_Form::render( array( 'id' => 99999999 ) ), 'Disabled or missing form rendered.' );
$milk_html = do_shortcode( '[gkch_form id="' . $milk . '"]' );
$coal_html = do_shortcode( '[gkch_form id="' . $coal . '"]' );
$check( str_contains( $milk_html, 'Order milk' ) && str_contains( $milk_html, 'Cream' ) && ! str_contains( $milk_html, 'Order coal' ) && ! str_contains( $milk_html, 'milk@example.org' ), 'Milk form isolation/public recipient disclosure.' );
$check( str_contains( $coal_html, 'Order coal' ) && ! str_contains( $coal_html, 'Cream' ), 'Coal form topics were mixed.' );
$check( str_contains( render_block( array( 'blockName' => 'gkch/form', 'attrs' => array( 'formId' => $church ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ), 'Pilgrimage' ), 'Editor form selection did not reach the renderer.' );
$token = static function ( string $html, string $field ): string { preg_match( '/name="' . preg_quote( $field, '/' ) . '"[^>]*value="([^"]*)"/', $html, $match ); return html_entity_decode( $match[1] ?? '', ENT_QUOTES ); };
wp_set_current_user( 0 );
$milk_html = Request_Form::render( array( 'id' => $milk ) ); $coal_html = Request_Form::render( array( 'id' => $coal ) );
$input = array( '_wpnonce' => $token( $milk_html, '_wpnonce' ), 'token' => $token( $milk_html, 'token' ), 'form_id' => $milk, 'consent' => '1', 'name' => 'Synthetic visitor', 'email' => 'visitor@example.org', 'subject' => 'Milk', 'body' => 'Synthetic milk order.' );
$swapped = array_merge( $input, array( 'form_id' => $coal, '_wpnonce' => $token( $coal_html, '_wpnonce' ), 'subject' => 'Coal' ) );
$throws( static fn() => Request_Form::process_submission( $swapped, '192.0.2.1' ), 'A token was accepted for another form.' );
$request = Request_Form::process_submission( $input, '192.0.2.1' );
$check( (int) get_post_meta( $request, '_gkch_form_id', true ) === $milk && 'Milk supply' === get_post_meta( $request, '_gkch_form_name', true ), 'Source form was not recorded.' );
$check( 'milk@example.org' === $mail[0]['to'], 'Notification sent to another form recipient.' );
$throwing_notification = static function ( int $id ): void { throw new RuntimeException( 'Synthetic post-create notification failure.' ); };
add_action( 'gkps_request_created', $throwing_notification, 99 );
$request_with_notification_failure = Request_Form::process_submission( array_merge( $input, array( 'token' => $token( Request_Form::render( array( 'id' => $milk ) ), 'token' ), '_wpnonce' => wp_create_nonce( 'gkps_request_submit_' . $milk ), 'body' => 'Synthetic hook failure.' ) ), '192.0.2.1' );
remove_action( 'gkps_request_created', $throwing_notification, 99 );
$check( 'private' === get_post_status( $request_with_notification_failure ), 'A post-create notification failure turned a saved request into a failed submission.' );
$throws( static fn() => Request_Form::process_submission( $input, '192.0.2.1' ), 'A consumed token was reused.' );
$throws( static fn() => Request_Form::process_submission( array_merge( $input, array( '_wpnonce' => 'bad' ) ), '192.0.2.2' ), 'Invalid CSRF accepted.' );
$throws( static fn() => Forms::store( 0, array( 'name' => 'Unauthorized' ) ), 'Anonymous user modified forms.' );
wp_set_current_user( $admin_id );
$throws( static fn() => Forms::store( $milk, array( 'name' => 'Bad email', 'recipient' => 'invalid' ) ), 'Invalid notification email saved.' );
$check( 'Milk supply' === Forms::get( $milk )['name'], 'Failed save changed an existing form.' );
update_option( 'gkch_settings', array( 'enabled' => false ) );
$check( '' === Request_Form::render( array( 'id' => $milk ) ), 'Global switch did not disable public forms.' );
ob_start(); GeorgKlosterPrihodSocial\Request_Admin::render(); $inbox = ob_get_clean();
$check( str_contains( $inbox, 'Milk' ), 'Messages disappeared when incoming forms were disabled.' );
$check( str_contains( $inbox, 'wp-list-table widefat fixed striped' ) && str_contains( $inbox, 'Date received' ) && str_contains( $inbox, 'Sender' ) && str_contains( $inbox, 'confirm_delete' ), 'Messages list is missing its WordPress table layout or bulk-delete controls.' );
update_option( 'gkch_settings', array( 'enabled' => true ) );
$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Synthetic public post', 'post_content' => 'Synthetic public text.' ) );
$test_church = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Храм тестовый' ) );
$test_chapel = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Часовня тестовая' ) );
$test_hall = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Зал приходской' ) );
update_post_meta( $test_church, '_gkpc_details', array( 'address' => 'Kirchweg 12, 17268 Götschendorf, Deutschland' ) );
update_post_meta( $test_chapel, '_gkpc_details', array( 'address' => 'Dorfstraße 4, 17268 Milmersdorf, Deutschland' ) );
update_option( 'gkpc_profile', array( 'name' => 'Общий адрес прихода', 'address' => 'Общий адрес, который не должен заменить адрес места службы.' ) );
$GLOBALS['gkch_schedule_fixture_places'] = array( $test_church, $test_chapel, $test_hall );
$image = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Synthetic image', 'guid' => 'https://93.184.216.34/fixture.jpg', 'post_status' => 'inherit' ), '', $post );
set_post_thumbnail( $post, $image );
update_post_meta( $post, '_thumbnail_id', $image );
$check( '' !== Social_Providers::image( $post ), 'JPEG fixture unavailable.' );
foreach ( Publications::titles() as $id => $title ) {
    $settings = array_merge( Connector::instance( $id )->settings(), array( 'enabled' => true, 'token' => 'development_token', 'target' => '12345', 'api_version' => 'v25.0', 'recipient' => '49123456789', 'recipient_consent' => true, 'template' => 'development_template', 'language' => 'ru', 'application_key' => 'public123', 'application_secret' => 'secret123' ) );
    if ( 'telegram' === $id ) { $settings['token'] = '12345:development_only_token'; $settings['telegram_broadcasts'] = true; $settings['notify_schedule'] = true; }
    if ( 'vk' === $id ) { $settings['target'] = '-12345'; $settings['api_version'] = '5.199'; }
    update_option( 'gkps_connect_' . $id, $settings, false );
    $check( Connector::instance( $id )->valid( $settings ), 'Valid channel configuration rejected: ' . $id );
}
$telegram_tables = Telegram_Bot::tables();
$telegram_custom_command = array(
    array( 'type' => 'command', 'command' => 'prayer', 'description_ru' => 'Молитва', 'description_de' => 'Gebet', 'response_ru' => 'Напишите нам о своей молитвенной просьбе.', 'response_de' => 'Schreiben Sie uns Ihr Gebetsanliegen.' ),
    array( 'type' => 'text', 'trigger_ru' => 'расписание', 'trigger_de' => 'Gottesdienstplan', 'description_ru' => 'Открыть расписание', 'description_de' => 'Gottesdienstplan öffnen', 'response_ru' => 'Расписание монастыря.', 'response_de' => 'Gottesdienstplan des Klosters.' ),
);
update_option( 'gkch_telegram_custom_commands', $telegram_custom_command, false );
$active_languages_filter = static fn() => array( 'fr' => array( 'native_name' => 'Français', 'default_locale' => 'fr_FR' ), 'en' => array( 'native_name' => 'English', 'default_locale' => 'en_US' ) );
add_filter( 'wpml_active_languages', $active_languages_filter, 10, 2 );
$saved_get = $_GET; $_GET = array( 'kind' => 'custom', 'id' => '0' ); ob_start(); Telegram_Bot::render_command_editor(); $bot_command_editor = ob_get_clean(); $_GET = $saved_get;
ob_start(); Telegram_Bot::render_commands(); $bot_command_list = ob_get_clean();
$check( str_contains( $bot_command_list, 'wp-list-table widefat fixed striped' ) && str_contains( $bot_command_list, 'page-title-action' ) && ! str_contains( $bot_command_list, 'localized[' ), 'Bot commands were not separated into a WordPress-style list screen.' );
$check( str_contains( $bot_command_editor, 'localized[fr]' ) && str_contains( $bot_command_editor, 'localized[en]' ) && ! str_contains( $bot_command_editor, 'localized[ru]' ) && ! str_contains( $bot_command_editor, 'localized[de]' ) && str_contains( $bot_command_editor, 'What should the bot send?' ) && str_contains( $bot_command_editor, 'Telegram HTML' ) && str_contains( $bot_command_editor, 'WordPress page' ) && str_contains( $bot_command_editor, 'gkch-schedule-option' ) && str_contains( $bot_command_editor, 'wp-editor-area' ) && str_contains( $bot_command_editor, 'localized[fr][response]' ) && str_contains( $bot_command_editor, 'gkch-telegram-media-select' ) && str_contains( $bot_command_editor, 'localized[fr][media_ids]' ), 'Command editor missed the WordPress editor, Telegram media picker, or active WPML fields.' );
$saved_get = $_GET; $_GET = array( 'page' => 'gkch-telegram-command-edit' ); Telegram_Bot::admin_assets( 'toplevel_page_gkch-telegram-command-edit' ); $_GET = $saved_get;
$check( wp_script_is( 'gkch-telegram-bot-admin', 'enqueued' ) && wp_script_is( 'media-editor', 'enqueued' ), 'The command editor did not enqueue its editor and WordPress media scripts on the hidden admin page.' );
$check( 'fr' === Telegram_Bot::language_from_telegram( 'fr-FR' ) && isset( Telegram_Bot::menu_languages()['fr'] ), 'Bot languages did not follow active WPML language codes.' );
remove_filter( 'wpml_active_languages', $active_languages_filter, 10 );
$ru_menu = Telegram_Bot::command_descriptions( 'ru' );
$de_menu = Telegram_Bot::command_descriptions( 'de' );
$check( in_array( array( 'command' => 'prayer', 'description' => 'Молитва' ), $ru_menu, true ) && in_array( array( 'command' => 'prayer', 'description' => 'Gebet' ), $de_menu, true ), 'Custom Telegram command descriptions were not localized in RU and DE.' );
$check( array( 'start', 'stop', 'today', 'week', 'lang', 'help', 'menu' ) === array_slice( array_column( $ru_menu, 'command' ), 0, 7 ), 'Built-in bot commands were not all included in the configurable Telegram menu.' );
$telegram_translation_map = array();
$german_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Deutscher Beispielbeitrag', 'post_content' => 'Deutscher Beispieltext.' ) );
$telegram_translation_map[$post] = array( 'language' => 'ru', 'ru' => $post, 'de' => $german_post );
$telegram_translation_map[$german_post] = array( 'language' => 'de', 'ru' => $post, 'de' => $german_post );
add_filter( 'wpml_element_language_code', static function ( $language, $post_id ) use ( &$telegram_translation_map ) { return $telegram_translation_map[$post_id]['language'] ?? $language; }, 10, 2 );
add_filter( 'wpml_object_id', static function ( $translated, $post_id, $type, $return_original, $language ) use ( &$telegram_translation_map ) { return $telegram_translation_map[$post_id][$language] ?? false; }, 10, 5 );
$telegram_start = static function ( int $update_id, string $chat_id, string $language ): array { return array( 'update_id' => $update_id, 'message' => array( 'text' => '/start', 'chat' => array( 'id' => $chat_id, 'type' => 'private' ), 'from' => array( 'id' => $chat_id, 'language_code' => $language ) ) ); };
$check( Telegram_Bot::receive_update( $telegram_start( 51001, '70101', 'ru' ) ) && Telegram_Bot::receive_update( $telegram_start( 51002, '70102', 'de' ) ), 'Bot /start did not subscribe Russian and German readers.' );
$check( 2 === (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$telegram_tables['subscribers']} WHERE status='active'" ), 'Started bot users were not stored as active subscribers.' );
$before_http = count( $http );
$jobs = Publications::enqueue( $post, array_keys( Publications::titles() ) );
$check( 5 === count( $jobs ) && count( $http ) === $before_http, 'Publication sent synchronously or missing channels.' );
$check( $jobs === Publications::enqueue( $post, array_keys( Publications::titles() ) ), 'Repeated publication did not deduplicate.' );
foreach ( array( 'facebook', 'vk', 'ok' ) as $id ) {
    Connector::instance( $id )->worker( $jobs[$id] );
    $check( 'sent' === get_option( 'gkps_job_' . $id . '_' . $jobs[$id] )['status'], 'Channel did not accept a valid response: ' . $id );
    $calls = count( $http ); Connector::instance( $id )->worker( $jobs[$id] );
    $check( $calls === count( $http ), 'Sent publication was delivered twice: ' . $id );
}
$telegram_campaign_id = (int) str_replace( 'tg-broadcast-', '', $jobs['telegram'] );
$telegram_campaign = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT * FROM {$telegram_tables['campaigns']} WHERE id=%d", $telegram_campaign_id ), ARRAY_A );
$check( 2 === (int) $telegram_campaign['total'] && str_contains( $telegram_campaign['payload_ru'], 'Synthetic public post' ) && str_contains( $telegram_campaign['payload_de'], 'Deutscher Beispielbeitrag' ), 'Telegram post broadcast did not select the matching Russian/German translations.' );
Telegram_Bot::worker( $telegram_campaign_id );
$telegram_deliveries = $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( "SELECT d.status,s.chat_id,d.language FROM {$telegram_tables['deliveries']} d JOIN {$telegram_tables['subscribers']} s ON s.id=d.subscriber_id WHERE d.campaign_id=%d ORDER BY d.language", $telegram_campaign_id ), ARRAY_A );
$check( array( 'de', 'ru' ) === array_column( $telegram_deliveries, 'language' ) && array( '70102', '70101' ) === array_column( $telegram_deliveries, 'chat_id' ) && array( 'sent', 'sent' ) === array_column( $telegram_deliveries, 'status' ), 'Telegram publication did not reach each active subscriber with their language.' );
$check( 'sent' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT status FROM {$telegram_tables['campaigns']} WHERE id=%d", $telegram_campaign_id ) ), 'Telegram campaign did not record completion.' );
$telegram_update = array( 'update_id' => 51003, 'message' => array( 'text' => '/today', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $telegram_update ), 'The /today command was not accepted.' );
wp_clear_scheduled_hook( 'gkch_telegram_command', array( '70101', 'ru', 'today', 51003, '{schedule}' ) );
$today_http_start = count( $http );
Telegram_Bot::command_worker( '70101', 'ru', 'today', 51003, '{schedule}' );
$today_messages = array_values( array_filter( array_slice( $http, $today_http_start ), static fn( $call ) => str_contains( $call['url'], 'api.telegram.org' ) ) );
$today_bodies = array_map( static fn( $call ) => json_decode( $call['args']['body'] ?? '{}', true ), $today_messages );
$today_output = implode( "\n", array_map( static fn( $body ) => (string) ( $body['caption'] ?? $body['text'] ?? '' ), $today_bodies ) );
$today_photo_body = array_values( array_filter( $today_bodies, static fn( $body ) => isset( $body['photo'] ) ) )[0] ?? array();
$check( $today_photo_body && '70101' === $today_photo_body['chat_id'] && str_ends_with( (string) $today_photo_body['photo'], str_repeat( 'a', 64 ) . '.jpg' ) && 'HTML' === ( $today_photo_body['parse_mode'] ?? '' ) && str_contains( $today_output, '━━━━━━━━' ) && str_contains( $today_output, 'Икона святого Георгия' ), '/today did not send its day icon and formatted HTML message.' );
$check( str_contains( $today_output, 'Память святого Георгия, великомученика' ) && str_contains( $today_output, 'Память праведного Иоанна' ) && ! str_contains( $today_output, 'Святой Георгий' ) && str_contains( $today_output, 'Божественная литургия 6' ), '/today shortened or omitted calendar commemorations/services.' );
$check( str_contains( $today_output, '🟩 ┃' ), '/today omitted the automatic vestment-color marker.' );
$check( array_reduce( $today_messages, static fn( $valid, $call ) => $valid && $telegram_message_valid( $call ), true ), '/today split a long message past Telegram’s limits or with unbalanced HTML tags.' );
$today_template = "Рады вас видеть.\n{schedule}";
$today_options = get_option( 'gkch_telegram_system_commands', array() );
$today_options['today']['response_ru'] = $today_template;
update_option( 'gkch_telegram_system_commands', $today_options, false );
$custom_today_update = array( 'update_id' => 51013, 'message' => array( 'text' => '/today', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $custom_today_update ), 'The customized /today command was not accepted.' );
wp_clear_scheduled_hook( 'gkch_telegram_command', array( '70101', 'ru', 'today', 51013, $today_template ) );
Telegram_Bot::command_worker( '70101', 'ru', 'today', 51013, $today_template );
$custom_today_calls = array_values( array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && str_contains( (string) ( $body['caption'] ?? $body['text'] ?? '' ), 'Рады вас видеть.' ); } ) );
$custom_today_body = $custom_today_calls ? json_decode( end( $custom_today_calls )['args']['body'], true ) : array();
$custom_today_text = (string) ( $custom_today_body['caption'] ?? $custom_today_body['text'] ?? '' );
$check( str_contains( $custom_today_text, 'Расписание на сегодня' ) && ! str_contains( $custom_today_text, '{schedule}' ), '/today did not substitute its generated schedule into the configured answer template.' );
$week_update = array( 'update_id' => 51004, 'message' => array( 'text' => '/week', 'chat' => array( 'id' => '70102', 'type' => 'private' ), 'from' => array( 'id' => '70102', 'language_code' => 'de' ) ) );
$check( Telegram_Bot::receive_update( $week_update ), 'The /week command was not accepted.' );
wp_clear_scheduled_hook( 'gkch_telegram_command', array( '70102', 'de', 'week', 51004, '{schedule}' ) );
$week_http_start = count( $http );
Telegram_Bot::command_worker( '70102', 'de', 'week', 51004 );
$week_messages = array_values( array_filter( array_slice( $http, $week_http_start ), static fn( $call ) => str_contains( $call['url'], 'api.telegram.org' ) && str_ends_with( (string) wp_parse_url( $call['url'], PHP_URL_PATH ), '/sendMessage' ) ) );
$week_bodies = array_map( static fn( $call ) => json_decode( $call['args']['body'] ?? '{}', true ), $week_messages );
$week_output = implode( "\n", array_map( static fn( $body ) => (string) ( $body['text'] ?? '' ), $week_bodies ) );
$week_body = $week_bodies[0] ?? array();
$check( $week_body && '70102' === $week_body['chat_id'] && 'HTML' === ( $week_body['parse_mode'] ?? '' ) && str_contains( $week_output, 'Gottesdienste diese Woche' ) && str_contains( $week_output, '━━━━━━━━' ), '/week did not send a formatted German private response.' );
$check( str_contains( $week_output, '🟩 ┃' ) && str_contains( $week_output, 'Божественная литургия 6' ), '/week omitted vestment colors or later services.' );
$check( 1 === substr_count( $week_output, 'Храм тестовый' ) && 1 === substr_count( $week_output, 'Часовня тестовая' ) && 1 === substr_count( $week_output, 'Зал приходской' ) && 1 === substr_count( $week_output, 'Kirchweg 12, 17268 Götschendorf, Deutschland' ) && 1 === substr_count( $week_output, 'Dorfstraße 4, 17268 Milmersdorf, Deutschland' ) && 1 === substr_count( $week_output, 'Общий адрес, который не должен заменить адрес места службы.' ), '/week repeated venues, missed their own addresses, or failed to use the parish address as a fallback.' );
$check( array_reduce( array_slice( $http, $week_http_start ), static fn( $valid, $call ) => $valid && ( ! str_contains( $call['url'], 'api.telegram.org' ) || $telegram_message_valid( $call ) ), true ), '/week split a long message past Telegram’s limits or with unbalanced HTML tags.' );
$prayer_ru_update = array( 'update_id' => 51007, 'message' => array( 'text' => '/prayer', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$prayer_de_update = array( 'update_id' => 51008, 'message' => array( 'text' => '/prayer', 'chat' => array( 'id' => '70102', 'type' => 'private' ), 'from' => array( 'id' => '70102', 'language_code' => 'de' ) ) );
$check( Telegram_Bot::receive_update( $prayer_ru_update ) && Telegram_Bot::receive_update( $prayer_de_update ), 'Configured custom Telegram commands were not accepted.' );
$check( (bool) array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && '70101' === (string) ( $body['chat_id'] ?? '' ) && 'Напишите нам о своей молитвенной просьбе.' === ( $body['text'] ?? '' ); } ), 'Custom command did not send its Russian reply to the subscriber.' );
$check( (bool) array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && '70102' === (string) ( $body['chat_id'] ?? '' ) && 'Schreiben Sie uns Ihr Gebetsanliegen.' === ( $body['text'] ?? '' ); } ), 'Custom command did not send its German reply to the subscriber.' );
$text_reply_update = array( 'update_id' => 51009, 'message' => array( 'text' => '  РАСПИСАНИЕ  ', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $text_reply_update ), 'A case-insensitive exact text responder did not accept a Russian trigger.' );
$check( (bool) array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && '70101' === (string) ( $body['chat_id'] ?? '' ) && 'Расписание монастыря.' === ( $body['text'] ?? '' ); } ), 'Text autoresponder did not send the configured Russian answer.' );
$unmatched_text_update = array( 'update_id' => 51010, 'message' => array( 'text' => 'This is not a configured phrase.', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( ! Telegram_Bot::receive_update( $unmatched_text_update ), 'Unmatched free text was incorrectly consumed by the bot responder.' );
$menu_update = array( 'update_id' => 51011, 'message' => array( 'text' => '/menu', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $menu_update ), 'The /menu command was not accepted.' );
$menu_calls = array_values( array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); if ( ! str_contains( $call['url'], 'api.telegram.org') || '70101' !== (string) ( $body['chat_id'] ?? '' ) || 'sendMessage' !== basename( parse_url( $call['url'], PHP_URL_PATH ) ) ) { return false; } foreach ( $body['reply_markup']['inline_keyboard'] ?? array() as $row ) { foreach ( $row as $button ) { if ( str_starts_with( (string) ( $button['callback_data'] ?? '' ), 'reply:' ) ) { return true; } } } return false; } ) );
$menu_body = $menu_calls ? json_decode( $menu_calls[0]['args']['body'], true ) : array();
$check( ! empty( $menu_body['reply_markup']['inline_keyboard'] ), '/menu did not render interactive buttons.' );
$text_button = null;
foreach ( $menu_body['reply_markup']['inline_keyboard'] ?? array() as $button_row ) { foreach ( $button_row as $button ) { if ( 'расписание' === $button['text'] ) { $text_button = $button['callback_data']; } } }
$check( is_string( $text_button ) && str_starts_with( $text_button, 'reply:' ), 'The /menu button for the configured text responder is missing.' );
$text_button_update = array( 'update_id' => 51012, 'callback_query' => array( 'id' => 'text-button-1', 'from' => array( 'id' => '70101' ), 'data' => $text_button, 'message' => array( 'chat' => array( 'id' => '70101', 'type' => 'private' ) ) ) );
$check( Telegram_Bot::receive_update( $text_button_update ), 'The inline text-responder button was not accepted.' );
$check( (bool) array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org') && '70101' === (string) ( $body['chat_id'] ?? '' ) && 'Расписание монастыря.' === ( $body['text'] ?? '' ); } ), 'The inline responder button did not return its configured answer.' );
$impressum = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Impressum', 'post_content' => 'Legal details for visitors. <strong>Contact</strong>.' ) );
$telegram_translation_map[$impressum] = array( 'language' => 'ru', 'ru' => $impressum, 'de' => false );
$news_reply = array( 'type' => 'command', 'command' => 'latest', 'description_ru' => 'Последняя новость', 'reply_type' => 'news', 'reply_post_id' => $post );
$wrong_item_reply = array( 'type' => 'command', 'command' => 'badnews', 'description_ru' => 'Некорректная новость', 'reply_type' => 'news', 'reply_post_id' => $impressum );
$custom_reply_rows = get_option( 'gkch_telegram_custom_commands', array() );
$custom_reply_rows[] = array( 'type' => 'command', 'command' => 'impressum', 'description_ru' => 'Impressum', 'reply_type' => 'page', 'reply_post_id' => $impressum );
$custom_reply_rows[] = array( 'type' => 'command', 'command' => 'recht', 'description_de' => 'Impressum', 'reply_type' => 'page', 'reply_post_id' => $impressum );
$custom_reply_rows[] = $news_reply;
$custom_reply_rows[] = $wrong_item_reply;
$custom_reply_rows[] = array( 'type' => 'command', 'command' => 'format', 'description_ru' => 'HTML test', 'reply_type' => 'html', 'response_ru' => '<b>Bold answer</b><script>unsafe</script><div>safe text</div>' );
$media_photo = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Bot test photo', 'post_status' => 'inherit' ), false );
$media_video = wp_insert_attachment( array( 'post_mime_type' => 'video/mp4', 'post_title' => 'Bot test video', 'post_status' => 'inherit' ), false );
$media_unsupported = wp_insert_attachment( array( 'post_mime_type' => 'image/svg+xml', 'post_title' => 'Unsupported bot media', 'post_status' => 'inherit' ), false );
update_post_meta( $media_photo, '_wp_attached_file', '2026/10/bot-test.jpg' );
update_post_meta( $media_video, '_wp_attached_file', '2026/10/bot-test.mp4' );
update_post_meta( $media_unsupported, '_wp_attached_file', '2026/10/bot-test.svg' );
$custom_reply_rows[] = array( 'type' => 'command', 'command' => 'media', 'description_ru' => 'Фото и видео', 'reply_type' => 'html', 'response_ru' => '<strong>Описание</strong>', 'media_ids_ru' => implode( ',', array( $media_photo, $media_video, $media_unsupported ) ) );
$custom_reply_rows[] = array( 'type' => 'command', 'command' => 'photo', 'description_ru' => 'Фото', 'reply_type' => 'text', 'media_ids_ru' => (string) $media_photo );
update_option( 'gkch_telegram_custom_commands', $custom_reply_rows, false );
$page_reply_update = array( 'update_id' => 51014, 'message' => array( 'text' => '/impressum', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $page_reply_update ), 'A configured WordPress page reply was not accepted.' );
$page_reply = array_values( array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && '70101' === (string) ( $body['chat_id'] ?? '' ) && str_contains( (string) ( $body['text'] ?? '' ), 'Legal details for visitors.' ); } ) );
$page_reply_body = $page_reply ? json_decode( end( $page_reply )['args']['body'], true ) : array();
$check( $page_reply_body && 'HTML' === ( $page_reply_body['parse_mode'] ?? '' ) && str_contains( $page_reply_body['text'], '<b>Impressum</b>' ) && str_contains( $page_reply_body['text'], 'Legal details for visitors.' ) && str_contains( $page_reply_body['text'], '<strong>Contact</strong>' ), 'The page reply did not include formatted page content and its title.' );
$untranslated_page_update = array( 'update_id' => 51018, 'message' => array( 'text' => '/recht', 'chat' => array( 'id' => '70102', 'type' => 'private' ), 'from' => array( 'id' => '70102', 'language_code' => 'de' ) ) );
$check( Telegram_Bot::receive_update( $untranslated_page_update ) && ! array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && '70102' === (string) ( $body['chat_id'] ?? '' ) && str_contains( (string) ( $body['text'] ?? '' ), 'Legal details for visitors.' ); } ), 'A page in another language was sent when its translation was unavailable.' );
$news_reply_update = array( 'update_id' => 51016, 'message' => array( 'text' => '/latest', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $news_reply_update ), 'A configured published news post reply was not accepted.' );
$latest_reply = array_values( array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && '70101' === (string) ( $body['chat_id'] ?? '' ) && str_contains( (string) ( $body['text'] ?? '' ), 'Synthetic public post' ); } ) );
$check( $latest_reply, 'The news reply did not send the selected published post.' );
$wrong_news_update = array( 'update_id' => 51017, 'message' => array( 'text' => '/badnews', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( ! Telegram_Bot::receive_update( $wrong_news_update ), 'A page was incorrectly accepted as a news post reply.' );
$html_reply_update = array( 'update_id' => 51015, 'message' => array( 'text' => '/format', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $html_reply_update ), 'A configured HTML reply was not accepted.' );
$html_reply = array_values( array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_contains( $call['url'], 'api.telegram.org' ) && '70101' === (string) ( $body['chat_id'] ?? '' ) && 'HTML' === ( $body['parse_mode'] ?? '' ); } ) );
$html_body = $html_reply ? json_decode( end( $html_reply )['args']['body'], true ) : array();
$check( str_contains( $html_body['text'] ?? '', '<b>Bold answer</b>' ) && str_contains( $html_body['text'] ?? '', 'safe text' ) && ! str_contains( $html_body['text'] ?? '', '<script>' ) && ! str_contains( $html_body['text'] ?? '', '<div>' ), 'Telegram HTML output did not preserve supported formatting or strip unsupported tags.' );
$media_reply_update = array( 'update_id' => 51020, 'message' => array( 'text' => '/media', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $media_reply_update ), 'A command with selected image and video attachments was rejected.' );
$media_calls = array_values( array_filter( $http, static function ( $call ) { return str_ends_with( (string) wp_parse_url( $call['url'], PHP_URL_PATH ), '/sendMediaGroup' ); } ) );
$media_body = $media_calls ? json_decode( end( $media_calls )['args']['body'], true ) : array();
$check( '70101' === (string) ( $media_body['chat_id'] ?? '' ) && 2 === count( $media_body['media'] ?? array() ) && array( 'photo', 'video' ) === array_column( $media_body['media'], 'type' ) && 'HTML' === ( $media_body['media'][0]['parse_mode'] ?? '' ) && str_contains( $media_body['media'][0]['caption'] ?? '', '<strong>Описание</strong>' ), 'The Telegram media group did not contain supported, translated attachments and its formatted caption.' );
$photo_reply_update = array( 'update_id' => 51021, 'message' => array( 'text' => '/photo', 'chat' => array( 'id' => '70101', 'type' => 'private' ), 'from' => array( 'id' => '70101', 'language_code' => 'ru' ) ) );
$check( Telegram_Bot::receive_update( $photo_reply_update ), 'A media-only command was rejected.' );
$photo_calls = array_values( array_filter( $http, static function ( $call ) { return str_ends_with( (string) wp_parse_url( $call['url'], PHP_URL_PATH ), '/sendPhoto' ); } ) );
$photo_body = $photo_calls ? json_decode( end( $photo_calls )['args']['body'], true ) : array();
$check( '70101' === (string) ( $photo_body['chat_id'] ?? '' ) && str_ends_with( (string) ( $photo_body['photo'] ?? '' ), '/2026/10/bot-test.jpg' ), 'The media-only Telegram command did not send its selected photo.' );
$subscriber_languages_filter = static fn() => array( 'ru' => array( 'native_name' => 'Русский', 'default_locale' => 'ru_RU' ), 'de' => array( 'native_name' => 'Deutsch', 'default_locale' => 'de_DE' ) );
add_filter( 'wpml_active_languages', $subscriber_languages_filter, 10, 2 );
$language_update = array( 'update_id' => 51005, 'callback_query' => array( 'id' => 'callback-1', 'from' => array( 'id' => '70101' ), 'data' => 'lang:de', 'message' => array( 'chat' => array( 'id' => '70101', 'type' => 'private' ) ) ) );
$check( Telegram_Bot::receive_update( $language_update ) && 'de' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT language FROM {$telegram_tables['subscribers']} WHERE chat_id=%s", '70101' ) ), 'Bot language selection was not saved.' );
remove_filter( 'wpml_active_languages', $subscriber_languages_filter, 10 );
$stop_update = array( 'update_id' => 51006, 'message' => array( 'text' => '/stop', 'chat' => array( 'id' => '70102', 'type' => 'private' ), 'from' => array( 'id' => '70102', 'language_code' => 'de' ) ) );
$check( Telegram_Bot::receive_update( $stop_update ) && ! $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT id FROM {$telegram_tables['subscribers']} WHERE chat_id=%s", '70102' ) ), '/stop did not remove the active subscriber.' );
$service_notice = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Synthetic schedule change', 'post_content' => 'Updated service time.' ) );
do_action( 'gkpsch_schedule_changed', $service_notice );
$schedule_campaign_id = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT id FROM {$telegram_tables['campaigns']} WHERE source_type='schedule' AND source_id=%d", $service_notice ) );
$check( $schedule_campaign_id > 0, 'A published schedule change did not create a subscriber campaign.' );
Telegram_Bot::worker( $schedule_campaign_id );
$check( 'sent' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT status FROM {$telegram_tables['campaigns']} WHERE id=%d", $schedule_campaign_id ) ), 'Schedule-change notification was not delivered.' );
$schedule_message_calls = array_values( array_filter( $http, static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_ends_with( (string) wp_parse_url( $call['url'], PHP_URL_PATH ), '/sendMessage' ) && str_contains( (string) ( $body['text'] ?? '' ), 'Synthetic schedule change' ); } ) );
$check( $schedule_message_calls && 'HTML' === ( json_decode( end( $schedule_message_calls )['args']['body'], true )['parse_mode'] ?? '' ) && str_contains( json_decode( end( $schedule_message_calls )['args']['body'], true )['text'] ?? '', '<b>' ), 'Schedule-change notices were not sent as formatted HTML.' );
$telegram_settings = Connector::instance( 'telegram' )->settings();
$telegram_settings['daily_digest_enabled'] = true;
update_option( 'gkps_connect_telegram', $telegram_settings, false );
Telegram_Bot::ensure_digest();
$check( (bool) wp_next_scheduled( 'gkch_telegram_daily_digest' ), 'Daily digest was not scheduled in the WordPress time zone.' );
Telegram_Bot::daily_digest();
$digest_campaign_id = (int) $GLOBALS['wpdb']->get_var( "SELECT id FROM {$telegram_tables['campaigns']} WHERE source_type='digest' ORDER BY id DESC LIMIT 1" );
$check( $digest_campaign_id > 0, 'Daily digest did not create a subscriber campaign.' );
$digest_http_start = count( $http );
Telegram_Bot::worker( $digest_campaign_id );
$check( 'sent' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT status FROM {$telegram_tables['campaigns']} WHERE id=%d", $digest_campaign_id ) ), 'Daily digest campaign did not complete.' );
$digest_photo_calls = array_values( array_filter( array_slice( $http, $digest_http_start ), static function ( $call ) { $body = json_decode( $call['args']['body'] ?? '{}', true ); return str_ends_with( (string) wp_parse_url( $call['url'], PHP_URL_PATH ), '/sendPhoto' ) && ! empty( $body['caption'] ); } ) );
$check( 1 === count( $digest_photo_calls ) && array_reduce( $digest_photo_calls, static fn( $ok, $call ) => $ok && 'HTML' === ( json_decode( $call['args']['body'], true )['parse_mode'] ?? '' ), true ), 'Daily digest was not delivered with its day icon and formatted HTML caption to the active language subscriber.' );
ob_start(); Telegram_Bot::render(); $telegram_admin = ob_get_clean();
$check( str_contains( $telegram_admin, '70101' ) && str_contains( $telegram_admin, 'Recent broadcast status' ) && str_contains( $telegram_admin, 'Active subscribers' ) && str_contains( $telegram_admin, 'Manage bot commands and replies' ) && ! str_contains( $telegram_admin, 'bot_commands[0][command]' ), 'Telegram subscriber screen did not link to the separate command editor.' );
$message_fixture = wp_insert_post( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'post_title' => 'Synthetic outgoing log fixture', 'post_content' => 'Private message body to audit.' ) );
foreach ( array( 'telegram' => '12345' ) as $channel => $recipient ) {
    $job_id = Connector::instance( $channel )->queue( 'Synthetic outgoing message body.', '', 'request', $message_fixture, array(), $recipient );
    $check( '' !== $job_id, 'Request message was not queued for the technical journal: ' . $channel );
    Connector::instance( $channel )->worker( $job_id );
    $events = GeorgKlosterPrihodSocial\Outgoing_Log::rows( array( 'channel' => $channel ) );
    $event = array_values( array_filter( $events, static fn( $row ) => $message_fixture === (int) $row['source_id'] ) )[0] ?? array();
    $check( 'accepted' === ( $event['status'] ?? '' ) && $recipient === ( $event['recipient'] ?? '' ) && str_contains( $event['content'] ?? '', 'Synthetic outgoing message body.' ), 'Request-message journal missed actual destination, content or accepted status: ' . $channel );
}
ob_start(); GeorgKlosterPrihodSocial\Outgoing_Log::render(); $log_html = ob_get_clean();
$check( str_contains( $log_html, 'Synthetic outgoing message body.' ) && str_contains( $log_html, '49123456789' ) && ! str_contains( $log_html, 'inbox delivery is not confirmed' ) && ! str_contains( $log_html, 'почтовый ящик не подтверждена' ), 'The authorized message journal is missing sent details or still shows the removed inbox-delivery sentence.' );
GeorgKlosterPrihodSocial\Requests::delete( $message_fixture );
$check( ! GeorgKlosterPrihodSocial\Outgoing_Log::for_source( $message_fixture ), 'Deleting a private request retained its outgoing journal entries.' );
$vk_call = array_values( array_filter( $http, static fn( $r ) => str_contains( $r['url'], 'api.vk.com' ) ) )[0];
$check( '-12345' === $vk_call['args']['body']['owner_id'] && 1 === $vk_call['args']['body']['from_group'] && 32 === strlen( $vk_call['args']['body']['guid'] ), 'VK request contract.' );
$ok_call = array_values( array_filter( $http, static fn( $r ) => str_contains( $r['url'], 'api.ok.ru' ) ) )[0];
$ok_body = $ok_call['args']['body'];
$signature = 'application_key=public123attachment=' . $ok_body['attachment'] . 'format=jsongid=12345method=mediatopic.posttype=GROUP_THEME' . md5( 'development_tokensecret123' );
$check( md5( $signature ) === $ok_body['sig'] && 'development_token' === $ok_body['access_token'], 'OK signature differs from official parameter ordering.' );
Connector::instance( 'instagram' )->worker( $jobs['instagram'] );
$ig_key = 'gkps_job_instagram_' . $jobs['instagram'];
$check( 'processing' === get_option( $ig_key )['status'] && '77001' === get_option( $ig_key )['container_id'], 'Instagram container not persisted.' );
Connector::instance( 'instagram' )->worker( $jobs['instagram'] );
$check( 'processing' === get_option( $ig_key )['status'], 'Instagram processing was not rescheduled.' );
$ig_status = 'FINISHED'; Connector::instance( 'instagram' )->worker( $jobs['instagram'] );
$check( 'sent' === get_option( $ig_key )['status'] && '88001' === get_option( $ig_key )['remote_id'], 'Instagram container not published.' );
$ig_creates = array_filter( $http, static fn( $r ) => str_ends_with( $r['url'], '/media' ) );
$check( 1 === count( $ig_creates ), 'Instagram created multiple containers for the same publication.' );
$mode = 'vk_denied';
$failed_job = Connector::instance( 'vk' )->queue( 'Synthetic failure', get_permalink( $post ), 'post', $post );
Connector::instance( 'vk' )->worker( $failed_job );
$check( 'failed' === get_option( 'gkps_job_vk_' . $failed_job )['status'], 'VK API error in HTTP 200 was accepted.' );
$mode = 'timeout';
$uncertain = Connector::instance( 'ok' )->queue( 'Synthetic uncertain result', get_permalink( $post ), 'post', $post );
wp_clear_scheduled_hook( 'gkps_ok_worker', array( $uncertain ) ); Connector::instance( 'ok' )->worker( $uncertain );
$check( 'uncertain' === get_option( 'gkps_job_ok_' . $uncertain )['status'] && ! wp_next_scheduled( 'gkps_ok_worker', array( $uncertain ) ), 'OK ambiguous timeout automatically retried.' );
$throws( static fn() => Publications::change_job( 'ok', $uncertain, 'retry' ), 'Uncertain post retried without verification.' );
Publications::change_job( 'ok', $uncertain, 'retry', true );
$check( 'pending' === get_option( 'gkps_job_ok_' . $uncertain )['status'], 'Verified retry not queued.' );
Publications::change_job( 'ok', $uncertain, 'cancel' );
$check( 'cancelled' === get_option( 'gkps_job_ok_' . $uncertain )['status'], 'Publication not cancelled.' );
$check( '' === Connector::instance( 'vk' )->queue( 'Private message', '', 'request', $request ), 'Private messages could be sent to a public social network.' );
$draft = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Synthetic draft' ) );
$throws( static fn() => Publications::enqueue( $draft, array( 'vk' ) ), 'Draft publication accepted.' );
foreach ( array_keys( Publications::titles() ) as $network ) {
    $_GET['network'] = $network; ob_start(); Hub_Settings::render(); $screen = ob_get_clean();
    $check( str_contains( $screen, Publications::titles()[$network] ) && ! str_contains( $screen, 'development_token' ) && ! str_contains( $screen, 'secret123' ), 'Network screen leaked secrets or omitted provider.' );
    $check( str_contains( Connector::instance( $network )->settings_url(), 'page=gkch-settings' ) && str_contains( $screen, 'name="action" value="gkps_connect_' . $network . '"' ), 'Network moved without its save handler.' );
    $check( 6 === preg_match_all( '/<a class="nav-tab(?: nav-tab-active)?"/', $screen ), 'Settings must show General and the five remaining channel tabs.' );
    if ( 'telegram' === $network ) { $check( str_contains( $screen, 'name="telegram_full_requests"' ) && str_contains( $screen, 'value="gkps_telegram_receive"' ) && str_contains( $screen, 'previous application will stop receiving messages' ), 'Telegram reply controls or webhook takeover warning missing.' ); }
}
// Intercept redirects before exit so the real save/legacy handlers can be exercised.
$redirect = static function ( callable $action ): string {
    $url = '';
    $capture = static function ( $target ) use ( &$url ) { $url = $target; throw new RuntimeException( 'fixture_redirect' ); };
    add_filter( 'wp_redirect', $capture );
    try { $action(); } catch ( RuntimeException $error ) { if ( 'fixture_redirect' !== $error->getMessage() ) { throw $error; } }
    finally { remove_filter( 'wp_redirect', $capture ); }
    return $url;
};
$bulk_fixture = wp_insert_post( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'post_title' => 'Synthetic bulk delete fixture', 'post_content' => 'Private bulk-delete fixture.' ) );
$_POST = array( 'status' => 'new', 'form_id' => (string) $milk, 'bulk_action' => 'delete', 'request_ids' => array( (string) $bulk_fixture ), 'confirm_delete' => '0' );
$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'gkps_request_bulk_delete' ) );
$redirect( array( GeorgKlosterPrihodSocial\Request_Admin::class, 'bulk_delete' ) );
$check( (bool) get_post( $bulk_fixture ), 'Bulk deletion proceeded without explicit confirmation.' );
$_POST['confirm_delete'] = '1';
$bulk_redirect = $redirect( array( GeorgKlosterPrihodSocial\Request_Admin::class, 'bulk_delete' ) );
$check( ! get_post( $bulk_fixture ) && str_contains( $bulk_redirect, 'status=new' ) && str_contains( $bulk_redirect, 'form_id=' . $milk ), 'Authorized bulk deletion did not remove the selected message or preserve filters.' );
$_POST = $_REQUEST = array();
foreach ( array( 'telegram' ) as $provider ) {
    $connector = Connector::instance( $provider ); $saved = $connector->settings();
    $_POST = array_merge( $saved, array( 'token' => '', 'application_secret' => '' ) );
    $_REQUEST = array( '_wpnonce' => wp_create_nonce( 'gkps_connect_' . $provider ) );
    $check( $connector->settings_url() === $redirect( array( $connector, 'save' ) ) && $saved === $connector->settings(), 'Saving a moved channel lost credentials/settings or returned to the old page.' );
    $_GET = array( 'gkps_connect_error' => 'token_missing,unknown' );
    $target = $redirect( array( $connector, 'legacy_settings' ) );
    $check( $target === ( 'telegram' === $provider ? add_query_arg( 'gkps_connect_error', 'token_missing', $connector->settings_url() ) : $connector->settings_url() ), 'Old channel URL does not redirect to the Settings tab with safe error codes.' );
    $check( (bool) has_action( 'load-admin_page_gkps-connect-' . $provider, array( $connector, 'legacy_settings' ) ), 'Old page is not registered as a hidden redirect.' );
}
foreach ( array( 'en_US' => 'Shortcodes and blocks', 'ru_RU' => 'Шорткоды и блоки', 'de_DE' => 'Shortcodes und Blöcke' ) as $help_locale => $heading ) {
    switch_to_locale( $help_locale );
    $_GET = array();
    ob_start(); Hub_Settings::render(); $help = ob_get_clean();
    $check( str_contains( $help, $heading ) && str_contains( $help, '[gkch_form id=' ) && str_contains( $help, '[gkps_contact_form id=' ), 'Localized form shortcode help is missing: ' . $help_locale );
    restore_previous_locale();
}
$_POST['gkps_connect_telegram'] = array( 'enabled' => true, 'token' => '12345:development_only_token', 'target' => '12345', 'notify_requests' => true );
$telegram_settings = Connector::instance( 'telegram' )->settings(); $telegram_settings['enabled'] = true; $telegram_settings['notify_requests'] = true;
update_option( 'gkps_connect_telegram', $telegram_settings, false );
update_option( GeorgKlosterPrihodSocial\Telegram_Requests::OPTION, array_merge( GeorgKlosterPrihodSocial\Telegram_Requests::options(), array( 'enabled' => true, 'staff' => $admin_id, 'registered' => false, 'configuration' => '' ) ), false );
$telegram_webhook_url = 'https://old-test.example/telegram-webhook';
$connection = GeorgKlosterPrihodSocial\Telegram_Request_Settings::configure();
$check( true === $connection && GeorgKlosterPrihodSocial\Telegram_Requests::ready(), 'Explicit Telegram connect action did not take over a webhook from the previous site.' );
$check( rest_url( GeorgKlosterPrihodSocial\Plugin::REST_NAMESPACE . '/telegram-reply' ) === $telegram_webhook_url && false === $telegram_set_webhook['drop_pending_updates'], 'Webhook moved to this site or pending Telegram messages were preserved incorrectly.' );
$telegram_request = Connector::instance( 'telegram' )->request( array( 'kind' => 'request', 'text' => 'Complete incoming request', 'url' => 'https://example.org/wp-admin/admin.php?page=gkps-requests&request_id=1', 'reply_context' => array( 'telegram_html' => true ) ) );
$telegram_request_body = json_decode( $telegram_request['args']['body'], true );
$check( 'Complete incoming request' === $telegram_request_body['text'] && ! str_contains( $telegram_request_body['text'], 'wp-admin' ), 'Telegram request messages must not include service text or admin links.' );
$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'gkps_telegram_receive' ) );
$check( Connector::instance( 'telegram' )->settings_url() === $redirect( array( GeorgKlosterPrihodSocial\Telegram_Request_Settings::class, 'connect' ) ), 'Telegram receive/reply setup returns to the old page.' );
$_POST = $_REQUEST = array();
$_GET = array();
GeorgKlosterPrihodSocial\Blocks::register();
$editor = wp_scripts()->get_data( 'gkps-blocks', 'data' );
$check( str_contains( $editor, 'Milk supply' ) && str_contains( $editor, 'Coal supply' ) && ! str_contains( $editor, 'milk@example.org' ), 'Editor form selector leaks settings or omits forms.' );
$check( 6 <= count( Publications::history() ), 'Publication history missing.' );
require __DIR__ . '/connect-hub-automation.php';
file_put_contents( __DIR__ . '/.connect-hub-result.json', wp_json_encode( array( 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'CONNECT_HUB_OK' ) ) );
echo 'CONNECT_HUB_OK ' . $checks;
