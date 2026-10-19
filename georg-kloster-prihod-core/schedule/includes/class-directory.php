<?php
namespace GeorgKlosterPrihodSchedule;

defined( 'ABSPATH' ) || exit;

final class Directory {
    public static function register(): void {}
    public static function details( int $id ): array { return \GeorgKlosterPrihodCore\Directory::details( $id ); }
    public static function title( int $id ): string {
        $title = get_the_title( $id );
        $translated = apply_filters( 'wpml_translate_single_string', false, 'georg-kloster-prihod-schedule', 'location-' . $id );
        return is_string( $translated ) && '' !== $translated ? $translated : $title;
    }
    public static function public_title( int $id ): string {
        $places = \GeorgKlosterPrihodCore\Directory::places( array( 'numberposts' => 2, 'fields' => 'ids' ) );
        return count( $places ) > 1 ? self::title( $id ) : '';
    }
    public static function meta_boxes(): void {}
    public static function save( int $id ): void { \GeorgKlosterPrihodCore\Directory::save( $id ); }
}
