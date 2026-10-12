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
$wizard_meta_before = get_user_meta( get_current_user_id(), 'prihod_wizard_' . get_current_blog_id(), true );
$wizard_state = Prihod\Wizard::state();
$check( 6 === count( Prihod\Wizard::steps() ) && isset( $wizard_state['stage'] ), 'Wizard must expose six actual stages.' );
$check( $wizard_meta_before === get_user_meta( get_current_user_id(), 'prihod_wizard_' . get_current_blog_id(), true ), 'Reading the new wizard must preserve stored interview answers.' );
$check( is_readable( WP_PLUGIN_DIR . '/prihod/assets/admin-ui.css' ) && is_readable( WP_PLUGIN_DIR . '/prihod/assets/parish-symbols.svg' ), 'Shared own-screen appearance and church symbols must be installed.' );
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

$check( '1.0.0-rc.10' === PRIHOD_VERSION && in_array( wp_get_theme()->get( 'Version' ), array( '0.3.17', '0.3.18' ), true ), 'Authorized calendar Core and compatible installed theme must be present.' );
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
$checked_service_rows = 0;
foreach ( get_posts( array( 'post_type' => 'prihod_service', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $post ) {
    $rule = get_post_meta( $post->ID, '_prihod_service', true );
    if ( ! is_array( $rule ) || ! empty( $rule['exceptions'] ) ) { continue; }
    $date = ( new DateTimeImmutable( $rule['date'] ) )->format( 'd.m.Y' );
    if ( 'regular' === ( $rule['kind'] ?? '' ) ) { $date = ! empty( $rule['until'] ) ? sprintf( __( 'С %1$s по %2$s', 'prihod' ), $date, ( new DateTimeImmutable( $rule['until'] ) )->format( 'd.m.Y' ) ) : sprintf( __( 'С %s, без конечной даты', 'prihod' ), $date ); }
    $row = Prihod\Schedule_Admin::column_data( $post->ID );
    if ( $row['date'] !== $date || $row['time'] !== $rule['time'] || $row['timezone'] !== Prihod\Schedule::timezone( $rule['location_id'], $rule['timezone'] ?? '' ) ) { throw new RuntimeException( 'Service list does not match stored dates, times and timezone.' ); }
    ++$checked_service_rows;
}
$check( $checked_service_rows > 0, 'Published service rows must match the actual stored schedule, including recurring services.' );
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
$previous_query = $GLOBALS['wp_query']; $previous_post = $GLOBALS['post'] ?? null; $previous_get = $_GET;
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $resources['about']['id'] ) ); $GLOBALS['post'] = get_post( $resources['about']['id'] ); $_GET = array();
if ( prihod_theme_about_page() ) {
    $about = prihod_theme_about_content( '' ); $nav = prihod_theme_about_navigation();
    $check( str_contains( prihod_theme_heading(), 'prihod-about-intro' ) && str_contains( prihod_theme_heading(), esc_url( prihod_theme_url( 'first-visit' ) ) ), 'About heading must expose actual introduction and visitor destination.' );
    $anchors_ok = true;
    foreach ( array( 'history', 'life', 'clergy', 'activities' ) as $anchor ) { $anchors_ok = $anchors_ok && str_contains( $nav, 'href="#prihod-about-' . $anchor . '"' ) && str_contains( $about, 'id="prihod-about-' . $anchor . '"' ); }
    $check( $anchors_ok, 'About section navigation must have real matching targets.' );
    $check( str_contains( $about, 'is-history' ) && str_contains( $about, 'is-life' ) && substr_count( $about, '<li><strong>' ) === 4, 'About photographic compositions must retain the actual four historical milestones.' );
    $check( str_contains( $about, 'prihod-about-directions' ) && str_contains( prihod_theme_about_invitation(), esc_url( prihod_theme_url( 'contacts' ) ) ) && str_contains( prihod_theme_about_invitation(), esc_url( prihod_theme_url( 'schedule' ) ) ), 'About activities and final invitation must link to actual published pages.' );
}
$GLOBALS['wp_query'] = $previous_query; $GLOBALS['post'] = $previous_post; $_GET = $previous_get;
echo "ABOUT_LAYOUT_CHECK_OK: {$checked} total checks.\n";
$previous_query = $GLOBALS['wp_query']; $previous_post = $GLOBALS['post'] ?? null;
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $resources['requests']['id'] ) ); $GLOBALS['post'] = get_post( $resources['requests']['id'] );
$focused = do_blocks( file_get_contents( get_template_directory() . '/templates/focused.html' ) );
$check( 1 === substr_count( $focused, 'prihod-request-heading' ) && strpos( $focused, 'prihod-request-heading' ) < strpos( $focused, '<main' ) && str_contains( $focused, 'aria-current="page"' ), 'Question photo heading and breadcrumb must precede both content columns.' );
$form = Prihod\Request_Form::render();
$check( str_contains( $form, '<select name="subject" required' ) && str_contains( $form, 'value="__other"' ) && str_contains( $form, 'name="consent"' ) && str_contains( $form, 'placeholder="name@example.org"' ) && str_contains( $form, 'prihod-required-help' ), 'Native topic choices, required hints and consent must remain available.' );
$check( wp_script_is( 'prihod-request-form', 'enqueued' ) && 'Own subject' === Prihod\Request_Form::submission_data( array( 'subject' => '__other', 'subject_other' => 'Own subject' ) )['subject'], 'Own-subject enhancement and existing submission normalization must be available.' );
$GLOBALS['wp_query'] = $previous_query; $GLOBALS['post'] = $previous_post;
echo "QUESTION_FORM_CHECK_OK: {$checked} total checks.\n";
$previous_query = $GLOBALS['wp_query']; $previous_post = $GLOBALS['post'] ?? null; $previous_get = $_GET;
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $resources['news']['id'] ) ); $GLOBALS['post'] = get_post( $resources['news']['id'] ); $_GET = array();
$catalog = apply_filters( 'the_content', $GLOBALS['post']->post_content );
$check( str_contains( $catalog, 'prihod-catalog-controls' ) && str_contains( $catalog, 'prihod-catalog-filters' ) && str_contains( $catalog, 'wp-block-read-more' ) && strpos( $catalog, 'wp-block-post-date' ) < strpos( $catalog, 'wp-block-post-title' ), 'Native news catalog must expose search, real categories and reference card order.' );
$news_category = get_category_by_slug( 'prihod-news' );
$news = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'category' => $news_category->term_id ) )[0];
$_GET['prihod_catalog_search'] = $news->post_title; $catalog = apply_filters( 'the_content', $GLOBALS['post']->post_content );
$check( 1 === preg_match_all( '/<li[^>]*class="[^"]*\bwp-block-post\b/', $catalog ) && str_contains( $catalog, esc_html( $news->post_title ) ), 'Catalog search must return its actual matching published item.' );
$_GET['prihod_catalog_search'] = 'prihod-no-such-material-12345'; $catalog = apply_filters( 'the_content', $GLOBALS['post']->post_content );
$check( str_contains( $catalog, 'prihod-catalog-empty' ) && ! preg_match( '/<li[^>]*class="[^"]*\bwp-block-post\b/', $catalog ), 'Empty news search must show a resettable no-results state.' );
$GLOBALS['wp_query'] = $previous_query; $GLOBALS['post'] = $previous_post; $_GET = $previous_get;
echo "CATALOG_CHECK_OK: {$checked} total checks.\n";

$previous_query = $GLOBALS['wp_query']; $previous_post = $GLOBALS['post'] ?? null;
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $resources['clergy']['id'] ) ); $GLOBALS['post'] = get_post( $resources['clergy']['id'] );
$clergy_original = $GLOBALS['post']->post_content; $clergy = prihod_theme_clergy_content( do_blocks( $clergy_original ) );
$check( str_contains( $clergy, 'prihod-clergy-card' ) && 1 === substr_count( $clergy, '<img' ), 'Generated clergy page must present one native biography with its own portrait.' );
$check( get_post_field( 'post_content', $GLOBALS['post']->ID ) === $clergy_original && str_contains( $clergy, esc_url( prihod_theme_url( 'requests' ) ) ), 'Clergy rendering preserves original content and the real question target.' );
$GLOBALS['wp_query'] = $previous_query; $GLOBALS['post'] = $previous_post;
echo "CLERGY_DESIGN_CHECK_OK: {$checked} total checks.\n";

$previous_query = $GLOBALS['wp_query']; $previous_post = $GLOBALS['post'] ?? null; $previous_template = $GLOBALS['_wp_current_template_id'] ?? ''; $previous_template_content = $GLOBALS['_wp_current_template_content'] ?? '';
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $resources['first-visit']['id'] ) ); $GLOBALS['post'] = get_post( $resources['first-visit']['id'] ); $pilgrim_original = $GLOBALS['post']->post_content; $pilgrim_template_original = get_page_template_slug( $GLOBALS['post']->ID );
get_page_template(); $pilgrim_template = get_block_template( get_stylesheet() . '//pilgrim', 'wp_template' ); $pilgrim_html = do_blocks( $pilgrim_template->content );
$check( get_stylesheet() . '//pilgrim' === $GLOBALS['_wp_current_template_id'] && str_contains( $pilgrim_html, 'prihod-pilgrim-quick' ) && str_contains( $pilgrim_html, 'prihod-pilgrim-photos' ) && ! str_contains( $pilgrim_template->content, 'prihod-sidebar' ), 'Pilgrim page must use its wide reference composition with genuine photographs.' );
$check( str_contains( $pilgrim_html, 'id="prihod-pilgrim-groups"' ) && str_contains( $pilgrim_html, esc_url( prihod_theme_url( 'requests' ) ) ) && str_contains( $pilgrim_html, 'openstreetmap.org/export/embed.html' ), 'Visitor group agreement and the real configured road map must be available.' );
$check( get_post_field( 'post_content', $GLOBALS['post']->ID ) === $pilgrim_original && get_page_template_slug( $GLOBALS['post']->ID ) === $pilgrim_template_original, 'Pilgrim presentation must not save content or the selected template.' );
$GLOBALS['wp_query'] = $previous_query; $GLOBALS['post'] = $previous_post; $GLOBALS['_wp_current_template_id'] = $previous_template; $GLOBALS['_wp_current_template_content'] = $previous_template_content;
echo "PILGRIM_DESIGN_CHECK_OK: {$checked} total checks.\n";

$previous_query = $GLOBALS['wp_query']; $previous_post = $GLOBALS['post'] ?? null; $previous_template = $GLOBALS['_wp_current_template_id'] ?? ''; $previous_template_content = $GLOBALS['_wp_current_template_content'] ?? '';
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $resources['contacts']['id'] ) ); $GLOBALS['post'] = get_post( $resources['contacts']['id'] ); $contact_original = $GLOBALS['post']->post_content; $contact_slug = get_page_template_slug( $GLOBALS['post']->ID );
get_page_template(); $contact_template = get_block_template( get_stylesheet() . '//contacts', 'wp_template' ); $contact_html = do_blocks( $contact_template->content );
$check( get_stylesheet() . '//contacts' === $GLOBALS['_wp_current_template_id'] && strpos( $contact_html, 'prihod-contacts-primary' ) < strpos( $contact_html, 'prihod-contacts-road' ), 'Real contacts must precede detailed road instructions.' );
$check( str_contains( $contact_html, 'href="tel:+491714340444"' ) && str_contains( $contact_html, 'href="mailto:info@georg-kloster.de"' ) && str_contains( $contact_html, 'openstreetmap.org/export/embed.html' ), 'Native phone/email and existing confirmed map must be available.' );
$check( get_post_field( 'post_content', $GLOBALS['post']->ID ) === $contact_original && get_page_template_slug( $GLOBALS['post']->ID ) === $contact_slug, 'Contact presentation must preserve page data and selected template.' );
$GLOBALS['wp_query'] = $previous_query; $GLOBALS['post'] = $previous_post; $GLOBALS['_wp_current_template_id'] = $previous_template; $GLOBALS['_wp_current_template_content'] = $previous_template_content;
echo "CONTACTS_DESIGN_CHECK_OK: {$checked} total checks.\n";

ob_start(); Prihod\Dashboard::overview(); $overview_html = ob_get_clean();
$check( str_contains( $overview_html, 'prihod-site-summary' ) && str_contains( $overview_html, 'prihod-page-states' ) && str_contains( $overview_html, 'prihod-check' ), 'Owner overview must expose actual diagnostic and page states.' );
$check( str_contains( $overview_html, 'prihod-card-integrations_card' ) && str_contains( $overview_html, 'prihod-wizard' ) && str_contains( $overview_html, 'prihod-requests' ), 'Overview must retain native plugin, wizard and request navigation.' );
echo "ADMIN_OVERVIEW_CHECK_OK: {$checked} total checks.\n";

if ( '0.3.18' === wp_get_theme()->get( 'Version' ) ) {
$photo_page = get_post( absint( $resources['photos']['id'] ?? 0 ) );
$check( $photo_page instanceof WP_Post && str_contains( do_blocks( $photo_page->post_content ), 'wp-lightbox-container' ), 'Saved gallery must render the native viewer without changing its content.' );
$photo_ids = array_filter( array_map( 'absint', explode( ',', prihod_theme_options()['gallery'] ) ), 'wp_attachment_is_image' );
$check( count( $photo_ids ) > 1, 'Actual gallery must contain several valid photographs.' );
$photo = prihod_theme_photo( reset( $photo_ids ), 'large', array(), '', home_url( '/' ) );
$check( (bool) preg_match( '/<a\b[^>]*>\s*<img\b[^>]*>\s*<\/a>\s*<button\b/s', $photo ), 'Existing image destinations and native zoom buttons must remain separate.' );
$check( str_contains( prihod_theme_home_gallery( $photo_ids, false ), 'data-wp-interactive="core/gallery"' ), 'Dynamic homepage photographs must support the current native gallery navigation.' );
echo "PHOTO_VIEWER_CHECK_OK: {$checked} total checks.\n";
} else { echo "PHOTO_VIEWER_PENDING_PERMISSION: theme0.3.17 remains installed.\n"; }

$check( false === Prihod\Schedule::defaults()['day_icon'], 'Existing services retain opt-out unless their owner enables the calendar icon.' );
$check( is_callable( array( '\Orthocal_Plugin', 'request' ) ) && is_callable( array( '\Orthocal_Plugin', 'icon_items' ) ), 'Calendar features use the installed provider API and icon catalogue.' );
ob_start(); Prihod\Schedule_Calendar::editor( Prihod\Schedule::defaults() ); $calendar_editor = ob_get_clean();
$check( str_contains( $calendar_editor, 'prihod_service[day_icon]' ) && str_contains( $calendar_editor, 'data-prihod-use-feast' ), 'Service editor provides explicit feast acceptance and automatic icon checkbox.' );
$reminders = Prihod\Calendar_Reminders::options();
$check( is_bool( $reminders['enabled'] ) && ( '' === $reminders['time'] || Prihod\Occurrences::time( $reminders['time'] ) ), 'Reminder configuration is readable without enabling it or sending messages.' );
echo "CALENDAR_FEATURES_CHECK_OK: {$checked} total checks.\n";
