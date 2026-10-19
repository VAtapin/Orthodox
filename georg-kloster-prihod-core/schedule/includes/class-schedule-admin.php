<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

final class Schedule_Admin {
    public static function columns( array $columns ): array {
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[$key] = 'date' === $key ? __( 'Publication date', 'georg-kloster-prihod-schedule' ) : $label;
            if ( 'title' === $key ) {
                $result += array( 'gkpsch_service_when' => __( 'Service date and time', 'georg-kloster-prihod-schedule' ), 'gkpsch_service_place' => __( 'Service place', 'georg-kloster-prihod-schedule' ), 'gkpsch_service_kind' => __( 'Type / recurrence', 'georg-kloster-prihod-schedule' ), 'gkpsch_service_changes' => __( 'Changes', 'georg-kloster-prihod-schedule' ) );
            }
        }
        return $result;
    }
    /** Read the existing rule without changing it or confusing recurrence with an occurrence. */
    public static function column_data( int $id ): array {
        $empty = array( 'date' => __( 'Not configured', 'georg-kloster-prihod-schedule' ), 'time' => '', 'timezone' => '', 'place' => '—', 'kind' => '—', 'changes' => '—' );
        $data = get_post_meta( $id, '_gkpsch_service', true );
        if ( ! is_array( $data ) ) { return $empty; }
        try { $rule = Schedule::validate( $data ); } catch ( \InvalidArgumentException $error ) { return $empty; }
        $zone = Schedule::timezone( $rule['location_id'], $rule['timezone'] );
        $date = ( new \DateTimeImmutable( $rule['date'] ) )->format( 'd.m.Y' );
        $kinds = array( 'single' => __( 'One-time', 'georg-kloster-prihod-schedule' ), 'regular' => __( 'Recurring', 'georg-kloster-prihod-schedule' ), 'holiday' => __( 'Feast day', 'georg-kloster-prihod-schedule' ) );
        $result = array( 'date' => $date, 'time' => $rule['time'], 'timezone' => $zone, 'place' => get_the_title( $rule['location_id'] ), 'kind' => $kinds[$rule['kind']], 'changes' => '—' );
        if ( 'regular' === $rule['kind'] ) {
            $result['date'] = $rule['until'] ? sprintf( __( 'From %1$s to %2$s', 'georg-kloster-prihod-schedule' ), $date, ( new \DateTimeImmutable( $rule['until'] ) )->format( 'd.m.Y' ) ) : sprintf( __( 'From %s, without an end date', 'georg-kloster-prihod-schedule' ), $date );
            $days = array( 1 => __( 'Mon', 'georg-kloster-prihod-schedule' ), __( 'Tue', 'georg-kloster-prihod-schedule' ), __( 'Wed', 'georg-kloster-prihod-schedule' ), __( 'Thu', 'georg-kloster-prihod-schedule' ), __( 'Fri', 'georg-kloster-prihod-schedule' ), __( 'Sat', 'georg-kloster-prihod-schedule' ), __( 'Sun', 'georg-kloster-prihod-schedule' ) );
            $result['kind'] .= ' · ' . implode( ', ', array_intersect_key( $days, array_flip( $rule['weekdays'] ) ) );
            if ( $rule['exceptions'] ) {
                $cancelled = count( array_filter( $rule['exceptions'], static fn( $change ) => 'cancel' === $change['action'] ) );
                $result['changes'] = sprintf( __( 'Cancelled dates: %1$d. Rescheduled: %2$d.', 'georg-kloster-prihod-schedule' ), $cancelled, count( array_filter( $rule['exceptions'], static fn( $change ) => 'replace' === $change['action'] ) ) );
                $colors = count( array_filter( $rule['exceptions'], static fn( $change ) => 'color' === $change['action'] ) );
                if ( $colors ) { $result['changes'] .= ' ' . sprintf( __( 'Dates with a separate color: %d.', 'georg-kloster-prihod-schedule' ), $colors ); }
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
            if ( $event['cancelled'] ) { $result['changes'] = __( 'Cancelled', 'georg-kloster-prihod-schedule' ); }
            elseif ( $event['moved'] ) { $result['changes'] = sprintf( __( 'Rescheduled from %s', 'georg-kloster-prihod-schedule' ), $date ); }
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
        wp_localize_script( 'gkpsch-schedule-admin', 'gkpschScheduleUI', array( 'description' => __( 'Service description', 'georg-kloster-prihod-schedule' ), 'endpoint' => rest_url( Plugin::REST_NAMESPACE . '/calendar-day' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'loading' => __( 'Checking the calendar…', 'georg-kloster-prihod-schedule' ), 'unavailable' => __( 'The calendar is temporarily unavailable. You can fill in the schedule manually.', 'georg-kloster-prihod-schedule' ), 'empty' => __( 'There are no great feasts on this date.', 'georg-kloster-prihod-schedule' ), 'liturgy' => __( 'Liturgy', 'georg-kloster-prihod-schedule' ), 'colorRecommendation' => __( 'Recommendation: %1$s — %2$s. You can change the color.', 'georg-kloster-prihod-schedule' ), 'colorEmpty' => __( 'There is no clear recommendation. Select a color manually.', 'georg-kloster-prihod-schedule' ), 'colorRegular' => __( 'The automatic color follows the calendar for each date. A date-specific exception takes priority.', 'georg-kloster-prihod-schedule' ), 'titleFilled' => __( 'The title is already filled in. Clear it first to use the suggestion.', 'georg-kloster-prihod-schedule' ) ) );
    }
    public static function input( string $name, string $value, string $type = 'text' ): string {
        return '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    }
    public static function locations( string $name, int $selected_id, bool $required = false ): string {
        $html = '<select name="' . esc_attr( $name ) . '"' . ( $required ? ' required aria-required="true" data-required-message="' . esc_attr__( 'Select a service place before saving.', 'georg-kloster-prihod-schedule' ) . '"' : '' ) . '><option value="' . ( $required ? '' : '0' ) . '">' . esc_html__( 'Select a place', 'georg-kloster-prihod-schedule' ) . '</option>';
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
        echo '<div class="gkpsch-service-editor"><div class="gkpsch-service-intro"><h2>' . esc_html__( 'When and where the service takes place', 'georg-kloster-prihod-schedule' ) . '</h2><p>' . esc_html__( 'Enter a date, time and place. For a recurring service, select the weekdays.', 'georg-kloster-prihod-schedule' ) . '</p></div><fieldset class="gkpsch-service-section"><legend>' . esc_html__( 'Basic details', 'georg-kloster-prihod-schedule' ) . '</legend><div class="gkpsch-service-grid"><label class="gkpsch-service-field">' . esc_html__( 'Service type', 'georg-kloster-prihod-schedule' ) . '<select name="gkpsch_service[kind]">';
        foreach ( array( 'single' => __( 'One-time', 'georg-kloster-prihod-schedule' ), 'regular' => __( 'Recurring', 'georg-kloster-prihod-schedule' ), 'holiday' => __( 'Feast day', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $rule['kind'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        foreach ( array( 'date' => __( 'Date / recurrence start', 'georg-kloster-prihod-schedule' ), 'time' => __( 'Start time', 'georg-kloster-prihod-schedule' ), 'end' => __( 'End time (optional)', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            echo '<label class="gkpsch-service-field">' . esc_html( $label ) . self::input( 'gkpsch_service[' . $key . ']', $rule[ $key ], 'date' === $key ? 'date' : 'time' ) . '</label>';
        }
        echo '<label class="gkpsch-service-field gkpsch-service-wide">' . esc_html__( 'Service place', 'georg-kloster-prihod-schedule' ) . ' *' . self::locations( 'gkpsch_service[location_id]', (int) $rule['location_id'], true ) . '<span class="description">' . esc_html__( 'Select a service place before saving.', 'georg-kloster-prihod-schedule' ) . '</span></label></div>';
        if ( ! get_posts( array( 'post_type' => \GeorgKlosterPrihodCore\Directory::location_types(), 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 1, 'fields' => 'ids' ) ) && current_user_can( 'manage_gkpc_profile' ) ) { echo '<p class="gkpsch-service-help"><a href="' . esc_url( admin_url( 'post-new.php?post_type=gkpc_location' ) ) . '">' . esc_html__( 'Add a service place first', 'georg-kloster-prihod-schedule' ) . '</a></p>'; }
        echo '</fieldset>';
        Schedule_Calendar::editor( $rule );
        echo '<fieldset class="gkpsch-service-section" data-gkpsch-repeat><legend>' . esc_html__( 'Recurrence', 'georg-kloster-prihod-schedule' ) . '</legend><div class="gkpsch-weekday-choices">';
        $days = array( __( 'Mon', 'georg-kloster-prihod-schedule' ), __( 'Tue', 'georg-kloster-prihod-schedule' ), __( 'Wed', 'georg-kloster-prihod-schedule' ), __( 'Thu', 'georg-kloster-prihod-schedule' ), __( 'Fri', 'georg-kloster-prihod-schedule' ), __( 'Sat', 'georg-kloster-prihod-schedule' ), __( 'Sun', 'georg-kloster-prihod-schedule' ) );
        foreach ( $days as $index => $label ) {
            echo '<label><input type="checkbox" name="gkpsch_service[weekdays][]" value="' . esc_attr( $index + 1 ) . '"' . checked( in_array( $index + 1, $rule['weekdays'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
        }
        echo '</div><label class="gkpsch-service-field">' . esc_html__( 'Last recurrence date (optional)', 'georg-kloster-prihod-schedule' ) . self::input( 'gkpsch_service[until]', $rule['until'], 'date' ) . '</label></fieldset><details class="gkpsch-service-section"><summary>' . esc_html__( 'Time zone and time details', 'georg-kloster-prihod-schedule' ) . '</summary><label class="gkpsch-service-field">' . esc_html__( 'Time zone (leave empty to use the place or parish time zone)', 'georg-kloster-prihod-schedule' ) . self::input( 'gkpsch_service[timezone]', $rule['timezone'] ) . '</label><p class="gkpsch-service-help">' . esc_html__( 'An end time earlier than or equal to the start time means the following day. Recurring services at nonexistent local times are skipped during the spring clock change; the first occurrence is used during the autumn clock change.', 'georg-kloster-prihod-schedule' ) . '</p></details><details class="gkpsch-service-section"' . ( $rule['exceptions'] ? ' open' : '' ) . '><summary>' . esc_html__( 'Date-specific exceptions', 'georg-kloster-prihod-schedule' ) . '</summary><div id="gkpsch-exceptions">';
        $index = 0;
        foreach ( $rule['exceptions'] as $date => $change ) { echo self::row( $index++, $date, $change, $rule ); }
        echo '</div>';
        echo '<template id="gkpsch-exception-template">' . self::row( '__INDEX__', '', array(), $rule ) . '</template>';
        echo '<button type="button" class="button" id="gkpsch-add-exception" data-index="' . esc_attr( $index ) . '">' . esc_html__( 'Add an exception', 'georg-kloster-prihod-schedule' ) . '</button></details></div>';
    }
    private static function row( $index, string $date, array $change, array $rule ): string {
        $prefix = 'gkpsch_exceptions[' . $index . ']';
        $html = '<fieldset class="gkpsch-exception-row"><legend>' . esc_html__( 'Exception', 'georg-kloster-prihod-schedule' ) . '</legend><div class="gkpsch-service-grid"><label class="gkpsch-service-field">' . esc_html__( 'Original date', 'georg-kloster-prihod-schedule' ) . self::input( $prefix . '[original]', $date, 'date' ) . '</label><label class="gkpsch-service-field">' . esc_html__( 'Action', 'georg-kloster-prihod-schedule' ) . '<select name="' . esc_attr( $prefix . '[action]' ) . '">';
        foreach ( array( 'cancel' => __( 'Cancellation', 'georg-kloster-prihod-schedule' ), 'replace' => __( 'Reschedule / change time', 'georg-kloster-prihod-schedule' ), 'color' => __( 'Vestment color for this date', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            $html .= '<option value="' . esc_attr( $key ) . '"' . selected( $change['action'] ?? 'cancel', $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        $html .= '</select></label></div><div class="gkpsch-service-grid" data-gkpsch-replacement>';
        foreach ( array( 'date' => __( 'New date', 'georg-kloster-prihod-schedule' ), 'time' => __( 'New start time', 'georg-kloster-prihod-schedule' ), 'end' => __( 'New end time', 'georg-kloster-prihod-schedule' ) ) as $key => $label ) {
            $html .= '<label class="gkpsch-service-field">' . esc_html( $label ) . self::input( $prefix . '[' . $key . ']', $change[ $key ] ?? ( 'date' === $key ? $date : $rule[ $key ] ), 'date' === $key ? 'date' : 'time' ) . '</label>';
        }
        $html .= '<label class="gkpsch-service-field">' . esc_html__( 'Place after rescheduling', 'georg-kloster-prihod-schedule' ) . self::locations( $prefix . '[location_id]', (int) ( $change['location_id'] ?? $rule['location_id'] ) ) . '</label></div>';
        return $html . Calendar_Colors::field( $prefix . '[vestment_color]', $change['vestment_color'] ?? ( $rule['vestment_color'] ?? '' ), array_key_exists( 'vestment_color', $change ) ) . '<label class="gkpsch-exception-remove"><input type="checkbox" name="' . esc_attr( $prefix . '[remove]' ) . '" value="1"> ' . esc_html__( 'Remove exception', 'georg-kloster-prihod-schedule' ) . '</label></fieldset>';
    }
    private static function can_save( int $id ): bool {
        if ( ! Modules::enabled( 'schedule' ) || ! current_user_can( 'manage_gkpsch_schedule' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return false; }
        $nonce = $_POST['gkpsch_service_nonce'] ?? '';
        return is_string( $nonce ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'gkpsch_service_' . $id ) && is_array( $_POST['gkpsch_service'] ?? null );
    }
    /** Reject the classic editor submission before WordPress writes its title/status. */
    public static function before_save( array $data, array $postarr ): array {
        $id = (int) ( $postarr['ID'] ?? 0 );
        if ( 'gkpsch_service' !== $data['post_type'] || ! $id || ! self::can_save( $id ) ) { return $data; }
        try { self::submitted_rule(); }
        catch ( \InvalidArgumentException $error ) {
            wp_die( esc_html( $error->getMessage() ) . '<br>' . esc_html__( 'The service was not saved. Return to the form and correct the error.', 'georg-kloster-prihod-schedule' ), esc_html__( 'Cannot save the service', 'georg-kloster-prihod-schedule' ), array( 'response' => 400, 'back_link' => true ) );
        }
        return $data;
    }
    private static function submitted_rule(): array {
        $input = wp_unslash( $_POST['gkpsch_service'] );
        $input['exceptions'] = array();
        $rows = wp_unslash( $_POST['gkpsch_exceptions'] ?? array() );
        if ( ! is_array( $rows ) ) { throw new \InvalidArgumentException( __( 'Invalid exceptions.', 'georg-kloster-prihod-schedule' ) ); }
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! is_string( $row['original'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Invalid original date.', 'georg-kloster-prihod-schedule' ) ); }
            $date = $row['original'] ?? '';
            if ( '' === $date || ! empty( $row['remove'] ) ) { continue; }
            if ( isset( $input['exceptions'][ $date ] ) ) { throw new \InvalidArgumentException( __( 'The original exception date is duplicated.', 'georg-kloster-prihod-schedule' ) ); }
            unset( $row['original'], $row['remove'] );
            $input['exceptions'][ $date ] = $row;
        }
        return Schedule::validate( $input );
    }
    public static function save( int $id ): void {
        if ( ! self::can_save( $id ) ) { return; }
        try {
            update_post_meta( $id, '_gkpsch_service', self::submitted_rule() );
            delete_transient( 'gkpsch_schedule_error_' . get_current_user_id() );
            do_action( 'gkpsch_schedule_changed', $id );
        } catch ( \InvalidArgumentException $error ) {
            set_transient( 'gkpsch_schedule_error_' . get_current_user_id(), $error->getMessage(), 60 );
        }
    }
    public static function notice(): void {
        $key = 'gkpsch_schedule_error_' . get_current_user_id();
        if ( current_user_can( 'manage_gkpsch_schedule' ) && ( $message = get_transient( $key ) ) ) {
            delete_transient( $key );
            echo '<div class="notice notice-error"><p>' . esc_html( $message ) . ' ' . esc_html__( 'The previous schedule has been retained.', 'georg-kloster-prihod-schedule' ) . '</p></div>';
        }
    }
}
