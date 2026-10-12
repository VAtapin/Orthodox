<?php
// Non-destructive verification of an already installed, explicitly authorized WordPress.
defined( 'ABSPATH' ) || exit;
if ( 'prihod.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { throw new RuntimeException( 'This check is restricted to the authorized WordPress domain.' ); }
$checked = 0;
$check = function ( bool $ok, string $label ) use ( &$checked ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checked; };
require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'prihod/prihod.php', 'orthocal/orthocal.php', 'georg-kloster-orthodox-donate/georg-kloster-orthodox-donate.php', 'prihod-telegram/prihod-telegram.php', 'prihod-whatsapp/prihod-whatsapp.php', 'prihod-social/prihod-social.php' ) as $plugin ) { $check( is_plugin_active( $plugin ), 'Inactive component: ' . $plugin ); }
$check( 'prihod-theme' === get_stylesheet(), 'Prihod theme must be active.' );
$check( false === apply_filters( 'pre_http_request', false, array(), 'https://example.invalid/' ), 'HTTP must not be blocked in WordPress.' );
$check( null === apply_filters( 'pre_wp_mail', null, array() ), 'Email must not be blocked in WordPress.' );
$form_tag = shortcode_exists( 'gkod_form' ) ? 'gkod_form' : 'kloster_form';
$donation_tag = shortcode_exists( 'gkod_donation' ) ? 'gkod_donation' : 'kloster_donation';
foreach ( array( 'orthocal_calendar', 'orthocal_today', $form_tag, $donation_tag ) as $tag ) { $check( shortcode_exists( $tag ), 'Missing actual shortcode: ' . $tag ); }
foreach ( array( 'prihod/profile', 'prihod/schedule', 'prihod/requests', 'prihod-theme/home' ) as $block ) { $check( WP_Block_Type_Registry::get_instance()->is_registered( $block ), 'Missing block: ' . $block ); }
$check( str_contains( do_shortcode( '[' . $form_tag . ']' ), '<form' ), 'Actual prayer form must render.' );
$check( str_contains( do_shortcode( '[' . $donation_tag . ']' ), '<form' ), 'Actual donation form must render.' );
$check( '' !== do_shortcode( '[orthocal_today]' ), 'Actual calendar must render its current state.' );
$check( str_contains( prihod_theme_home(), 'prihod-home-hero' ), 'Theme must render actual homepage.' );
$check( 4 === count( glob( get_template_directory() . '/styles/*.json' ) ), 'Five schemes required.' );
foreach ( array( 'wide', 'focused', 'catalog' ) as $name ) { $check( (bool) get_block_template( 'prihod-theme//' . $name, 'wp_template' ), 'Missing inner template: ' . $name ); }
$previous = get_current_user_id(); wp_set_current_user( get_user_by( 'login', 'prihod-owner' )->ID );
$check( current_user_can( 'manage_prihod_settings' ), 'Owner must have setup access.' );
ob_start(); prihod_theme_settings_page(); $theme_panel = ob_get_clean();
$check( 5 === substr_count( $theme_panel, 'name="prihod_theme_options[scheme]"' ) && str_contains( $theme_panel, 'prihod-option-demo' ), 'Theme management provides five schemes and demo import.' );
$check( count( prihod_theme_demo_pages() ) >= 10, 'Demo catalog includes actual parish and provider compositions.' );
ob_start(); Prihod\Wizard::render(); $wizard = ob_get_clean();
$check( str_contains( $wizard, 'prihod-wizard' ), 'Setup wizard must render.' );
ob_start(); Prihod\Dashboard::overview(); $dashboard = ob_get_clean();
$check( str_contains( $dashboard, 'prihod-plugins' ) && str_contains( $dashboard, 'prihod-wizard' ), 'Dashboard must expose plugins and setup.' );
ob_start(); Prihod\Dashboard::plugins(); $plugins_html = ob_get_clean();
$check( str_contains( $plugins_html, '1.0.2' ) && str_contains( $plugins_html, 'prihod-connect-telegram' ), 'Plugin cards show installed donation version and connector settings.' );
$check( 'ready' === ( new Prihod\Existing_Integration( 'calendar' ) )->health()['status'], 'Public calendar API must not require a legacy key.' );
wp_set_current_user( $previous );
foreach ( array( 'de_DE', 'pl_PL', 'uk' ) as $locale ) {
    load_textdomain( 'prihod-theme', get_template_directory() . '/languages/' . $locale . '.mo' );
    $check( 'Главная' !== __( 'Главная', 'prihod-theme' ), 'Missing theme locale: ' . $locale );
    unload_textdomain( 'prihod-theme' );
}
echo wp_json_encode( array( 'status' => 'PRIHOD_SERVER_OK', 'checks' => $checked, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'theme' => wp_get_theme()->get( 'Version' ) ), JSON_UNESCAPED_UNICODE ) . PHP_EOL;

$check( '1.0.0-rc.6' === PRIHOD_VERSION && '0.3.8' === wp_get_theme()->get( 'Version' ), 'Reference-design release versions must be installed.' );
prihod_theme_prepare_provider_assets();
do_shortcode( '[' . $donation_tag . ']' );
$check( str_contains( (string) wp_scripts()->get_data( 'georg-kloster-orthodox-donate', 'data' ), 'georgKlosterOrthodoxDonate' ), 'Native donation configuration must exist before the block theme prints scripts.' );
$config = array( 'example' => '[orthocal_today]', 'array' => array( 1, 2 ) );
$markup = prihod_theme_calendar_markup( '<section data-orthocal="' . esc_attr( wp_json_encode( $config ) ) . '"></section>', array( 'blockName' => 'orthocal/calendar' ) );
$tags = new WP_HTML_Tag_Processor( do_shortcode( $markup ) ); $tags->next_tag();
$check( $config === json_decode( $tags->get_attribute( 'data-orthocal' ), true ), 'Calendar parameters must survive native shortcode processing.' );
echo "REFERENCE_DESIGN_CHECK_OK: {$checked} total checks.\n";
$columns = apply_filters( 'manage_prihod_service_posts_columns', array( 'title' => 'Title', 'date' => 'Date' ) );
$check( isset( $columns['prihod_service_when'], $columns['prihod_service_place'], $columns['prihod_service_kind'], $columns['prihod_service_changes'] ) && __( 'Дата публикации', 'prihod' ) === $columns['date'], 'Administrative service list must distinguish schedule information from publication.' );
$single_dates = array();
foreach ( get_posts( array( 'post_type' => 'prihod_service', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $post ) {
    $rule = get_post_meta( $post->ID, '_prihod_service', true );
    if ( 'single' !== ( $rule['kind'] ?? '' ) || ! empty( $rule['exceptions'] ) ) { continue; }
    $row = Prihod\Schedule_Admin::column_data( $post->ID );
    if ( $row['date'] !== ( new DateTimeImmutable( $rule['date'] ) )->format( 'd.m.Y' ) || $row['time'] !== $rule['time'] || $row['timezone'] !== Prihod\Schedule::timezone( $rule['location_id'], $rule['timezone'] ?? '' ) ) { throw new RuntimeException( 'Service list does not match stored dates, times and timezone.' ); }
    $single_dates[] = $row['date'];
}
$check( count( array_unique( $single_dates ) ) >= 5, 'Existing dated liturgies must be distinguishable in the service list.' );
echo "SERVICE_LIST_CHECK_OK: {$checked} total checks.\n";
$_GET = array( 'prihod_date' => ( new DateTimeImmutable( 'now', new DateTimeZone( Prihod\Profile::get()['timezone'] ) ) )->format( 'Y-m-d' ), 'prihod_view' => 'week' );
$schedule_html = Prihod\Public_View::render( 'schedule' );
$check( str_contains( $schedule_html, 'prihod-upcoming-card' ) && str_contains( $schedule_html, 'prihod-changes-card' ), 'Schedule must have two native overview panels before its filters.' );
$check( 1 === substr_count( $schedule_html, 'aria-current="date"' ) && preg_match_all( '/<a class="[^"]*\bis-selected-week\b/', $schedule_html ) >= 1, 'Mini calendar must distinguish today and the selected week.' );
echo "SCHEDULE_OVERVIEW_CHECK_OK: {$checked} total checks.\n";
$previous_query = $GLOBALS['wp_query']; $previous_post = $GLOBALS['post'] ?? null;
$resources = get_option( 'prihod_resources', array() );
foreach ( array( 'news', 'sermons' ) as $section_key ) {
    $category = get_category_by_slug( 'prihod-' . $section_key );
    $articles = $category ? get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'category' => $category->term_id ) ) : array();
    if ( ! $articles ) { throw new RuntimeException( 'Expected published article section.' ); }
    $GLOBALS['wp_query'] = new WP_Query( array( 'p' => $articles[0]->ID ) ); $GLOBALS['post'] = $articles[0];
    $section = prihod_theme_article_section();
    $article_html = do_blocks( file_get_contents( get_template_directory() . '/templates/single.html' ) );
    $check( str_contains( $article_html, 'prihod-article-breadcrumb' ) && str_contains( $article_html, 'prihod-article-return' ) && strpos( $article_html, '</main>' ) < strpos( $article_html, 'class="prihod-related"' ) && $section['url'] === get_permalink( $resources[$section_key]['id'] ) && str_contains( prihod_theme_article_meta(), 'data-share-url="' . esc_url( get_permalink( $articles[0] ) ) . '"' ), 'Article navigation, clean sharing link and full-width related cards: ' . $section_key );
}
$GLOBALS['wp_query'] = $previous_query; $GLOBALS['post'] = $previous_post;
echo "ARTICLE_LAYOUT_CHECK_OK: {$checked} total checks.\n";
