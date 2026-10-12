<?php
// Disposable fixture only. No saved content is changed by the theme renderer.
if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'prihod/clergy' ) ) { Prihod\Public_View::register(); }
$clergy_saved_query = $GLOBALS['wp_query']; $clergy_saved_post = $GLOBALS['post'];
$clergy_other_fixtures = array();
foreach ( get_posts( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $fixture_person ) {
    if ( 'clergy' !== get_post_meta( $fixture_person->ID, '_prihod_monastery_record', true ) ) {
        $clergy_other_fixtures[] = $fixture_person->ID; wp_update_post( array( 'ID' => $fixture_person->ID, 'post_status' => 'draft' ) );
    }
}
$clergy_page = get_post( $first['pages']['clergy'] );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $clergy_page->ID ) ); $GLOBALS['post'] = $clergy_page;
$clergy_original = $clergy_page->post_content;
$clergy_native = do_blocks( $clergy_original ); $clergy_result = prihod_theme_clergy_content( $clergy_native );
$person = get_posts( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'numberposts' => 1 ) )[0];
$clergy_bio = trim( wp_strip_all_tags( $person->post_content ) );
$check( str_contains( $clergy_result, 'prihod-clergy-card' ) && 1 === substr_count( wp_strip_all_tags( $clergy_result ), $clergy_bio ), 'Generated duplicate biography is represented exactly once by the live clergy directory.' );
$check( 1 === substr_count( $clergy_result, '<img' ) && str_contains( $clergy_result, 'prihod-clergy-photo' ) && str_contains( $clergy_result, esc_html( $person->post_title ) ), 'The actual single priest keeps his generated portrait and native directory heading.' );
$check( str_contains( $clergy_result, 'Беседа со священником' ) && str_contains( $clergy_result, 'georg-kloster.ru/igumen-daniil-irbits/' ), 'Meeting information and the original source link remain visible.' );
$check( str_contains( $clergy_result, esc_url( prihod_theme_url( 'requests' ) ) ) && get_post_field( 'post_content', $clergy_page->ID ) === $clergy_original, 'Question navigation uses the registered page while original content is never saved.' );
$second = wp_insert_post( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'post_title' => 'Disposable second priest', 'post_content' => 'Second real fixture biography.' ) );
$two_native = do_blocks( $clergy_original );
$check( prihod_theme_clergy_content( $two_native ) === $two_native, 'A single photograph is never assigned to multiple published people.' );
wp_delete_post( $second, true );
$draft = wp_insert_post( array( 'post_type' => 'prihod_clergy', 'post_status' => 'draft', 'post_title' => 'Private clergy fixture' ) );
$check( str_contains( prihod_theme_clergy_content( $clergy_native ), 'prihod-clergy-card' ), 'Unpublished clergy do not appear in the public card or prevent the single published portrait.' );
wp_delete_post( $draft, true );
wp_update_post( array( 'ID' => $clergy_page->ID, 'post_content' => $clergy_original . '<!-- wp:paragraph --><p>Owner-written clergy text.</p><!-- /wp:paragraph -->' ) );
$GLOBALS['post'] = get_post( $clergy_page->ID ); $manual = do_blocks( $GLOBALS['post']->post_content );
$check( prihod_theme_clergy_content( $manual ) === $manual, 'Manual page changes disable automatic biography merging and remain intact.' );
wp_update_post( array( 'ID' => $clergy_page->ID, 'post_content' => $clergy_original ) ); $GLOBALS['post'] = get_post( $clergy_page->ID );
$template_saved = get_page_template_slug( $clergy_page->ID ); update_post_meta( $clergy_page->ID, '_wp_page_template', 'wide' );
$check( prihod_theme_clergy_content( $clergy_native ) === $clergy_native, 'A custom page template keeps its own composition.' );
update_post_meta( $clergy_page->ID, '_wp_page_template', $template_saved );
foreach ( $clergy_other_fixtures as $fixture_id ) { wp_update_post( array( 'ID' => $fixture_id, 'post_status' => 'publish' ) ); }
$GLOBALS['wp_query'] = $clergy_saved_query; $GLOBALS['post'] = $clergy_saved_post;
