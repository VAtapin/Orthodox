<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Demo_Admin {
    private static string $hook = '';

    public static function menu(): void {
        self::$hook = (string) add_submenu_page( 'gkpc', __( 'Demo content', 'georg-kloster-prihod-core' ), __( 'Demo content', 'georg-kloster-prihod-core' ), 'manage_options', 'gkpc-demo', array( self::class, 'page' ) );
    }

    public static function assets( string $hook ): void {
        if ( '' !== self::$hook && self::$hook === $hook ) { wp_enqueue_style( 'gkpc-demo', plugins_url( 'assets/demo-admin.css', GKPC_FILE ), array(), GKPC_VERSION ); }
    }

    public static function link(): void {
        if ( Demo_Importer::allowed() ) { echo '<p><a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=gkpc-demo' ) ) . '">' . esc_html__( 'Import demo content', 'georg-kloster-prihod-core' ) . '</a></p>'; }
    }

    public static function page(): void {
        if ( ! Demo_Importer::allowed() ) { wp_die( esc_html__( 'You do not have permission to import this demo.', 'georg-kloster-prihod-core' ) ); }
        $state = Demo_Importer::state();
        $requested = is_string( $_GET['demo_locale'] ?? null ) ? sanitize_text_field( wp_unslash( $_GET['demo_locale'] ) ) : '';
        $locale = $state['locale'] ?? ( in_array( $requested, array( 'ru_RU', 'en_US' ), true ) ? $requested : ( str_starts_with( determine_locale(), 'ru' ) ? 'ru_RU' : 'en_US' ) );
        $pack = Demo_Importer::pack( $locale );
        ?>
        <div class="wrap gkpc-demo"><h1><?php esc_html_e( 'Demo content', 'georg-kloster-prihod-core' ); ?></h1>
        <?php if ( isset( $_GET['gkpc_demo_error'] ) ) : ?><div class="notice notice-error"><p><?php esc_html_e( 'The import did not finish. Saved demo items remain; repeat the import to resume without duplicating them.', 'georg-kloster-prihod-core' ); ?></p></div><?php endif; ?>
        <?php if ( ! empty( $state['complete'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Demo content has been imported. Repeating the action will not duplicate items or overwrite your edits.', 'georg-kloster-prihod-core' ); ?></p></div><?php endif; ?>
        <p><?php esc_html_e( 'Import an editable fictional parish and diocese. Photographs of Saint George Monastery are illustrations, not photographs of the fictional parish.', 'georg-kloster-prihod-core' ); ?></p>
        <?php if ( empty( $state['locale'] ) ) : ?>
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="gkpc-demo"><label for="gkpc-demo-language"><?php esc_html_e( 'Demo content language', 'georg-kloster-prihod-core' ); ?></label> <select name="demo_locale" id="gkpc-demo-language"><option value="ru_RU" <?php selected( $locale, 'ru_RU' ); ?>><?php esc_html_e( 'Russian', 'georg-kloster-prihod-core' ); ?></option><option value="en_US" <?php selected( $locale, 'en_US' ); ?>><?php esc_html_e( 'English', 'georg-kloster-prihod-core' ); ?></option></select> <?php submit_button( __( 'Show demo preview', 'georg-kloster-prihod-core' ), 'secondary', 'submit', false ); ?></form>
        <?php endif; ?>
        <h2><?php echo esc_html( $pack['title'] ); ?></h2><p><?php echo esc_html( $pack['disclaimer'] ); ?></p>
        <p><?php echo esc_html( sprintf( __( 'Items to create: %1$d pages, %2$d posts, %3$d images. Existing content is kept.', 'georg-kloster-prihod-core' ), count( $pack['pages'] ), count( $pack['posts'] ), count( $pack['media'] ) ) ); ?></p>
        <div class="gkpc-demo-images">
        <?php foreach ( $pack['media'] as $row ) : ?><figure><img src="<?php echo esc_url( plugins_url( 'demo/media/' . basename( $row['file'] ), GKPC_FILE ) ); ?>" alt="<?php echo esc_attr( $row['alt'] ); ?>"><figcaption><?php echo esc_html( $row['caption'] ); ?></figcaption></figure><?php endforeach; ?>
        </div>
        <details><summary><?php esc_html_e( 'Pages included in the demo', 'georg-kloster-prihod-core' ); ?></summary><ul><?php foreach ( $pack['pages'] as $row ) : ?><li><?php echo esc_html( $row['title'] ); ?></li><?php endforeach; ?></ul></details>
        <p><?php esc_html_e( 'The import creates published demo pages, posts, images and a separate menu. It does not send messages, enable payment services, install plugins or contact an external service.', 'georg-kloster-prihod-core' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="gkpc_demo_import"><input type="hidden" name="demo_locale" value="<?php echo esc_attr( $locale ); ?>">
            <?php wp_nonce_field( 'gkpc_demo_import' ); ?>
            <?php if ( empty( $state['applied'] ) && current_user_can( 'edit_theme_options' ) ) : ?>
            <p><label><input type="checkbox" name="apply_layout" value="1"> <?php esc_html_e( 'Use the demo homepage. Fill empty parish/theme fields and page links; assign the demo menu only if the primary menu is unassigned.', 'georg-kloster-prihod-core' ); ?></label></p>
            <p class="description"><?php esc_html_e( 'This changes the front-page selection, not the content of your current homepage. Filled fields and assigned menus remain unchanged. You can restore the previous front page under Settings > Reading.', 'georg-kloster-prihod-core' ); ?></p>
            <?php endif; ?>
            <?php if ( Demo_Importer::schedule_available() ) : ?><p><label><input type="checkbox" name="include_schedule" value="1"> <?php esc_html_e( 'Add three example services to the active Schedule plugin. Dates are illustrative and do not represent a real parish schedule.', 'georg-kloster-prihod-core' ); ?></label></p><?php else : ?><p class="description"><?php esc_html_e( 'Without Schedule, the demo includes an ordinary editable schedule page. No plugin is required for the page content.', 'georg-kloster-prihod-core' ); ?></p><?php endif; ?>
            <p><label><input type="checkbox" name="consent" value="1" required> <?php esc_html_e( 'I understand that this creates fictional demonstration content on this site.', 'georg-kloster-prihod-core' ); ?></label></p>
            <?php submit_button( empty( $state['complete'] ) ? __( 'Import demo content', 'georg-kloster-prihod-core' ) : __( 'Apply selected demo options', 'georg-kloster-prihod-core' ), 'primary' ); ?>
        </form>
        <?php if ( ! empty( $state['pages']['home'] ) ) : ?><p><a class="button" href="<?php echo esc_url( get_permalink( $state['pages']['home'] ) ); ?>"><?php esc_html_e( 'View created homepage', 'georg-kloster-prihod-core' ); ?></a> <a class="button" href="<?php echo esc_url( get_edit_post_link( $state['pages']['home'], 'raw' ) ); ?>"><?php esc_html_e( 'Edit demo page', 'georg-kloster-prihod-core' ); ?></a></p><?php endif; ?>
        </div>
        <?php
    }

    public static function handle(): void {
        if ( ! Demo_Importer::allowed() ) { wp_die( esc_html__( 'You do not have permission to import this demo.', 'georg-kloster-prihod-core' ) ); }
        check_admin_referer( 'gkpc_demo_import' );
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || '1' !== ( $_POST['consent'] ?? '' ) ) { wp_die( esc_html__( 'Confirm the demo import first.', 'georg-kloster-prihod-core' ) ); }
        $locale = is_string( $_POST['demo_locale'] ?? null ) ? sanitize_text_field( wp_unslash( $_POST['demo_locale'] ) ) : '';
        $result = Demo_Importer::run( $locale, '1' === ( $_POST['apply_layout'] ?? '' ), '1' === ( $_POST['include_schedule'] ?? '' ) );
        wp_safe_redirect( admin_url( 'admin.php?page=gkpc-demo' . ( is_wp_error( $result ) ? '&gkpc_demo_error=1' : '' ) ) );
        exit;
    }
}
