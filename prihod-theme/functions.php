<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/design.php';
require_once __DIR__ . '/includes/demo.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/calendar-markup.php';
require_once __DIR__ . '/includes/home.php';
require_once __DIR__ . '/includes/article.php';
add_action( 'after_setup_theme', function () {
    load_theme_textdomain( 'prihod-theme', get_template_directory() . '/languages' );
    add_theme_support( 'editor-styles' ); add_editor_style( array( 'style.css', 'assets/theme.css' ) );
} );
add_action( 'wp_enqueue_scripts', function () { wp_enqueue_script( 'prihod-theme-gallery', get_template_directory_uri() . '/assets/gallery.js', array(), '0.3.9', true ); wp_enqueue_style( 'prihod-theme', get_stylesheet_uri(), array(), '0.3.9' ); wp_enqueue_style( 'prihod-theme-design', get_template_directory_uri() . '/assets/theme.css', array( 'prihod-theme' ), '0.3.9' ); wp_enqueue_style( 'prihod-theme-reference', get_template_directory_uri() . '/assets/reference.css', array( 'prihod-theme-design' ), '0.3.9.' . filemtime( get_template_directory() . '/assets/reference.css' ) ); } );
add_action( 'wp_enqueue_scripts', function () {
    if ( ( ! shortcode_exists( 'kloster_form' ) && ! shortcode_exists( 'gkod_form' ) ) || empty( prihod_theme_options()['stepped_forms'] ) ) { return; }
    $dependencies = array();
    foreach ( array( 'kloster-prayer', 'georg-kloster-orthodox-donate' ) as $handle ) { if ( wp_script_is( $handle, 'registered' ) ) { $dependencies[] = $handle; } }
    wp_enqueue_script( 'prihod-theme-forms', get_template_directory_uri() . '/assets/forms.js', $dependencies, '0.3.9', true );
    wp_localize_script( 'prihod-theme-forms', 'prihodFormUI', array( 'type' => __( 'Тип записки', 'prihod-theme' ), 'names' => __( 'Имена', 'prihod-theme' ), 'total' => __( 'Сумма', 'prihod-theme' ), 'amount' => __( 'Пожертвование', 'prihod-theme' ), 'contacts' => __( 'Контакты', 'prihod-theme' ), 'review' => __( 'Подтверждение', 'prihod-theme' ), 'steps' => __( 'Этапы заполнения', 'prihod-theme' ), 'back' => __( 'Назад', 'prihod-theme' ), 'next' => __( 'Продолжить', 'prihod-theme' ), 'service' => __( 'Выберите требу перед продолжением.', 'prihod-theme' ), 'check' => __( 'Проверьте перед отправкой', 'prihod-theme' ), 'intention' => __( 'Поминовение', 'prihod-theme' ), 'services' => __( 'Выбранные требы', 'prihod-theme' ), 'payment' => __( 'Способ оплаты', 'prihod-theme' ), 'yourPrayer' => __( 'Ваша записка', 'prihod-theme' ), 'yourDonation' => __( 'Ваше пожертвование', 'prihod-theme' ), 'yourContacts' => __( 'Ваши контактные данные', 'prihod-theme' ), 'edit' => __( 'Изменить', 'prihod-theme' ), 'frequency' => __( 'Периодичность', 'prihod-theme' ), 'providers' => array( 'stripe' => __( 'Карта (Stripe)', 'prihod-theme' ), 'paypal' => 'PayPal' ) ) );
}, 20 );
add_filter( 'body_class', function ( array $classes ) {
    $settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
    $scheme = $settings['custom']['prihod']['scheme'] ?? 'classic';
    if ( prihod_theme_options()['sticky_header'] ) { $classes[] = 'prihod-sticky-header'; }
    foreach ( get_option( 'prihod_resources', array() ) as $key => $resource ) { if ( ! empty( $resource['id'] ) && is_page( absint( $resource['id'] ) ) ) { $classes[] = 'prihod-page-' . sanitize_html_class( $key ); } }
    $classes[] = 'prihod-scheme-' . sanitize_html_class( $scheme ); return $classes;
} );
function prihod_theme_register_blocks(): void {
    wp_register_script( 'prihod-theme-blocks', get_template_directory_uri() . '/assets/editor.js', array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n' ), '0.3.9', true );
    $blocks = array( 'header' => __( 'Шапка прихода', 'prihod-theme' ), 'footer' => __( 'Подвал прихода', 'prihod-theme' ), 'home' => __( 'Главная прихода', 'prihod-theme' ), 'article-meta' => __( 'Дата и ссылка статьи', 'prihod-theme' ), 'article-return' => __( 'Возврат к разделу', 'prihod-theme' ), 'related-posts' => __( 'Читайте также', 'prihod-theme' ), 'sidebar' => __( 'Боковая колонка', 'prihod-theme' ), 'page-heading' => __( 'Заголовок страницы', 'prihod-theme' ) );
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

add_filter( 'render_block', function ( string $html ): string {
    if ( ! prihod_theme_preview_scheme() ) { return $html; }
    $tags = new WP_HTML_Tag_Processor( $html );
    while ( $tags->next_tag( 'a' ) ) { $url = $tags->get_attribute( 'href' ); if ( is_string( $url ) ) { $tags->set_attribute( 'href', prihod_theme_preview_url( $url ) ); } }
    return $tags->get_updated_html();
} );

add_action( 'admin_enqueue_scripts', function () {
    if ( 'prihod-wizard' !== ( $_GET['page'] ?? '' ) ) { return; }
    $photo = wp_get_attachment_image_url( prihod_theme_options()['hero_image'], 'full' );
    if ( $photo ) { wp_add_inline_style( 'prihod-wizard', '.prihod-wizard-welcome{--prihod-wizard-photo:url(' . esc_url( $photo ) . ')}' ); }
}, 30 );

// Block themes render content before wp_head: register the provider's own assets
// early so its shortcode can attach its native localized configuration.
function prihod_theme_prepare_provider_assets(): void {
    foreach ( array( '\\GeorgKlosterOrthodoxDonate\\Public_Form', '\\KlosterPrayer\\Public_Form' ) as $provider ) {
        if ( shortcode_exists( 'gkod_form' ) || shortcode_exists( 'kloster_form' ) ) { if ( is_callable( array( $provider, 'register_assets' ) ) ) { $provider::register_assets(); } }
    }
}
add_action( 'init', 'prihod_theme_prepare_provider_assets', 50 );

add_action( 'wp_enqueue_scripts', function () {
    if ( is_singular( 'post' ) ) { wp_enqueue_script( 'prihod-theme-article', get_template_directory_uri() . '/assets/article.js', array(), '0.3.9.' . filemtime( get_template_directory() . '/assets/article.js' ), true ); }
} );
