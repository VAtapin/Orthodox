<?php
/** Correct imported service text and rules on the new WordPress installation. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This script is restricted to new.georg-kloster.ru.' );
}

$primary = get_post( 617 );
if ( ! $primary || 'gkpsch_service' !== $primary->post_type || 'Божественная литургия' !== $primary->post_title ) {
    throw new RuntimeException( 'The expected recurring service is missing.' );
}
$rule = get_post_meta( 617, '_gkpsch_service', true );
$rule['kind'] = 'regular';
$rule['date'] = '2026-10-17';
$rule['until'] = '';
$rule['time'] = '10:00';
$rule['weekdays'] = array( 6, 7 );
$rule['day_icon'] = true;
$rule['vestment_color'] = 'auto';
$rule['exceptions'] = array();
update_post_meta( 617, '_gkpsch_service', \GeorgKlosterPrihodSchedule\Schedule::validate( $rule ) );
$description = 'Божественная литургия совершается по субботам и воскресеньям в 10:00 в монастырском храме. Перед поездкой проверьте выбранную дату в расписании и при необходимости уточните время по телефону +49 171 4340444.';
if ( trim( $primary->post_content ) !== $description ) {
    wp_update_post( wp_slash( array( 'ID' => 617, 'post_content' => $description ) ) );
}
foreach ( array( 618 => 'Божественная литургия', 619 => 'Чтение акафистов' ) as $id => $title ) {
    $post = get_post( $id );
    if ( ! $post || 'gkpsch_service' !== $post->post_type || $title !== $post->post_title ) { throw new RuntimeException( "Unexpected service {$id}." ); }
    if ( 'trash' !== $post->post_status ) { wp_trash_post( $id ); }
}

$changes = array(
    34 => array(
        'Богослужебная жизнь является сердцем монастыря. Каждый день братия собирается на молитву, но особенно торжественные службы совершаются в выходные и праздничные дни. Паломники и гости всегда могут присоединиться к молитве, чтобы разделить духовную радость и благодать церковного общения.' => 'Божественная литургия совершается в монастырском храме по субботам и воскресеньям в 10:00. Паломники и гости могут присоединиться к общей молитве.',
        'Кроме этого, богослужения совершаются во все великие православные праздники согласно церковному календарю. В дни Пасхи, Рождества Христова, Богоявления, Преображения, Покрова и других торжеств службы проходят особенно торжественно. Внизу страницы размещён календарь, где можно ознакомиться с актуальным расписанием праздничных богослужений.' => '',
    ),
    49 => array(
        'Das liturgische Leben ist das Herz des Klosters. Jeden Tag versammeln sich die Brüder zum Gebet, doch besonders feierliche Gottesdienste finden an Wochenenden und Feiertagen statt. Pilger und Gäste können sich jederzeit dem Gebet anschließen, um die geistliche Freude und die Gnade der kirchlichen Gemeinschaft zu teilen.' => 'Die Göttliche Liturgie findet in der Klosterkirche samstags und sonntags um 10:00 Uhr statt. Pilger und Gäste sind herzlich eingeladen, gemeinsam zu beten.',
        'Darüber hinaus finden Gottesdienste an allen großen orthodoxen Feiertagen gemäß dem Kirchenkalender statt. An den Tagen von Ostern, Weihnachten, Epiphanie, der Verklärung des Herrn, Mariä Schutz und Fürbitte und anderen Festen finden die Gottesdienste besonders feierlich statt. Unten auf der Seite befindet sich ein Kalender, in dem Sie den aktuellen Plan der festlichen Gottesdienste einsehen können.' => '',
    ),
);
foreach ( $changes as $id => $replacements ) {
    $post = get_post( $id );
    if ( ! $post || 'page' !== $post->post_type ) { throw new RuntimeException( "Schedule page {$id} is missing." ); }
    $content = $post->post_content;
    foreach ( $replacements as $old => $new ) { $content = str_replace( $old, $new, $content ); }
    $content = preg_replace( '~\s*<li><strong>(?:Пятница|Freitag)</strong>\s*[-–]\s*15:00(?: Uhr)? (?:Чтение акафистов|Akathistos-Lesung)</li>~u', '', $content );
    $content = preg_replace( '~\s*<ul>\s*<li><strong>(?:Суббота|Samstag)</strong>.*?</ul>~su', '', $content, 1 );
    if ( 34 === $id ) {
        $intro = '<h2>Перед поездкой</h2><p>Богослужения проходят по субботам и воскресеньям в 10:00 по часовому поясу Europe/Berlin. Перед дорогой проверьте выбранную дату в расписании и при необходимости уточните время по телефону +49 171 4340444.</p>';
    } else {
        $intro = '<h2>Vor der Anreise</h2><p>Die Göttliche Liturgie findet samstags und sonntags um 10:00 Uhr in der Zeitzone Europe/Berlin statt. Bitte prüfen Sie vor der Anreise das Datum im Gottesdienstplan und fragen Sie bei Bedarf telefonisch unter +49 171 4340444 nach.</p>';
    }
    if ( false === strpos( $content, $intro ) ) {
        $content = str_replace( '[gkpsch_schedule]', $intro . "\n\n[gkpsch_schedule]", $content );
    }
    if ( $content !== $post->post_content ) { wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ) ); }
}
update_option( 'gkpsch_resources', array( 'contacts' => array( 'id' => 29 ), 'requests' => array( 'id' => 614 ) ) );
WP_CLI::success( 'One weekend service rule and RU/DE schedule text configured.' );
