<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

/** Recommendations are deliberately narrower than the administrator's palette. */
final class Calendar_Colors {
    public static function labels(): array {
        return array( 'auto' => __( 'Автоматически по календарю', 'georg-kloster-prihod-schedule' ), '' => __( 'Без выделения', 'georg-kloster-prihod-schedule' ), 'white' => __( 'Белый', 'georg-kloster-prihod-schedule' ), 'red' => __( 'Красный', 'georg-kloster-prihod-schedule' ), 'gold' => __( 'Золотой / жёлтый', 'georg-kloster-prihod-schedule' ), 'green' => __( 'Зелёный', 'georg-kloster-prihod-schedule' ), 'blue' => __( 'Голубой', 'georg-kloster-prihod-schedule' ), 'purple' => __( 'Фиолетовый', 'georg-kloster-prihod-schedule' ), 'black' => __( 'Чёрный', 'georg-kloster-prihod-schedule' ) );
    }
    public static function validate( $color ): string {
        if ( ! is_string( $color ) || ! array_key_exists( $color, self::labels() ) ) { throw new \InvalidArgumentException( __( 'Выберите цвет облачения из списка.', 'georg-kloster-prihod-schedule' ) ); }
        return $color;
    }
    /** Resolve only explicit ranked commemorations; ordinary saints do not override feasts. */
    public static function day( array $day ): ?array {
        $ranked = array(); $periods = array(); $titles = array();
        foreach ( $day['events'] ?? array() as $event ) {
            if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! is_numeric( $event['typeCode'] ?? null ) || ! is_string( $event['title'] ?? null ) ) { continue; }
            $rank = (int) $event['typeCode'];
            $titles[] = $event['title'];
            if ( preg_match( '/попразднств|отдание|віддання|nachfeier|festabschluss|poprazdn|zakończeni.*święta/iu', $event['title'] ) ) { $periods[] = $event['title']; }
            if ( $rank >= 0 && $rank <= 5 ) { $ranked[$rank][] = sanitize_text_field( $event['title'] ); }
        }
        ksort( $ranked );
        $major = $ranked && array_key_first( $ranked ) <= 2;
        if ( $major ) { return self::recommendation( reset( $ranked ) ); }
        if ( $periods ) { $period = self::recommendation( $periods ); if ( $period ) { return $period; } }
        $date = is_string( $day['date'] ?? null ) && Occurrences::date( $day['date'] ) ? new \DateTimeImmutable( $day['date'], new \DateTimeZone( 'UTC' ) ) : null;
        // Calendar period markers, not the dietary fasting rule for Wednesdays/Fridays.
        if ( preg_match( '/велик.*пост|велик.*піст|große.*fastenzeit|wielki.*post|страстн.*седмиц|страсн.*тиж|karwoche|wielki.*tydzień/iu', implode( '; ', $titles ) ) ) {
            $color = ( $date && (int) $date->format( 'N' ) >= 6 ) || ( $ranked && array_key_first( $ranked ) <= 4 ) ? 'purple' : 'black';
            return self::resolved( $color, implode( '; ', $titles ), 'period' );
        }
        if ( $date && '7' === $date->format( 'N' ) ) { return self::resolved( 'gold', self::labels()['gold'], 'ordinary-sunday' ); }
        $recommendation = $ranked ? self::recommendation( reset( $ranked ) ) : null;
        return $recommendation ?: ( $date ? self::resolved( 'gold', self::labels()['gold'], 'ordinary-day' ) : null );
    }
    private static function resolved( string $color, string $reason, string $source = 'tradition' ): array { return array( 'color' => $color, 'label' => self::labels()[$color], 'reason' => $reason, 'source' => $source ); }
    public static function recommendation( array $feasts ): ?array {
        $colors = array(); $reasons = array();
        foreach ( $feasts as $title ) {
            // Confirmed Marian feasts, in the four supported calendar languages.
            if ( preg_match( '/покров|покрова|schutz.*(gottes|mutter)|opieki.*(bog|matki)|рождеств.*богород|різдв.*богород|geburt.*(gottes|maria)|narodzeni.*(bog|mary)|введени.*(богород|храм)|введенн.*(богород|храм)|tempel.*(maria|gottes)|(maria|gottes).*tempel|wprowadzeni.*(bog|mary)|успени.*богород|успінн.*богород|entschlaf.*(gottes|maria)|zaśnięci.*(bog|mary)/iu', $title ) ) { $colors['blue'] = true; }
            elseif ( preg_match( '/благовещени|благовіщенн|verkündigung|zwiastowani/iu', $title ) ) { $colors['blue'] = true; }
            elseif ( preg_match( '/троиц|трійц|пятидесятниц|п.?ятдесятниц|pfingsten|pięćdziesiątnic|zesłani.*ducha|вход.*иерусалим|вхід.*єрусалим|palmsonntag|niedziela.*palmow/iu', $title ) ) { $colors['green'] = true; }
            elseif ( preg_match( '/преображени|преображенн|verklärung|przemienieni/iu', $title ) ) { $colors['white'] = true; }
            elseif ( preg_match( '/рождеств.*христ|різдв.*христ|weihnacht|boż.*narodzeni|богоявлен|богоявлен|крещени.*господ|хрещенн.*господ|theophani|taufe.*herr|chrzest.*pańsk|вознесени|вознесінн|himmelfahrt|wniebowstąpieni/iu', $title ) ) { $colors['white'] = true; }
            elseif ( preg_match( '/воздвижени|воздвиженн|крестопоклон|хрестопоклон|kreuzerhöhung|podwyższeni.*krzyża/iu', $title ) ) { $colors['purple'] = true; }
            elseif ( preg_match( '/пасх|великдень|ostern|wielkanoc|мучен|мч[цч]*\.|страстотерп|märtyr|męczenn/iu', $title ) ) { $colors['red'] = true; }
            elseif ( preg_match( '/преподоб|прп[п]*\.|юродив|ehrwürdig|świątobliw/iu', $title ) ) { $colors['green'] = true; }
            elseif ( preg_match( '/святител|свт[т]*\.|апостол|ап[п]*\.|hierarch|apostel|apostoł/iu', $title ) ) { $colors['gold'] = true; }
            else { return null; } // Mixed or unknown commemorations do not get a guess.
            $reasons[] = $title;
        }
        if ( 1 !== count( $colors ) ) { return null; }
        $color = key( $colors );
        return array( 'color' => $color, 'label' => self::labels()[$color], 'reason' => implode( '; ', $reasons ), 'source' => 'tradition' );
    }
    public static function field( string $name, string $selected, bool $persisted = false ): string {
        $html = '<div class="gkpsch-color-control" data-gkpsch-color-control><label class="gkpsch-service-field">' . esc_html__( 'Цвет облачения', 'georg-kloster-prihod-schedule' ) . '<select name="' . esc_attr( $name ) . '" data-gkpsch-color data-persisted="' . ( $persisted ? '1' : '0' ) . '">';
        foreach ( self::labels() as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        $html .= '</select></label><span class="gkpsch-color-swatch" data-gkpsch-color-swatch aria-hidden="true"></span><p data-gkpsch-color-status role="status" aria-live="polite"></p><button type="button" class="button" data-gkpsch-use-color hidden>' . esc_html__( 'Использовать рекомендованный', 'georg-kloster-prihod-schedule' ) . '</button><div class="gkpsch-color-palette" aria-label="' . esc_attr__( 'Образцы цветов', 'georg-kloster-prihod-schedule' ) . '">';
        foreach ( self::labels() as $value => $label ) { if ( $value && 'auto' !== $value ) { $html .= '<span><i class="gkpsch-color-swatch" data-color="' . esc_attr( $value ) . '" aria-hidden="true"></i>' . esc_html( $label ) . '</span>'; } }
        return $html . '</div></div>';
    }
}
