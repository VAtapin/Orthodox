<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

final class Schedule_Admin {
    public static function columns( array $columns ): array {
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[$key] = 'date' === $key ? __( 'Дата публикации', 'georg-kloster-prihod-schedule' ) : $label;
            if ( 'title' === $key ) {
                $result += array( 'gkpsch_service_when' => __( 'Дата и время службы', 'georg-kloster-prihod-schedule' ), 'gkpsch_service_place' => __( 'Место служения', 'georg-kloster-prihod-schedule' ), 'gkpsch_service_kind' => __( 'Тип / повторение', 'georg-kloster-prihod-schedule' ), 'gkpsch_service_changes' => __( 'Изменения', 'georg-kloster-prihod-schedule' ) );
            }
        }
        return $result;
    }
    /** Read the existing rule without changing it or confusing recurrence with an occurrence. */
    public static function column_data( int $id ): array {
        $empty = array( 'date' => __( 'Не настроено', 'georg-kloster-prihod-schedule' ), 'time' => '', 'timezone' => '', 'place' => '—', 'kind' => '—', 'changes' => '—' );
        $data = get_post_meta( $id, '_gkpsch_service', true );
        if ( ! is_array( $data ) ) { return $empty; }
        try { $rule = Schedule::validate( $data ); } catch ( \InvalidArgumentException $error ) { return $empty; }
        $zone = Schedule::timezone( $rule['location_id'], $rule['timezone'] );
        $date = ( new \DateTimeImmutable( $rule['date'] ) )->format( 'd.m.Y' );
        $kinds = array( 'single' => __( 'Разовая', 'georg-kloster-prihod-schedule' ), 'regular' => __( 'Регулярная', 'georg-kloster-prihod-schedule' ), 'holiday' => __( 'Праздничная', 'georg-kloster-prihod-schedule' ) );
        $result = array( 'date' => $date, 'time' => $rule['time'], 'timezone' => $zone, 'place' => get_the_title( $rule['location_id'] ), 'kind' => $kinds[$rule['kind']], 'changes' => '—' );
        if ( 'regular' === $rule['kind'] ) {
            $result['date'] = $rule['until'] ? sprintf( __( 'С %1$s по %2$s', 'georg-kloster-prihod-schedule' ), $date, ( new \DateTimeImmutable( $rule['until'] ) )->format( 'd.m.Y' ) ) : sprintf( __( 'С %s, без конечной даты', 'georg-kloster-prihod-schedule' ), $date );
            $days = array( 1 => __( 'Пн', 'georg-kloster-prihod-schedule' ), __( 'Вт', 'georg-kloster-prihod-schedule' ), __( 'Ср', 'georg-kloster-prihod-schedule' ), __( 'Чт', 'georg-kloster-prihod-schedule' ), __( 'Пт', 'georg-kloster-prihod-schedule' ), __( 'Сб', 'georg-kloster-prihod-schedule' ), __( 'Вс', 'georg-kloster-prihod-schedule' ) );
            $result['kind'] .= ' · ' . implode( ', ', array_intersect_key( $days, array_flip( $rule['weekdays'] ) ) );
            if ( $rule['exceptions'] ) {
                $cancelled = count( array_filter( $rule['exceptions'], static fn( $change ) => 'cancel' === $change['action'] ) );
                $result['changes'] = sprintf( __( 'Отменённых дат: %1$d. Переносов: %2$d.', 'georg-kloster-prihod-schedule' ), $cancelled, count( array_filter( $rule['exceptions'], static fn( $change ) => 'replace' === $change['action'] ) ) );
                $colors = count( array_filter( $rule['exceptions'], static fn( $change ) => 'color' === $change['action'] ) );
                if ( $colors ) { $result['changes'] .= ' ' . sprintf( __( 'Дат с отдельным цветом: %d.', 'georg-kloster-prihod-schedule' ), $colors ); }
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
            if ( $event['cancelled'] ) { $result['changes'] = __( 'Отменено', 'georg-kloster-prihod-schedule' ); }
            elseif ( $event['moved'] ) { $result['changes'] = sprintf( __( 'Перенесено с %s', 'georg-kloster-prihod-schedule' ), $date ); }
        }
        return $result;
    }
    public static function column( string $column, int $id ): void {
        if ( ! in_array( $column, array( 'gkpsch_service_when', 'gkpsch_service_place', 'gkpsch_service_kind', 'gkpsch_service_changes' ), true ) ) { return; }
        $data = self::column_data( $id );
        if ( 'gkpsch_service_when' === $column ) { echo '<strong>' . esc_html( $data['date'] ) . '</strong><br>' . esc_html( $data['time'] ) . '<br><span class="description">' . esc_html( $data['timezone'] ) . '</span>'; }
        else { echo esc_html( $data[ array( 'gkpsch_service_place' => 'place', 'gkpsch_service_kind' => 'kind', 'gkpsch_service_changes' => 'changes' )[$column] ] ); }
    }
    public static function assets(): void {
        if ( 'gkpsch_service' !== ( get_current_screen()->post_type ?? '' ) ) { return; }
        wp_enqueue_style( 'gkpsch-schedule-admin', plugins_url( 'assets/schedule-admin.css', GKPSCH_FILE ), array(), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/schedule-admin.css' ) );
        wp_enqueue_script( 'gkpsch-schedule-admin', plugins_url( 'assets/schedule-admin.js', GKPSCH_FILE ), array(), GKPSCH_VERSION, true );
        wp_enqueue_script( 'gkpsch-calendar-colors', plugins_url( 'assets/calendar-colors.js', GKPSCH_FILE ), array( 'gkpsch-schedule-admin' ), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/calendar-colors.js' ), true );
        wp_enqueue_script( 'gkpsch-calendar-admin', plugins_url( 'assets/calendar-admin.js', GKPSCH_FILE ), array( 'gkpsch-schedule-admin' ), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/calendar-admin.js' ), true );
        wp_localize_script( 'gkpsch-schedule-admin', 'gkpschScheduleUI', array( 'description' => __( 'Описание богослужения', 'georg-kloster-prihod-schedule' ), 'endpoint' => rest_url( Plugin::REST_NAMESPACE . '/calendar-day' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'loading' => __( 'Проверяем календарь…', 'georg-kloster-prihod-schedule' ), 'unavailable' => __( 'Календарь временно недоступен. Расписание можно заполнить вручную.', 'georg-kloster-prihod-schedule' ), 'empty' => __( 'Великих праздников на эту дату нет.', 'georg-kloster-prihod-schedule' ), 'liturgy' => __( 'Литургия', 'georg-kloster-prihod-schedule' ), 'colorRecommendation' => __( 'Рекомендация: %1$s — %2$s. Цвет можно изменить.', 'georg-kloster-prihod-schedule' ), 'colorEmpty' => __( 'Однозначной рекомендации нет. Выберите цвет вручную.', 'georg-kloster-prihod-schedule' ), 'colorRegular' => __( 'Автоматический цвет определяется по календарю для каждой даты. Исключение на дату имеет приоритет.', 'georg-kloster-prihod-schedule' ), 'titleFilled' => __( 'Название уже заполнено. Чтобы использовать подсказку, сначала очистите его.', 'georg-kloster-prihod-schedule' ) ) );
    }
    public static function input( string $name, string $value, string $type = 'text' ): string {
        return '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    }
    public static function locations( string $name, int $selected_id ): string {
        $html = '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Выберите место', 'georg-kloster-prihod-schedule' ) . '</option>';
        foreach ( get_posts( array( 'post_type' => \GeorgKlosterPrihodCore\Directory::location_types(), 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
            $html .= '<option value="' . esc_attr( $post->ID ) . '"' . selected( $selected_id, $post->ID, false ) . '>' . esc_html( $post->post_title ) . '</option>';
        }
        return $html . '</select>';
    }
    public static function render( $post ): void {
        $data = get_post_meta( $post->ID, '_gkpsch_service', true );
        $rule = array_merge( Schedule::defaults(), is_array( $data ) ? $data : array() );
        $rule['_color_persisted'] = is_array( $data ) && array_key_exists( 'vestment_color', $data );
        $suggested_date = $_GET['gkpsch_calendar_date'] ?? '';
        if ( 'auto-draft' === $post->post_status && is_string( $suggested_date ) && Occurrences::date( $suggested_date ) ) { $rule['date'] = $suggested_date; $rule['time'] = ''; }
        wp_nonce_field( 'gkpsch_service_' . $post->ID, 'gkpsch_service_nonce' );
        echo '<div class="gkpsch-service-editor"><div class="gkpsch-service-intro"><h2>' . esc_html__( 'Когда и где состоится богослужение', 'georg-kloster-prihod-schedule' ) . '</h2><p>' . esc_html__( 'Укажите дату, время и место. Для регулярной службы выберите дни недели.', 'georg-kloster-prihod-schedule' ) . '</p></div><fieldset class="gkpsch-service-section"><legend>' . esc_html__( 'Основные сведения', 'georg-kloster-prihod-schedule' ) . '</legend><div class="gkpsch-service-grid"><label class="gkpsch-service-field">' . esc_html__( 'Тип службы', 'georg-kloster-prihod-schedule' ) . '<select name="gkpsch_service[kind]">';
        foreach ( array( 'single' => __( 'Разовая', 'georg-kloster-prihod-schedule' ), 'regular' => __( 'Регулярная', 'georg-kloster-prihod-schedule' ), 'holiday' => __( 'Праздничная', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $rule['kind'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        foreach ( array( 'date' => __( 'Дата / начало повторений', 'georg-kloster-prihod-schedule' ), 'time' => __( 'Начало', 'georg-kloster-prihod-schedule' ), 'end' => __( 'Окончание (необязательно)', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            echo '<label class="gkpsch-service-field">' . esc_html( $label ) . self::input( 'gkpsch_service[' . $key . ']', $rule[ $key ], 'date' === $key ? 'date' : 'time' ) . '</label>';
        }
        echo '<label class="gkpsch-service-field gkpsch-service-wide">' . esc_html__( 'Место служения', 'georg-kloster-prihod-schedule' ) . self::locations( 'gkpsch_service[location_id]', (int) $rule['location_id'] ) . '</label></div>';
        if ( ! get_posts( array( 'post_type' => \GeorgKlosterPrihodCore\Directory::location_types(), 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 1, 'fields' => 'ids' ) ) && current_user_can( 'manage_gkpc_profile' ) ) { echo '<p class="gkpsch-service-help"><a href="' . esc_url( admin_url( 'post-new.php?post_type=gkpc_location' ) ) . '">' . esc_html__( 'Сначала добавьте место служения', 'georg-kloster-prihod-schedule' ) . '</a></p>'; }
        echo '</fieldset>';
        Schedule_Calendar::editor( $rule );
        echo '<fieldset class="gkpsch-service-section" data-gkpsch-repeat><legend>' . esc_html__( 'Повторение', 'georg-kloster-prihod-schedule' ) . '</legend><div class="gkpsch-weekday-choices">';
        $days = array( __( 'Пн', 'georg-kloster-prihod-schedule' ), __( 'Вт', 'georg-kloster-prihod-schedule' ), __( 'Ср', 'georg-kloster-prihod-schedule' ), __( 'Чт', 'georg-kloster-prihod-schedule' ), __( 'Пт', 'georg-kloster-prihod-schedule' ), __( 'Сб', 'georg-kloster-prihod-schedule' ), __( 'Вс', 'georg-kloster-prihod-schedule' ) );
        foreach ( $days as $index => $label ) {
            echo '<label><input type="checkbox" name="gkpsch_service[weekdays][]" value="' . esc_attr( $index + 1 ) . '"' . checked( in_array( $index + 1, $rule['weekdays'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
        }
        echo '</div><label class="gkpsch-service-field">' . esc_html__( 'Последняя дата повторений (необязательно)', 'georg-kloster-prihod-schedule' ) . self::input( 'gkpsch_service[until]', $rule['until'], 'date' ) . '</label></fieldset><details class="gkpsch-service-section"><summary>' . esc_html__( 'Часовой пояс и особенности времени', 'georg-kloster-prihod-schedule' ) . '</summary><label class="gkpsch-service-field">' . esc_html__( 'Часовой пояс (пусто — пояс места или прихода)', 'georg-kloster-prihod-schedule' ) . self::input( 'gkpsch_service[timezone]', $rule['timezone'] ) . '</label><p class="gkpsch-service-help">' . esc_html__( 'Окончание раньше или равное началу означает следующий день. При весеннем переводе часов несуществующее время регулярной службы пропускается; осенью используется первое вхождение времени.', 'georg-kloster-prihod-schedule' ) . '</p></details><details class="gkpsch-service-section"' . ( $rule['exceptions'] ? ' open' : '' ) . '><summary>' . esc_html__( 'Исключения отдельных дат', 'georg-kloster-prihod-schedule' ) . '</summary><div id="gkpsch-exceptions">';
        $index = 0;
        foreach ( $rule['exceptions'] as $date => $change ) { echo self::row( $index++, $date, $change, $rule ); }
        echo '</div>';
        echo '<template id="gkpsch-exception-template">' . self::row( '__INDEX__', '', array(), $rule ) . '</template>';
        echo '<button type="button" class="button" id="gkpsch-add-exception" data-index="' . esc_attr( $index ) . '">' . esc_html__( 'Добавить исключение', 'georg-kloster-prihod-schedule' ) . '</button></details></div>';
    }
    private static function row( $index, string $date, array $change, array $rule ): string {
        $prefix = 'gkpsch_exceptions[' . $index . ']';
        $html = '<fieldset class="gkpsch-exception-row"><legend>' . esc_html__( 'Исключение', 'georg-kloster-prihod-schedule' ) . '</legend><div class="gkpsch-service-grid"><label class="gkpsch-service-field">' . esc_html__( 'Исходная дата', 'georg-kloster-prihod-schedule' ) . self::input( $prefix . '[original]', $date, 'date' ) . '</label><label class="gkpsch-service-field">' . esc_html__( 'Действие', 'georg-kloster-prihod-schedule' ) . '<select name="' . esc_attr( $prefix . '[action]' ) . '">';
        foreach ( array( 'cancel' => __( 'Отмена', 'georg-kloster-prihod-schedule' ), 'replace' => __( 'Перенос / изменение времени', 'georg-kloster-prihod-schedule' ), 'color' => __( 'Цвет облачения на эту дату', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            $html .= '<option value="' . esc_attr( $key ) . '"' . selected( $change['action'] ?? 'cancel', $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        $html .= '</select></label></div><div class="gkpsch-service-grid" data-gkpsch-replacement>';
        foreach ( array( 'date' => __( 'Новая дата', 'georg-kloster-prihod-schedule' ), 'time' => __( 'Новое начало', 'georg-kloster-prihod-schedule' ), 'end' => __( 'Новое окончание', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            $html .= '<label class="gkpsch-service-field">' . esc_html( $label ) . self::input( $prefix . '[' . $key . ']', $change[ $key ] ?? ( 'date' === $key ? $date : $rule[ $key ] ), 'date' === $key ? 'date' : 'time' ) . '</label>';
        }
        $html .= '<label class="gkpsch-service-field">' . esc_html__( 'Место после переноса', 'georg-kloster-prihod-schedule' ) . self::locations( $prefix . '[location_id]', (int) ( $change['location_id'] ?? $rule['location_id'] ) ) . '</label></div>';
        return $html . Calendar_Colors::field( $prefix . '[vestment_color]', $change['vestment_color'] ?? ( $rule['vestment_color'] ?? '' ), array_key_exists( 'vestment_color', $change ) ) . '<label class="gkpsch-exception-remove"><input type="checkbox" name="' . esc_attr( $prefix . '[remove]' ) . '" value="1"> ' . esc_html__( 'Удалить исключение', 'georg-kloster-prihod-schedule' ) . '</label></fieldset>';
    }
    public static function save( int $id ): void {
        if ( ! Modules::enabled( 'schedule' ) || ! current_user_can( 'manage_gkpsch_schedule' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return; }
        $nonce = $_POST['gkpsch_service_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'gkpsch_service_' . $id ) || ! is_array( $_POST['gkpsch_service'] ?? null ) ) { return; }
        try {
            $input = wp_unslash( $_POST['gkpsch_service'] );
            $input['exceptions'] = array();
            $rows = wp_unslash( $_POST['gkpsch_exceptions'] ?? array() );
            if ( ! is_array( $rows ) ) { throw new \InvalidArgumentException( __( 'Некорректные исключения.', 'georg-kloster-prihod-schedule' ) ); }
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) || ! is_string( $row['original'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Некорректная исходная дата.', 'georg-kloster-prihod-schedule' ) ); }
                $date = $row['original'] ?? '';
                if ( '' === $date || ! empty( $row['remove'] ) ) { continue; }
                if ( isset( $input['exceptions'][ $date ] ) ) { throw new \InvalidArgumentException( __( 'Исходная дата исключения повторяется.', 'georg-kloster-prihod-schedule' ) ); }
                unset( $row['original'], $row['remove'] );
                $input['exceptions'][ $date ] = $row;
            }
            update_post_meta( $id, '_gkpsch_service', Schedule::validate( $input ) );
            do_action( 'gkpsch_schedule_changed', $id );
        } catch ( \InvalidArgumentException $error ) {
            set_transient( 'gkpsch_schedule_error_' . get_current_user_id(), $error->getMessage(), 60 );
        }
    }
    public static function notice(): void {
        $key = 'gkpsch_schedule_error_' . get_current_user_id();
        if ( current_user_can( 'manage_gkpsch_schedule' ) && ( $message = get_transient( $key ) ) ) {
            delete_transient( $key );
            echo '<div class="notice notice-error"><p>' . esc_html( $message ) . ' ' . esc_html__( 'Предыдущее расписание сохранено.', 'georg-kloster-prihod-schedule' ) . '</p></div>';
        }
    }
}
