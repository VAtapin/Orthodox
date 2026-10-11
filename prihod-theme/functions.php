<?php
defined( 'ABSPATH' ) || exit;
add_action( 'after_setup_theme', function () {
    load_theme_textdomain( 'prihod-theme', get_template_directory() . '/languages' );
    add_theme_support( 'editor-styles' ); add_editor_style( 'style.css' );
} );
add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'prihod-theme', get_stylesheet_uri(), array(), '0.1.0' ); } );
add_action( 'init', function () {
    register_block_pattern_category( 'prihod-theme', array( 'label' => __( 'Приход', 'prihod-theme' ) ) );
    if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'prihod/profile' ) ) {
        register_block_pattern( 'prihod-theme/parish-home', array( 'title' => __( 'Главная прихода', 'prihod-theme' ), 'categories' => array( 'prihod-theme' ),
            'content' => '<!-- wp:group {"align":"wide","className":"prihod-theme-hero","style":{"spacing":{"padding":{"top":"3rem","right":"3rem","bottom":"3rem","left":"3rem"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignwide prihod-theme-hero" style="padding:3rem"><!-- wp:prihod/profile /--><!-- wp:prihod/next-service /--></div><!-- /wp:group --><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:prihod/next-services /--></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:prihod/contacts /--></div><!-- /wp:column --></div><!-- /wp:columns -->' ) );
        register_block_pattern( 'prihod-theme/service-week', array( 'title' => __( 'Неделя богослужений', 'prihod-theme' ), 'categories' => array( 'prihod-theme' ), 'content' => '<!-- wp:prihod/schedule {"view":"week"} /-->' ) );
        register_block_pattern( 'prihod-theme/contacts', array( 'title' => __( 'Контакты и духовенство', 'prihod-theme' ), 'categories' => array( 'prihod-theme' ), 'content' => '<!-- wp:prihod/contacts /--><!-- wp:prihod/clergy /-->' ) );
    }
}, 30 );
