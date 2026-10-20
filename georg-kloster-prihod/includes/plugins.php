<?php
defined( 'ABSPATH' ) || exit;

/** Optional integrations, never theme requirements or bundled plugin packages. */
function prihod_theme_plugin_catalog(): array {
    return array(
        'georg-kloster-prihod-core' => array(
            'name' => __( 'Georg-Kloster Parish', 'georg-kloster-prihod' ),
            'description' => __( 'Parish profile, clergy, service places with logos and photos, service schedules and calendar. Publish with blocks or shortcodes using any theme.', 'georg-kloster-prihod' ),
            'page' => 'gkpc', 'capability' => 'manage_gkpc_profile', 'preview' => 'core',
        ),
        'georg-kloster-connect-hub' => array(
            'name' => __( 'Georg-Kloster Connect Hub', 'georg-kloster-prihod' ),
            'description' => __( 'Independent forms, private messages and queued publications. Connect Telegram, WhatsApp, Facebook, Instagram, VK and OK separately.', 'georg-kloster-prihod' ),
            'page' => 'gkps', 'capability' => 'manage_gkps_settings', 'preview' => 'social',
        ),
    );
}

/** Core owns compatibility validation and the standard activation flow. */
function prihod_theme_plugin_action( string $file, array $installed ): array {
    if ( is_plugin_active( $file ) ) { return array( 'state' => 'active' ); }
    if ( ! isset( $installed[ $file ] ) ) { return array( 'state' => 'missing' ); }
    if ( ! is_wp_version_compatible( $installed[ $file ]['RequiresWP'] ?? '' ) || ! is_php_version_compatible( $installed[ $file ]['RequiresPHP'] ?? '' ) ) {
        return array( 'state' => 'incompatible' );
    }
    if ( current_user_can( 'activate_plugin', $file ) ) {
        return array(
            'state' => 'inactive',
            'label' => __( 'Activate', 'georg-kloster-prihod' ),
            'url' => wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'plugin' => $file ), self_admin_url( 'plugins.php' ) ), 'activate-plugin_' . $file ),
        );
    }
    return array( 'state' => 'inactive' );
}

function prihod_theme_plugins_panel(): void {
    if ( ! current_user_can( 'edit_theme_options' ) ) { return; }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $installed = get_plugins();
    $states = array(
        'active' => __( 'Active', 'georg-kloster-prihod' ),
        'inactive' => __( 'Installed, inactive', 'georg-kloster-prihod' ),
        'incompatible' => __( 'Requires a newer WordPress or PHP version', 'georg-kloster-prihod' ),
    );
    ?>
    <section id="prihod-option-plugins" class="prihod-option-section">
        <h2><?php esc_html_e( 'Optional plugins', 'georg-kloster-prihod' ); ?></h2>
        <p><?php esc_html_e( 'The theme works on its own. Optional integrations appear here after you install them.', 'georg-kloster-prihod' ); ?></p>
        <p><a class="button" href="<?php echo esc_url( add_query_arg( array( 's' => 'Georg-Kloster', 'tab' => 'search', 'type' => 'term' ), self_admin_url( 'plugin-install.php' ) ) ); ?>"><?php esc_html_e( 'Find Georg-Kloster plugins', 'georg-kloster-prihod' ); ?></a></p>
        <div class="prihod-plugin-cards">
        <?php $count = 0; foreach ( prihod_theme_plugin_catalog() as $slug => $plugin ) :
            $file = $slug . '/' . $slug . '.php';
            if ( ! isset( $installed[ $file ] ) ) { continue; }
            ++$count;
            $action = prihod_theme_plugin_action( $file, $installed );
            $preview = get_template_directory_uri() . '/assets/plugins/' . $plugin['preview'] . '.png';
            ?>
            <article class="prihod-plugin-card">
                <a class="prihod-plugin-preview" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( sprintf( __( 'Screenshot: %s (new tab)', 'georg-kloster-prihod' ), $plugin['name'] ) ); ?>"><img src="<?php echo esc_url( $preview ); ?>" alt="<?php echo esc_attr( $plugin['name'] ); ?>" loading="lazy"></a>
                <div class="prihod-plugin-copy">
                    <span class="prihod-plugin-state"><?php echo esc_html( $states[ $action['state'] ] ?? '' ); ?></span>
                    <h3><?php echo esc_html( $plugin['name'] ); ?></h3>
                    <p><?php echo esc_html( $plugin['description'] ); ?></p>
                    <small><?php echo esc_html( sprintf( __( 'Version: %s', 'georg-kloster-prihod' ), $installed[ $file ]['Version'] ?? '' ) ); ?></small>
                    <div class="prihod-plugin-actions">
                    <?php if ( ! empty( $action['url'] ) ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
                    <?php endif; ?>
                    <?php if ( 'active' === $action['state'] && current_user_can( $plugin['capability'] ) ) : ?>
                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $plugin['page'] ) ); ?>"><?php esc_html_e( 'Open settings', 'georg-kloster-prihod' ); ?></a>
                    <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ( ! $count ) : ?><p><?php esc_html_e( 'No optional integrations are currently installed.', 'georg-kloster-prihod' ); ?></p><?php endif; ?>
    </section>
    <?php
}
