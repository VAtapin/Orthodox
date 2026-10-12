<?php
// Requires the disposable monastery fixture; all assertions inspect real WP records.
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; };
foreach ( array( 'classic' => 4, 'hospitality' => 5, 'editorial' => 5, 'tradition' => 5, 'blue' => 5 ) as $scheme => $count ) {
    $html = prihod_theme_home_features( $scheme, array_values( $first['images'] ), 'О монастыре', true );
    $check( substr_count( $html, 'class="prihod-feature ' ) + substr_count( $html, 'class="prihod-feature"' ) === $count, 'Reference feature count: ' . $scheme );
    $check( substr_count( $html, 'prihod-feature-photo' ) === ( in_array( $scheme, array( 'classic', 'editorial', 'tradition' ), true ) ? $count : 0 ), 'Reference photo/icon family: ' . $scheme );
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
$original_about = $GLOBALS['post']; wp_update_post( array( 'ID' => $original_about->ID, 'post_content' => 'Owner custom about' ) ); $GLOBALS['post'] = get_post( $original_about->ID );
$check( 'original' === prihod_theme_about_content( 'original' ), 'About presentation preserves manually edited owner content.' );
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
$GLOBALS['post'] = get_post( $first['pages']['home'] );
echo "DESIGN_SMOKE_OK: {$checks} checks.\n";
