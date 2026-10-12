<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Wizard_View {
    public static function render( array $state ): void {
        $stage = $state['stage']; $answers = $state['answers'];
        echo '<div class="wrap prihod-wizard"><header class="prihod-wizard-header">' . Admin_Navigation::symbol( 'church' ) . '<div><h1>' . esc_html__( 'Приход', 'prihod' ) . '</h1><p>' . esc_html__( 'Мастер настройки', 'prihod' ) . '</p></div></header><ol class="prihod-wizard-progress" aria-label="' . esc_attr__( 'Этапы настройки', 'prihod' ) . '">';
        foreach ( Wizard::steps() as $index => $label ) { echo '<li class="' . ( $index < $stage ? 'is-complete' : '' ) . '"' . ( $index === $stage ? ' aria-current="step"' : '' ) . '><span>' . ( $index + 1 ) . '</span>' . esc_html( $label ) . '</li>'; }
        echo '</ol>';
        if ( ! $stage ) { self::welcome( $state ); echo '</div>'; return; }
        echo '<section class="prihod-wizard-card">';
        $headings = array( 1 => __( 'Расскажите о вашем приходе', 'prihod' ), 2 => __( 'Выберите возможности', 'prihod' ), 3 => __( 'Страницы сайта', 'prihod' ), 4 => __( 'Проверьте план изменений', 'prihod' ) );
        if ( 5 !== $stage ) { echo '<div class="prihod-step-heading"><h2>' . esc_html( $headings[$stage] ) . '</h2></div>'; }
        if ( ! empty( $state['notice'] ) && __( 'Состояние мастера сохранено.', 'prihod' ) !== $state['notice'] ) { echo '<p class="prihod-ui-notice" role="' . ( 'error' === ( $state['notice_type'] ?? '' ) ? 'alert' : 'status' ) . '">' . esc_html( $state['notice'] ) . '</p>'; }
        self::form( $state );
        if ( 1 === $stage ) {
            if ( ! empty( $state['profile_input'] ) ) { $answers['profile'] = array_merge( $answers['profile'], $state['profile_input'] ); }
            self::profile( $answers, 'error' === ( $state['notice_type'] ?? '' ) );
        }
        elseif ( 2 === $stage ) { self::features( $answers ); }
        elseif ( 3 === $stage ) { self::pages( $answers ); }
        elseif ( 4 === $stage ) { self::plan( $state ); }
        else { self::result(); }
        echo '<div class="prihod-wizard-actions">';
        if ( $stage > 0 ) { echo '<button class="button" name="operation" value="back" formnovalidate>← ' . esc_html__( 'Назад', 'prihod' ) . '</button>'; }
        if ( $stage < 4 ) { echo '<button class="button button-primary" name="operation" value="next">' . esc_html__( 'Продолжить', 'prihod' ) . ' →</button><button class="button button-link" name="operation" value="skip" formnovalidate>' . esc_html__( 'Настроить позже', 'prihod' ) . '</button>'; }
        if ( 5 === $stage ) { echo '<button class="button" name="operation" value="restart" formnovalidate>' . esc_html__( 'Начать интервью заново (сохранить ресурсы)', 'prihod' ) . '</button>'; }
        echo '</div></form>';
        self::site_details();
        echo '</section></div>';
    }
    private static function form( array $state ): void {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_wizard"><input type="hidden" name="interface" value="2"><input type="hidden" name="revision" value="' . esc_attr( $state['revision'] ) . '">'; wp_nonce_field( 'prihod_wizard' );
    }
    private static function field( string $name, string $label, $value, string $type = 'text' ): void {
        $value = is_string( $value ) ? $value : '';
        echo '<label class="prihod-wizard-field">' . esc_html( $label );
        if ( 'textarea' === $type ) { echo '<textarea name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( $value ) . '</textarea>'; }
        else { echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">'; }
        echo '</label>';
    }
    private static function select( string $name, string $label, array $choices, $value ): void {
        echo '<label class="prihod-wizard-field">' . esc_html( $label ) . '<select name="' . esc_attr( $name ) . '">';
        foreach ( $choices as $key => $text ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $text ) . '</option>'; }
        echo '</select></label>';
    }
    private static function profile( array $answers, bool $error = false ): void {
        $profile = $answers['profile'];
        echo '<fieldset class="prihod-type-grid"><legend>' . esc_html__( 'Тип организации', 'prihod' ) . '</legend>';
        foreach ( Profile::types() as $key => $label ) { echo '<label><input type="radio" name="profile[type]" value="' . esc_attr( $key ) . '"' . checked( $profile['type'], $key, false ) . '>' . Admin_Navigation::symbol( $key ) . '<strong>' . esc_html( $label ) . '</strong></label>'; }
        echo '</fieldset><div class="prihod-wizard-profile-main">';
        foreach ( array( 'name', 'short_name', 'description' ) as $key ) { self::field( 'profile[' . $key . ']', Profile::fields()[$key], $profile[$key] ?? '', 'description' === $key ? 'textarea' : 'text' ); }
        echo '</div><details class="prihod-wizard-details"' . ( $error ? ' open' : '' ) . '><summary>' . esc_html__( 'Контакты, языки и часовой пояс', 'prihod' ) . '</summary><div class="prihod-wizard-fields">';
        foreach ( array( 'address' => 'text', 'phone' => 'tel', 'email' => 'email', 'website' => 'url' ) as $key => $type ) { self::field( 'profile[' . $key . ']', Profile::fields()[$key], $profile[$key] ?? '', $type ); }
        self::select( 'profile[language]', Profile::fields()['language'], array( 'ru_RU' => 'Русский', 'de_DE' => 'Deutsch', 'pl_PL' => 'Polski', 'uk' => 'Українська' ), $profile['language'] ?? 'ru_RU' );
        self::field( 'profile[timezone]', Profile::fields()['timezone'], $profile['timezone'] ?? 'UTC' );
        echo '</div></details>';
    }
    private static function features( array $answers ): void {
        $features = Wizard_Interview::features( $answers );
        $definitions = array(
            'schedule' => array( 'calendar-alt', __( 'Расписание богослужений', 'prihod' ), __( 'Публикация расписания, ближайшая служба, календарь', 'prihod' ) ),
            'requests' => array( 'admin-links', __( 'Вопрос священнику', 'prihod' ), __( 'Форма для обращений, кабинет для ответственных', 'prihod' ) ),
            'publications' => array( 'admin-comments', __( 'Новости и объявления', 'prihod' ), __( 'Рубрики новостей, объявлений и проповедей', 'prihod' ) ),
            'clergy' => array( 'admin-users', __( 'Духовенство', 'prihod' ), __( 'Подготовить страницу и сведения о священнослужителях', 'prihod' ) ),
            'locations' => array( 'location-alt', __( 'Места служения', 'prihod' ), __( 'Подготовить сведения о храмах и адресах', 'prihod' ) ),
            'integrations' => array( 'admin-plugins', __( 'Подключения', 'prihod' ), __( 'Просмотреть установленные плагины и варианты подключения', 'prihod' ) ),
        );
        echo '<input type="hidden" name="features_present" value="1"><div class="prihod-feature-list">';
        foreach ( $definitions as $key => $definition ) {
            echo '<article class="prihod-feature"><label class="prihod-feature-row"><span class="dashicons dashicons-' . esc_attr( $definition[0] ) . '" aria-hidden="true"></span><span><strong>' . esc_html( $definition[1] ) . '</strong><small>' . esc_html( $definition[2] ) . '</small></span><input type="checkbox" role="switch" name="features[' . esc_attr( $key ) . ']" value="1"' . checked( $features[$key], true, false ) . '></label>';
            if ( 'publications' !== $key ) {
                echo '<details class="prihod-feature-details"><summary>' . esc_html__( 'Дополнительная настройка', 'prihod' ) . '</summary>';
                if ( 'schedule' === $key ) {
                    echo '<p>' . esc_html__( 'Первая разовая служба (необязательно)', 'prihod' ) . '</p><div class="prihod-wizard-fields">';
                    foreach ( array( 'title' => __( 'Название службы', 'prihod' ), 'date' => __( 'Дата', 'prihod' ), 'time' => __( 'Время', 'prihod' ) ) as $field => $label ) { self::field( 'service[' . $field . ']', $label, $answers['service'][$field] ?? '', 'title' === $field ? 'text' : $field ); }
                    echo '</div><p>' . esc_html__( 'Если место не выбрано, используется первое новое место из интервью.', 'prihod' ) . '</p><label class="prihod-wizard-field">' . esc_html__( 'Место служения', 'prihod' ) . Schedule_Admin::locations( 'service[location_id]', absint( $answers['service']['location_id'] ?? 0 ) ) . '</label>';
                } elseif ( 'requests' === $key ) {
                    $users = array( 0 => __( 'Ответственный не назначен', 'prihod' ) ); foreach ( get_users( array( 'capability' => 'view_prihod_requests' ) ) as $user ) { if ( user_can( $user, 'reply_prihod_requests' ) ) { $users[$user->ID] = $user->display_name; } }
                    self::select( 'responsible', __( 'Ответственный', 'prihod' ), $users, $answers['responsible'] ?? 0 );
                } elseif ( in_array( $key, array( 'locations', 'clergy' ), true ) ) { self::directory( $key, $answers ); }
                else {
                    echo '<p>' . esc_html__( 'Дополнительные компоненты устанавливаются вручную и отдельно. Автоматических установок нет.', 'prihod' ) . '</p>';
                    foreach ( Plugin::boot()->integrations()->all() as $integration ) { $report = Plugin::boot()->integrations()->report( $integration ); echo '<p><strong>' . esc_html( $report['name'] ) . '</strong><br>' . esc_html( $report['message'] ) . '</p>'; }
                }
                echo '</details>';
            }
            echo '</article>';
        }
        echo '</div><p class="prihod-ui-hint">' . esc_html__( 'Существующие записи сохраняются. Новые сведения будут созданы только после подтверждения плана, сначала черновиками.', 'prihod' ) . '</p>';
    }
    private static function directory( string $group, array $answers ): void {
        if ( 'locations' === $group ) { self::select( 'places_mode', __( 'Места служения', 'prihod' ), array( 'one' => __( 'Одно место', 'prihod' ), 'multiple' => __( 'Несколько мест', 'prihod' ) ), $answers['places_mode'] ?? 'one' ); }
        for ( $index = 0; $index < max( 3, count( $answers[$group] ?? array() ) ); ++$index ) {
            echo '<div class="prihod-wizard-fields">';
            self::field( $group . '[' . $index . '][name]', __( 'Название / имя', 'prihod' ), $answers[$group][$index]['name'] ?? '' );
            $key = 'locations' === $group ? 'address' : 'rank';
            self::field( $group . '[' . $index . '][' . $key . ']', 'locations' === $group ? __( 'Адрес', 'prihod' ) : __( 'Сан / должность', 'prihod' ), $answers[$group][$index][$key] ?? '' );
            echo '</div>';
        }
        echo '<p>' . esc_html__( 'Дополнительные записи и связи можно добавить позже в разделах мест служения и духовенства.', 'prihod' ) . '</p>';
    }
    private static function pages( array $answers ): void {
        $site = Site_Plan::analyze(); $resources = get_option( 'prihod_resources', array() ); $features = Wizard_Interview::features( $answers );
        echo '<input type="hidden" name="pages_present" value="1"><div class="prihod-wizard-pages">';
        foreach ( Site_Plan::pages() as $key => $template ) {
            $feature = in_array( $key, array( 'news', 'sermons' ), true ) ? 'publications' : $key;
            if ( isset( $features[$feature] ) && ! $features[$feature] ) { continue; }
            $selected = $answers['reuse'][$key] ?? ( $resources[$key]['id'] ?? 0 );
            $choices = array( 0 => __( 'Создать черновик', 'prihod' ) );
            foreach ( $site['pages'] as $id => $page ) { if ( 'page' === $page['type'] ) { $choices[$id] = $page['title']; if ( ! $selected && $page['title'] === $template['title'] ) { $selected = $id; } } }
            echo '<article><label><input type="checkbox" name="pages[' . esc_attr( $key ) . ']" value="1"' . checked( ! isset( $answers['pages'] ) || ! empty( $answers['pages'][$key] ), true, false ) . '> <strong>' . esc_html( $template['title'] ) . '</strong></label>';
            self::select( 'reuse[' . $key . ']', __( 'Использовать страницу', 'prihod' ), $choices, $selected );
            if ( $selected && current_user_can( 'read_post', $selected ) ) { echo '<a href="' . esc_url( 'publish' === get_post_status( $selected ) ? get_permalink( $selected ) : get_preview_post_link( $selected ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Предварительный просмотр', 'prihod' ) . '</a>'; }
            echo '</article>';
        }
        echo '</div><details class="prihod-wizard-details"><summary>' . esc_html__( 'Оформление', 'prihod' ) . '</summary>';
        $choices = array( 'current' => __( 'Текущая тема', 'prihod' ) . ' — ' . wp_get_theme()->get( 'Name' ) );
        if ( wp_get_theme( 'prihod-theme' )->exists() && current_user_can( 'switch_themes' ) ) { $choices['prihod-theme'] = wp_get_theme( 'prihod-theme' )->get( 'Name' ); }
        self::select( 'theme_choice', __( 'Использовать тему', 'prihod' ), $choices, $answers['theme_choice'] ?? 'current' );
        $schemes = array( 'classic' => __( 'Классическая золотая', 'prihod' ), 'hospitality' => __( 'Светлая гостеприимная', 'prihod' ), 'editorial' => __( 'Журнальная', 'prihod' ), 'tradition' => __( 'Традиционная', 'prihod' ), 'blue' => __( 'Современная синяя', 'prihod' ) );
        if ( wp_get_theme( 'prihod-theme' )->exists() && current_user_can( 'manage_options' ) ) { self::select( 'scheme_choice', __( 'Оформление темы «Приход»', 'prihod' ), array( 'inherit' => __( 'Сохранить текущее оформление', 'prihod' ) ) + $schemes, $answers['scheme_choice'] ?? 'inherit' ); }
        echo '<div class="prihod-scheme-grid">';
        foreach ( $schemes as $scheme => $label ) { echo '<div class="prihod-scheme-preview ' . esc_attr( $scheme ) . '"><span aria-hidden="true"></span><strong>' . esc_html( $label ) . '</strong></div>'; }
        echo '</div><p>' . esc_html__( 'Выбор установленной темы и оформления применяется только после подтверждения плана. Установка новых тем выполняется вручную.', 'prihod' ) . '</p>';
        if ( wp_get_theme( 'prihod-theme' )->exists() && current_user_can( 'switch_themes' ) ) { echo '<a href="' . esc_url( add_query_arg( 'wp_theme_preview', 'prihod-theme', admin_url( 'site-editor.php' ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Предварительный просмотр темы', 'prihod' ) . '</a>'; }
        echo '</details>';
    }
    private static function plan( array $state ): void {
        $plan = $state['plan'] ?? Site_Plan::plan( Wizard_Interview::plan_answers( $state['answers'] ) ); $answers = $plan['answers'];
        echo '<div class="prihod-plan-list"><article><span class="dashicons dashicons-media-document" aria-hidden="true"></span><div><h3>' . esc_html__( 'Страницы сайта', 'prihod' ) . '</h3><ul>';
        foreach ( $plan['pages'] as $page ) { echo '<li>' . esc_html( $page['title'] ) . ' — ' . esc_html( 'create' === $page['action'] ? __( 'новый черновик', 'prihod' ) : __( 'использовать без изменения содержимого', 'prihod' ) ) . '</li>'; }
        echo '</ul></div></article><article><span class="dashicons dashicons-menu" aria-hidden="true"></span><div><h3>' . esc_html__( 'Меню', 'prihod' ) . '</h3><p>' . esc_html__( 'Главная и меню настраиваются отдельно при публикации.', 'prihod' ) . '</p></div></article><article><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><div><h3>' . esc_html__( 'Возможности', 'prihod' ) . '</h3><ul>';
        foreach ( array( 'schedule' => __( 'Расписание', 'prihod' ), 'requests' => __( 'Обращения', 'prihod' ) ) as $key => $label ) { echo '<li>' . esc_html( $label ) . ': ' . esc_html( ! empty( $answers['modules'][$key] ) ? __( 'Включено', 'prihod' ) : __( 'Выключено', 'prihod' ) ) . '</li>'; }
        if ( ! empty( $answers['publications'] ) ) { echo '<li>' . esc_html__( 'Рубрики новостей, объявлений и проповедей', 'prihod' ) . '</li>'; }
        foreach ( array( 'locations', 'clergy' ) as $group ) { foreach ( $answers[$group] ?? array() as $input ) { if ( is_string( $input['name'] ?? null ) && trim( $input['name'] ) ) { echo '<li>' . esc_html( $input['name'] ) . ' — ' . esc_html__( 'новый черновик', 'prihod' ) . '</li>'; } } }
        if ( ! empty( $answers['modules']['schedule'] ) && ! empty( $answers['service']['title'] ) ) { echo '<li>' . esc_html( $answers['service']['title'] ) . ' — ' . esc_html__( 'черновик первой службы', 'prihod' ) . '</li>'; }
        $planned_theme = 'prihod-theme' === ( $answers['theme_choice'] ?? '' ) ? 'prihod-theme' : $plan['site']['theme'];
        echo '</ul></div></article><article><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><div><h3>' . esc_html__( 'Оформление', 'prihod' ) . '</h3><p>' . esc_html( wp_get_theme( $planned_theme )->get( 'Name' ) );
        $scheme_names = array( 'classic' => __( 'Классическая золотая', 'prihod' ), 'hospitality' => __( 'Светлая гостеприимная', 'prihod' ), 'editorial' => __( 'Журнальная', 'prihod' ), 'tradition' => __( 'Традиционная', 'prihod' ), 'blue' => __( 'Современная синяя', 'prihod' ) );
        if ( isset( $scheme_names[$answers['scheme_choice'] ?? ''] ) ) { echo ' · ' . esc_html( $scheme_names[$answers['scheme_choice']] ); }
        echo '</p></div></article><article><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span><div><h3>' . esc_html__( 'Подключения', 'prihod' ) . '</h3><p>' . esc_html__( 'Дополнительные компоненты устанавливаются вручную и отдельно. Автоматических установок нет.', 'prihod' ) . '</p></div></article></div>';
        echo '<p class="prihod-ui-warning">' . esc_html__( 'До подтверждения страницы, меню и тема не изменяются. Новые материалы будут черновиками.', 'prihod' ) . '</p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'Подтверждаю этот план', 'prihod' ) . '</label><p><button class="button button-primary" name="operation" value="apply">' . esc_html__( 'Применить план', 'prihod' ) . ' →</button></p>';
        if ( ! empty( $answers['modules']['requests'] ) && ! get_privacy_policy_url() ) { echo '<p class="prihod-ui-warning">' . esc_html__( 'Перед публикацией обращений задайте страницу политики конфиденциальности.', 'prihod' ) . '</p>'; }
    }
    private static function result(): void {
        if ( ! Wizard_Result::render() ) { return; }
        echo '<details class="prihod-wizard-details"><summary>' . esc_html__( 'Публикация и меню', 'prihod' ) . '</summary><label><input type="checkbox" name="set_front" value="1"> ' . esc_html__( 'Назначить выбранную «Главную» главной страницей сайта', 'prihod' ) . '</label><label><input type="checkbox" name="navigation" value="1"> ' . esc_html__( 'Создать отдельную блоковую навигацию для выбора в редакторе сайта', 'prihod' ) . '</label>';
        self::select( 'menu_location', __( 'Меню', 'prihod' ), array( '' => __( 'Не менять меню', 'prihod' ) ) + get_registered_nav_menus(), '' );
        echo '<p>' . esc_html__( 'Выбор позиции меню заменит её текущую привязку новым меню «Прихода». Существующее меню и его пункты сохранятся.', 'prihod' ) . '</p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Подтверждаю публикацию и выбранные изменения главной/навигации', 'prihod' ) . '</label><button class="button button-primary" name="operation" value="publish">' . esc_html__( 'Опубликовать выбранное', 'prihod' ) . '</button></details>';
    }
    private static function site_details(): void {
        $site = Site_Plan::analyze();
        echo '<details class="prihod-wizard-details prihod-site-details"><summary>' . esc_html__( 'Сведения о текущем сайте', 'prihod' ) . '</summary><p>' . esc_html( wp_get_theme()->get( 'Name' ) . ' · ' . count( $site['pages'] ) . ' · ' . $site['language'] . ' / ' . $site['timezone'] ) . '</p></details>';
    }
    private static function welcome( array $state ): void {
        echo '<section class="prihod-wizard-welcome"><div><h2>' . esc_html__( 'Добро пожаловать!', 'prihod' ) . '</h2><p class="prihod-wizard-lead">' . esc_html__( 'Этот мастер поможет подготовить сайт вашего прихода.', 'prihod' ) . '</p><ul>';
        foreach ( array( 'admin-generic' => array( __( 'Пошаговая настройка', 'prihod' ), __( 'Ответьте на несколько вопросов', 'prihod' ) ), 'media-document' => array( __( 'Подготовка страниц', 'prihod' ), __( 'Страницы создаются черновиками после подтверждения', 'prihod' ) ), 'screenoptions' => array( __( 'Только то, что нужно', 'prihod' ), __( 'Вы выбираете нужные возможности', 'prihod' ) ), 'backup' => array( __( 'Можно настроить позже', 'prihod' ), __( 'Вернитесь к любому шагу, сохранив ресурсы', 'prihod' ) ) ) as $icon => $text ) { echo '<li><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span><div><strong>' . esc_html( $text[0] ) . '</strong><p>' . esc_html( $text[1] ) . '</p></div></li>'; }
        echo '</ul>'; self::form( $state ); echo '<button class="button button-primary" name="operation" value="start">' . esc_html__( 'Начать настройку', 'prihod' ) . ' →</button> <button class="button button-link" name="operation" value="dismiss">' . esc_html__( 'Настроить позже', 'prihod' ) . '</button></form></div><aside>';
        $options = get_option( 'prihod_theme_options', array() ); $photo = absint( $options['hero_image'] ?? 0 );
        if ( $photo ) { echo wp_get_attachment_image( $photo, 'large', false, array( 'alt' => '' ) ); }
        else { echo Admin_Navigation::symbol( 'church' ) . '<h3>' . esc_html__( 'Сайт прихода', 'prihod' ) . '</h3>'; }
        echo '</aside></section>';
    }
}
