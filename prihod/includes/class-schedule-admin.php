<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Schedule_Admin {
    public static function columns( array $columns ): array {
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[$key] = 'date' === $key ? __( 'Дата публикации', 'prihod' ) : $label;
            if ( 'title' === $key ) {
                $result += array( 'prihod_service_when' => __( 'Дата и время службы', 'prihod' ), 'prihod_service_place' => __( 'Место служения', 'prihod' ), 'prihod_service_kind' => __( 'Тип / повторение', 'prihod' ), 'prihod_service_changes' => __( 'Изменения', 'prihod' ) );
            }
        }
        return $result;
    }
    /** Read the existing rule without changing it or confusing recurrence with an occurrence. */
    public static function column_data( int $id ): array {
        $empty = array( 'date' => __( 'Не настроено', 'prihod' ), 'time' => '', 'timezone' => '', 'place' => '—', 'kind' => '—', 'changes' => '—' );
        $data = get_post_meta( $id, '_prihod_service', true );
        if ( ! is_array( $data ) ) { return $empty; }
        try { $rule = Schedule::validate( $data ); } catch ( \InvalidArgumentException $error ) { return $empty; }
        $zone = Schedule::timezone( $rule['location_id'], $rule['timezone'] );
        $date = ( new \DateTimeImmutable( $rule['date'] ) )->format( 'd.m.Y' );
        $kinds = array( 'single' => __( 'Разовая', 'prihod' ), 'regular' => __( 'Регулярная', 'prihod' ), 'holiday' => __( 'Праздничная', 'prihod' ) );
        $result = array( 'date' => $date, 'time' => $rule['time'], 'timezone' => $zone, 'place' => get_the_title( $rule['location_id'] ), 'kind' => $kinds[$rule['kind']], 'changes' => '—' );
        if ( 'regular' === $rule['kind'] ) {
            $result['date'] = $rule['until'] ? sprintf( __( 'С %1$s по %2$s', 'prihod' ), $date, ( new \DateTimeImmutable( $rule['until'] ) )->format( 'd.m.Y' ) ) : sprintf( __( 'С %s, без конечной даты', 'prihod' ), $date );
            $days = array( 1 => __( 'Пн', 'prihod' ), __( 'Вт', 'prihod' ), __( 'Ср', 'prihod' ), __( 'Чт', 'prihod' ), __( 'Пт', 'prihod' ), __( 'Сб', 'prihod' ), __( 'Вс', 'prihod' ) );
            $result['kind'] .= ' · ' . implode( ', ', array_intersect_key( $days, array_flip( $rule['weekdays'] ) ) );
            if ( $rule['exceptions'] ) {
                $cancelled = count( array_filter( $rule['exceptions'], static fn( $change ) => 'cancel' === $change['action'] ) );
                $result['changes'] = sprintf( __( 'Отменённых дат: %1$d. Переносов: %2$d.', 'prihod' ), $cancelled, count( array_filter( $rule['exceptions'], static fn( $change ) => 'replace' === $change['action'] ) ) );
                $colors = count( array_filter( $rule['exceptions'], static fn( $change ) => 'color' === $change['action'] ) );
                if ( $colors ) { $result['changes'] .= ' ' . sprintf( __( 'Дат с отдельным цветом: %d.', 'prihod' ), $colors ); }
            }
        } else {
            // Use the same recurrence engine as the public schedule for overrides.
            $change = $rule['exceptions'][$rule['date']] ?? array();
            $effective_zone = $change['timezone'] ?? $zone;
            $anchor = new \DateTimeImmutable( $change['date'] ?? $rule['date'], new \DateTimeZone( $effective_zone ) );
            $rule = array_merge( $rule, array( 'timezone' => $zone, 'id' => $id, 'title' => get_the_title( $id ), 'description' => '' ) );
            $events = Occurrences::expand( $rule, $anchor->modify( '-1 day' ), $anchor->modify( '+2 days' ) );
            if ( ! $events ) { return $empty; }
            $event = $events[0];
            $result['date'] = $event['start']->format( 'd.m.Y' ); $result['time'] = $event['start']->format( 'H:i' ); $result['timezone'] = $event['start']->getTimezone()->getName(); $result['place'] = get_the_title( $event['location_id'] );
            if ( $event['cancelled'] ) { $result['changes'] = __( 'Отменено', 'prihod' ); }
            elseif ( $event['moved'] ) { $result['changes'] = sprintf( __( 'Перенесено с %s', 'prihod' ), $date ); }
        }
        return $result;
    }
    public static function column( string $column, int $id ): void {
        if ( ! in_array( $column, array( 'prihod_service_when', 'prihod_service_place', 'prihod_service_kind', 'prihod_service_changes' ), true ) ) { return; }
        $data = self::column_data( $id );
        if ( 'prihod_service_when' === $column ) { echo '<strong>' . esc_html( $data['date'] ) . '</strong><br>' . esc_html( $data['time'] ) . '<br><span class="description">' . esc_html( $data['timezone'] ) . '</span>'; }
        else { echo esc_html( $data[ array( 'prihod_service_place' => 'place', 'prihod_service_kind' => 'kind', 'prihod_service_changes' => 'changes' )[$column] ] ); }
    }
    public static function assets(): void {
        if ( 'prihod_service' !== ( get_current_screen()->post_type ?? '' ) ) { return; }
        wp_enqueue_style( 'prihod-schedule-admin', plugins_url( 'assets/schedule-admin.css', PRIHOD_FILE ), array(), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/schedule-admin.css' ) );
        wp_enqueue_script( 'prihod-schedule-admin', plugins_url( 'assets/schedule-admin.js', PRIHOD_FILE ), array(), PRIHOD_VERSION, true );
        wp_enqueue_script( 'prihod-calendar-colors', plugins_url( 'assets/calendar-colors.js', PRIHOD_FILE ), array( 'prihod-schedule-admin' ), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/calendar-colors.js' ), true );
        wp_enqueue_script( 'prihod-calendar-admin', plugins_url( 'assets/calendar-admin.js', PRIHOD_FILE ), array( 'prihod-schedule-admin' ), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/calendar-admin.js' ), true );
        wp_localize_script( 'prihod-schedule-admin', 'prihodScheduleUI', array( 'description' => __( 'Описание богослужения', 'prihod' ), 'endpoint' => rest_url( Plugin::REST_NAMESPACE . '/calendar-day' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'loading' => __( 'Проверяем календарь…', 'prihod' ), 'unavailable' => __( 'Календарь временно недоступен. Расписание можно заполнить вручную.', 'prihod' ), 'empty' => __( 'Великих праздников на эту дату нет.', 'prihod' ), 'liturgy' => __( 'Литургия', 'prihod' ), 'colorRecommendation' => __( 'Рекомендация: %1$s — %2$s. Цвет можно изменить.', 'prihod' ), 'colorEmpty' => __( 'Однозначной рекомендации нет. Выберите цвет вручную.', 'prihod' ), 'colorRegular' => __( 'Автоматический цвет определяется по календарю для каждой даты. Исключение на дату имеет приоритет.', 'prihod' ), 'titleFilled' => __( 'Название уже заполнено. Чтобы использовать подсказку, сначала очистите его.', 'prihod' ) ) );
    }
    public static function input( string $name, string $value, string $type = 'text' ): string {
        return '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    }
    public static function locations( string $name, int $selected_id ): string {
        $html = '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Выберите место', 'prihod' ) . '</option>';
        foreach ( get_posts( array( 'post_type' => 'prihod_location', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
            $html .= '<option value="' . esc_attr( $post->ID ) . '"' . selected( $selected_id, $post->ID, false ) . '>' . esc_html( $post->post_title ) . '</option>';
        }
        return $html . '</select>';
    }
    public static function render( $post ): void {
        $data = get_post_meta( $post->ID, '_prihod_service', true );
        $rule = array_merge( Schedule::defaults(), is_array( $data ) ? $data : array() );
        $rule['_color_persisted'] = is_array( $data ) && array_key_exists( 'vestment_color', $data );
        $suggested_date = $_GET['prihod_calendar_date'] ?? '';
        if ( 'auto-draft' === $post->post_status && is_string( $suggested_date ) && Occurrences::date( $suggested_date ) ) { $rule['date'] = $suggested_date; $rule['time'] = ''; }
        wp_nonce_field( 'prihod_service_' . $post->ID, 'prihod_service_nonce' );
        echo '<div class="prihod-service-editor"><div class="prihod-service-intro"><h2>' . esc_html__( 'Когда и где состоится богослужение', 'prihod' ) . '</h2><p>' . esc_html__( 'Укажите дату, время и место. Для регулярной службы выберите дни недели.', 'prihod' ) . '</p></div><fieldset class="prihod-service-section"><legend>' . esc_html__( 'Основные сведения', 'prihod' ) . '</legend><div class="prihod-service-grid"><label class="prihod-service-field">' . esc_html__( 'Тип службы', 'prihod' ) . '<select name="prihod_service[kind]">';
        foreach ( array( 'single' => __( 'Разовая', 'prihod' ), 'regular' => __( 'Регулярная', 'prihod' ), 'holiday' => __( 'Праздничная', 'prihod' ) ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $rule['kind'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        foreach ( array( 'date' => __( 'Дата / начало повторений', 'prihod' ), 'time' => __( 'Начало', 'prihod' ), 'end' => __( 'Окончание (необязательно)', 'prihod' ) ) as $key => $label ) {
            echo '<label class="prihod-service-field">' . esc_html( $label ) . self::input( 'prihod_service[' . $key . ']', $rule[ $key ], 'date' === $key ? 'date' : 'time' ) . '</label>';
        }
        echo '<label class="prihod-service-field prihod-service-wide">' . esc_html__( 'Место служения', 'prihod' ) . self::locations( 'prihod_service[location_id]', (int) $rule['location_id'] ) . '</label></div>';
        if ( ! get_posts( array( 'post_type' => 'prihod_location', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 1, 'fields' => 'ids' ) ) && current_user_can( 'manage_prihod' ) ) { echo '<p class="prihod-service-help"><a href="' . esc_url( admin_url( 'post-new.php?post_type=prihod_location' ) ) . '">' . esc_html__( 'Сначала добавьте место служения', 'prihod' ) . '</a></p>'; }
        echo '</fieldset>';
        Schedule_Calendar::editor( $rule );
        echo '<fieldset class="prihod-service-section" data-prihod-repeat><legend>' . esc_html__( 'Повторение', 'prihod' ) . '</legend><div class="prihod-weekday-choices">';
        $days = array( __( 'Пн', 'prihod' ), __( 'Вт', 'prihod' ), __( 'Ср', 'prihod' ), __( 'Чт', 'prihod' ), __( 'Пт', 'prihod' ), __( 'Сб', 'prihod' ), __( 'Вс', 'prihod' ) );
        foreach ( $days as $index => $label ) {
            echo '<label><input type="checkbox" name="prihod_service[weekdays][]" value="' . esc_attr( $index + 1 ) . '"' . checked( in_array( $index + 1, $rule['weekdays'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
        }
        echo '</div><label class="prihod-service-field">' . esc_html__( 'Последняя дата повторений (необязательно)', 'prihod' ) . self::input( 'prihod_service[until]', $rule['until'], 'date' ) . '</label></fieldset><details class="prihod-service-section"><summary>' . esc_html__( 'Часовой пояс и особенности времени', 'prihod' ) . '</summary><label class="prihod-service-field">' . esc_html__( 'Часовой пояс (пусто — пояс места или прихода)', 'prihod' ) . self::input( 'prihod_service[timezone]', $rule['timezone'] ) . '</label><p class="prihod-service-help">' . esc_html__( 'Окончание раньше или равное началу означает следующий день. При весеннем переводе часов несуществующее время регулярной службы пропускается; осенью используется первое вхождение времени.', 'prihod' ) . '</p></details><details class="prihod-service-section"' . ( $rule['exceptions'] ? ' open' : '' ) . '><summary>' . esc_html__( 'Исключения отдельных дат', 'prihod' ) . '</summary><div id="prihod-exceptions">';
        $index = 0;
        foreach ( $rule['exceptions'] as $date => $change ) { echo self::row( $index++, $date, $change, $rule ); }
        echo '</div>';
        echo '<template id="prihod-exception-template">' . self::row( '__INDEX__', '', array(), $rule ) . '</template>';
        echo '<button type="button" class="button" id="prihod-add-exception" data-index="' . esc_attr( $index ) . '">' . esc_html__( 'Добавить исключение', 'prihod' ) . '</button></details></div>';
    }
    private static function row( $index, string $date, array $change, array $rule ): string {
        $prefix = 'prihod_exceptions[' . $index . ']';
        $html = '<fieldset class="prihod-exception-row"><legend>' . esc_html__( 'Исключение', 'prihod' ) . '</legend><div class="prihod-service-grid"><label class="prihod-service-field">' . esc_html__( 'Исходная дата', 'prihod' ) . self::input( $prefix . '[original]', $date, 'date' ) . '</label><label class="prihod-service-field">' . esc_html__( 'Действие', 'prihod' ) . '<select name="' . esc_attr( $prefix . '[action]' ) . '">';
        foreach ( array( 'cancel' => __( 'Отмена', 'prihod' ), 'replace' => __( 'Перенос / изменение времени', 'prihod' ), 'color' => __( 'Цвет облачения на эту дату', 'prihod' ) ) as $key => $label ) {
            $html .= '<option value="' . esc_attr( $key ) . '"' . selected( $change['action'] ?? 'cancel', $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        $html .= '</select></label></div><div class="prihod-service-grid" data-prihod-replacement>';
        foreach ( array( 'date' => __( 'Новая дата', 'prihod' ), 'time' => __( 'Новое начало', 'prihod' ), 'end' => __( 'Новое окончание', 'prihod' ) ) as $key => $label ) {
            $html .= '<label class="prihod-service-field">' . esc_html( $label ) . self::input( $prefix . '[' . $key . ']', $change[ $key ] ?? ( 'date' === $key ? $date : $rule[ $key ] ), 'date' === $key ? 'date' : 'time' ) . '</label>';
        }
        $html .= '<label class="prihod-service-field">' . esc_html__( 'Место после переноса', 'prihod' ) . self::locations( $prefix . '[location_id]', (int) ( $change['location_id'] ?? $rule['location_id'] ) ) . '</label></div>';
        return $html . Calendar_Colors::field( $prefix . '[vestment_color]', $change['vestment_color'] ?? ( $rule['vestment_color'] ?? '' ), array_key_exists( 'vestment_color', $change ) ) . '<label class="prihod-exception-remove"><input type="checkbox" name="' . esc_attr( $prefix . '[remove]' ) . '" value="1"> ' . esc_html__( 'Удалить исключение', 'prihod' ) . '</label></fieldset>';
    }
    public static function save( int $id ): void {
        if ( ! Modules::enabled( 'schedule' ) || ! current_user_can( 'manage_prihod_schedule' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return; }
        $nonce = $_POST['prihod_service_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'prihod_service_' . $id ) || ! is_array( $_POST['prihod_service'] ?? null ) ) { return; }
        try {
            $input = wp_unslash( $_POST['prihod_service'] );
            $input['exceptions'] = array();
            $rows = wp_unslash( $_POST['prihod_exceptions'] ?? array() );
            if ( ! is_array( $rows ) ) { throw new \InvalidArgumentException( __( 'Некорректные исключения.', 'prihod' ) ); }
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) || ! is_string( $row['original'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Некорректная исходная дата.', 'prihod' ) ); }
                $date = $row['original'] ?? '';
                if ( '' === $date || ! empty( $row['remove'] ) ) { continue; }
                if ( isset( $input['exceptions'][ $date ] ) ) { throw new \InvalidArgumentException( __( 'Исходная дата исключения повторяется.', 'prihod' ) ); }
                unset( $row['original'], $row['remove'] );
                $input['exceptions'][ $date ] = $row;
            }
            update_post_meta( $id, '_prihod_service', Schedule::validate( $input ) );
            do_action( 'prihod_schedule_changed', $id );
        } catch ( \InvalidArgumentException $error ) {
            set_transient( 'prihod_schedule_error_' . get_current_user_id(), $error->getMessage(), 60 );
        }
    }
    public static function notice(): void {
        $key = 'prihod_schedule_error_' . get_current_user_id();
        if ( current_user_can( 'manage_prihod_schedule' ) && ( $message = get_transient( $key ) ) ) {
            delete_transient( $key );
            echo '<div class="notice notice-error"><p>' . esc_html( $message ) . ' ' . esc_html__( 'Предыдущее расписание сохранено.', 'prihod' ) . '</p></div>';
        }
    }
}
