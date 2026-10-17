<?php
namespace GeorgKlosterPrihodSchedule;

defined( 'ABSPATH' ) || exit;

final class Modules {
    public static function enabled( string $module ): bool { return 'schedule' === $module; }
}
