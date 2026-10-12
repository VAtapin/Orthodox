<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_demo_templates( array $pages = array() ): array {
    if ( ! $pages ) {
        $pages = array(
            'home' => array( 'title' => __( 'Главная', 'prihod-theme' ), 'content' => '' ),
            'about' => array( 'title' => __( 'О приходе', 'prihod-theme' ), 'content' => '' ),
            'first-visit' => array( 'title' => __( 'Первый раз в храме', 'prihod-theme' ), 'content' => '' ),
            'news' => array( 'title' => __( 'Новости', 'prihod-theme' ), 'content' => '<!-- wp:query {"query":{"perPage":6,"postType":"post","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true} /--><!-- wp:post-excerpt /--><!-- /wp:post-template --></div><!-- /wp:query -->' ),
            'contacts' => array( 'title' => __( 'Контакты', 'prihod-theme' ), 'content' => '' ),
        );
    }
    $pages['photos'] = array( 'title' => __( 'Фотогалерея', 'prihod-theme' ), 'content' => '<!-- wp:gallery {"columns":3} --><figure class="wp-block-gallery has-nested-images columns-3 is-cropped"></figure><!-- /wp:gallery -->' );
    $texts = array(
        'home' => __( 'Приход — место общей молитвы, взаимной помощи и встречи. Добро пожаловать! Здесь можно узнать о жизни общины и подготовиться к посещению храма.', 'prihod-theme' ),
        'about' => __( 'Наша община объединяет людей вокруг богослужения и добрых дел. На этой странице расскажите об истории храма, святынях и людях, которые заботятся о приходе.', 'prihod-theme' ),
        'first-visit' => __( 'Если вы впервые приходите в храм, не стесняйтесь задавать вопросы. Добавьте часы работы, советы для первого посещения и сведения о том, к кому можно обратиться за помощью.', 'prihod-theme' ),
        'clergy' => __( 'Познакомьте посетителей со священнослужителями: добавьте фотографии, краткие биографии и время для беседы.', 'prihod-theme' ),
        'schedule' => __( 'Здесь отображается расписание вашего прихода. Добавьте богослужения в разделе «Информация → Расписание».', 'prihod-theme' ),
        'requests' => __( 'Вы можете обратиться к священнику через форму ниже. Перед публикацией страницы проверьте настройки обращений и ответственного сотрудника.', 'prihod-theme' ),
        'news' => __( 'На этой странице появляются новости общины. Добавляйте рассказы о праздниках, встречах и добрых делах в раздел «Записи».', 'prihod-theme' ),
        'sermons' => __( 'Здесь можно собирать проповеди, записи бесед и материалы для самостоятельного чтения.', 'prihod-theme' ),
        'contacts' => __( 'Мы будем рады встрече. Добавьте адрес, телефон, электронную почту и описание дороги в сведениях о приходе.', 'prihod-theme' ),
        'photos' => __( 'Фотографии помогают увидеть жизнь общины. Выберите собственные снимки в блоке галереи: богослужения, праздники, встречи и помощь ближним.', 'prihod-theme' ),
        'calendar' => __( 'Православный календарь и чтения на день предоставляются календарным плагином.', 'prihod-theme' ),
        'prayer-notes' => __( 'Здесь можно подать записку. Перед публикацией настройте требы и приём записок в плагине пожертвований.', 'prihod-theme' ),
        'donations' => __( 'Поддержите жизнь и добрые дела прихода. Перед публикацией укажите назначение пожертвований и настройте способы оплаты.', 'prihod-theme' ),
    );
    foreach ( $pages as $key => &$page ) {
        if ( isset( $texts[$key] ) ) { $page['content'] = '<!-- wp:paragraph --><p>' . esc_html( $texts[$key] ) . '</p><!-- /wp:paragraph -->' . $page['content']; }
        $page['template'] = in_array( $key, array( 'requests', 'prayer-notes', 'donations' ), true ) ? 'focused' : ( in_array( $key, array( 'news', 'sermons' ), true ) ? 'catalog' : ( in_array( $key, array( 'photos', 'calendar', 'schedule' ), true ) ? 'wide' : 'default' ) );
    }
    if ( isset( $pages['about'] ) ) { $pages['about']['template'] = 'about'; }
    unset( $page ); return $pages;
}
function prihod_theme_demo_pages(): array {
    $pages = prihod_theme_demo_templates( class_exists( '\Prihod\Site_Plan' ) ? \Prihod\Site_Plan::pages() : array() );
    if ( class_exists( '\Prihod\Modules' ) ) { foreach ( array( 'schedule', 'requests' ) as $key ) { if ( ! \Prihod\Modules::enabled( $key ) ) { unset( $pages[$key] ); } } }
    return $pages;
}
function prihod_theme_import_demo( array $keys, bool $publish = false, bool $front = false, bool $navigation = false ): array {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_pages' ) || ! current_user_can( 'edit_theme_options' ) || ( $publish && ! current_user_can( 'publish_pages' ) ) ) { throw new RuntimeException( __( 'Недостаточно прав.', 'prihod-theme' ) ); }
    $templates = prihod_theme_demo_pages();
    $keys = array_values( array_intersect( array_keys( $templates ), array_filter( $keys, 'is_string' ) ) );
    $reuse = array(); $saved_resources = get_option( 'prihod_resources', array() );
    foreach ( $keys as $index => $key ) {
        $post = ! empty( $saved_resources[$key]['id'] ) ? get_post( $saved_resources[$key]['id'] ) : null;
        if ( ! $post ) {
            $found = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ), 'numberposts' => 1, 'meta_key' => '_prihod_resource', 'meta_value' => $key ) );
            $post = $found[0] ?? null;
        }
        if ( $post && 'trash' === $post->post_status ) { unset( $keys[$index] ); }
        elseif ( $post ) { $reuse[$key] = $post->ID; }
    }
    if ( ! $keys ) { throw new RuntimeException( __( 'Выберите хотя бы одну страницу.', 'prihod-theme' ) ); }
    if ( $front && ( ! $publish || ! in_array( 'home', $keys, true ) ) ) { throw new RuntimeException( __( 'Для назначения главной выберите её и включите публикацию.', 'prihod-theme' ) ); }
    if ( $navigation && ! $publish ) { throw new RuntimeException( __( 'Для создания меню включите публикацию страниц.', 'prihod-theme' ) ); }
    $token = ( time() + 120 ) . '|' . wp_generate_uuid4();
    $old_lock = get_option( 'prihod_theme_demo_lock', '' );
    if ( is_string( $old_lock ) && $old_lock && (int) $old_lock < time() ) { prihod_theme_demo_unlock( $old_lock ); }
    if ( ! add_option( 'prihod_theme_demo_lock', $token, '', false ) ) { throw new RuntimeException( __( 'Другой запуск мастера уже выполняется.', 'prihod-theme' ) ); }
    try {
        $resources = get_option( 'prihod_resources', array() ); $created = array();
        if ( class_exists( '\Prihod\Site_Plan' ) ) {
            add_filter( 'prihod_page_templates', 'prihod_theme_demo_templates', 100 );
            try {
                $answers = array( 'profile' => \Prihod\Profile::get(), 'modules' => array( 'schedule' => \Prihod\Modules::enabled( 'schedule' ), 'requests' => \Prihod\Modules::enabled( 'requests' ) ), 'responsible' => get_option( 'prihod_default_responsible', 0 ), 'pages' => array_fill_keys( $keys, true ), 'reuse' => $reuse );
                $plan = \Prihod\Site_Plan::plan( $answers );
                $resources = \Prihod\Site_Plan::apply( $plan );
                foreach ( $plan['pages'] as $key => $page ) { if ( ! $page['id'] ) { $created[$key] = $resources[$key]['id']; } }
            } finally { remove_filter( 'prihod_page_templates', 'prihod_theme_demo_templates', 100 ); }
        } else {
            // Standalone theme uses the same ownership markers; no second Core implementation.
            foreach ( $keys as $key ) {
                if ( ! empty( $resources[$key]['id'] ) && get_post( $resources[$key]['id'] ) ) { continue; }
                $found = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ), 'numberposts' => 1, 'meta_key' => '_prihod_resource', 'meta_value' => $key ) );
                if ( $found ) { $resources[$key] = array( 'id' => $found[0]->ID, 'owned' => true ); continue; }
                $page = $templates[$key];
                $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $page['title'], 'post_content' => $page['content'], 'meta_input' => array( '_prihod_resource' => $key, '_prihod_generated_hash' => hash( 'sha256', $page['title'] . '|' . $page['content'] ) ) ) ), true );
                if ( is_wp_error( $id ) ) { throw new RuntimeException( __( 'Не удалось создать страницу.', 'prihod-theme' ) ); }
                $created[$key] = $id; $resources[$key] = array( 'id' => $id, 'owned' => true );
                update_option( 'prihod_resources', $resources, false );
            }
        }
        foreach ( $created as $key => $id ) { update_post_meta( $id, '_prihod_theme_demo', 1 ); update_post_meta( $id, '_wp_page_template', $templates[$key]['template'] ); }
        $eligible = array();
        foreach ( $keys as $key ) {
            $post = ! empty( $resources[$key]['id'] ) ? get_post( $resources[$key]['id'] ) : null;
            if ( $post && 'page' === $post->post_type && current_user_can( 'edit_post', $post->ID ) && get_post_meta( $post->ID, '_prihod_theme_demo', true ) && 'trash' !== $post->post_status && hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), hash( 'sha256', $post->post_title . '|' . $post->post_content ) ) ) { $eligible[$key] = $post; }
        }
        if ( $publish && $eligible ) {
            if ( class_exists( '\Prihod\Site_Plan' ) ) { \Prihod\Site_Plan::publish( array_keys( $eligible ), $front && isset( $eligible['home'] ), '', $navigation ); }
            else {
                foreach ( $eligible as $post ) { $result = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ), true ); if ( is_wp_error( $result ) ) { throw new RuntimeException( __( 'Не удалось опубликовать страницу.', 'prihod-theme' ) ); } }
                if ( $front && isset( $eligible['home'] ) ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $eligible['home']->ID ); }
                if ( $navigation ) { prihod_theme_demo_navigation( $eligible, $resources ); }
            }
        }
        // Core publish may have added the navigation resource; keep its final registry.
        if ( class_exists( '\Prihod\Site_Plan' ) ) { $resources = get_option( 'prihod_resources', $resources ); }
        update_option( 'prihod_resources', $resources, false );
        return array( 'created' => $created, 'resources' => $resources );
    } finally { prihod_theme_demo_unlock( $token ); }
}
function prihod_theme_demo_unlock( string $token ): void {
    if ( class_exists( '\Prihod\Lock' ) ) { \Prihod\Lock::release( 'prihod_theme_demo_lock', $token ); return; }
    global $wpdb;
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", 'prihod_theme_demo_lock', $token ) );
    wp_cache_delete( 'prihod_theme_demo_lock', 'options' ); wp_cache_delete( 'notoptions', 'options' );
}
function prihod_theme_demo_navigation( array $pages, array &$resources ): void {
    if ( ! empty( $resources['_navigation']['id'] ) && get_post( $resources['_navigation']['id'] ) ) { return; }
    $content = '';
    foreach ( $pages as $post ) { $content .= '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $post->post_title, 'type' => 'page', 'id' => $post->ID, 'url' => get_permalink( $post->ID ), 'kind' => 'post-type' ) ) . ' /-->'; }
    $id = wp_insert_post( wp_slash( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => __( 'Навигация прихода', 'prihod-theme' ), 'post_content' => $content ) ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( __( 'Не удалось создать навигацию.', 'prihod-theme' ) ); }
    $resources['_navigation'] = array( 'id' => $id, 'owned' => true );
}
add_action( 'admin_post_prihod_theme_demo', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod-theme' ), '', array( 'response' => 403 ) ); }
    check_admin_referer( 'prihod_theme_demo' );
    $keys = isset( $_POST['pages'] ) && is_array( $_POST['pages'] ) ? wp_unslash( $_POST['pages'] ) : array();
    try { prihod_theme_import_demo( $keys, ! empty( $_POST['publish'] ), ! empty( $_POST['front'] ), ! empty( $_POST['navigation'] ) ); }
    catch ( Throwable $error ) { wp_die( esc_html( $error->getMessage() ), '', array( 'back_link' => true ) ); }
    wp_safe_redirect( admin_url( 'admin.php?page=prihod-theme&demo_done=1#prihod-option-demo' ) ); exit;
} );
function prihod_theme_demo_panel(): void {
    echo '<p>' . esc_html__( 'Готовые редактируемые страницы с примерными текстами и блоками ваших плагинов. Повторный запуск не перезаписывает материалы и не создаёт дубликаты. По умолчанию создаются черновики.', 'prihod-theme' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_theme_demo">';
    wp_nonce_field( 'prihod_theme_demo' ); $resources = get_option( 'prihod_resources', array() );
    foreach ( prihod_theme_demo_pages() as $key => $page ) {
        $post = ! empty( $resources[$key]['id'] ) ? get_post( $resources[$key]['id'] ) : null;
        echo '<div class="prihod-demo-choice"><label><input type="checkbox" name="pages[]" value="' . esc_attr( $key ) . '" checked> ' . esc_html( $page['title'] ) . '</label>';
        if ( $post ) { echo '<small>' . esc_html__( 'Уже есть — будет сохранена', 'prihod-theme' ) . '</small>'; if ( current_user_can( 'edit_post', $post->ID ) ) { echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Редактировать', 'prihod-theme' ) . '</a>'; } }
        else { echo '<small>' . esc_html__( 'Новая страница', 'prihod-theme' ) . '</small>'; }
        echo '</div>';
    }
    echo '<div class="prihod-demo-actions">';
    foreach ( array( 'publish' => __( 'Опубликовать новые демо-страницы', 'prihod-theme' ), 'front' => __( 'Назначить демо-главную главной страницей сайта', 'prihod-theme' ), 'navigation' => __( 'Добавить опубликованные демо-страницы в навигацию', 'prihod-theme' ) ) as $key => $title ) { echo '<p><label><input type="checkbox" name="' . $key . '" value="1"> ' . esc_html( $title ) . '</label></p>'; }
    submit_button( __( 'Создать демо-страницы', 'prihod-theme' ) ); echo '</div></form>';
}
