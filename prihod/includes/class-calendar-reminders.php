<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

/** Per-site, explicitly configured WordPress Cron; no service is created by mail. */
final class Calendar_Reminders {
    public const OPTION = 'prihod_calendar_reminders';
    public const HOOK = 'prihod_calendar_reminder';
    public static function hooks(): void {
        add_action( 'init', array( self::class, 'schedule_next' ), 30 );
        add_action( self::HOOK, array( self::class, 'run' ), 10, 2 );
        foreach ( array( self::OPTION, Profile::OPTION, 'prihod_modules' ) as $option ) {
            add_action( 'update_option_' . $option, static function () { wp_clear_scheduled_hook( self::HOOK ); self::schedule_next(); } );
            add_action( 'add_option_' . $option, static function () { wp_clear_scheduled_hook( self::HOOK ); self::schedule_next(); } );
        }
        add_action( 'admin_init', static function () { register_setting( 'prihod_calendar', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( self::class, 'sanitize' ), 'show_in_rest' => false ) ); } );
        add_filter( 'option_page_capability_prihod_calendar', static fn() => 'manage_prihod_settings' );
    }
    public static function options(): array { return wp_parse_args( get_option( self::OPTION, array() ), array( 'enabled' => false, 'weekday' => 0, 'time' => '' ) ); }
    public static function sanitize( $input ): array {
        if ( ! is_array( $input ) || ! is_scalar( $input['weekday'] ?? null ) || ! preg_match( '/^[0-6]$/D', (string) $input['weekday'] ) || ! is_string( $input['time'] ?? null ) || ( $input['time'] && ! Occurrences::time( $input['time'] ) ) || ( ! empty( $input['enabled'] ) && ! $input['time'] ) ) {
            add_settings_error( self::OPTION, 'calendar_settings', __( 'Выберите день и время недельного напоминания.', 'prihod' ) ); return self::options();
        }
        return array( 'enabled' => ! empty( $input['enabled'] ), 'weekday' => (int) $input['weekday'], 'time' => $input['time'] );
    }
    public static function next( \DateTimeImmutable $now ): ?\DateTimeImmutable {
        $options = self::options();
        if ( ! $options['enabled'] || ! Occurrences::time( $options['time'] ) || ! Modules::enabled( 'schedule' ) ) { return null; }
        $now = $now->setTimezone( new \DateTimeZone( Profile::get()['timezone'] ) );
        $day = $now->setTime( 0, 0 )->modify( '+' . ( ( (int) $options['weekday'] - (int) $now->format( 'w' ) + 7 ) % 7 ) . ' days' );
        for ( $attempt = 0; $attempt < 3; ++$attempt, $day = $day->modify( '+7 days' ) ) {
            $due = Occurrences::instant( $day->format( 'Y-m-d' ), $options['time'], $now->getTimezone()->getName() );
            if ( $due && $due > $now ) { return $due; }
        }
        return null;
    }
    public static function schedule_next(): void {
        $due = self::next( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
        if ( ! $due ) { return; }
        $args = array( $due->format( 'Y-m-d' ), 0 );
        if ( ! wp_next_scheduled( self::HOOK, $args ) ) { wp_schedule_single_event( $due->getTimestamp(), self::HOOK, $args ); }
    }
    public static function run( string $date, int $attempt = 0, ?\DateTimeImmutable $now = null ): void {
        self::schedule_next(); $options = self::options();
        if ( ! $options['enabled'] || ! Occurrences::time( $options['time'] ) || ! Modules::enabled( 'schedule' ) || ! Occurrences::date( $date ) || ! in_array( $attempt, array( 0, 1, 2 ), true ) ) { return; }
        $zone = new \DateTimeZone( Profile::get()['timezone'] ); $from = new \DateTimeImmutable( $date, $zone ); $now = $now ? $now->setTimezone( $zone ) : new \DateTimeImmutable( 'now', $zone );
        if ( $from > $now || $from->modify( '+7 days' ) <= $now || get_option( 'prihod_calendar_last_sent', '' ) === $date || get_option( 'prihod_calendar_last_attempt', '' ) === $date ) { return; }
        $lock = Lock::acquire( 'prihod_calendar_mail_lock', 300 ); if ( ! $lock ) { return; }
        try {
            if ( get_option( 'prihod_calendar_last_sent', '' ) === $date || get_option( 'prihod_calendar_last_attempt', '' ) === $date ) { return; }
            $feasts = Calendar_Adapter::week( $from );
            if ( is_wp_error( $feasts ) ) {
                update_option( 'prihod_calendar_reminder_status', 'calendar_unavailable', false );
                if ( $attempt < 2 ) { wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::HOOK, array( $date, $attempt + 1 ) ); }
                return;
            }
            $feasts = array_filter( $feasts, static fn( $day ) => $day >= $now->format( 'Y-m-d' ), ARRAY_FILTER_USE_KEY );
            if ( ! $feasts ) { update_option( 'prihod_calendar_last_sent', $date, false ); update_option( 'prihod_calendar_reminder_status', 'no_feasts', false ); return; }
            $body = __( 'Праздники на ближайшие семь дней. Откройте редактор, выберите время и место и подтвердите богослужение.', 'prihod' ) . "\n\n";
            foreach ( $feasts as $day => $titles ) {
                $link = add_query_arg( array( 'post_type' => 'prihod_service', 'prihod_calendar_date' => $day ), admin_url( 'post-new.php' ) );
                $body .= ( new \DateTimeImmutable( $day ) )->format( 'd.m.Y' ) . ': ' . implode( '; ', $titles ) . "\n" . $link . "\n\n";
            }
            update_option( 'prihod_calendar_last_attempt', $date, false );
            $sent = wp_mail( get_option( 'admin_email' ), __( 'Приход: праздники недели', 'prihod' ), $body );
            update_option( 'prihod_calendar_reminder_status', $sent ? 'sent' : 'mail_failed', false );
            if ( $sent ) { update_option( 'prihod_calendar_last_sent', $date, false ); }
            // Do not automatically resend after an uncertain transport failure.
        } finally { Lock::release( 'prihod_calendar_mail_lock', $lock ); }
    }
    public static function render(): void {
        $options = self::options();
        echo '<section class="prihod-dash-card"><h2>' . esc_html__( 'Недельное напоминание о праздниках', 'prihod' ) . '</h2><p>' . esc_html__( 'Отправляется на email администратора WordPress. До выбора времени и включения письма не отправляются.', 'prihod' ) . '</p>';
        settings_errors( self::OPTION );
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">'; settings_fields( 'prihod_calendar' );
        echo '<p><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[enabled]" value="1"' . checked( $options['enabled'], true, false ) . '> ' . esc_html__( 'Включить недельное напоминание', 'prihod' ) . '</label></p><p><label>' . esc_html__( 'День отправки', 'prihod' ) . ' <select name="' . esc_attr( self::OPTION ) . '[weekday]">';
        foreach ( array( __( 'Воскресенье', 'prihod' ), __( 'Понедельник', 'prihod' ), __( 'Вторник', 'prihod' ), __( 'Среда', 'prihod' ), __( 'Четверг', 'prihod' ), __( 'Пятница', 'prihod' ), __( 'Суббота', 'prihod' ) ) as $day => $label ) { echo '<option value="' . esc_attr( $day ) . '"' . selected( $options['weekday'], $day, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label></p><p><label>' . esc_html__( 'Время отправки', 'prihod' ) . ' <input type="time" name="' . esc_attr( self::OPTION ) . '[time]" value="' . esc_attr( $options['time'] ) . '"></label> ' . esc_html( Profile::get()['timezone'] ) . '</p>';
        $labels = array( 'calendar_unavailable' => __( 'Календарь временно недоступен.', 'prihod' ), 'no_feasts' => __( 'На ближайшую неделю великих праздников нет.', 'prihod' ), 'sent' => __( 'Напоминание передано почтовой службе WordPress.', 'prihod' ), 'mail_failed' => __( 'Почтовая служба не подтвердила отправку.', 'prihod' ) );
        $status = get_option( 'prihod_calendar_reminder_status', '' );
        if ( isset( $labels[$status] ) ) { echo '<p>' . esc_html( $labels[$status] ) . '</p>'; }
        submit_button(); echo '</form></section>';
    }
}
