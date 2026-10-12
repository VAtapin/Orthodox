<?php
// Requires the disposable monastery fixture; all assertions inspect real WP records.
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; };
foreach ( array( 'classic' => 4, 'hospitality' => 5, 'editorial' => 5, 'tradition' => 5, 'blue' => 5 ) as $scheme => $count ) {
    $html = prihod_theme_home_features( $scheme, array_values( $first['images'] ), 'О монастыре', true );
    $check( substr_count( $html, 'class="prihod-feature ' ) + substr_count( $html, 'class="prihod-feature"' ) === $count, 'Reference feature count: ' . $scheme );
    $check( substr_count( $html, 'prihod-feature-photo' ) === ( 'editorial' === $scheme ? $count - 1 : ( in_array( $scheme, array( 'classic', 'tradition' ), true ) ? $count : 0 ) ), 'Reference photo/icon family: ' . $scheme );
    $html = prihod_theme_home_news( $scheme, true );
    $check( substr_count( $html, 'class="prihod-news-card"' ) === ( 'hospitality' === $scheme ? 4 : 3 ), 'Reference news count: ' . $scheme );
}
$_GET['prihod_preview'] = 'blue'; WP_Theme_JSON_Resolver::clean_cached_data();
$gallery = prihod_theme_home_gallery( array_slice( array_values( $first['images'] ), 0, 5 ), true );
$check( substr_count( $gallery, '<img' ) >= 6 && 2 === substr_count( $gallery, 'data-gallery-direction=' ), 'Blue gallery uses real published imagery for six desktop cells and two working controls.' );
unset( $_GET['prihod_preview'] ); WP_Theme_JSON_Resolver::clean_cached_data();
$check( prihod_theme_resource_image( 'clergy' ) === $first['images']['clergy'], 'Question cards use the actual clergy portrait rather than cycling through arbitrary gallery photos.' );
$html = prihod_theme_home_content();
$check( str_contains( $html, get_post( $first['pages']['home'] )->post_content ? 'wp-block-post-content' : 'unexpected' ), 'Native homepage content remains accessible.' );
$_GET['prihod_date'] = '2026-10-17';
$html = Prihod\Public_View::render( 'schedule' );
$check( str_contains( $html, 'prihod-mini-month' ) && str_contains( $html, 'prihod-schedule-columns' ), 'Weekly schedule has a real mini-month and secondary column.' );
$check( str_contains( $html, 'prihod-event-description' ) && str_contains( $html, 'prihod-event-time' ), 'Real service descriptions remain accessible in compact rows.' );
$check( ! str_contains( $html, 'is-cancelled' ) && ! str_contains( $html, 'is-moved' ), 'No cancellation or transfer statuses are fabricated.' );
$_GET['prihod_view'] = 'month'; $html = Prihod\Public_View::render( 'schedule' ); unset( $_GET['prihod_view'] );
$check( str_contains( $html, 'class="prihod-month"' ) && ! str_contains( $html, 'prihod-schedule-columns' ), 'Month view retains its full-width native layout.' );
unset( $_GET['prihod_date'] );
foreach ( array( 'prayer-notes', 'donations', 'requests' ) as $key ) { $GLOBALS['post'] = get_post( $first['pages'][$key] ); $rendered = prihod_theme_focus_content( apply_filters( 'the_content', $GLOBALS['post']->post_content ) ); $check( str_contains( $rendered, 'prihod-form-guidance' ) && ! str_contains( $rendered, '[gkod_' ), 'Guidance preserves rendered provider shortcodes: ' . $key ); }
$GLOBALS['post'] = get_post( $first['pages']['about'] ); $about_html = prihod_theme_about_content( 'original' );
$check( substr_count( $about_html, '<li><strong>' ) === 4 && str_contains( $about_html, '2006' ) && str_contains( $about_html, '2013' ), 'About timeline uses all four actual dates from the unchanged monastery history.' );
foreach ( array( 'classic', 'hospitality', 'editorial', 'tradition', 'blue' ) as $scheme ) {
    $_GET['prihod_preview'] = $scheme; WP_Theme_JSON_Resolver::clean_cached_data();
    $heading = prihod_theme_heading(); $nav = prihod_theme_about_navigation(); $invitation = prihod_theme_about_invitation();
    $check( str_contains( $heading, 'prihod-about-intro' ) && str_contains( $heading, esc_url( prihod_theme_url( 'first-visit' ) ) ), 'About hero uses the actual introduction and visitor URL: ' . $scheme );
    foreach ( array( 'history', 'life', 'clergy', 'activities' ) as $anchor ) { $check( str_contains( $nav, 'href="#prihod-about-' . $anchor . '"' ) && str_contains( $about_html, 'id="prihod-about-' . $anchor . '"' ), 'Every about navigation anchor has a real target: ' . $scheme . '/' . $anchor ); }
    $check( str_contains( $invitation, esc_url( prihod_theme_url( 'contacts' ) ) ) && str_contains( $invitation, esc_url( prihod_theme_url( 'schedule' ) ) ), 'Invitation uses published contacts and schedule URLs, preserving preview: ' . $scheme );
}
unset( $_GET['prihod_preview'] ); WP_Theme_JSON_Resolver::clean_cached_data();
$check( str_contains( $about_html, 'is-history' ) && str_contains( $about_html, 'is-life' ) && str_contains( $about_html, 'prihod-about-directions' ), 'About renders two photographic regions and confirmed existing directions.' );
$about_options = get_option( 'prihod_theme_options' ); $options = prihod_theme_options();
$options['about_history_photos'] = (string) $first['images']['kitchen']; update_option( 'prihod_theme_options', $options );
$check( str_contains( prihod_theme_about_photos( 'history' ), get_the_title( $first['images']['kitchen'] ) ) && 1 === substr_count( prihod_theme_about_photos( 'history' ), '<figure>' ), 'Owner-selected photographs and authentic captions take precedence over gallery defaults.' );
$sanitized_photos = prihod_theme_sanitize( array( 'about_history_photos' => $first['images']['monastery'] . ',999999,' . $first['images']['clergy'], 'about_life_photos' => array( 'invalid' ) ) );
$check( ! str_contains( $sanitized_photos['about_history_photos'], '999999' ) && '' === $sanitized_photos['about_life_photos'], 'About photo options accept images only and reject malformed input.' );
update_option( 'prihod_theme_options', $about_options );
$original_about = $GLOBALS['post']; wp_update_post( array( 'ID' => $original_about->ID, 'post_content' => 'Owner custom about' ) ); $GLOBALS['post'] = get_post( $original_about->ID );
$check( 'original' === prihod_theme_about_content( 'original' ), 'About presentation preserves manually edited owner content.' );
$check( '' === prihod_theme_about_navigation() && '' === prihod_theme_about_invitation() && '' === prihod_theme_about_intro(), 'An owner-edited about page is not transformed or given broken generated navigation.' );
wp_update_post( array( 'ID' => $original_about->ID, 'post_content' => $original_about->post_content ) );
$GLOBALS['post'] = get_post( $first['pages']['home'] );
$_GET['prihod_preview'] = 'classic'; WP_Theme_JSON_Resolver::clean_cached_data();
$home = prihod_theme_home();
$quote_at = strpos( $home, '<blockquote' ); $description_at = strpos( $home, 'class="prihod-hero-description"' );
$check( false !== $quote_at && false !== $description_at && $quote_at < $description_at, 'Classic hero places the verse before the real profile description.' );
unset( $_GET['prihod_preview'] ); WP_Theme_JSON_Resolver::clean_cached_data();
$saved_query = $GLOBALS['wp_query'];
foreach ( $first['posts'] as $article_id ) {
    $GLOBALS['wp_query'] = new WP_Query( array( 'p' => $article_id ) );
    $GLOBALS['post'] = get_post( $article_id );
    $_GET['prihod_preview'] = 'classic';
    $section = prihod_theme_article_section();
    $expected_key = 'prihod-sermons' === get_the_category()[0]->slug ? 'sermons' : 'news';
    $check( $section['url'] === prihod_theme_preview_url( get_permalink( $first['pages'][$expected_key] ) ), 'Article navigation resolves its actual news or sermons section.' );
    $meta = prihod_theme_article_meta();
    $check( str_contains( $meta, 'data-share-url="' . esc_url( get_permalink( $article_id ) ) . '"' ) && ! str_contains( $meta, 'prihod_preview' ), 'Shared public links never contain administrative preview parameters.' );
    $related = prihod_theme_related_posts();
    $check( 2 === substr_count( $related, 'class="prihod-news-card"' ) && 2 === substr_count( $related, 'class="prihod-read-more"' ) && ! str_contains( $related, '<h3>' . esc_html( get_the_title() ) . '</h3>' ), 'Related cards use real same-category articles, exclude this post and expose reading links.' );
    $rendered = do_blocks( file_get_contents( get_template_directory() . '/templates/single.html' ) );
    $check( strpos( $rendered, '</main>' ) < strpos( $rendered, 'class="prihod-related"' ) && str_contains( $rendered, 'prihod-article-breadcrumb' ) && str_contains( $rendered, 'prihod-article-return' ), 'Related articles follow both main columns; category path and return stay inside the article.' );
}
unset( $_GET['prihod_preview'] );
$GLOBALS['wp_query'] = $saved_query;
$GLOBALS['post'] = get_post( $first['pages']['requests'] );
$question_sidebar = prihod_theme_sidebar();
$check( str_contains( $question_sidebar, 'prihod-verse' ) && ! str_contains( $question_sidebar, 'prihod-service-compact' ) && ! str_contains( $question_sidebar, __( 'Есть вопросы?', 'prihod-theme' ) ), 'Question sidebar keeps the calendar/verse composition without repeating the question invitation or service list.' );
foreach ( array( 'classic', 'hospitality', 'editorial', 'tradition', 'blue' ) as $scheme ) {
    $_GET['prihod_preview'] = $scheme; WP_Theme_JSON_Resolver::clean_cached_data();
    $heading = prihod_theme_focused_banner();
    $check( str_contains( $heading, 'prihod-request-heading' ) && str_contains( $heading, 'aria-current="page"' ) && str_contains( $heading, esc_url( prihod_theme_url( 'about' ) ) ) && '' === prihod_theme_focused_heading(), 'Question photo banner and real breadcrumb render once, preserving preview: ' . $scheme );
}
unset( $_GET['prihod_preview'] ); WP_Theme_JSON_Resolver::clean_cached_data();
$GLOBALS['post'] = get_post( $first['pages']['donations'] );
$check( '' === prihod_theme_focused_banner() && str_contains( prihod_theme_focused_heading(), 'prihod-page-heading' ), 'Other focused form pages retain their existing heading composition.' );
$GLOBALS['post'] = get_post( $first['pages']['home'] );
$saved_query = $GLOBALS['wp_query'];
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $first['pages']['home'] ) );
foreach ( array( 'classic', 'hospitality', 'editorial', 'tradition', 'blue' ) as $scheme ) {
    $_GET['prihod_preview'] = $scheme; WP_Theme_JSON_Resolver::clean_cached_data();
    $header = prihod_theme_header(); $contacts = prihod_theme_contact_panel();
    $check( str_contains( $header, 'prihod-header-support' ) === in_array( $scheme, array( 'hospitality', 'editorial' ), true ), 'Only the two reference header families gain a dedicated support button: ' . $scheme );
    if ( 'hospitality' === $scheme ) { $check( str_contains( $contacts, 'prihod-contact-quote' ) && str_contains( $header, esc_url( prihod_theme_url( 'donations' ) ) ), 'Hospitality adds a third quote region and a real donation target.' ); }
    if ( 'editorial' === $scheme ) {
        $home = prihod_theme_home();
        $check( str_contains( $contacts, 'prihod-contact-welcome' ) && str_contains( $contacts, '<img' ) && str_contains( $header, esc_url( prihod_theme_url( 'donations' ) ) ), 'Editorial adds actual welcome text/photo and a real donation target.' );
        $check( str_contains( $home, __( 'Живая Церковь', 'prihod-theme' ) ) && str_contains( $home, __( 'Фотолетопись', 'prihod-theme' ) ) && str_contains( $home, 'prihod-feature-support-symbol' ), 'Editorial restores news/gallery eyebrows and the support symbol tile.' );
    }
}
$original_options = get_option( 'prihod_theme_options' );
$custom_options = prihod_theme_options(); $custom_options['feature_donations'] = $first['images']['monastery'];
update_option( 'prihod_theme_options', $custom_options );
$features = prihod_theme_home_features( 'editorial', array_values( $first['images'] ), 'О монастыре', true );
$check( 5 === substr_count( $features, 'prihod-feature-photo' ) && ! str_contains( $features, 'prihod-feature-support-symbol' ), 'An owner-selected support photo takes precedence over the default symbolic tile.' );
$custom_options['quote_text'] = 'Owner custom quote'; $custom_options['quote_source'] = 'Owner source';
update_option( 'prihod_theme_options', $custom_options );
$check( str_contains( prihod_theme_verse(), 'Owner custom quote' ) && str_contains( prihod_theme_verse(), 'Owner source' ), 'The quote uses saved owner content.' );
$sanitized = prihod_theme_sanitize( array( 'quote_text' => '<script>bad</script>Safe text', 'quote_source' => '<b>Source</b>' ) );
$check( ! str_contains( $sanitized['quote_text'], '<script>' ) && 'Source' === $sanitized['quote_source'], 'Quote fields are sanitized as text.' );
update_option( 'prihod_theme_options', $original_options );
unset( $_GET['prihod_preview'] ); WP_Theme_JSON_Resolver::clean_cached_data();
$GLOBALS['wp_query'] = $saved_query;
require __DIR__ . '/catalog-smoke.php';
require __DIR__ . '/clergy-design-smoke.php';
require __DIR__ . '/pilgrim-design-smoke.php';
echo "DESIGN_SMOKE_OK: {$checks} checks.\n";
