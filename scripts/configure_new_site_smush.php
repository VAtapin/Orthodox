<?php
/** Keep Smush lazy loading away from WordPress's native image lightbox. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This script is restricted to new.georg-kloster.ru.' );
}

$settings = get_option( 'wp-smush-lazy_load' );
if ( ! is_array( $settings ) || ! isset( $settings['exclude-classes'] ) || ! is_array( $settings['exclude-classes'] ) ) {
    throw new RuntimeException( 'Smush lazy load settings are unavailable.' );
}

// Smush matches exclusion keywords against each image tag's markup.
$keyword = 'data-wp-on--click="actions.showLightbox"';
if ( ! in_array( $keyword, $settings['exclude-classes'], true ) ) {
    $settings['exclude-classes'][] = $keyword;
    update_option( 'wp-smush-lazy_load', $settings );
}
WP_CLI::success( 'Native WordPress lightbox images excluded from Smush lazy load.' );
