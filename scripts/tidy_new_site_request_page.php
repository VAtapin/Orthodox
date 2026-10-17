<?php
/** Give the two contact pages a distinct form heading. Run with wp eval-file. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This update is limited to the test WordPress.' );
}

foreach ( array(
    614 => array( 'vopros-svyashchenniku', '[gkps_contact_form title="Вопрос священнику" intro="Задайте свой вопрос" submit="Отправить вопрос"]', '[gkps_contact_form title="Напишите священнику" intro="Задайте свой вопрос" submit="Отправить вопрос"]' ),
    615 => array( 'frage-an-den-priester', '[gkps_contact_form title="Frage an den Priester" intro="Stellen Sie Ihre Frage" submit="Frage senden"]', '[gkps_contact_form title="Schreiben Sie dem Priester" intro="Stellen Sie Ihre Frage" submit="Frage senden"]' ),
) as $id => $details ) {
    $page = get_post( $id );
    if ( ! $page || 'page' !== $page->post_type || $details[0] !== $page->post_name || 'publish' !== $page->post_status ) {
        throw new RuntimeException( 'Unexpected request page: ' . $id );
    }
    if ( $details[2] === $page->post_content ) {
        echo 'Already updated: ' . $id . PHP_EOL;
        continue;
    }
    if ( $details[1] !== $page->post_content ) {
        throw new RuntimeException( 'Request page has other content: ' . $id );
    }
    $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $details[2] ) ), true );
    if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
    echo 'Updated request page: ' . $id . PHP_EOL;
}
