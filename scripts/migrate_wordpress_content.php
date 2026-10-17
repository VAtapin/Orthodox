<?php
/**
 * One-time content migration, executed with WP-CLI on the destination site.
 *
 * Source credentials are supplied as environment variables by the operator.
 * The source connection is read-only; no credentials or option values are logged.
 * Run with --skip-plugins --skip-themes so imports do not send notifications.
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( 1 );
}
require_once __DIR__ . '/legacy-youtube.php';

global $wpdb;

$required = array( 'GK_SOURCE_DB_NAME', 'GK_SOURCE_DB_USER', 'GK_SOURCE_DB_PASSWORD', 'GK_SOURCE_DB_HOST', 'GK_SOURCE_PREFIX' );
foreach ( $required as $name ) {
    if ( false === getenv( $name ) ) {
        WP_CLI::error( 'Missing source connection variable: ' . $name );
    }
}

$source_prefix = getenv( 'GK_SOURCE_PREFIX' );
if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $source_prefix ) ) {
    WP_CLI::error( 'Invalid source table prefix.' );
}

$source = new wpdb( getenv( 'GK_SOURCE_DB_USER' ), getenv( 'GK_SOURCE_DB_PASSWORD' ), getenv( 'GK_SOURCE_DB_NAME' ), getenv( 'GK_SOURCE_DB_HOST' ) );
$source->set_prefix( $source_prefix );
$source->hide_errors();
if ( ! $source->get_var( 'SELECT 1' ) ) {
    WP_CLI::error( 'Source database is unavailable.' );
}

$source_home = 'https://georg-kloster.ru';
$target_home = home_url();
$source_de   = 'https://georg-kloster.de';
$phase       = getenv( 'GK_MIGRATION_PHASE' ) ?: 'content';
$GLOBALS['source'] = $source;
$GLOBALS['source_home'] = $source_home;
$GLOBALS['target_home'] = $target_home;
$GLOBALS['source_de'] = $source_de;

function gkm_source_option( string $name, $fallback = null ) {
    global $source;
    $value = $source->get_var( $source->prepare( "SELECT option_value FROM {$source->options} WHERE option_name = %s", $name ) );
    return null === $value ? $fallback : maybe_unserialize( $value );
}

function gkm_replace_urls( $value ) {
    global $source_home, $source_de, $target_home;
    if ( is_array( $value ) ) {
        foreach ( $value as &$item ) {
            $item = gkm_replace_urls( $item );
        }
        return $value;
    }
    if ( ! is_string( $value ) ) {
        return $value;
    }
    return str_replace(
        array( $source_home, 'http://georg-kloster.ru', $source_de, 'http://georg-kloster.de' ),
        array( $target_home, $target_home, $target_home . '/de', $target_home . '/de' ),
        $value
    );
}

function gkm_clean_content( string $content, string $type, int $source_id ): string {
    $content = gkm_replace_urls( $content );
    $content = preg_replace( '/\[fusion_form\b[^\]]*\]/iu', '[gkps_contact_form]', $content );
    $content = gkm_convert_legacy_youtube( $content );
    $content = preg_replace( '/\[\/?fusion_[^\]]*\]/iu', "\n\n", $content );
    $content = preg_replace( '/\[\/?bwg[^\]]*\]/iu', "\n\n", $content );
    $content = preg_replace( '/\[\/?avada_[^\]]*\]/iu', "\n\n", $content );
    // Legacy shortcodes for dynamic Avada cards would otherwise render as raw text.
    $content = preg_replace( '/\[(?:\/)?(?:tribe_events|rev_slider|layerslider|kloster_form)[^\]]*\]/iu', '', $content );
    if ( 'post' === $type && 2216 === $source_id ) {
        $content = preg_replace( '/\[calendar\b[^\]]*\]/iu', '[gkpsch_schedule]', $content );
    }
    if ( 'page' === $type && in_array( $source_id, array( 2204, 2883 ), true ) && false === strpos( $content, '[gkps_contact_form]' ) ) {
        $content .= "\n\n[gkps_contact_form]";
    }
    return trim( $content );
}

function gkm_refresh_content(): void {
    global $source;
    $rows = $source->get_results( "SELECT ID,post_type,post_content,post_excerpt FROM {$source->posts} WHERE post_type IN ('post','page') AND post_status NOT IN ('trash','auto-draft')", ARRAY_A );
    $updated = 0;
    foreach ( $rows as $row ) {
        $old_id = (int) $row['ID'];
        if ( 3372 === $old_id ) { continue; } // Native gallery replaces Photo Gallery markup.
        $id = gkm_existing_post( $old_id );
        if ( ! $id ) { continue; }
        wp_update_post( wp_slash( array(
            'ID' => $id,
            'post_content' => gkm_clean_content( $row['post_content'], $row['post_type'], $old_id ),
            'post_excerpt' => gkm_clean_content( $row['post_excerpt'], $row['post_type'], $old_id ),
        ) ) );
        ++$updated;
    }
    WP_CLI::log( "Legacy content refreshed for {$updated} posts and pages." );
}

function gkm_meta_allowed( string $key ): bool {
    if ( preg_match( '/^(?:_?fusion|_?avada|pyre_|_?awb_|_edit_|_wp_old_slug|_oembed_)/i', $key ) ) {
        return false;
    }
    if ( '_wp_page_template' === $key ) {
        return false;
    }
    return true;
}

function gkm_existing_post( int $source_id ): int {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gkm_source_id' AND meta_value = %s LIMIT 1",
        (string) $source_id
    ) );
}

function gkm_content(): void {
    global $source, $wpdb, $source_home, $target_home;
    $posts = $source->get_results(
        "SELECT * FROM {$source->posts} WHERE post_type IN ('post','page','attachment','popup') AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY ID",
        ARRAY_A
    );
    $attachments = $source->get_results( "SELECT * FROM {$source->posts} WHERE post_type='attachment' AND post_status='inherit' ORDER BY ID", ARRAY_A );
    $posts = array_merge( $posts, $attachments );
    $map = array();
    $new_post_ids = array();
    $created = 0;
    foreach ( $posts as $old ) {
        $old_id = (int) $old['ID'];
        $existing = gkm_existing_post( $old_id );
        if ( $existing ) {
            $map[ $old_id ] = $existing;
            continue;
        }
        $type = $old['post_type'];
        $status = $old['post_status'];
        if ( 'popup' === $type || ( 'page' === $type && in_array( $old_id, array( 2036, 2791 ), true ) ) ) {
            $status = 'draft';
        }
        $postarr = array(
            'post_type'         => $type,
            'post_status'       => $status,
            'post_title'        => $old['post_title'],
            'post_content'      => gkm_clean_content( $old['post_content'], $type, $old_id ),
            'post_excerpt'      => gkm_clean_content( $old['post_excerpt'], $type, $old_id ),
            'post_name'         => in_array( $old_id, array( 2036, 2791 ), true ) ? 'legacy-home-' . $old_id : $old['post_name'],
            'post_date'         => $old['post_date'],
            'post_date_gmt'     => $old['post_date_gmt'],
            'post_modified'     => $old['post_modified'],
            'post_modified_gmt' => $old['post_modified_gmt'],
            'post_author'       => 1,
            'post_mime_type'    => $old['post_mime_type'],
            'post_password'     => $old['post_password'],
            'comment_status'    => $old['comment_status'],
            'ping_status'       => $old['ping_status'],
            'menu_order'        => (int) $old['menu_order'],
            'guid'              => str_replace( $source_home, $target_home, $old['guid'] ),
        );
        $new_id = wp_insert_post( wp_slash( $postarr ), true );
        if ( is_wp_error( $new_id ) ) {
            WP_CLI::error( 'Could not import source post ' . $old_id . ': ' . $new_id->get_error_message() );
        }
        $map[ $old_id ] = (int) $new_id;
        if ( 'post' === $type ) { $new_post_ids[ (int) $new_id ] = true; }
        add_post_meta( $new_id, '_gkm_source_id', $old_id, true );
        ++$created;
    }

    $meta_rows = $source->get_results(
        "SELECT pm.post_id,pm.meta_key,pm.meta_value FROM {$source->postmeta} pm INNER JOIN {$source->posts} p ON p.ID=pm.post_id WHERE p.post_type IN ('post','page','attachment','popup') AND p.post_status NOT IN ('trash','auto-draft') ORDER BY pm.meta_id",
        ARRAY_A
    );
    $meta_count = 0;
    foreach ( $meta_rows as $row ) {
        $old_id = (int) $row['post_id'];
        if ( ! isset( $map[ $old_id ] ) || ! gkm_meta_allowed( $row['meta_key'] ) ) {
            continue;
        }
        $new_id = $map[ $old_id ];
        $key = $row['meta_key'];
        if ( metadata_exists( 'post', $new_id, $key ) && ! in_array( $key, array( '_wp_attachment_metadata', '_wp_attached_file', '_wp_attachment_image_alt' ), true ) ) {
            continue;
        }
        $value = maybe_unserialize( $row['meta_value'] );
        if ( '_thumbnail_id' === $key ) {
            $value = $map[ (int) $value ] ?? 0;
        } elseif ( '_wp_attached_file' !== $key && '_wp_attachment_metadata' !== $key ) {
            $value = gkm_replace_urls( $value );
        }
        if ( '_thumbnail_id' === $key && ! $value ) {
            continue;
        }
        add_post_meta( $new_id, $key, wp_slash( $value ) );
        ++$meta_count;
    }

    foreach ( $posts as $old ) {
        $old_id = (int) $old['ID'];
        if ( isset( $map[ $old_id ] ) && ! empty( $old['post_parent'] ) && isset( $map[ (int) $old['post_parent'] ] ) ) {
            $wpdb->update( $wpdb->posts, array( 'post_parent' => $map[ (int) $old['post_parent'] ] ), array( 'ID' => $map[ $old_id ] ) );
        }
    }

    $term_map = array();
    $terms = $source->get_results(
        "SELECT tt.term_taxonomy_id,tt.term_id,tt.taxonomy,tt.description,tt.parent,t.name,t.slug FROM {$source->term_taxonomy} tt INNER JOIN {$source->terms} t ON t.term_id=tt.term_id WHERE tt.taxonomy IN ('category','post_tag') ORDER BY tt.term_taxonomy_id",
        ARRAY_A
    );
    foreach ( $terms as $term ) {
        $result = term_exists( $term['slug'], $term['taxonomy'] );
        if ( ! $result ) {
            $result = wp_insert_term( $term['name'], $term['taxonomy'], array( 'slug' => $term['slug'], 'description' => $term['description'] ) );
        }
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( 'Could not import term ' . $term['term_taxonomy_id'] . ': ' . $result->get_error_message() );
        }
        $term_map[ (int) $term['term_taxonomy_id'] ] = array( 'term_id' => (int) $result['term_id'], 'term_taxonomy_id' => (int) $result['term_taxonomy_id'] );
    }
    $relationships = $source->get_results(
        "SELECT object_id,term_taxonomy_id FROM {$source->term_relationships} ORDER BY object_id,term_taxonomy_id",
        ARRAY_A
    );
    $new_post_categories = array();
    foreach ( $relationships as $row ) {
        $old_post = (int) $row['object_id'];
        $old_tt = (int) $row['term_taxonomy_id'];
        if ( isset( $map[ $old_post ], $term_map[ $old_tt ] ) ) {
            $taxonomy = $wpdb->get_var( $wpdb->prepare( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $term_map[ $old_tt ]['term_taxonomy_id'] ) );
            $term_id = $term_map[ $old_tt ]['term_id'];
            wp_set_object_terms( $map[ $old_post ], $term_id, $taxonomy, true );
            if ( 'category' === $taxonomy && isset( $new_post_ids[ $map[ $old_post ] ] ) ) {
                $new_post_categories[ $map[ $old_post ] ][] = $term_id;
            }
        }
    }
    // wp_insert_post assigns WordPress's default category before the source terms arrive.
    $default_category = (int) get_option( 'default_category' );
    foreach ( $new_post_categories as $post_id => $category_ids ) {
        if ( ! in_array( $default_category, $category_ids, true ) ) {
            $removed = wp_remove_object_terms( $post_id, $default_category, 'category' );
            if ( is_wp_error( $removed ) ) {
                WP_CLI::error( 'Could not remove default category from imported post ' . $post_id . ': ' . $removed->get_error_message() );
            }
        }
    }

    $source_translations = $source_prefix = getenv( 'GK_SOURCE_PREFIX' ) . 'icl_translations';
    $target_translations = $wpdb->prefix . 'icl_translations';
    $translations = $source->get_results(
        "SELECT element_type,element_id,trid,language_code,source_language_code FROM {$source_translations} WHERE element_type IN ('post_post','post_page','post_attachment','post_popup','tax_category','tax_post_tag') ORDER BY translation_id",
        ARRAY_A
    );
    $translation_count = 0;
    foreach ( $translations as $row ) {
        $old_id = (int) $row['element_id'];
        $is_tax = 0 === strpos( $row['element_type'], 'tax_' );
        $new_id = $is_tax ? ( $term_map[ $old_id ]['term_taxonomy_id'] ?? 0 ) : ( $map[ $old_id ] ?? 0 );
        if ( ! $new_id ) {
            continue;
        }
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT translation_id FROM {$target_translations} WHERE element_type=%s AND element_id=%d", $row['element_type'], $new_id ) );
        if ( $exists ) {
            continue;
        }
        $wpdb->insert( $target_translations, array(
            'element_type' => $row['element_type'],
            'element_id' => $new_id,
            'trid' => (int) $row['trid'],
            'language_code' => $row['language_code'],
            'source_language_code' => $row['source_language_code'],
        ) );
        ++$translation_count;
    }
    WP_CLI::log( "Content: {$created} new posts, {$meta_count} metadata entries, " . count( $terms ) . " terms, {$translation_count} translation links." );
}

function gkm_settings(): void {
    global $source, $wpdb;
    $exact = array(
        'icl_sitepress_settings', 'icl_st_settings', 'wpml_language_switcher', 'wpml_cms_nav_settings', '_wpml_media',
        'wp_mail_smtp', 'wp_mail_smtp_mail_key',
        'redirection_options', 'wp-smush-settings', 'wp-smush-lazy_load', 'wp-smush-resize_sizes', 'wp-smush-preset_configs',
        'wpseo', 'wpseo_titles', 'wpseo_social', 'wpseo_premium', 'wpseo_redirect', 'wpseo_llmstxt',
        'wpseo-premium-redirects-base', 'wpseo-premium-redirects-export-plain', 'wpseo-premium-redirects-export-regex',
        'pum_privacy_cookie_list', 'pum_gutenberg_legacy_choice',
    );
    $copied = 0;
    foreach ( $exact as $name ) {
        $value = gkm_source_option( $name );
        if ( null === $value ) {
            continue;
        }
        if ( 'icl_sitepress_settings' === $name && is_array( $value ) ) {
            $value['language_negotiation_type'] = 1;
            $value['language_domains'] = array();
            $value['language_per_domain_sso_enabled'] = 0;
            $value['urls']['directory_for_default_language'] = 0;
            $value['urls']['hide_language_switchers'] = 0;
        } elseif ( ! in_array( $name, array( 'wp_mail_smtp', 'wp_mail_smtp_mail_key' ), true ) ) {
            $value = gkm_replace_urls( $value );
        }
        update_option( $name, $value, false );
        ++$copied;
    }
    $source_languages = $source->get_results( 'SELECT code FROM ' . getenv( 'GK_SOURCE_PREFIX' ) . 'icl_languages WHERE active=1', ARRAY_A );
    foreach ( $source_languages as $language ) {
        $wpdb->update( $wpdb->prefix . 'icl_languages', array( 'active' => 1 ), array( 'code' => $language['code'] ) );
    }
    // Redirection rules and FileBird folders are plugin data, not theme layout.
    gkm_copy_table( 'redirection_groups', null, null );
    gkm_copy_table( 'redirection_items', null, 'redirect_url' );
    gkm_copy_table( 'fbv', null, null );
    gkm_copy_table( 'fbv_attachment_folder', 'attachment_id', null );
    WP_CLI::log( "Plugin settings: {$copied} option groups copied." );
}

function gkm_schema(): void {
    global $source, $wpdb;
    $source_prefix = getenv( 'GK_SOURCE_PREFIX' );
    $tables = $source->get_col( $source->prepare( 'SHOW TABLES LIKE %s', $source_prefix . 'icl_%' ) );
    foreach ( array( 'redirection_groups', 'redirection_items', 'redirection_logs', 'redirection_404' ) as $suffix ) {
        $tables[] = $source_prefix . $suffix;
    }
    $created = 0;
    foreach ( $tables as $from ) {
        $to = $wpdb->prefix . substr( $from, strlen( $source_prefix ) );
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $to ) ) ) {
            continue;
        }
        $definition = $source->get_row( "SHOW CREATE TABLE {$from}", ARRAY_N );
        if ( ! $definition || ! isset( $definition[1] ) ) {
            continue;
        }
        $sql = preg_replace( '/^CREATE TABLE `[^`]+`/', 'CREATE TABLE `' . $to . '`', $definition[1] );
        if ( false === $wpdb->query( $sql ) ) {
            WP_CLI::error( 'Could not create plugin table ' . $to );
        }
        ++$created;
    }
    WP_CLI::log( "Plugin schema: {$created} missing tables created, without copying old site content." );
}

function gkm_wpml_strings(): void {
    global $source, $wpdb;
    $contexts = array(
        'default', 'sitepress', 'wpml-string-translation', 'wpml-translation-management',
        'wordpress-seo', 'wordpress-seo-premium', 'admin_texts_wpseo_titles', 'admin_texts_wpseo_social',
        'popup-maker', 'redirection', 'filebird', 'wp-mail-smtp', 'wp-smushit', 'duplicator',
    );
    $quoted = implode( ',', array_fill( 0, count( $contexts ), '%s' ) );
    $old_strings = getenv( 'GK_SOURCE_PREFIX' ) . 'icl_strings';
    $old_translations = getenv( 'GK_SOURCE_PREFIX' ) . 'icl_string_translations';
    $new_strings = $wpdb->prefix . 'icl_strings';
    $new_translations = $wpdb->prefix . 'icl_string_translations';
    $strings = $source->get_results( $source->prepare( "SELECT * FROM {$old_strings} WHERE context IN ({$quoted}) ORDER BY id", $contexts ), ARRAY_A );
    $string_columns = array_flip( $wpdb->get_col( "SHOW COLUMNS FROM {$new_strings}" ) );
    $string_ids = array();
    $count = 0;
    foreach ( $strings as $row ) {
        $id = (int) $row['id'];
        $string_ids[ $id ] = true;
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$new_strings} WHERE id=%d", $id ) ) ) {
            continue;
        }
        if ( $wpdb->insert( $new_strings, array_intersect_key( $row, $string_columns ) ) ) {
            ++$count;
        }
    }
    $translations = $source->get_results( $source->prepare(
        "SELECT st.* FROM {$old_translations} st INNER JOIN {$old_strings} s ON s.id=st.string_id WHERE s.context IN ({$quoted}) ORDER BY st.id",
        $contexts
    ), ARRAY_A );
    $translation_columns = array_flip( $wpdb->get_col( "SHOW COLUMNS FROM {$new_translations}" ) );
    $translated = 0;
    foreach ( $translations as $row ) {
        if ( ! isset( $string_ids[ (int) $row['string_id'] ] ) ) {
            continue;
        }
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$new_translations} WHERE id=%d", (int) $row['id'] ) ) ) {
            continue;
        }
        if ( $wpdb->insert( $new_translations, array_intersect_key( $row, $translation_columns ) ) ) {
            ++$translated;
        }
    }
    WP_CLI::log( "WPML strings: {$count} source strings and {$translated} translations for installed plugins and WordPress." );
}

function gkm_copy_table( string $suffix, ?string $post_column, ?string $url_column ): void {
    global $source, $wpdb;
    $from = getenv( 'GK_SOURCE_PREFIX' ) . $suffix;
    $to = $wpdb->prefix . $suffix;
    if ( ! $source->get_var( $source->prepare( 'SHOW TABLES LIKE %s', $from ) ) || ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $to ) ) ) {
        return;
    }
    $rows = $source->get_results( "SELECT * FROM {$from}", ARRAY_A );
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$to}" );
    $map = array();
    if ( $post_column ) {
        foreach ( $wpdb->get_results( "SELECT post_id,meta_value FROM {$wpdb->postmeta} WHERE meta_key='_gkm_source_id'", ARRAY_A ) as $item ) {
            $map[ (int) $item['meta_value'] ] = (int) $item['post_id'];
        }
    }
    $count = 0;
    foreach ( $rows as $row ) {
        $row = array_intersect_key( $row, array_flip( $columns ) );
        if ( $post_column && isset( $row[ $post_column ] ) ) {
            if ( ! isset( $map[ (int) $row[ $post_column ] ] ) ) {
                continue;
            }
            $row[ $post_column ] = $map[ (int) $row[ $post_column ] ];
        }
        if ( $url_column && isset( $row[ $url_column ] ) ) {
            $row[ $url_column ] = gkm_replace_urls( $row[ $url_column ] );
        }
        if ( $wpdb->replace( $to, $row ) ) {
            ++$count;
        }
    }
    WP_CLI::log( "Plugin table {$suffix}: {$count} records copied." );
}

function gkm_gallery(): void {
    global $source, $wpdb;
    $upload = wp_get_upload_dir();
    $old_tables = getenv( 'GK_SOURCE_PREFIX' );
    $galleries = $source->get_results( "SELECT id,name,slug FROM {$old_tables}bwg_gallery ORDER BY id", ARRAY_A );
    $index_id = gkm_existing_post( 3372 );
    if ( ! $index_id ) {
        $index_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Фотогалерея', 'post_name' => 'fotogalereya' ) );
    }
    $parts = array( '<!-- wp:paragraph --><p>Фотографии монастыря и строительства храма.</p><!-- /wp:paragraph -->' );
    $image_count = 0;
    foreach ( $galleries as $gallery ) {
        $parts[] = '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' . esc_html( $gallery['name'] ) . '</h2><!-- /wp:heading -->';
        $images = $source->get_results( $source->prepare(
            "SELECT id,image_url,alt,description FROM {$old_tables}bwg_image WHERE gallery_id=%d AND published=1 ORDER BY `order`,id",
            (int) $gallery['id']
        ), ARRAY_A );
        $gallery_blocks = array();
        foreach ( $images as $image ) {
            $relative = 'photo-gallery' . urldecode( $image['image_url'] );
            $file = trailingslashit( $upload['basedir'] ) . $relative;
            if ( ! is_file( $file ) ) {
                WP_CLI::warning( 'Gallery file missing for image ' . $image['id'] );
                continue;
            }
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value=%s LIMIT 1", $relative ) );
            if ( $existing ) {
                $id = (int) $existing;
            } else {
                $mime = wp_check_filetype( $file )['type'] ?: 'image/jpeg';
                $id = wp_insert_attachment( array(
                    'post_title' => pathinfo( $file, PATHINFO_FILENAME ),
                    'post_excerpt' => $image['description'],
                    'post_mime_type' => $mime,
                    'post_status' => 'inherit',
                    'post_parent' => $index_id,
                ), $file, $index_id );
                if ( is_wp_error( $id ) ) {
                    WP_CLI::warning( 'Could not register gallery image ' . $image['id'] );
                    continue;
                }
                update_post_meta( $id, '_wp_attachment_image_alt', $image['alt'] );
                ++$image_count;
            }
            $url = wp_get_attachment_url( $id );
            $gallery_blocks[] = '<!-- wp:image {"id":' . (int) $id . ',"sizeSlug":"large","lightbox":{"enabled":true}} --><figure class="wp-block-image size-large"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $image['alt'] ) . '" class="wp-image-' . (int) $id . '"/></figure><!-- /wp:image -->';
        }
        if ( $gallery_blocks ) {
            $parts[] = '<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">' . implode( '', $gallery_blocks ) . '</figure><!-- /wp:gallery -->';
        }
    }
    wp_update_post( wp_slash( array( 'ID' => $index_id, 'post_content' => implode( "\n\n", $parts ), 'post_status' => 'publish' ) ) );
    WP_CLI::log( 'Native galleries: ' . count( $galleries ) . " groups, {$image_count} new media entries; page {$index_id}." );
}

switch ( $phase ) {
    case 'content':
        gkm_content();
        break;
    case 'settings':
        gkm_settings();
        break;
    case 'gallery':
        gkm_gallery();
        break;
    case 'schema':
        gkm_schema();
        break;
    case 'strings':
        gkm_wpml_strings();
        break;
    case 'refresh':
        gkm_refresh_content();
        break;
    default:
        WP_CLI::error( 'Unknown migration phase.' );
}
