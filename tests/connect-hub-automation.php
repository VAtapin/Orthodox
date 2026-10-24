<?php
/** Included by connect-hub.php; uses only disposable data and intercepted HTTP. */
use GeorgKlosterPrihodSocial\Publication_Rules;
use GeorgKlosterPrihodSocial\Publication_Rules_Admin;
use GeorgKlosterPrihodSocial\Publications;
use GeorgKlosterPrihodSocial\Connector;
use GeorgKlosterPrihodSocial\Forms;
use GeorgKlosterPrihodSocial\Telegram_Bot;

$routing_form_one = Forms::sanitize( array( 'enabled' => true, 'telegram_chat_id' => '10101', 'telegram_staff' => $admin_id ) );
$routing_form_two = Forms::sanitize( array( 'enabled' => true, 'telegram_chat_id' => '20202', 'telegram_staff' => $admin_id ) );
$check( '10101' === $routing_form_one['telegram_chat_id'] && '20202' === $routing_form_two['telegram_chat_id'], 'Each form retains its independent Telegram private Chat ID.' );
$no_route_form = Forms::store( 0, array( 'name' => 'No Telegram route', 'enabled' => true ) );
$check( '' === Forms::telegram_target( $no_route_form, '' ), 'A form without a private Chat ID fell back to the old global Telegram destination.' );
try { Forms::sanitize( array( 'telegram_chat_id' => '@department' ) ); $check( false, 'A public username or channel must not be accepted for a private-reply route.' ); }
catch ( InvalidArgumentException $error ) { $check( true, 'Per-form Telegram routes require a numeric private Chat ID.' ); }
$routing_settings = Connector::instance( 'telegram' )->settings();
$routing_request = wp_insert_post( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'post_title' => 'Synthetic routing request' ) );
$routing_one_job = Connector::instance( 'telegram' )->queue( 'Synthetic request', '', 'request', $routing_request, array(), '10101' );
$routing_two_job = Connector::instance( 'telegram' )->queue( 'Synthetic request', '', 'request', $routing_request, array(), '20202' );
$routing_one = get_option( 'gkps_job_telegram_' . $routing_one_job );
$routing_two = get_option( 'gkps_job_telegram_' . $routing_two_job );
$routing_one_body = json_decode( Connector::instance( 'telegram' )->request( $routing_one )['args']['body'], true );
$routing_two_body = json_decode( Connector::instance( 'telegram' )->request( $routing_two )['args']['body'], true );
$check( '10101' === $routing_one['telegram_target'] && '20202' === $routing_two['telegram_target'] && '10101' === $routing_one_body['chat_id'] && '20202' === $routing_two_body['chat_id'], 'Queued Telegram messages use the destination configured for their own form.' );
foreach ( array( $routing_one_job, $routing_two_job ) as $routing_job ) { delete_option( 'gkps_job_telegram_' . $routing_job ); wp_clear_scheduled_hook( 'gkps_telegram_worker', array( $routing_job ) ); }
wp_delete_post( $routing_request, true );

$mode = 'success';
$check( false === apply_filters( 'gkps_connector_job_allowed', true, 'telegram', array( 'kind' => 'request', 'reply_context' => array( 'configuration' => 'stale', 'request_id' => 1 ) ) ), 'Private Telegram context validation was bypassed.' );
$check( array() === Publication_Rules::all(), 'Upgrade enabled or created automation rules implicitly.' );
$before = count( Publications::history() );
$rule_input = array( 'name' => 'Synthetic news automation', 'type' => 'new_posts', 'enabled' => true, 'channels' => array( 'telegram', 'vk' ) );
$category = wp_insert_term( 'Synthetic automation category', 'category' )['term_id'];
$news = Publication_Rules::store( 0, $rule_input + array( 'category' => $category ) );
$check( $before === count( Publications::history() ), 'Saving a new-post rule sent existing posts.' );
wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Outside selected category' ) );
$check( $before === count( Publications::history() ), 'Category filter ignored.' );
$calls = count( $http );
$new_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'New synthetic news', 'post_content' => 'News excerpt', 'post_category' => array( $category ) ) );
$check( $before + 1 === count( Publications::history() ) && $calls === count( $http ), 'First publication did not queue selected channels asynchronously.' );
$new_jobs = array_values( array_filter( Publications::history(), static fn( $j ) => ( $j['reply_context']['rule_id'] ?? 0 ) === $news ) );
$check( 1 === count( $new_jobs ) && array( 'vk' ) === array_column( $new_jobs, 'provider' ), 'Non-Telegram rule channel routing.' );
foreach ( $new_jobs as $job ) { Connector::instance( $job['provider'] )->worker( $job['id'] ); }
$check( $calls + 1 === count( $http ), 'Queued rule jobs were not delivered by workers. ' . wp_json_encode( array( 'calls' => array( $calls, count( $http ) ), 'jobs' => array_map( static fn( $j ) => get_option( 'gkps_job_' . $j['provider'] . '_' . $j['id'] ), $new_jobs ) ) ) );
$telegram_tables = Telegram_Bot::tables();
$news_campaign_id = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT id FROM {$telegram_tables['campaigns']} WHERE source_type='post' AND source_id=%d ORDER BY id DESC LIMIT 1", $new_post ) );
$check( $news_campaign_id > 0, 'New-post rule did not create a Telegram subscriber campaign.' );
Telegram_Bot::worker( $news_campaign_id );
$check( 'partial' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT status FROM {$telegram_tables['campaigns']} WHERE id=%d", $news_campaign_id ) ), 'New-post campaign did not record its untranslated German recipient as skipped.' );
wp_update_post( array( 'ID' => $new_post, 'post_content' => 'Edited after publication' ) );
wp_update_post( array( 'ID' => $new_post, 'post_status' => 'draft' ) );
wp_update_post( array( 'ID' => $new_post, 'post_status' => 'publish' ) );
$check( $before + 1 === count( Publications::history() ), 'Edit or republish produced a duplicate.' );
$scheduled_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'future', 'post_date' => wp_date( 'Y-m-d H:i:s', time() + 86400 ), 'post_title' => 'Scheduled synthetic news', 'post_category' => array( $category ) ) );
wp_set_current_user( 0 ); wp_publish_post( $scheduled_post );
$check( $before + 4 === count( Publications::history() ), 'WordPress future-post publication did not use the saved rule owner.' );
wp_set_current_user( $admin_id );

$news_rule = Publication_Rules::get( $news );
Publication_Rules::store( $news, array_merge( $news_rule, array( 'enabled' => false ) ) );
$pending = array_values( array_filter( Publications::history(), static fn( $j ) => $j['source'] === $scheduled_post && 'post' === $j['kind'] ) );
$calls = count( $http );
foreach ( $pending as $job ) { Connector::instance( $job['provider'] )->worker( $job['id'] ); }
$check( $calls === count( $http ) && 'cancelled' === get_option( 'gkps_job_' . $pending[0]['provider'] . '_' . $pending[0]['id'] )['status'], 'Pausing a rule failed to cancel pending delivery.' );
$throws( static fn() => Publications::change_job( $pending[0]['provider'], $pending[0]['id'], 'retry' ), 'Cancelled rule job could be retried.' );

update_option( 'timezone_string', 'Europe/Berlin' );
$active_rule_languages_filter = static fn() => array( 'fr' => array( 'native_name' => 'Français', 'default_locale' => 'fr_FR' ), 'en' => array( 'native_name' => 'English', 'default_locale' => 'en_US' ) );
add_filter( 'wpml_active_languages', $active_rule_languages_filter, 10, 2 );
$_GET['page'] = 'gkch-publications'; $_GET['rule_type'] = 'message';
ob_start(); Publication_Rules_Admin::render(); $translated_rule_editor = ob_get_clean();
$check( str_contains( $translated_rule_editor, 'translations[fr]' ) && str_contains( $translated_rule_editor, 'translations[en]' ) && ! str_contains( $translated_rule_editor, 'text_ru' ) && ! str_contains( $translated_rule_editor, 'text_de' ), 'Reminder editor did not follow the active WPML languages.' );
remove_filter( 'wpml_active_languages', $active_rule_languages_filter, 10 );
unset( $_GET['page'], $_GET['rule_type'] );
$message_input = array( 'name' => 'Synthetic weekly reminder', 'type' => 'message', 'enabled' => true, 'channels' => array( 'telegram', 'facebook', 'vk', 'ok', 'instagram' ), 'text' => 'Synthetic reminder without a source post', 'text_ru' => 'Русское напоминание', 'text_de' => 'Deutsche Erinnerung', 'image' => $image, 'frequency' => 'weekly', 'weekday' => 1, 'time' => '09:15' );
$reminder = Publication_Rules::store( 0, $message_input );
$rule = Publication_Rules::get( $reminder );
$check( wp_next_scheduled( Publication_Rules::HOOK, array( $reminder, $rule['revision'], $rule['next'] ) ) === $rule['next'], 'Reminder cron event missing.' );
$check( '1 09:15' === wp_date( 'w H:i', $rule['next'] ), 'Weekly day or local time ignored.' );
$next = Publication_Rules::next_time( array_merge( $rule, array( 'frequency' => 'daily', 'time' => '09:00' ) ), strtotime( '2026-03-28 08:00:00 UTC' ) );
$check( $next === strtotime( '2026-03-29 07:00:00 UTC' ), 'Daily wall-clock time drifted at the spring DST change.' );
$next = Publication_Rules::next_time( array_merge( $rule, array( 'frequency' => 'daily', 'time' => '09:00' ) ), strtotime( '2026-10-24 07:00:00 UTC' ) );
$check( $next === strtotime( '2026-10-25 08:00:00 UTC' ), 'Daily wall-clock time drifted at the autumn DST change.' );
$gap = Publication_Rules::next_time( array_merge( $rule, array( 'frequency' => 'daily', 'time' => '02:30' ) ), strtotime( '2026-03-28 23:30:00 UTC' ) );
$check( '2026-03-29 03:30' === wp_date( 'Y-m-d H:i', $gap ), 'Nonexistent spring time was not normalized forward.' );

// reCAPTCHA v3 is verified server-side, and its secret is non-autoloaded.
update_option( 'gkch_settings', array( 'enabled' => true ) );
update_option( 'gkch_settings', array( 'enabled' => true, 'recaptcha_site_key' => 'synthetic-site-key', 'recaptcha_threshold' => 0.5 ), false );
$check( 'synthetic-site-key' === GeorgKlosterPrihodSocial\Hub_Settings::get()['recaptcha_site_key'], 'WordPress settings sanitization discarded the submitted reCAPTCHA site key.' );
update_option( 'gkch_recaptcha_secret', 'synthetic-secret', false );
$check( ! isset( wp_load_alloptions()['gkch_recaptcha_secret'] ), 'reCAPTCHA secret was stored as an autoload option.' );
$check( 'synthetic-site-key' === GeorgKlosterPrihodSocial\Hub_Settings::sanitize_general( array( 'enabled' => true ) )['recaptcha_site_key'], 'Saving general settings removed the reCAPTCHA configuration.' );
ob_start(); GeorgKlosterPrihodSocial\Hub_Settings::render(); $settings_page = ob_get_clean();
$check( 1 === substr_count( $settings_page, '<form ' ) && 1 === substr_count( $settings_page, 'type="submit"' ) && str_contains( $settings_page, 'admin-post.php' ) && ! str_contains( $settings_page, 'options.php' ), 'General settings do not use one save form that returns through the settings handler.' );
$check( str_contains( $settings_page, 'reCAPTCHA is configured.' ) && ! str_contains( $settings_page, 'synthetic-secret' ), 'Settings page does not show the configuration status safely.' );
$captcha_score = 0.9;
$captcha_filter = static function ( $pre, $args, $url ) use ( &$captcha_score ) {
    if ( 'https://www.google.com/recaptcha/api/siteverify' !== $url ) { return $pre; }
    return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'success' => true, 'hostname' => wp_parse_url( home_url( '/' ), PHP_URL_HOST ), 'action' => 'gkps_request_submit', 'score' => $captcha_score ) ) );
};
add_filter( 'pre_http_request', $captcha_filter, 9, 3 );
$captcha_form = Request_Form::render( array( 'id' => $milk ) );
$check( str_contains( $captcha_form, 'g-recaptcha-response' ) && str_contains( wp_scripts()->get_data( 'gkps-request-form', 'data' ), 'synthetic-site-key' ) && wp_scripts()->query( 'gkps-recaptcha-v3', 'enqueued' ) && ! str_contains( $captcha_form, 'synthetic-secret' ), 'reCAPTCHA frontend was not added safely to the public form.' );
$captcha_submission = array_merge( $input, array( '_wpnonce' => $token( $captcha_form, '_wpnonce' ), 'token' => $token( $captcha_form, 'token' ), 'g-recaptcha-response' => 'synthetic-response-token', 'body' => 'Synthetic recaptcha valid request.' ) );
$captcha_request = Request_Form::process_submission( $captcha_submission, '192.0.2.240' );
$check( 'private' === get_post_status( $captcha_request ), 'A valid reCAPTCHA v3 submission was rejected.' );
$captcha_score = 0.1;
$low_score_form = Request_Form::render( array( 'id' => $milk ) );
$low_score_input = array_merge( $input, array( '_wpnonce' => $token( $low_score_form, '_wpnonce' ), 'token' => $token( $low_score_form, 'token' ), 'g-recaptcha-response' => 'synthetic-low-score', 'body' => 'Synthetic low score request.' ) );
$throws( static fn() => Request_Form::process_submission( $low_score_input, '192.0.2.241' ), 'A low-score reCAPTCHA submission was accepted.' );
remove_filter( 'pre_http_request', $captcha_filter, 9 );
delete_option( 'gkch_recaptcha_secret' );
update_option( 'gkch_settings', array( 'enabled' => true ) );

// Move only this fixture's due slot into the past. No machine clock changes.
$due = static function ( int $id, int $slot ): array {
    $r = Publication_Rules::get( $id );
    wp_clear_scheduled_hook( Publication_Rules::HOOK, array( $id, $r['revision'], $r['next'] ) );
    $r['next'] = $slot; update_post_meta( $id, '_gkch_rule', $r ); return $r;
};
$rule = $due( $reminder, time() - 3600 );
$before = count( Publications::history() ); $calls = count( $http );
do_action( Publication_Rules::HOOK, $reminder, $rule['revision'], $rule['next'] );
$check( $before + 4 === count( Publications::history() ) && $calls === count( $http ), 'Reminder did not use asynchronous queues for the selected channels.' );
$reminder_jobs = array_values( array_filter( Publications::history(), static fn( $j ) => 'automation' === $j['kind'] && $j['source'] === $reminder ) );
$check( 4 === count( $reminder_jobs ), 'Social reminder jobs were not queued.' );
foreach ( $reminder_jobs as $job ) {
    Connector::instance( $job['provider'] )->worker( $job['id'] );
    if ( 'instagram' === $job['provider'] ) { Connector::instance( 'instagram' )->worker( $job['id'] ); }
    $check( 'sent' === get_option( 'gkps_job_' . $job['provider'] . '_' . $job['id'] )['status'], 'Standalone reminder rejected: ' . $job['provider'] );
}
$reminder_campaign_id = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT id FROM {$telegram_tables['campaigns']} WHERE source_type='automation' AND source_id=%d ORDER BY id DESC LIMIT 1", $reminder ) );
$check( $reminder_campaign_id > 0, 'Scheduled reminder did not queue a Telegram subscriber campaign.' );
Telegram_Bot::worker( $reminder_campaign_id );
$check( 'sent' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT status FROM {$telegram_tables['campaigns']} WHERE id=%d", $reminder_campaign_id ) ), 'Bilingual Telegram reminder campaign did not complete.' );
$calls = count( $http );
do_action( Publication_Rules::HOOK, $reminder, $rule['revision'], $rule['next'] );
$check( $before + 4 === count( Publications::history() ) && $calls === count( $http ), 'Duplicate cron invocation replayed a reminder.' );
$check( Publication_Rules::get( $reminder )['next'] > time(), 'Late cron run failed to advance to a future slot.' );
$ok_call = array_values( array_filter( $http, static fn( $r ) => str_contains( $r['url'], 'api.ok.ru' ) ) );
$ok_media = json_decode( end( $ok_call )['args']['body']['attachment'], true )['media'];
$check( 1 === count( $ok_media ) && 'text' === $ok_media[0]['type'], 'Standalone OK reminder included an invalid empty link.' );
$fb_call = array_values( array_filter( $http, static fn( $r ) => str_ends_with( $r['url'], '/feed' ) ) );
$check( ! isset( json_decode( end( $fb_call )['args']['body'], true )['link'] ), 'Standalone Facebook reminder included an invalid empty link.' );

$rule2 = $due( $reminder, $rule['next'] + 60 );
Publication_Rules::run( $reminder, $rule2['revision'], $rule2['next'] );
$check( $before + 8 === count( Publications::history() ), 'Identical reminder text was deduplicated across different occurrences.' );
$vk_jobs = array_values( array_filter( Publications::history(), static fn( $j ) => 'vk' === $j['provider'] && 'automation' === $j['kind'] ) );
Connector::instance( 'vk' )->worker( $vk_jobs[0]['id'] );
$vk_calls = array_values( array_filter( $http, static fn( $r ) => str_contains( $r['url'], 'api.vk.com' ) ) );
$check( end( $vk_calls )['args']['body']['guid'] !== $vk_calls[count( $vk_calls ) - 2]['args']['body']['guid'], 'VK occurrence GUID repeated across reminders.' );

$old_revision = $rule2['revision'];
Publication_Rules::store( $reminder, array_merge( Publication_Rules::get( $reminder ), array( 'text' => 'Edited reminder', 'channels' => array( 'telegram' ) ) ) );
$calls = count( $http );
$pending_facebook = array_values( array_filter( Publications::history(), static fn( $j ) => 'facebook' === $j['provider'] && 'automation' === $j['kind'] && 'pending' === $j['status'] ) )[0];
Connector::instance( 'facebook' )->worker( $pending_facebook['id'] );
$check( count( $http ) === $calls && 'cancelled' === get_option( 'gkps_job_facebook_' . $pending_facebook['id'] )['status'], 'Edited rule sent its old payload.' );
$before = count( Publications::history() );
Publication_Rules::run( $reminder, $old_revision, $rule2['next'] );
$check( $before === count( Publications::history() ), 'Stale cron revision executed.' );

$once = Publication_Rules::store( 0, array_merge( $message_input, array( 'name' => 'Once', 'channels' => array( 'telegram' ), 'frequency' => 'once', 'date' => wp_date( 'Y-m-d', time() + 86400 ) ) ) );
$once_rule = $due( $once, time() - 1 );
// Its date is changed only in this fixture so the past occurrence is complete.
$once_rule['date'] = wp_date( 'Y-m-d', time() - 86400 ); update_post_meta( $once, '_gkch_rule', $once_rule );
Publication_Rules::run( $once, $once_rule['revision'], $once_rule['next'] );
$check( 0 === Publication_Rules::get( $once )['next'], 'One-time reminder was scheduled again.' );
$once_campaign_id = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT id FROM {$telegram_tables['campaigns']} WHERE source_type='automation' AND source_id=%d ORDER BY id DESC LIMIT 1", $once ) );
 $check( $once_campaign_id > 0, 'Telegram-only one-time reminder did not create a subscriber campaign.' );
Publication_Rules::remove( $once );
$calls = count( $http ); Telegram_Bot::worker( $once_campaign_id );
$check( null === Publication_Rules::get( $once ) && $calls === count( $http ), 'Deleted rule delivered a queued reminder.' );
$check( $before < count( Publications::history() ), 'Removing a rule removed publication history.' );

$throws( static fn() => Publication_Rules::store( 0, array_merge( $message_input, array( 'time' => '25:75' ) ) ), 'Invalid schedule time accepted.' );
$throws( static fn() => Publication_Rules::store( 0, array_merge( $message_input, array( 'frequency' => 'once', 'date' => '2026-02-30' ) ) ), 'Invalid calendar date accepted.' );
$throws( static fn() => Publication_Rules::store( 0, array_merge( $message_input, array( 'frequency' => 'once', 'date' => '2020-01-01' ) ) ), 'Past one-time reminder accepted.' );
$throws( static fn() => Publication_Rules::store( 0, array_merge( $message_input, array( 'image' => 0 ) ) ), 'Instagram reminder without a public image accepted.' );
$throws( static fn() => Publication_Rules::store( 0, array_merge( $message_input, array( 'url' => 'javascript:alert(1)' ) ) ), 'Invalid reminder link accepted.' );
$throws( static fn() => Publication_Rules::store( 0, array_merge( $message_input, array( 'channels' => array( 'unknown' ) ) ) ), 'Unknown channel accepted.' );
$settings = Connector::instance( 'telegram' )->settings(); update_option( 'gkps_connect_telegram', array_merge( $settings, array( 'enabled' => false ) ) );
$throws( static fn() => Publication_Rules::store( 0, $rule_input ), 'Enabled rule accepted a disabled channel.' );
update_option( 'gkps_connect_telegram', $settings );

$editor_id = wp_insert_user( array( 'user_login' => 'automation_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
wp_set_current_user( $editor_id );
$check( ! current_user_can( 'edit_post', $reminder ) && ! current_user_can( 'read_post', $reminder ), 'Generic WordPress capabilities exposed private rule editing.' );
$throws( static fn() => Publication_Rules::store( $news, $rule_input ), 'Editor changed global publication rules without settings permission.' );
$throws( static fn() => Publication_Rules::remove( $reminder ), 'Editor removed rules without settings permission.' );
wp_set_current_user( 0 );
$throws( static fn() => Publication_Rules::store( 0, $rule_input ), 'Anonymous user created automation.' );
$check( '' === Connector::instance( 'vk' )->queue( 'Invalid source', '', 'automation', $post ), 'Automation kind bypassed rule validation.' );
wp_set_current_user( $admin_id );

$rule = $due( $reminder, time() - 5 );
wp_get_current_user()->add_cap( 'manage_gkps_settings', false );
$before = count( Publications::history() ); Publication_Rules::run( $reminder, $rule['revision'], $rule['next'] );
$check( $before === count( Publications::history() ) && '' !== get_post_meta( $reminder, '_gkch_rule_error', true ), 'Revoked rule owner permissions were ignored.' );
wp_get_current_user()->add_cap( 'manage_gkps_settings' );
Publication_Rules::reschedule(); $rule = Publication_Rules::get( $reminder );
Publication_Rules::deactivate();
$check( ! wp_next_scheduled( Publication_Rules::HOOK, array( $reminder, $rule['revision'], $rule['next'] ) ), 'Deactivation left a reminder event.' );
Publication_Rules::ensure_scheduled();
$check( (bool) wp_next_scheduled( Publication_Rules::HOOK, array( $reminder, $rule['revision'], $rule['next'] ) ), 'Reactivation did not restore the saved rule schedule.' );

foreach ( array( 'ru_RU' => array( 'Правила автоматических публикаций', 'Запланированные сообщения и напоминания' ), 'de_DE' => array( 'Regeln für automatische Veröffentlichungen', 'Geplante Nachrichten und Erinnerungen' ), 'en_US' => array( 'Automatic publication rules', 'Scheduled messages and reminders' ) ) as $test_locale => $labels ) {
    $switched = switch_to_locale( $test_locale );
    $_GET = array( 'page' => 'gkch-publications', 'rule_type' => 'message' );
    ob_start(); Publications::render(); $screen = ob_get_clean();
    $check( str_contains( $screen, $labels[0] ) && str_contains( $screen, $labels[1] ) && ! str_contains( $screen, 'name="post_id"' ) && ! str_contains( $screen, 'value="gkch_publish"' ), 'Automation screen translations or obsolete single-post picker: ' . $test_locale );
    if ( $switched ) { restore_previous_locale(); }
}
$_GET = array( 'page' => 'gkch-publications', 'rule_type' => 'message' ); Publication_Rules_Admin::assets();
$check( wp_script_is( 'gkch-publications', 'enqueued' ) && str_contains( wp_scripts()->get_data( 'gkch-publications', 'data' ), 'gkchPublicationsMedia' ), 'Reminder media picker assets missing.' );
$_GET = array();
