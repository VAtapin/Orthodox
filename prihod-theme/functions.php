<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/render.php';
add_action( 'after_setup_theme', function () {
    load_theme_textdomain( 'prihod-theme', get_template_directory() . '/languages' );
    add_theme_support( 'editor-styles' ); add_editor_style( array( 'style.css', 'assets/theme.css' ) );
} );
add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'prihod-theme', get_stylesheet_uri(), array(), '0.2.1' ); wp_enqueue_style( 'prihod-theme-design', get_template_directory_uri() . '/assets/theme.css', array( 'prihod-theme' ), '0.2.1' ); } );
add_action( 'wp_enqueue_scripts', function () {
    if ( ( ! shortcode_exists( 'kloster_form' ) && ! shortcode_exists( 'gkod_form' ) ) || empty( prihod_theme_options()['stepped_forms'] ) ) { return; }
    $dependencies = array();
    foreach ( array( 'kloster-prayer', 'georg-kloster-orthodox-donate' ) as $handle ) { if ( wp_script_is( $handle, 'registered' ) ) { $dependencies[] = $handle; } }
    wp_enqueue_script( 'prihod-theme-forms', get_template_directory_uri() . '/assets/forms.js', $dependencies, '0.2.1', true );
    wp_localize_script( 'prihod-theme-forms', 'prihodFormUI', array( 'type' => __( 'Тип записки', 'prihod-theme' ), 'names' => __( 'Имена', 'prihod-theme' ), 'total' => __( 'Сумма', 'prihod-theme' ), 'amount' => __( 'Пожертвование', 'prihod-theme' ), 'contacts' => __( 'Контакты', 'prihod-theme' ), 'review' => __( 'Подтверждение', 'prihod-theme' ), 'steps' => __( 'Этапы заполнения', 'prihod-theme' ), 'back' => __( 'Назад', 'prihod-theme' ), 'next' => __( 'Продолжить', 'prihod-theme' ), 'service' => __( 'Выберите требу перед продолжением.', 'prihod-theme' ), 'check' => __( 'Проверьте перед отправкой', 'prihod-theme' ) ) );
}, 20 );
add_filter( 'body_class', function ( array $classes ) {
    $settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
    $scheme = $settings['custom']['prihod']['scheme'] ?? 'classic';
    $classes[] = 'prihod-scheme-' . sanitize_html_class( $scheme ); return $classes;
} );
function prihod_theme_register_blocks(): void {
    wp_register_script( 'prihod-theme-blocks', get_template_directory_uri() . '/assets/editor.js', array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n' ), '0.2.1', true );
    $blocks = array( 'header' => __( 'Шапка прихода', 'prihod-theme' ), 'footer' => __( 'Подвал прихода', 'prihod-theme' ), 'home' => __( 'Главная прихода', 'prihod-theme' ), 'sidebar' => __( 'Боковая колонка', 'prihod-theme' ), 'page-heading' => __( 'Заголовок страницы', 'prihod-theme' ) );
    wp_localize_script( 'prihod-theme-blocks', 'prihodThemeBlocks', $blocks );
    foreach ( $blocks as $name => $title ) {
        $callback = 'prihod_theme_' . str_replace( array( 'page-heading', '-' ), array( 'heading', '_' ), $name );
        register_block_type( 'prihod-theme/' . $name, array( 'api_version' => 3, 'title' => $title, 'category' => 'theme', 'editor_script' => 'prihod-theme-blocks', 'render_callback' => $callback ) );
    }
}
add_action( 'init', 'prihod_theme_register_blocks' );
add_action( 'init', function () {
    register_block_pattern_category( 'prihod-theme', array( 'label' => __( 'Приход', 'prihod-theme' ) ) );
    if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'prihod/profile' ) ) {
        register_block_pattern( 'prihod-theme/parish-home', array( 'title' => __( 'Главная прихода', 'prihod-theme' ), 'categories' => array( 'prihod-theme' ),
            'content' => '<!-- wp:group {"align":"wide","className":"prihod-theme-hero","style":{"spacing":{"padding":{"top":"3rem","right":"3rem","bottom":"3rem","left":"3rem"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignwide prihod-theme-hero" style="padding:3rem"><!-- wp:prihod/profile /--><!-- wp:prihod/next-service /--></div><!-- /wp:group --><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:prihod/next-services /--></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:prihod/contacts /--></div><!-- /wp:column --></div><!-- /wp:columns -->' ) );
        register_block_pattern( 'prihod-theme/service-week', array( 'title' => __( 'Неделя богослужений', 'prihod-theme' ), 'categories' => array( 'prihod-theme' ), 'content' => '<!-- wp:prihod/schedule {"view":"week"} /-->' ) );
        register_block_pattern( 'prihod-theme/contacts', array( 'title' => __( 'Контакты и духовенство', 'prihod-theme' ), 'categories' => array( 'prihod-theme' ), 'content' => '<!-- wp:prihod/contacts /--><!-- wp:prihod/clergy /-->' ) );
    }
}, 30 );
