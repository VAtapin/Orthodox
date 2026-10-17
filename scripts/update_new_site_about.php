<?php
/** Arrange the existing bilingual monastery pages with editable core blocks. Run with wp eval-file. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This update is limited to the test WordPress.' );
}

function gk_about_paragraph( string $text, string $class = '' ): string {
    $attribute = $class ? ' {"className":"' . $class . '"}' : '';
    $html_class = $class ? ' class="' . $class . '"' : '';
    return '<!-- wp:paragraph' . $attribute . ' --><p' . $html_class . '>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
}
function gk_about_heading( string $text, int $level = 2 ): string {
    return '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . $level . '><!-- /wp:heading -->';
}
function gk_about_group( string $class, string $content ): string {
    return '<!-- wp:group {"className":"' . $class . '"} --><div class="wp-block-group ' . $class . '">' . $content . '</div><!-- /wp:group -->';
}
function gk_about_columns( string $left, string $right ): string {
    return '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column">' . $left . '</div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column">' . $right . '</div><!-- /wp:column --></div><!-- /wp:columns -->';
}
function gk_about_button( string $label, string $url, bool $outline = false ): string {
    $style = $outline ? ' {"className":"is-style-outline"}' : '';
    $class = $outline ? ' is-style-outline' : '';
    return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button' . $style . ' --><div class="wp-block-button' . $class . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
}
function gk_about_image( int $id, string $alt ): string {
    $url = wp_get_attachment_image_url( $id, 'large' );
    if ( ! $url ) { throw new RuntimeException( 'The monastery photograph is missing.' ); }
    return '<!-- wp:image {"id":' . $id . ',"sizeSlug":"large","linkDestination":"none","lightbox":{"enabled":true}} --><figure class="wp-block-image size-large"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $id . '"/></figure><!-- /wp:image -->';
}
function gk_about_timeline( array $items ): string {
    $html = '<!-- wp:list {"ordered":true,"className":"prihod-about-timeline"} --><ol class="wp-block-list prihod-about-timeline">';
    foreach ( $items as $year => $description ) {
        $html .= '<!-- wp:list-item --><li><strong>' . esc_html( (string) $year ) . '</strong><span>' . esc_html( $description ) . '</span></li><!-- /wp:list-item -->';
    }
    return $html . '</ol><!-- /wp:list -->';
}

$targets = array(
    'ru' => array( 'id' => 23, 'slug' => 'o-monastyre', 'title' => 'О монастыре', 'old_hash' => '6cf3e90cb438cd1e6fcfd0584bbaae332f38f32ce4052c30b3df60db935d4fc5', 'history' => 31, 'abbot' => 24, 'archimandrite' => 26, 'patron' => 25, 'schedule' => 34 ),
    'de' => array( 'id' => 55, 'slug' => 'ueber-das-kloster', 'title' => 'Über das Kloster', 'old_hash' => '128e0cbba8899207ca471977b7bf9d25abc4435e3f06eaa4d679938b67c12286', 'history' => 53, 'abbot' => 44, 'archimandrite' => 54, 'patron' => 60, 'schedule' => 49 ),
);
foreach ( $targets as $lang => $target ) {
    $page = get_post( $target['id'] );
    if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || $target['slug'] !== $page->post_name || $target['title'] !== $page->post_title ) {
        throw new RuntimeException( 'Unexpected monastery page: ' . $lang );
    }
    foreach ( array( 'history', 'abbot', 'archimandrite', 'patron', 'schedule' ) as $key ) {
        if ( 'publish' !== get_post_status( $target[$key] ) ) { throw new RuntimeException( 'Missing related content: ' . $lang . '/' . $key ); }
    }
}

$copy = array(
    'ru' => array(
        'lead' => 'Среди лесов и озёр Уккермарка, в Гётшендорфе, находится Свято-Георгиевский мужской монастырь. Обитель посвящена великомученику Георгию Победоносцу и открыта для молитвы, паломников и гостей.',
        'lead_more' => 'История этого места началась задолго до основания монастыря. Сегодня старинная усадьба обрела новую жизнь.',
        'alt' => 'Храм и здания Свято-Георгиевского монастыря в Гётшендорфе',
        'history_heading' => 'От усадьбы к обители',
        'history_text' => 'Гётшендорф известен по письменным источникам с XIV века. Здесь сменялись владельцы и эпохи: рыцарское имение, господский дом, годы запустения. В начале XXI века Берлинская епархия Русской Православной Церкви взялась за восстановление усадьбы. Подробная история деревни, замка и его жителей собрана в отдельном очерке.',
        'history_button' => 'Читать историю Гётшендорфа',
        'timeline_heading' => 'Короткая хронология',
        'timeline' => array( '2006' => 'Берлинская епархия приобрела усадьбу и начала её восстановление.', '2007' => 'Основан монастырь во имя святого Георгия Победоносца.', '2011' => 'В обитель прибыли первые монахи.', '2013' => 'Освящён купольный крест главного храма.' ),
        'people_heading' => 'Люди, стоявшие у истоков',
        'arch_heading' => 'Архиепископ Феофан',
        'arch_text' => 'Первый Священноархимандрит монастыря. Его забота и духовное руководство помогли обители обрести прочное основание.',
        'arch_button' => 'О владыке Феофане',
        'abbot_heading' => 'Игумен Даниил',
        'abbot_text' => 'Наместник обители, с именем которого связаны первые труды по устройству монастырской жизни и восстановлению усадьбы.',
        'abbot_button' => 'Об игумене Данииле',
        'today_heading' => 'Обитель сегодня',
        'today_text' => 'На территории находятся храм в северорусском стиле, гостевые дома и кельи. Среди лесов и у озера монастырь остаётся местом тишины, труда и встречи. Для паломников и гостей совершается Божественная литургия по субботам и воскресеньям в 10:00.',
        'today_more' => 'Монастырь сохраняет память о прошлом Гётшендорфа и продолжает свою жизнь под покровом святого Георгия.',
        'schedule_button' => 'Посмотреть расписание',
        'patron_button' => 'О святом Георгии',
    ),
    'de' => array(
        'lead' => 'Zwischen Wäldern und Seen der Uckermark liegt in Götschendorf das St. Georgs-Männerkloster. Es ist dem heiligen Großmärtyrer Georg gewidmet und heißt Pilger und Gäste zum Gebet willkommen.',
        'lead_more' => 'Die Geschichte dieses Ortes begann lange vor der Klostergründung. Heute hat das alte Gutshaus ein neues Leben gefunden.',
        'alt' => 'Kirche und Gebäude des St. Georgs-Klosters in Götschendorf',
        'history_heading' => 'Vom Gutshof zum Kloster',
        'history_text' => 'Götschendorf ist seit dem 14. Jahrhundert schriftlich belegt. Rittergut, Herrenhaus und Jahre des Verfalls prägten den Ort. Anfang des 21. Jahrhunderts begann die Berliner Diözese der Russisch-Orthodoxen Kirche mit der Wiederherstellung des Anwesens. Ein eigener Beitrag erzählt ausführlich von der Geschichte des Dorfes und seiner Bewohner.',
        'history_button' => 'Geschichte Götschendorfs lesen',
        'timeline_heading' => 'Die wichtigsten Jahre',
        'timeline' => array( '2006' => 'Die Berliner Diözese erwarb das Anwesen und begann mit der Restaurierung.', '2007' => 'Gründung des Klosters zu Ehren des heiligen Georg.', '2011' => 'Die ersten Mönche kamen nach Götschendorf.', '2013' => 'Weihe des Kuppelkreuzes der Hauptkirche.' ),
        'people_heading' => 'Menschen der ersten Jahre',
        'arch_heading' => 'Erzbischof Theophan',
        'arch_text' => 'Der erste Archimandrit des Klosters. Seine Fürsorge und geistliche Leitung gaben der Gemeinschaft ein festes Fundament.',
        'arch_button' => 'Über Erzbischof Theophan',
        'abbot_heading' => 'Hegumen Daniil',
        'abbot_text' => 'Der Statthalter des Klosters begleitete die ersten Schritte des monastischen Lebens und die Wiederherstellung des Anwesens.',
        'abbot_button' => 'Über Hegumen Daniil',
        'today_heading' => 'Das Kloster heute',
        'today_text' => 'Zum Gelände gehören die Kirche im nordrussischen Stil, Gästehäuser und Mönchszellen. Zwischen Wald und See ist das Kloster ein Ort der Stille, der Arbeit und der Begegnung. Die Göttliche Liturgie findet samstags und sonntags um 10:00 Uhr statt.',
        'today_more' => 'Das Kloster bewahrt die Erinnerung an Götschendorfs Vergangenheit und lebt unter dem Schutz des heiligen Georg weiter.',
        'schedule_button' => 'Gottesdienstplan ansehen',
        'patron_button' => 'Über den heiligen Georg',
    ),
);

foreach ( $targets as $lang => $target ) {
    do_action( 'wpml_switch_language', $lang );
    $t = $copy[$lang];
    $lead = gk_about_columns( gk_about_paragraph( $t['lead'], 'prihod-about-lead-text' ) . gk_about_paragraph( $t['lead_more'] ), gk_about_image( 235, $t['alt'] ) );
    $history = gk_about_heading( $t['history_heading'] ) . gk_about_paragraph( $t['history_text'] ) . gk_about_button( $t['history_button'], get_permalink( $target['history'] ), true );
    $timeline = gk_about_heading( $t['timeline_heading'] ) . gk_about_timeline( $t['timeline'] );
    $arch = gk_about_group( 'prihod-about-person', gk_about_heading( $t['arch_heading'], 3 ) . gk_about_paragraph( $t['arch_text'] ) . gk_about_button( $t['arch_button'], get_permalink( $target['archimandrite'] ), true ) );
    $abbot = gk_about_group( 'prihod-about-person', gk_about_heading( $t['abbot_heading'], 3 ) . gk_about_paragraph( $t['abbot_text'] ) . gk_about_button( $t['abbot_button'], get_permalink( $target['abbot'] ), true ) );
    $people = gk_about_heading( $t['people_heading'] ) . gk_about_columns( $arch, $abbot );
    $today = gk_about_heading( $t['today_heading'] ) . gk_about_paragraph( $t['today_text'] ) . gk_about_paragraph( $t['today_more'] ) . gk_about_button( $t['schedule_button'], get_permalink( $target['schedule'] ) ) . gk_about_button( $t['patron_button'], get_permalink( $target['patron'] ), true );
    $content = gk_about_group( 'prihod-about-content', gk_about_group( 'prihod-about-lead', $lead ) . gk_about_group( 'prihod-about-history', $history ) . gk_about_group( 'prihod-about-chronology', $timeline ) . gk_about_group( 'prihod-about-people', $people ) . gk_about_group( 'prihod-about-today', $today ) );
    $blocks = parse_blocks( $content );
    if ( 1 !== count( $blocks ) || 'core/group' !== $blocks[0]['blockName'] || ! str_contains( $content, get_permalink( $target['history'] ) ) ) {
        throw new RuntimeException( 'Generated page is not a core-block layout: ' . $lang );
    }
    $page = get_post( $target['id'] );
    if ( $page->post_content === $content ) { echo 'Already updated: ' . $lang . PHP_EOL; continue; }
    if ( $target['old_hash'] !== hash( 'sha256', $page->post_content ) ) { throw new RuntimeException( 'Monastery page was edited after inspection: ' . $lang ); }
    $updated = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $content ) ), true );
    if ( is_wp_error( $updated ) ) { throw new RuntimeException( $updated->get_error_message() ); }
    echo 'Updated monastery page: ' . $lang . ' / ' . $page->ID . PHP_EOL;
}
