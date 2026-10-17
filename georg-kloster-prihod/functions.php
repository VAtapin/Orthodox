<?php
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/branding.php';
require_once __DIR__ . '/includes/design.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/photos.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/calendar-markup.php';
require_once __DIR__ . '/includes/home.php';
require_once __DIR__ . '/includes/article.php';
require_once __DIR__ . '/includes/about.php';
require_once __DIR__ . '/includes/catalog.php';
require_once __DIR__ . '/includes/clergy.php';
require_once __DIR__ . '/includes/pilgrim.php';
require_once __DIR__ . '/includes/contacts.php';

add_action( 'after_setup_theme', function () {
    load_theme_textdomain( 'georg-kloster-prihod', get_template_directory() . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'editor-styles' );
    add_editor_style( array( 'style.css', 'assets/theme.css' ) );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'align-wide' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'wp-block-styles' );
    register_nav_menus( array( 'primary' => __( 'Основное меню', 'georg-kloster-prihod' ) ) );
} );

add_action( 'init', function () {
    $tagline = get_option( 'blogdescription' );
    if ( is_string( $tagline ) && '' !== $tagline ) {
        do_action( 'wpml_register_single_string', 'georg-kloster-prihod', 'site-tagline', $tagline );
    }
} );

add_action( 'wp_enqueue_scripts', function () {
    $uri = get_template_directory_uri();
    $version = wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'georg-kloster-prihod', get_stylesheet_uri(), array(), $version );
    wp_enqueue_style( 'georg-kloster-prihod-design', $uri . '/assets/theme.css', array( 'georg-kloster-prihod' ), $version );
    wp_enqueue_style( 'georg-kloster-prihod-reference', $uri . '/assets/reference.css', array( 'georg-kloster-prihod-design' ), $version . '.' . filemtime( get_template_directory() . '/assets/reference.css' ) );
    wp_enqueue_style( 'georg-kloster-prihod-home-content', $uri . '/assets/home-content.css', array( 'georg-kloster-prihod-reference' ), $version );
    wp_enqueue_script( 'georg-kloster-prihod-gallery', $uri . '/assets/gallery.js', array(), $version, true );
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
} );

add_filter( 'body_class', function ( array $classes ): array {
    $settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
    $scheme = $settings['custom']['prihod']['scheme'] ?? 'classic';
    if ( georg_kloster_prihod_options()['sticky_header'] ) {
        $classes[] = 'prihod-sticky-header';
    }
    foreach ( prihod_theme_resources() as $key => $resource ) {
        if ( ! empty( $resource['id'] ) && is_page( prihod_theme_resource_id( $key ) ) ) {
            $classes[] = 'prihod-page-' . sanitize_html_class( $key );
        }
    }
    $classes[] = 'prihod-scheme-' . sanitize_html_class( $scheme );
    return $classes;
} );

add_filter( 'render_block', function ( string $html ): string {
    if ( ! prihod_theme_preview_scheme() ) {
        return $html;
    }
    $tags = new WP_HTML_Tag_Processor( $html );
    while ( $tags->next_tag( 'a' ) ) {
        $url = $tags->get_attribute( 'href' );
        if ( is_string( $url ) ) {
            $tags->set_attribute( 'href', prihod_theme_preview_url( $url ) );
        }
    }
    return $tags->get_updated_html();
} );

add_filter( 'template_include', function ( string $template ): string {
    if ( ! is_singular( 'page' ) ) {
        return $template;
    }
    $id = get_queried_object_id();
    $assigned = get_page_template_slug( $id );
    if ( ! in_array( $assigned, array( '', 'default', 'about', 'catalog', 'wide', 'focused', 'pilgrim', 'contacts' ), true ) ) {
        return $template;
    }
    $templates = array( 'about' => 'about', 'first-visit' => 'pilgrim', 'news' => 'catalog', 'sermons' => 'catalog', 'contacts' => 'contacts', 'requests' => 'focused', 'schedule' => 'wide' );
    foreach ( $templates as $key => $name ) {
        if ( $id === prihod_theme_resource_id( $key ) ) {
            return get_template_directory() . '/' . $name . '.php';
        }
    }
    return $template;
}, 30 );
