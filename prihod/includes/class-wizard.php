<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Wizard {
    public static function hooks(): void {
        add_action( 'admin_menu', function () { add_submenu_page( 'prihod', __( 'Мастер настройки', 'prihod' ), __( 'Мастер настройки', 'prihod' ), 'manage_prihod_settings', 'prihod-wizard', array( Wizard::class, 'render' ) ); } );
        add_action( 'admin_post_prihod_wizard', array( self::class, 'submit' ) );
        add_action( 'admin_notices', array( self::class, 'invitation' ) );
        add_action( 'init', array( self::class, 'patterns' ) );
    }
    public static function state(): array {
        return get_user_meta( get_current_user_id(), 'prihod_wizard_' . get_current_blog_id(), true ) ?: array( 'step' => 0, 'answers' => array( 'profile' => Profile::get(), 'modules' => array( 'schedule' => Modules::enabled( 'schedule' ), 'requests' => Modules::enabled( 'requests' ) ) ), 'revision' => 0 );
    }
    private static function save( array $state ): void { update_user_meta( get_current_user_id(), 'prihod_wizard_' . get_current_blog_id(), $state ); }
    public static function steps(): array {
        return array( __( 'Тип организации', 'prihod' ), __( 'Места служения', 'prihod' ), __( 'Основные сведения', 'prihod' ), __( 'Контакты', 'prihod' ), __( 'Языки и часовой пояс', 'prihod' ), __( 'Духовенство', 'prihod' ), __( 'Расписание', 'prihod' ), __( 'Обращения', 'prihod' ), __( 'Публикации', 'prihod' ), __( 'Дополнительные плагины', 'prihod' ), __( 'Оформление', 'prihod' ), __( 'Страницы', 'prihod' ), __( 'План изменений', 'prihod' ), __( 'Результат и публикация', 'prihod' ) );
    }
    public static function invitation(): void {
        if ( current_user_can( 'manage_prihod_settings' ) && ! get_user_meta( get_current_user_id(), 'prihod_wizard_dismissed_' . get_current_blog_id(), true ) ) {
            echo '<div class="notice notice-info"><p><a href="' . esc_url( admin_url( 'admin.php?page=prihod-wizard' ) ) . '">' . esc_html__( 'Начать настройку «Прихода»', 'prihod' ) . '</a></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_wizard"><input type="hidden" name="operation" value="dismiss">'; wp_nonce_field( 'prihod_wizard' ); echo '<button class="button">' . esc_html__( 'Скрыть приглашение', 'prihod' ) . '</button></form></div>';
        }
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_prihod_settings' ) || ! current_user_can( 'manage_prihod' ) || ! current_user_can( 'edit_pages' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
        $state = self::state(); $step = $state['step']; $answers = $state['answers'];
        echo '<div class="wrap"><h1>' . esc_html__( 'Мастер настройки «Прихода»', 'prihod' ) . '</h1><h2>' . esc_html( self::steps()[ $step ] ) . '</h2>';
        if ( ! empty( $state['notice'] ) ) { echo '<p role="status">' . esc_html( $state['notice'] ) . '</p>'; }
        $site = Site_Plan::analyze();
        echo '<p>' . esc_html__( 'Тема:', 'prihod' ) . ' ' . esc_html( $site['theme'] ) . ' · ' . esc_html__( 'Страниц и навигаций:', 'prihod' ) . ' ' . esc_html( count( $site['pages'] ) ) . ' · ' . esc_html( $site['language'] . ' / ' . $site['timezone'] ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_wizard"><input type="hidden" name="revision" value="' . esc_attr( $state['revision'] ) . '">'; wp_nonce_field( 'prihod_wizard' );
        if ( 0 === $step ) { self::select( 'profile[type]', Profile::types(), $answers['profile']['type'] ); }
        elseif ( 1 === $step || 5 === $step ) {
            $type = 1 === $step ? 'prihod_location' : 'prihod_clergy';
            echo '<p>' . esc_html__( 'Существующие записи сохраняются. Новые сведения будут созданы только после подтверждения плана, сначала черновиками.', 'prihod' ) . '</p>';
            $group = 1 === $step ? 'locations' : 'clergy';
            if ( 1 === $step ) { self::select( 'places_mode', array( 'one' => __( 'Одно место', 'prihod' ), 'multiple' => __( 'Несколько мест', 'prihod' ) ), $answers['places_mode'] ?? 'one' ); }
            for ( $index = 0; $index < max( 3, count( $answers[ $group ] ?? array() ) ); $index++ ) {
                echo '<p><label>' . esc_html__( 'Название / имя', 'prihod' ) . ' <input name="' . esc_attr( $group . '[' . $index . '][name]' ) . '" value="' . esc_attr( $answers[ $group ][ $index ]['name'] ?? '' ) . '"></label> <label>' . esc_html( 1 === $step ? __( 'Адрес', 'prihod' ) : __( 'Сан / должность', 'prihod' ) ) . ' <input name="' . esc_attr( $group . '[' . $index . '][' . ( 1 === $step ? 'address' : 'rank' ) . ']' ) . '" value="' . esc_attr( $answers[ $group ][ $index ][ 1 === $step ? 'address' : 'rank' ] ?? '' ) . '"></label></p>';
            }
            echo '<p>' . esc_html__( 'Дополнительные записи и связи можно добавить позже в разделах мест служения и духовенства.', 'prihod' ) . '</p>';
        } elseif ( in_array( $step, array( 2, 3, 4 ), true ) ) {
            $fields = 2 === $step ? array( 'name', 'short_name', 'description' ) : ( 3 === $step ? array( 'address', 'phone', 'email', 'website' ) : array( 'language', 'timezone' ) );
            foreach ( $fields as $key ) { echo '<p><label>' . esc_html( Profile::fields()[ $key ] ) . '<br><input class="regular-text" name="profile[' . esc_attr( $key ) . ']" value="' . esc_attr( $answers['profile'][ $key ] ?? '' ) . '"></label></p>'; }
        } elseif ( 6 === $step || 7 === $step ) {
            $module = 6 === $step ? 'schedule' : 'requests';
            echo '<label><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $answers['modules'][ $module ] ), true, false ) . '> ' . esc_html__( 'Включить функцию', 'prihod' ) . '</label><p>' . esc_html__( 'После применения плана подробные настройки доступны в соответствующем разделе «Прихода».', 'prihod' ) . '</p>';
            if ( 6 === $step ) {
                echo '<fieldset><legend>' . esc_html__( 'Первая разовая служба (необязательно)', 'prihod' ) . '</legend>';
                foreach ( array( 'title' => __( 'Название службы', 'prihod' ), 'date' => __( 'Дата', 'prihod' ), 'time' => __( 'Время', 'prihod' ) ) as $key => $label ) { echo '<p><label>' . esc_html( $label ) . ' ' . Schedule_Admin::input( 'service[' . $key . ']', $answers['service'][ $key ] ?? '', 'title' === $key ? 'text' : ( 'date' === $key ? 'date' : 'time' ) ) . '</label></p>'; }
                echo '<p>' . esc_html__( 'Если место не выбрано, используется первое новое место из интервью.', 'prihod' ) . '</p>' . Schedule_Admin::locations( 'service[location_id]', absint( $answers['service']['location_id'] ?? 0 ) ) . '</fieldset>';
            } else {
                $users = array( 0 => __( 'Ответственный не назначен', 'prihod' ) ); foreach ( get_users( array( 'capability' => 'view_prihod_requests' ) ) as $user ) { if ( user_can( $user, 'reply_prihod_requests' ) ) { $users[ $user->ID ] = $user->display_name; } }
                self::select( 'responsible', $users, $answers['responsible'] ?? 0 );
            }
        } elseif ( 8 === $step ) { echo '<label><input type="checkbox" name="publications" value="1"' . checked( ! empty( $answers['publications'] ), true, false ) . '> ' . esc_html__( 'Подготовить рубрики новостей, объявлений и проповедей', 'prihod' ) . '</label>'; }
        elseif ( 9 === $step ) {
            echo '<p>' . esc_html__( 'Дополнительные компоненты устанавливаются вручную и отдельно. Автоматических установок нет.', 'prihod' ) . '</p>';
            foreach ( Plugin::boot()->integrations()->all() as $integration ) { $report = Plugin::boot()->integrations()->report( $integration ); echo '<p>' . esc_html( $report['name'] . ': ' . $report['message'] ) . '</p>'; }
        } elseif ( 10 === $step ) {
            echo '<p>' . esc_html__( 'Текущая тема сохраняется. Тема «Приход» необязательна; загрузите её ZIP вручную через «Внешний вид → Темы». Активация — отдельное действие.', 'prihod' ) . '</p>';
            $theme = wp_get_theme( 'prihod-theme' );
            if ( $theme->exists() && current_user_can( 'switch_themes' ) ) {
                echo '<p><a href="' . esc_url( add_query_arg( 'wp_theme_preview', 'prihod-theme', admin_url( 'site-editor.php' ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Предварительный просмотр темы', 'prihod' ) . '</a> · <a href="' . esc_url( wp_nonce_url( admin_url( 'themes.php?action=activate&stylesheet=prihod-theme' ), 'switch-theme_prihod-theme' ) ) . '">' . esc_html__( 'Отдельно активировать тему «Приход»', 'prihod' ) . '</a></p>';
            }
        }
        elseif ( 11 === $step ) {
            $resources = get_option( 'prihod_resources', array() );
            foreach ( Site_Plan::pages() as $key => $template ) {
                if ( in_array( $key, array( 'schedule', 'requests' ), true ) && empty( $answers['modules'][ $key ] ) ) { continue; }
                $selected = $answers['reuse'][ $key ] ?? ( $resources[ $key ]['id'] ?? 0 );
                if ( ! $selected ) { foreach ( $site['pages'] as $id => $page ) { if ( 'page' === $page['type'] && $page['title'] === $template['title'] ) { $selected = $id; break; } } }
                echo '<p><label><input type="checkbox" name="pages[' . esc_attr( $key ) . ']" value="1"' . checked( ! isset( $answers['pages'] ) || ! empty( $answers['pages'][ $key ] ), true, false ) . '> ' . esc_html( $template['title'] ) . '</label> <select name="reuse[' . esc_attr( $key ) . ']"><option value="0">' . esc_html__( 'Создать черновик', 'prihod' ) . '</option>';
                foreach ( $site['pages'] as $id => $page ) { if ( 'page' === $page['type'] ) { echo '<option value="' . esc_attr( $id ) . '"' . selected( $selected, $id, false ) . '>' . esc_html( $page['title'] . ' #' . $id ) . '</option>'; } }
                echo '</select></p>';
            }
        } elseif ( 12 === $step ) {
            $plan = $state['plan'] ?? Site_Plan::plan( $answers );
            echo '<ul>';
            foreach ( $plan['pages'] as $page ) { echo '<li>' . esc_html( $page['title'] ) . ' — ' . esc_html( 'create' === $page['action'] ? __( 'новый черновик', 'prihod' ) : __( 'использовать без изменения содержимого', 'prihod' ) ) . '</li>'; }
            foreach ( array( 'locations', 'clergy' ) as $group ) { foreach ( $answers[ $group ] ?? array() as $input ) { if ( ! empty( $input['name'] ) && is_string( $input['name'] ) ) { echo '<li>' . esc_html( $input['name'] ) . ' — ' . esc_html__( 'подготовить сведения (имеющиеся ресурсы мастера сохранятся)', 'prihod' ) . '</li>'; } } }
            if ( ! empty( $answers['modules']['schedule'] ) && ! empty( $answers['service']['title'] ) ) { echo '<li>' . esc_html( $answers['service']['title'] ) . ' — ' . esc_html__( 'черновик первой службы', 'prihod' ) . '</li>'; }
            echo '</ul><p>' . esc_html__( 'Будут сохранены введённые сведения и переключатели модулей. Главная, меню, тема, существующие страницы и внешние сервисы не изменяются на этом шаге.', 'prihod' ) . '</p>';
            if ( ! empty( $answers['publications'] ) ) { echo '<p>' . esc_html__( 'Будут подготовлены рубрики новостей, объявлений и проповедей.', 'prihod' ) . '</p>'; }
            echo '<label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'Подтверждаю этот план', 'prihod' ) . '</label><p><button class="button button-primary" name="operation" value="apply">' . esc_html__( 'Применить план', 'prihod' ) . '</button></p>';
        } else {
            foreach ( get_option( 'prihod_resources', array() ) as $key => $resource ) {
                if ( in_array( $key, array( '_menu', '_navigation' ), true ) ) { continue; }
                $post = get_post( $resource['id'] ); if ( ! $post ) { continue; }
                echo '<p><label><input type="checkbox" name="publish_keys[]" value="' . esc_attr( $key ) . '"> ' . esc_html( $post->post_title ) . '</label> ';
                if ( 'page' === $post->post_type ) { echo '<a href="' . esc_url( get_preview_post_link( $post ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Предварительный просмотр', 'prihod' ) . '</a> · '; }
                echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Редактор', 'prihod' ) . '</a></p>';
            }
            echo '<p><label><input type="checkbox" name="set_front" value="1"> ' . esc_html__( 'Назначить выбранную «Главную» главной страницей сайта', 'prihod' ) . '</label></p><p><label><input type="checkbox" name="navigation" value="1"> ' . esc_html__( 'Создать отдельную блоковую навигацию для выбора в редакторе сайта', 'prihod' ) . '</label></p>';
            self::select( 'menu_location', array( '' => __( 'Не менять меню', 'prihod' ) ) + get_registered_nav_menus(), '' );
            echo '<p>' . esc_html__( 'Выбор позиции меню заменит её текущую привязку новым меню «Прихода». Существующее меню и его пункты сохранятся.', 'prihod' ) . '</p><label><input required type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Подтверждаю публикацию и выбранные изменения главной/навигации', 'prihod' ) . '</label><p><button class="button button-primary" name="operation" value="publish">' . esc_html__( 'Опубликовать выбранное', 'prihod' ) . '</button></p>';
        }
        if ( $step < 12 ) { echo '<p><button class="button button-primary" name="operation" value="next">' . esc_html__( 'Сохранить и продолжить', 'prihod' ) . '</button> <button class="button" name="operation" value="skip">' . esc_html__( 'Настроить позже', 'prihod' ) . '</button></p>'; }
        if ( $step > 0 ) { echo '<button class="button" name="operation" value="back" formnovalidate>' . esc_html__( 'Назад', 'prihod' ) . '</button> '; }
        echo '<button class="button" name="operation" value="restart" formnovalidate>' . esc_html__( 'Начать интервью заново (сохранить ресурсы)', 'prihod' ) . '</button></form></div>';
    }
    private static function select( string $name, array $options, $value ): void {
        echo '<p><select name="' . esc_attr( $name ) . '">'; foreach ( $options as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>'; } echo '</select></p>';
    }
    public static function submit(): void {
        if ( ! current_user_can( 'manage_prihod_settings' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'prihod_wizard' );
        $lock = 'prihod_wizard_lock_' . get_current_blog_id() . '_' . get_current_user_id();
        $token = Lock::acquire( $lock, 120 ); if ( ! $token ) { wp_die( esc_html__( 'Сохранение мастера уже выполняется.', 'prihod' ) ); }
        $state = self::state(); $operation = is_string( $_POST['operation'] ?? null ) ? sanitize_key( $_POST['operation'] ) : '';
        try {
            if ( 'dismiss' === $operation ) { update_user_meta( get_current_user_id(), 'prihod_wizard_dismissed_' . get_current_blog_id(), true ); }
            else {
                if ( ! is_scalar( $_POST['revision'] ?? null ) || (int) $_POST['revision'] !== $state['revision'] ) { throw new \RuntimeException( __( 'Эта вкладка устарела. Откройте мастер заново.', 'prihod' ) ); }
                if ( 'restart' === $operation ) { $state['step'] = 0; unset( $state['plan'] ); }
                elseif ( 'back' === $operation ) { $state['step'] = max( 0, $state['step'] - 1 ); }
                elseif ( 'apply' === $operation ) {
                    if ( 12 !== $state['step'] || '1' !== ( $_POST['confirm'] ?? '' ) || empty( $state['plan'] ) ) { throw new \RuntimeException( __( 'Сначала подтвердите план.', 'prihod' ) ); }
                    Site_Plan::apply( $state['plan'] ); $state['step'] = 13;
                } elseif ( 'publish' === $operation ) {
                    if ( 13 !== $state['step'] || '1' !== ( $_POST['confirm'] ?? '' ) || ! is_array( $_POST['publish_keys'] ?? null ) ) { throw new \RuntimeException( __( 'Подтвердите публикацию.', 'prihod' ) ); }
                    $location = is_string( $_POST['menu_location'] ?? null ) ? sanitize_key( $_POST['menu_location'] ) : '';
                    Site_Plan::publish( wp_unslash( $_POST['publish_keys'] ), ! empty( $_POST['set_front'] ), $location, ! empty( $_POST['navigation'] ) );
                } elseif ( 'next' === $operation || 'skip' === $operation ) {
                    if ( $state['step'] >= 12 ) { throw new \RuntimeException( __( 'Требуется подтверждение.', 'prihod' ) ); }
                    if ( 'next' === $operation ) {
                        $step = $state['step'];
                        if ( isset( $_POST['profile'] ) && is_array( $_POST['profile'] ) ) { $state['answers']['profile'] = Profile::sanitize( array_merge( $state['answers']['profile'], wp_unslash( $_POST['profile'] ) ) ); }
                        if ( 1 === $step && is_string( $_POST['places_mode'] ?? null ) ) { $state['answers']['places_mode'] = 'multiple' === $_POST['places_mode'] ? 'multiple' : 'one'; }
                        if ( 1 === $step || 5 === $step ) { $group = 1 === $step ? 'locations' : 'clergy'; $state['answers'][ $group ] = is_array( $_POST[ $group ] ?? null ) ? array_slice( wp_unslash( $_POST[ $group ] ), 0, 100 ) : array(); }
                        if ( 6 === $step || 7 === $step ) { $state['answers']['modules'][ 6 === $step ? 'schedule' : 'requests' ] = ! empty( $_POST['enabled'] ); }
                        if ( 6 === $step ) { $state['answers']['service'] = array(); foreach ( array( 'title', 'date', 'time', 'location_id' ) as $key ) { if ( ! is_string( $_POST['service'][ $key ] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Некорректные сведения о службе.', 'prihod' ) ); } $state['answers']['service'][ $key ] = sanitize_text_field( wp_unslash( $_POST['service'][ $key ] ?? '' ) ); } }
                        if ( 7 === $step ) { $state['answers']['responsible'] = is_scalar( $_POST['responsible'] ?? null ) ? absint( $_POST['responsible'] ) : 0; }
                        if ( 8 === $step ) { $state['answers']['publications'] = ! empty( $_POST['publications'] ); }
                        if ( 11 === $step ) { $state['answers']['pages'] = is_array( $_POST['pages'] ?? null ) ? wp_unslash( $_POST['pages'] ) : array(); $state['answers']['reuse'] = is_array( $_POST['reuse'] ?? null ) ? wp_unslash( $_POST['reuse'] ) : array(); }
                    }
                    ++$state['step'];
                    if ( 12 === $state['step'] ) { $state['plan'] = Site_Plan::plan( $state['answers'] ); }
                }
                ++$state['revision']; $state['notice'] = __( 'Состояние мастера сохранено.', 'prihod' ); self::save( $state );
            }
        } catch ( \Throwable $error ) { $state['notice'] = $error->getMessage(); if ( 12 === $state['step'] ) { $state['plan'] = Site_Plan::plan( $state['answers'] ); } self::save( $state ); }
        Lock::release( $lock, $token );
        wp_safe_redirect( admin_url( 'admin.php?page=prihod-wizard' ) ); exit;
    }
    public static function patterns(): void {
        foreach ( array( 'news' => __( 'Новость прихода', 'prihod' ), 'announcement' => __( 'Объявление', 'prihod' ), 'sermon' => __( 'Проповедь', 'prihod' ) ) as $key => $title ) {
            register_block_pattern( 'prihod/' . $key, array( 'title' => $title, 'categories' => array( 'text' ), 'content' => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $title ) . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__( 'Добавьте текст публикации.', 'prihod' ) . '</p><!-- /wp:paragraph -->' ) );
        }
    }
}
