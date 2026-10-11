<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Site_Plan {
    public static function pages(): array {
        $pages = array(
            'home' => array( 'title' => __( 'Главная', 'prihod' ), 'content' => '<!-- wp:prihod/profile /--><!-- wp:prihod/next-service /--><!-- wp:prihod/contacts /-->' ),
            'about' => array( 'title' => __( 'О приходе', 'prihod' ), 'content' => '<!-- wp:prihod/profile /-->' ),
            'clergy' => array( 'title' => __( 'Духовенство', 'prihod' ), 'content' => '<!-- wp:prihod/clergy /-->' ),
            'schedule' => array( 'title' => __( 'Расписание богослужений', 'prihod' ), 'content' => '<!-- wp:prihod/schedule /-->' ),
            'requests' => array( 'title' => __( 'Вопрос священнику', 'prihod' ), 'content' => '<!-- wp:prihod/requests /-->' ),
            'first-visit' => array( 'title' => __( 'Первый раз в храме', 'prihod' ), 'content' => '<!-- wp:paragraph --><p>' . esc_html__( 'Добавьте сведения о посещении вашего храма: часы работы, дорогу и контакты ответственного.', 'prihod' ) . '</p><!-- /wp:paragraph -->' ),
            'news' => array( 'title' => __( 'Новости', 'prihod' ), 'content' => self::posts_content( 'news' ) ),
            'sermons' => array( 'title' => __( 'Проповеди', 'prihod' ), 'content' => self::posts_content( 'sermons' ) ),
            'contacts' => array( 'title' => __( 'Контакты', 'prihod' ), 'content' => '<!-- wp:prihod/contacts /-->' ),
        );
        return apply_filters( 'prihod_page_templates', $pages );
    }
    private static function posts_content( string $category ): string {
        $term = get_term_by( 'slug', 'prihod-' . $category, 'category' );
        $query = array( 'perPage' => 10, 'postType' => 'post', 'inherit' => false );
        if ( $term ) { $query['taxQuery'] = array( 'category' => array( $term->term_id ) ); }
        return '<!-- wp:query ' . wp_json_encode( array( 'query' => $query ) ) . ' --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true} /--><!-- wp:post-date /--><!-- wp:post-excerpt /--><!-- /wp:post-template --><!-- wp:query-pagination /--></div><!-- /wp:query -->';
    }
    public static function analyze(): array {
        $pages = array();
        foreach ( get_posts( array( 'post_type' => array( 'page', 'wp_navigation' ), 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
            if ( ! current_user_can( 'read_post', $post->ID ) ) { continue; }
            $pages[ $post->ID ] = array( 'title' => $post->post_title, 'type' => $post->post_type, 'status' => $post->post_status, 'hash' => hash( 'sha256', $post->post_title . $post->post_content . $post->post_status ) );
        }
        $data = array( 'theme' => get_stylesheet(), 'front' => get_option( 'page_on_front' ), 'front_mode' => get_option( 'show_on_front' ), 'menu_locations' => get_nav_menu_locations(),
            'menus' => wp_get_nav_menus(), 'pages' => $pages, 'language' => get_locale(), 'timezone' => wp_timezone_string(), 'plugins' => get_option( 'active_plugins', array() ),
            'profile' => Profile::get(), 'modules' => get_option( 'prihod_modules', array() ), 'resources' => get_option( 'prihod_resources', array() ) );
        $data['fingerprint'] = hash( 'sha256', wp_json_encode( $data ) );
        return $data;
    }
    public static function plan( array $answers ): array {
        $site = self::analyze(); $resources = get_option( 'prihod_resources', array() ); $pages = array();
        foreach ( self::pages() as $key => $template ) {
            if ( empty( $answers['pages'][ $key ] ) || ( in_array( $key, array( 'schedule', 'requests' ), true ) && empty( $answers['modules'][ $key ] ) ) ) { continue; }
            $id = absint( $answers['reuse'][ $key ] ?? 0 );
            if ( ! $id && ! empty( $resources[ $key ]['id'] ) && isset( $site['pages'][ $resources[ $key ]['id'] ] ) ) { $id = (int) $resources[ $key ]['id']; }
            if ( ! $id ) { $found = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'draft', 'publish', 'private', 'pending' ), 'numberposts' => 1, 'meta_key' => '_prihod_resource', 'meta_value' => $key ) ); if ( $found && isset( $site['pages'][ $found[0]->ID ] ) ) { $id = $found[0]->ID; } }
            if ( $id && ( ! isset( $site['pages'][ $id ] ) || 'page' !== $site['pages'][ $id ]['type'] ) ) { throw new \InvalidArgumentException( __( 'Выбранная страница больше не доступна. Проверьте план.', 'prihod' ) ); }
            $pages[ $key ] = array( 'id' => $id, 'title' => $id ? $site['pages'][ $id ]['title'] : $template['title'], 'content' => $template['content'], 'action' => $id ? 'reuse' : 'create' );
        }
        return array( 'site' => $site, 'pages' => $pages, 'answers' => $answers, 'fingerprint' => $site['fingerprint'] );
    }
    public static function apply( array $plan ): array {
        self::authorize(); $token = Lock::acquire( 'prihod_plan_lock', 120 );
        if ( ! $token ) { throw new \RuntimeException( __( 'Другой запуск мастера уже выполняется.', 'prihod' ) ); }
        try {
            if ( ! hash_equals( self::analyze()['fingerprint'], $plan['fingerprint'] ) ) { throw new \RuntimeException( __( 'Сайт изменился после просмотра плана. Сформируйте план заново.', 'prihod' ) ); }
            $answers = $plan['answers'];
            $responsible = absint( $answers['responsible'] ?? 0 );
            if ( $responsible && ( ! user_can( $responsible, 'view_prihod_requests' ) || ! user_can( $responsible, 'reply_prihod_requests' ) ) ) { throw new \RuntimeException( __( 'Ответственный больше не имеет необходимых прав.', 'prihod' ) ); }
            update_option( Profile::OPTION, Profile::sanitize( $answers['profile'] ?? array() ), false );
            update_option( 'prihod_modules', Modules::sanitize( $answers['modules'] ?? array() ), false );
            update_option( 'prihod_default_responsible', $responsible, false );
            $resources = get_option( 'prihod_resources', array() );
            foreach ( array( 'locations' => 'prihod_location', 'clergy' => 'prihod_clergy' ) as $group => $type ) {
                foreach ( $answers[ $group ] ?? array() as $index => $input ) {
                    if ( ! is_array( $input ) || ! is_string( $input['name'] ?? null ) || '' === trim( $input['name'] ) ) { continue; }
                    $key = '_' . $group . '_' . $index;
                    if ( ! empty( $resources[ $key ]['id'] ) && get_post( $resources[ $key ]['id'] ) ) { continue; }
                    $name = sanitize_text_field( $input['name'] );
                    $details = array();
                    if ( 'locations' === $group ) { $details['address'] = sanitize_textarea_field( is_string( $input['address'] ?? null ) ? $input['address'] : '' ); }
                    else { $details['rank'] = sanitize_text_field( is_string( $input['rank'] ?? null ) ? $input['rank'] : '' ); $details['location_ids'] = ! empty( $resources['_locations_0']['id'] ) ? array( $resources['_locations_0']['id'] ) : array(); }
                    $id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $name, 'post_status' => 'draft', 'meta_input' => array( '_prihod_details' => $details, '_prihod_generated_hash' => hash( 'sha256', $name . '|' ) ) ), true );
                    if ( is_wp_error( $id ) ) { throw new \RuntimeException( __( 'Не удалось сохранить сведения. Повторите настройку.', 'prihod' ) ); }
                    $resources[ $key ] = array( 'id' => $id, 'owned' => true ); update_option( 'prihod_resources', $resources, false );
                }
            }
            if ( ! empty( $answers['modules']['schedule'] ) && ! empty( $answers['service']['title'] ) && empty( $resources['_service_0']['id'] ) ) {
                if ( ! current_user_can( 'manage_prihod_schedule' ) ) { throw new \RuntimeException( __( 'Недостаточно прав для создания службы.', 'prihod' ) ); }
                Schedule::register();
                $input = $answers['service']; $name = sanitize_text_field( $input['title'] );
                $rule = Schedule::validate( array( 'date' => $input['date'], 'time' => $input['time'], 'location_id' => absint( $input['location_id'] ) ?: ( $resources['_locations_0']['id'] ?? 0 ) ) );
                $id = wp_insert_post( array( 'post_type' => 'prihod_service', 'post_status' => 'draft', 'post_title' => $name, 'meta_input' => array( '_prihod_service' => $rule, '_prihod_generated_hash' => hash( 'sha256', $name . '|' ) ) ), true );
                if ( is_wp_error( $id ) ) { throw new \RuntimeException( __( 'Не удалось создать службу.', 'prihod' ) ); }
                $resources['_service_0'] = array( 'id' => $id, 'owned' => true ); update_option( 'prihod_resources', $resources, false );
            }
            if ( ! empty( $answers['publications'] ) ) {
                foreach ( array( 'news' => __( 'Новости', 'prihod' ), 'announcements' => __( 'Объявления', 'prihod' ), 'sermons' => __( 'Проповеди', 'prihod' ) ) as $key => $title ) {
                    if ( ! get_term_by( 'slug', 'prihod-' . $key, 'category' ) ) { $term = wp_insert_term( $title, 'category', array( 'slug' => 'prihod-' . $key ) ); if ( is_wp_error( $term ) ) { throw new \RuntimeException( __( 'Не удалось подготовить рубрики.', 'prihod' ) ); } }
                }
            }
            // Refresh compositions after categories exist; existing content is never overwritten.
            $templates = self::pages();
            foreach ( $plan['pages'] as $key => $page ) {
                $id = $page['id'];
                if ( ! $id ) {
                    $found = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'draft', 'publish', 'private', 'pending' ), 'numberposts' => 1, 'meta_key' => '_prihod_resource', 'meta_value' => $key ) );
                    if ( $found ) { $id = $found[0]->ID; }
                }
                if ( ! $id ) {
                    $content = $templates[ $key ]['content'];
                    $id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => $page['title'], 'post_content' => $content, 'post_status' => 'draft',
                        'meta_input' => array( '_prihod_resource' => $key, '_prihod_generated_hash' => hash( 'sha256', $page['title'] . '|' . $content ) ) ), true );
                    if ( is_wp_error( $id ) ) { throw new \RuntimeException( __( 'Не удалось создать страницу. Сохранённые ресурсы доступны при повторном запуске.', 'prihod' ) ); }
                    $resources[ $key ] = array( 'id' => $id, 'owned' => true );
                } else { $resources[ $key ] = array( 'id' => $id, 'owned' => ( ! empty( $resources[ $key ]['owned'] ) && (int) $resources[ $key ]['id'] === (int) $id ) || $key === get_post_meta( $id, '_prihod_resource', true ) ); }
                update_option( 'prihod_resources', $resources, false );
            }
            return $resources;
        } finally { Lock::release( 'prihod_plan_lock', $token ); }
    }
    public static function publish( array $keys, bool $set_front, string $menu_location, bool $navigation ): void {
        self::authorize();
        if ( ! current_user_can( 'publish_pages' ) ) { throw new \RuntimeException( __( 'Недостаточно прав публикации.', 'prihod' ) ); }
        if ( ( $set_front || $menu_location || $navigation ) && ! current_user_can( 'edit_theme_options' ) ) { throw new \RuntimeException( __( 'Недостаточно прав изменения оформления.', 'prihod' ) ); }
        $resources = get_option( 'prihod_resources', array() );
        $pages = array();
        foreach ( $keys as $key ) {
            if ( ! is_string( $key ) || ! isset( $resources[ $key ] ) ) { throw new \InvalidArgumentException( __( 'Некорректная страница публикации.', 'prihod' ) ); }
            $resource = $resources[ $key ]; $post = get_post( $resource['id'] );
            if ( ! $post || ! in_array( $post->post_type, array( 'page', 'prihod_location', 'prihod_clergy', 'prihod_service' ), true ) ) { throw new \RuntimeException( __( 'Ресурс удалён.', 'prihod' ) ); }
            if ( ! current_user_can( 'edit_post', $post->ID ) ) { throw new \RuntimeException( __( 'Недостаточно прав для публикации ресурса.', 'prihod' ) ); }
            // Never publish an existing user draft, or a manually edited generated draft, through a bulk wizard action.
            if ( 'publish' !== $post->post_status && ( ! $resource['owned'] || ! hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), hash( 'sha256', $post->post_title . '|' . $post->post_content ) ) ) ) {
                throw new \RuntimeException( __( 'Страница изменена вручную или создана не мастером. Опубликуйте её отдельно в редакторе.', 'prihod' ) );
            }
            $pages[ $key ] = $post;
        }
        if ( $menu_location && ! array_key_exists( $menu_location, get_registered_nav_menus() ) ) { throw new \InvalidArgumentException( __( 'Позиция меню недоступна.', 'prihod' ) ); }
        if ( $set_front && ! isset( $pages['home'] ) ) { throw new \InvalidArgumentException( __( 'Выберите главную страницу для публикации.', 'prihod' ) ); }
        foreach ( $pages as $post ) { if ( 'publish' !== $post->post_status ) { $result = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ), true ); if ( is_wp_error( $result ) ) { throw new \RuntimeException( __( 'Не удалось опубликовать страницу.', 'prihod' ) ); } } }
        if ( $set_front ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $pages['home']->ID ); }
        $navigation_pages = array_filter( $pages, fn( $post ) => 'page' === $post->post_type );
        if ( $navigation ) { self::navigation( $navigation_pages, $resources ); }
        if ( $menu_location ) { self::menu( $navigation_pages, $resources, $menu_location ); }
        update_option( 'prihod_resources', $resources, false );
    }
    private static function navigation( array $pages, array &$resources ): void {
        $existing = ! empty( $resources['_navigation']['id'] ) ? get_post( $resources['_navigation']['id'] ) : null;
        if ( $existing && 'wp_navigation' !== $existing->post_type ) { throw new \RuntimeException( __( 'Неверный ресурс навигации.', 'prihod' ) ); }
        $content = $existing ? $existing->post_content : ''; $ids = array();
        $collect = function ( array $blocks ) use ( &$collect, &$ids ) { foreach ( $blocks as $block ) { if ( ! empty( $block['attrs']['id'] ) ) { $ids[] = (int) $block['attrs']['id']; } if ( ! empty( $block['innerBlocks'] ) ) { $collect( $block['innerBlocks'] ); } } };
        $collect( parse_blocks( $content ) );
        foreach ( $pages as $post ) { if ( ! in_array( $post->ID, $ids, true ) ) { $content .= '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $post->post_title, 'type' => 'page', 'id' => $post->ID, 'url' => get_permalink( $post->ID ), 'kind' => 'post-type' ) ) . ' /-->'; } }
        if ( $existing && $content === $existing->post_content ) { return; }
        $id = $existing ? wp_update_post( wp_slash( array( 'ID' => $existing->ID, 'post_content' => $content ) ), true ) : wp_insert_post( wp_slash( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => __( 'Навигация прихода', 'prihod' ), 'post_content' => $content ) ), true );
        if ( is_wp_error( $id ) ) { throw new \RuntimeException( __( 'Не удалось создать навигацию.', 'prihod' ) ); }
        $resources['_navigation'] = array( 'id' => $id, 'owned' => true );
        // No theme template is rewritten: user selects this navigation in Site Editor.
    }
    private static function menu( array $pages, array &$resources, string $location ): void {
        $id = $resources['_menu']['id'] ?? 0;
        if ( ! $id || ! wp_get_nav_menu_object( $id ) ) {
            $id = wp_create_nav_menu( __( 'Приход', 'prihod' ) . ' ' . wp_generate_password( 6, false, false ) );
            if ( is_wp_error( $id ) ) { throw new \RuntimeException( __( 'Не удалось создать меню.', 'prihod' ) ); }
            $resources['_menu'] = array( 'id' => $id, 'owned' => true );
            update_option( 'prihod_resources', $resources, false );
        }
        $ids = array_map( fn( $item ) => (int) $item->object_id, wp_get_nav_menu_items( $id ) ?: array() );
        foreach ( $pages as $post ) { if ( ! in_array( $post->ID, $ids, true ) ) { $item = wp_update_nav_menu_item( $id, 0, array( 'menu-item-object-id' => $post->ID, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) ); if ( is_wp_error( $item ) ) { throw new \RuntimeException( __( 'Не удалось создать пункт меню.', 'prihod' ) ); } } }
        $locations = get_nav_menu_locations(); $locations[ $location ] = (int) $id; set_theme_mod( 'nav_menu_locations', $locations );
    }
    private static function authorize(): void {
        if ( get_option( 'prihod_purge_job' ) ) { throw new \RuntimeException( __( 'Дождитесь завершения удаления данных.', 'prihod' ) ); }
        if ( ! current_user_can( 'manage_prihod_settings' ) || ! current_user_can( 'manage_prihod' ) || ! current_user_can( 'edit_pages' ) ) { throw new \RuntimeException( __( 'Недостаточно прав для настройки сайта.', 'prihod' ) ); }
    }
}
