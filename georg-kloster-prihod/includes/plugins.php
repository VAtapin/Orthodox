<?php
defined( 'ABSPATH' ) || exit;

/** Optional integrations, never theme requirements or bundled plugin packages. */
function prihod_theme_plugin_catalog(): array {
    return array(
        'georg-kloster-prihod-core' => array(
            'name' => __( 'Georg-Kloster Parish Core', 'georg-kloster-prihod' ),
            'description' => __( 'Parish profile, places and clergy. Publish these details with blocks or shortcodes, using any theme.', 'georg-kloster-prihod' ),
            'page' => 'gkpc', 'capability' => 'manage_gkpc_profile', 'preview' => 'core',
        ),
        'georg-kloster-prihod-social' => array(
            'name' => __( 'Georg-Kloster Parish Social', 'georg-kloster-prihod' ),
            'description' => __( 'Configurable contact forms and private correspondence. Email replies and optional Telegram, WhatsApp and Facebook channels are configured separately.', 'georg-kloster-prihod' ),
            'page' => 'gkps', 'capability' => 'manage_gkps_settings', 'preview' => 'social',
        ),
        'georg-kloster-prihod-schedule' => array(
            'name' => __( 'Georg-Kloster Parish Schedule', 'georg-kloster-prihod' ),
            'description' => __( 'Service schedules, recurring events, cancellations and changes. Calendar views, upcoming services and vestment colours work without Parish Core.', 'georg-kloster-prihod' ),
            'page' => 'gkpsch-settings', 'capability' => 'manage_gkpsch_settings', 'preview' => 'schedule',
        ),
    );
}

function prihod_theme_plugin_cache_key(): string {
    return 'prihod_theme_plugins_' . md5( determine_locale() );
}

/** Accept installable directory records only; a missing or failed lookup is not a recommendation. */
function prihod_theme_plugin_directory_record( $info, string $slug ): array {
    if ( ! is_object( $info ) || is_wp_error( $info ) || ( $info->slug ?? '' ) !== $slug || empty( $info->version ) || ! is_string( $info->version ) ) { return array(); }
    $url = is_string( $info->download_link ?? null ) ? wp_parse_url( $info->download_link ) : array();
    if ( ! is_array( $url ) || 'https' !== ( $url['scheme'] ?? '' ) || 'downloads.wordpress.org' !== ( $url['host'] ?? '' ) || ! str_starts_with( $url['path'] ?? '', '/plugin/' . $slug . '.' ) || ! str_ends_with( $url['path'] ?? '', '.zip' ) ) { return array(); }
    return array(
        'version' => sanitize_text_field( $info->version ),
        'requires' => is_string( $info->requires ?? null ) ? sanitize_text_field( $info->requires ) : '',
        'requires_php' => is_string( $info->requires_php ?? null ) ? sanitize_text_field( $info->requires_php ) : '',
    );
}

/** Called only after the administrator explicitly requests a directory check. */
function prihod_theme_refresh_plugin_directory(): array {
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    $records = array();
    foreach ( prihod_theme_plugin_catalog() as $slug => $plugin ) {
        $info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'locale' => determine_locale(), 'fields' => array( 'sections' => false, 'screenshots' => false, 'reviews' => false ) ) );
        $record = prihod_theme_plugin_directory_record( $info, $slug );
        if ( $record ) { $records[ $slug ] = $record; }
    }
    set_transient( prihod_theme_plugin_cache_key(), $records, HOUR_IN_SECONDS );
    return $records;
}

function prihod_theme_check_plugins_action(): void {
    if ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'install_plugins' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-prihod' ) ); }
    check_admin_referer( 'prihod_theme_check_plugins' );
    prihod_theme_refresh_plugin_directory();
    wp_safe_redirect( admin_url( 'themes.php?page=georg-kloster-prihod#prihod-option-plugins' ) );
    exit;
}
add_action( 'admin_post_prihod_theme_check_plugins', 'prihod_theme_check_plugins_action' );

/** Core owns installation, compatibility validation, filesystem credentials and activation. */
function prihod_theme_plugin_action( string $slug, string $file, array $installed, array $directory ): array {
    if ( isset( $installed[ $file ] ) ) {
        if ( is_plugin_active( $file ) ) { return array( 'state' => 'active' ); }
        if ( ! is_wp_version_compatible( $installed[ $file ]['RequiresWP'] ?? '' ) || ! is_php_version_compatible( $installed[ $file ]['RequiresPHP'] ?? '' ) ) { return array( 'state' => 'incompatible' ); }
        if ( current_user_can( 'activate_plugin', $file ) ) {
            return array( 'state' => 'inactive', 'label' => __( 'Activate', 'georg-kloster-prihod' ), 'url' => wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'plugin' => $file ), self_admin_url( 'plugins.php' ) ), 'activate-plugin_' . $file ) );
        }
        return array( 'state' => 'inactive' );
    }
    if ( ! $directory ) { return array( 'state' => 'unavailable' ); }
    if ( ! is_wp_version_compatible( $directory['requires'] ?? '' ) || ! is_php_version_compatible( $directory['requires_php'] ?? '' ) ) { return array( 'state' => 'incompatible' ); }
    if ( current_user_can( 'install_plugins' ) ) {
        return array( 'state' => 'available', 'label' => __( 'Install from WordPress.org', 'georg-kloster-prihod' ), 'url' => wp_nonce_url( add_query_arg( array( 'action' => 'install-plugin', 'plugin' => $slug ), self_admin_url( 'update.php' ) ), 'install-plugin_' . $slug ) );
    }
    return array( 'state' => 'available' );
}

function prihod_theme_plugins_panel(): void {
    if ( ! current_user_can( 'edit_theme_options' ) ) { return; }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $installed = get_plugins();
    $directory = get_transient( prihod_theme_plugin_cache_key() );
    $checked = is_array( $directory );
    $directory = $checked ? $directory : array();
    $states = array( 'active' => __( 'Active', 'georg-kloster-prihod' ), 'inactive' => __( 'Installed, inactive', 'georg-kloster-prihod' ), 'available' => __( 'Available on WordPress.org', 'georg-kloster-prihod' ), 'incompatible' => __( 'Requires a newer WordPress or PHP version', 'georg-kloster-prihod' ) );
    ?>
    <section id="prihod-option-plugins" class="prihod-option-section">
        <h2><?php esc_html_e( 'Optional plugins', 'georg-kloster-prihod' ); ?></h2>
        <p><?php esc_html_e( 'The theme works on its own. Add plugins only for the features you need; each plugin is independent.', 'georg-kloster-prihod' ); ?></p>
        <p><?php esc_html_e( 'Installed integrations appear below. New recommendations and installation buttons appear only after WordPress.org confirms that a plugin is available in its directory.', 'georg-kloster-prihod' ); ?></p>
        <?php if ( current_user_can( 'install_plugins' ) ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="prihod-plugin-check">
                <input type="hidden" name="action" value="prihod_theme_check_plugins">
                <?php wp_nonce_field( 'prihod_theme_check_plugins' ); ?>
                <?php submit_button( __( 'Check WordPress.org', 'georg-kloster-prihod' ), 'secondary', 'submit', false ); ?>
                <p class="description"><?php esc_html_e( 'This contacts the WordPress.org plugin directory. Nothing is installed or activated by the check.', 'georg-kloster-prihod' ); ?></p>
            </form>
        <?php else : ?>
            <p><?php esc_html_e( 'Ask an administrator with plugin installation permissions to check the directory or install plugins.', 'georg-kloster-prihod' ); ?></p>
        <?php endif; ?>
        <?php if ( $checked ) : ?>
            <p role="status"><?php esc_html_e( 'Directory check complete. Plugins that could not be confirmed are not offered for installation. You can check again if a plugin has just been published or the directory was unavailable.', 'georg-kloster-prihod' ); ?></p>
        <?php endif; ?>
        <div class="prihod-plugin-cards">
        <?php $count = 0; foreach ( prihod_theme_plugin_catalog() as $slug => $plugin ) :
            $file = $slug . '/' . $slug . '.php';
            if ( ! isset( $installed[ $file ] ) && ! isset( $directory[ $slug ] ) ) { continue; }
            ++$count;
            $action = prihod_theme_plugin_action( $slug, $file, $installed, $directory[ $slug ] ?? array() );
            $preview = get_template_directory_uri() . '/assets/plugins/' . $plugin['preview'] . '.png';
            ?>
            <article class="prihod-plugin-card">
                <a class="prihod-plugin-preview" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( sprintf( __( 'Screenshot: %s (new tab)', 'georg-kloster-prihod' ), $plugin['name'] ) ); ?>"><img src="<?php echo esc_url( $preview ); ?>" alt="<?php echo esc_attr( $plugin['name'] ); ?>" loading="lazy"></a>
                <div class="prihod-plugin-copy">
                    <span class="prihod-plugin-state"><?php echo esc_html( $states[ $action['state'] ] ?? '' ); ?></span>
                    <h3><?php echo esc_html( $plugin['name'] ); ?></h3>
                    <p><?php echo esc_html( $plugin['description'] ); ?></p>
                    <small><?php echo esc_html( sprintf( __( 'Version: %s', 'georg-kloster-prihod' ), $installed[ $file ]['Version'] ?? $directory[ $slug ]['version'] ?? '' ) ); ?></small>
                    <div class="prihod-plugin-actions">
                    <?php if ( ! empty( $action['url'] ) ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
                    <?php endif; ?>
                    <?php if ( 'active' === $action['state'] && current_user_can( $plugin['capability'] ) ) : ?>
                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $plugin['page'] ) ); ?>"><?php esc_html_e( 'Open settings', 'georg-kloster-prihod' ); ?></a>
                    <?php endif; ?>
                    <?php if ( isset( $directory[ $slug ] ) && current_user_can( 'install_plugins' ) ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'plugin-information', 'plugin' => $slug ), self_admin_url( 'plugin-install.php' ) ) ); ?>"><?php esc_html_e( 'Details on WordPress.org', 'georg-kloster-prihod' ); ?></a>
                    <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ( ! $count ) : ?><p><?php esc_html_e( 'No installed integrations or confirmed directory recommendations are available yet.', 'georg-kloster-prihod' ); ?></p><?php endif; ?>
    </section>
    <?php
}
