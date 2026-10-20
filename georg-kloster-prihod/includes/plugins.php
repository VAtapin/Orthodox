<?php
defined( 'ABSPATH' ) || exit;

/** Optional integrations, never theme requirements or bundled plugin packages. */
function prihod_theme_plugin_catalog(): array {
    return array(
        'georg-kloster-prihod-core' => array(
            'name' => __( 'Georg-Kloster Parish', 'georg-kloster-prihod' ),
            'description' => __( 'Parish profile, clergy, service places with logos and photos, service schedules and calendar. Publish with blocks or shortcodes using any theme.', 'georg-kloster-prihod' ),
            'file' => 'georg-kloster-prihod-core/georg-kloster-prihod-core.php', 'page' => 'gkpc', 'capability' => 'manage_gkpc_profile', 'preview' => 'core.png',
        ),
        'georg-kloster-connect-hub' => array(
            'name' => __( 'Georg-Kloster Connect Hub', 'georg-kloster-prihod' ),
            'description' => __( 'Independent forms, private messages and queued publications. Connect Telegram, WhatsApp, Facebook, Instagram, VK and OK separately.', 'georg-kloster-prihod' ),
            'file' => 'georg-kloster-connect-hub/georg-kloster-connect-hub.php', 'page' => 'gkps', 'capability' => 'manage_gkps_settings', 'preview' => 'social.png',
        ),
        'orthocal' => array(
            'name' => __( 'Georg-Kloster Calendar Workshop', 'georg-kloster-prihod' ),
            'description' => __( 'Orthodox calendar blocks and shortcodes for dates, fasting, commemorations, readings and liturgical texts.', 'georg-kloster-prihod' ),
            'file' => 'orthocal/orthocal.php', 'page' => 'orthocal', 'capability' => 'manage_options', 'preview' => 'calendar-workshop.png',
            'directory' => 'georg-kloster-calendar-workshop',
        ),
        'georg-kloster-orthodox-donations' => array(
            'name' => __( 'Georg Kloster – Orthodox Donations', 'georg-kloster-prihod' ),
            'description' => __( 'Prayer requests and donations with Stripe and PayPal, targeted campaigns, recurring support and printable prayer slips.', 'georg-kloster-prihod' ),
            'file' => 'georg-kloster-orthodox-donations/georg-kloster-orthodox-donations.php', 'page' => '', 'capability' => 'manage_options', 'preview' => 'donations.png',
            'directory' => 'georg-kloster-orthodox-donations',
        ),
        'georg-kloster-slavonic-translator' => array(
            'name' => __( 'Georg-Kloster Slavonic Translator', 'georg-kloster-prihod' ),
            'description' => __( 'Translate text into Church Slavonic and search its dictionary through Bible Desktop.', 'georg-kloster-prihod' ),
            'file' => 'georg-kloster-slavonic-translator/georg-kloster-slavonic-translator.php', 'page' => '', 'capability' => 'manage_options', 'preview' => 'translator.jpg',
            'directory' => 'georg-kloster-slavonic-translator',
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
        'missing' => __( 'Not installed', 'georg-kloster-prihod' ),
    );
    ?>
    <section id="prihod-option-plugins" class="prihod-option-section">
        <h2><?php esc_html_e( 'Optional plugins', 'georg-kloster-prihod' ); ?></h2>
        <p><?php esc_html_e( 'The theme works on its own. These optional Georg-Kloster plugins add parish management, a calendar, communication, Church Slavonic translation and donations. Install only the features you need.', 'georg-kloster-prihod' ); ?></p>
        <p><a class="button" href="<?php echo esc_url( add_query_arg( array( 's' => 'Georg-Kloster', 'tab' => 'search', 'type' => 'term' ), self_admin_url( 'plugin-install.php' ) ) ); ?>"><?php esc_html_e( 'Find Georg-Kloster plugins', 'georg-kloster-prihod' ); ?></a></p>
        <div class="prihod-plugin-cards">
        <?php $count = 0; foreach ( prihod_theme_plugin_catalog() as $slug => $plugin ) :
            $file = $plugin['file'];
            ++$count;
            $action = prihod_theme_plugin_action( $file, $installed );
            $preview = get_template_directory_uri() . '/assets/plugins/' . $plugin['preview'];
            ?>
            <article class="prihod-plugin-card">
                <a class="prihod-plugin-preview" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( sprintf( __( 'Screenshot: %s (new tab)', 'georg-kloster-prihod' ), $plugin['name'] ) ); ?>"><img src="<?php echo esc_url( $preview ); ?>" alt="<?php echo esc_attr( $plugin['name'] ); ?>" loading="lazy"></a>
                <div class="prihod-plugin-copy">
                    <span class="prihod-plugin-state"><?php echo esc_html( $states[ $action['state'] ] ?? '' ); ?></span>
                    <h3><?php echo esc_html( $plugin['name'] ); ?></h3>
                    <p><?php echo esc_html( $plugin['description'] ); ?></p>
                    <?php if ( ! empty( $installed[ $file ]['Version'] ) ) : ?><small><?php echo esc_html( sprintf( __( 'Version: %s', 'georg-kloster-prihod' ), $installed[ $file ]['Version'] ) ); ?></small><?php endif; ?>
                    <div class="prihod-plugin-actions">
                    <?php if ( ! empty( $action['url'] ) ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
                    <?php endif; ?>
                    <?php if ( ! empty( $plugin['directory'] ) ) : ?>
                        <a class="button" href="<?php echo esc_url( add_query_arg( array( 's' => $plugin['directory'], 'tab' => 'search', 'type' => 'term' ), self_admin_url( 'plugin-install.php' ) ) ); ?>"><?php esc_html_e( 'Install plugin', 'georg-kloster-prihod' ); ?></a>
                        <a href="<?php echo esc_url( 'https://ru.wordpress.org/plugins/' . $plugin['directory'] . '/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Plugin details', 'georg-kloster-prihod' ); ?></a>
                    <?php endif; ?>
                    <?php if ( 'active' === $action['state'] && ! empty( $plugin['page'] ) && current_user_can( $plugin['capability'] ) ) : ?>
                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $plugin['page'] ) ); ?>"><?php esc_html_e( 'Open settings', 'georg-kloster-prihod' ); ?></a>
                    <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ( ! $count ) : ?><p><?php esc_html_e( 'No optional plugins are listed.', 'georg-kloster-prihod' ); ?></p><?php endif; ?>
    </section>
    <?php
}
