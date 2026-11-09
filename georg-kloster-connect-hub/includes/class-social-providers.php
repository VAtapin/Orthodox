<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Official publishing transports. Network calls run only in the existing queue. */
final class Social_Providers {
    public const VK_API_VERSION = '5.199';
    public const META_GRAPH_API_VERSION = 'v27.0';
    public static function supports( string $id ): bool { return in_array( $id, array( 'instagram', 'vk', 'ok' ), true ); }

    public static function vk_community_target( string $value ): string {
        if ( ! preg_match( '/^-?(?:club)?([1-9][0-9]*)$/iD', trim( $value ), $match ) ) { return $value; }
        return '-' . $match[1];
    }

    public static function valid( string $id, array $settings ): bool {
        foreach ( array( 'token', 'target', 'api_version', 'application_key', 'application_secret' ) as $field ) { if ( isset( $settings[$field] ) && ! is_string( $settings[$field] ) ) { return false; } }
        if ( empty( $settings['enabled'] ) || ! is_string( $settings['token'] ) || ! preg_match( '/^[A-Za-z0-9._~-]+$/D', $settings['token'] ) ) { return false; }
        if ( 'vk' === $id ) { return (bool) preg_match( '/^-[1-9][0-9]*$/D', self::vk_community_target( $settings['target'] ) ) && (bool) preg_match( '/^[0-9]+\.[0-9]+$/D', $settings['api_version'] ); }
        if ( ! preg_match( '/^[1-9][0-9]*$/D', $settings['target'] ) ) { return false; }
        if ( 'instagram' === $id ) { return (bool) preg_match( '/^v[0-9]+\.[0-9]+$/D', $settings['api_version'] ); }
        return 'ok' === $id && (bool) preg_match( '/^[A-Za-z0-9]+$/D', $settings['application_key'] ?? '' ) && (bool) preg_match( '/^[A-Za-z0-9._-]+$/D', $settings['application_secret'] ?? '' );
    }

    public static function image( int $post_id ): string {
        return self::attachment_image( (int) get_post_thumbnail_id( $post_id ) );
    }

    public static function attachment_image( int $id ): string {
        $url = $id && 'image/jpeg' === get_post_mime_type( $id ) ? wp_get_attachment_url( $id ) : '';
        return is_string( $url ) && wp_http_validate_url( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ? $url : '';
    }

    public static function help( string $id ): string {
        if ( 'instagram' === $id ) { return __( 'Use a professional Instagram account linked to a Facebook Page and a token with instagram_basic and instagram_content_publish permissions. A public HTTPS JPEG featured image is required. The container is processed in the background before publication.', 'georg-kloster-connect-hub' ); }
        if ( 'vk' === $id ) { return ''; }
        return __( 'Use an OK application with GROUP_CONTENT permission, an access token, application public key and application secret. Only publication to the configured group is enabled.', 'georg-kloster-connect-hub' );
    }

    private static function call( string $method, string $url, array $body, array $headers = array() ): array {
        $args = array( 'method' => $method, 'timeout' => 15, 'redirection' => 0, 'headers' => $headers );
        if ( 'GET' !== $method ) { $args['body'] = $body; }
        try { $response = wp_safe_remote_request( $url, $args ); }
        catch ( \Throwable $error ) { $response = new \WP_Error( 'transport', 'Transport failed.' ); }
        $code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
        return array( $code, $data );
    }

    private static function api_error_details( $data ): array {
        if ( ! is_array( $data ) ) { return array(); }
        $error = isset( $data['error'] ) && is_array( $data['error'] ) ? $data['error'] : $data;
        $details = array();
        foreach ( array( 'message', 'type', 'code', 'error_subcode', 'fbtrace_id', 'error_user_title', 'error_user_msg', 'error_msg', 'error_code', 'is_transient' ) as $key ) {
            if ( ! isset( $error[ $key ] ) || ! is_scalar( $error[ $key ] ) ) { continue; }
            $value = is_bool( $error[ $key ] ) ? ( $error[ $key ] ? 'true' : 'false' ) : sanitize_text_field( (string) $error[ $key ] );
            if ( '' !== $value ) { $details[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 500 ) : substr( $value, 0, 500 ); }
        }
        return $details;
    }

    private static function result( int $code, bool $accepted, bool $pending = false, array $response = array(), bool $retry = false, $api_response = array() ): array {
        return array( 'http_code' => $code, 'accepted' => $accepted, 'pending' => $pending, 'response' => $response, 'api_error' => self::api_error_details( $api_response ), 'retry' => $retry || 0 === $code || 429 === $code || $code >= 500, 'error' => $accepted || $pending ? '' : __( 'The remote API did not confirm publication. Check permissions, credentials and account limits.', 'georg-kloster-connect-hub' ) );
    }

    private static function vk_wall_photo( array $settings, array &$job ): array {
        if ( ! empty( $job['vk_photo'] ) && is_string( $job['vk_photo'] ) && preg_match( '/^photo-?[1-9][0-9]*_[1-9][0-9]*$/D', $job['vk_photo'] ) ) { return array( $job['vk_photo'], array() ); }
        $attachment = (int) get_post_thumbnail_id( (int) $job['source'] );
        $path = $attachment ? get_attached_file( $attachment ) : false;
        $mime = $attachment ? get_post_mime_type( $attachment ) : '';
        if ( ! is_string( $path ) || ! is_readable( $path ) || ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) || ! is_file( $path ) || filesize( $path ) > 20 * 1024 * 1024 ) {
            $failure = self::result( 400, false, false, array(), false, array( 'error_msg' => 'VK requires a readable featured image (JPEG, PNG or GIF, at most 20 MB).' ) );
            $failure['endpoint'] = '';
            return array( '', $failure );
        }
        $group_id = substr( self::vk_community_target( $settings['target'] ), 1 );
        $endpoint = 'https://api.vk.com/method/photos.getWallUploadServer';
        list( $code, $data ) = self::call( 'POST', $endpoint, array( 'access_token' => $settings['token'], 'v' => $settings['api_version'], 'group_id' => $group_id ) );
        $upload_url = $data['response']['upload_url'] ?? '';
        if ( 200 !== $code || ! is_string( $upload_url ) || ! wp_http_validate_url( $upload_url ) || 'https' !== wp_parse_url( $upload_url, PHP_URL_SCHEME ) ) {
            $failure = self::result( $code, false, false, array(), false, $data ); $failure['endpoint'] = $endpoint;
            return array( '', $failure );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A bounded local WordPress media file is sent to VK.
        $file = file_get_contents( $path );
        if ( ! is_string( $file ) || '' === $file ) { return array( '', self::result( 400, false ) ); }
        $boundary = 'gkch' . str_replace( '-', '', wp_generate_uuid4() );
        $multipart = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"image\"\r\nContent-Type: " . $mime . "\r\n\r\n" . $file . "\r\n--" . $boundary . "--\r\n";
        try { $response = wp_safe_remote_request( $upload_url, array( 'method' => 'POST', 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ), 'body' => $multipart ) ); }
        catch ( \Throwable $error ) { $response = new \WP_Error( 'transport', 'Transport failed.' ); }
        $code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 200 !== $code || ! is_array( $data ) || ! isset( $data['server'], $data['photo'], $data['hash'] ) || ! is_scalar( $data['server'] ) || ! is_string( $data['photo'] ) || ! is_string( $data['hash'] ) ) {
            $failure = self::result( $code, false, false, array(), false, $data ); $failure['endpoint'] = 'https://api.vk.com/method/photos.getWallUploadServer';
            return array( '', $failure );
        }
        $endpoint = 'https://api.vk.com/method/photos.saveWallPhoto';
        list( $code, $data ) = self::call( 'POST', $endpoint, array( 'access_token' => $settings['token'], 'v' => $settings['api_version'], 'group_id' => $group_id, 'server' => $data['server'], 'photo' => $data['photo'], 'hash' => $data['hash'] ) );
        $photo = $data['response'][0] ?? array();
        $owner = $photo['owner_id'] ?? ''; $id = $photo['id'] ?? '';
        if ( 200 !== $code || ! is_scalar( $owner ) || ! is_scalar( $id ) || ! preg_match( '/^-?[1-9][0-9]*$/D', (string) $owner ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ) {
            $failure = self::result( $code, false, false, array(), false, $data ); $failure['endpoint'] = $endpoint;
            return array( '', $failure );
        }
        $job['vk_photo'] = 'photo' . $owner . '_' . $id;
        return array( $job['vk_photo'], array() );
    }

    public static function send( string $id, array $settings, array &$job ): array {
        if ( 'instagram' === $id ) { return self::instagram( $settings, $job ); }
        $text = $job['text'] . "\n" . $job['url'];
        if ( 'vk' === $id ) {
            $target = self::vk_community_target( $settings['target'] );
            $photo = '';
            if ( 'post' === ( $job['kind'] ?? '' ) ) {
                list( $photo, $failure ) = self::vk_wall_photo( $settings, $job );
                if ( '' === $photo ) { return $failure; }
            }
            $message = $job['text'];
            if ( 'post' === ( $job['kind'] ?? '' ) && ! empty( $job['source'] ) ) {
                $title = trim( wp_strip_all_tags( get_the_title( (int) $job['source'] ) ) );
                $prefix = $title . "\n";
                if ( '' !== $title && str_starts_with( $message, $prefix ) ) {
                    $excerpt = ltrim( substr( $message, strlen( $prefix ) ) );
                    if ( str_starts_with( $excerpt, $title ) ) { $excerpt = ltrim( substr( $excerpt, strlen( $title ) ) ); }
                    $message = $title . ( '' !== $excerpt ? "\n\n" . $excerpt : '' );
                }
            }
            $body = array( 'access_token' => $settings['token'], 'v' => $settings['api_version'], 'owner_id' => $target, 'from_group' => 1, 'message' => $message, 'guid' => substr( hash( 'sha256', $target . $job['source'] . $text . ( $job['reply_context']['configuration'] ?? '' ) ), 0, 32 ) );
            $attachments = array_filter( array( $photo, $job['url'] ), 'strlen' );
            if ( $attachments ) { $body['attachments'] = implode( ',', $attachments ); }
            list( $code, $data ) = self::call( 'POST', 'https://api.vk.com/method/wall.post', $body );
            $remote_id = is_array( $data ) ? ( $data['response']['post_id'] ?? null ) : null;
            $accepted = 200 === $code && is_array( $data ) && ! isset( $data['error'] ) && is_scalar( $remote_id ) && preg_match( '/^[1-9][0-9]*$/D', (string) $remote_id );
            $retry = is_array( $data ) && in_array( $data['error']['error_code'] ?? null, array( 6, 10, 29 ), true );
            return self::result( $code, $accepted, false, $accepted ? array( 'id' => (string) $data['response']['post_id'] ) : array(), $retry, $accepted ? array() : $data );
        }
        if ( 'ok' !== $id ) { return self::result( 0, false ); }
        $media = array( array( 'type' => 'text', 'text' => $job['text'] ) );
        if ( '' !== $job['url'] ) { $media[] = array( 'type' => 'link', 'url' => $job['url'] ); }
        $body = array( 'method' => 'mediatopic.post', 'application_key' => $settings['application_key'], 'format' => 'json', 'type' => 'GROUP_THEME', 'gid' => $settings['target'], 'attachment' => wp_json_encode( array( 'media' => $media, 'onBehalfOfGroup' => 'true' ) ) );
        ksort( $body, SORT_STRING ); $signature = '';
        foreach ( $body as $key => $value ) { $signature .= $key . '=' . $value; }
        $body['sig'] = md5( $signature . md5( $settings['token'] . $settings['application_secret'] ) );
        $body['access_token'] = $settings['token'];
        list( $code, $data ) = self::call( 'POST', 'https://api.ok.ru/fb.do', $body );
        $accepted = 200 === $code && ( is_string( $data ) || is_int( $data ) ) && preg_match( '/^[1-9][0-9]*$/D', (string) $data );
        $result = self::result( $code, (bool) $accepted, false, $accepted ? array( 'id' => (string) $data ) : array(), false, $accepted ? array() : $data );
        // OK has no publication idempotency key. A timeout needs a human check.
        if ( 0 === $code || $code >= 500 ) { $result['uncertain'] = true; $result['retry'] = false; }
        return $result;
    }

    private static function instagram( array $settings, array &$job ): array {
        $base = 'https://graph.facebook.com/' . $settings['api_version'] . '/';
        $headers = array( 'Authorization' => 'Bearer ' . $settings['token'] );
        if ( empty( $job['container_id'] ) ) {
            $image = $job['image_url'] ?? '';
            if ( ! $image ) { $result = self::result( 400, false ); $result['error'] = __( 'Instagram requires a public JPEG featured image.', 'georg-kloster-connect-hub' ); return $result; }
            list( $code, $data ) = self::call( 'POST', $base . $settings['target'] . '/media', array( 'image_url' => $image, 'caption' => wp_html_excerpt( $job['text'] . "\n" . $job['url'], 2200, '…' ) ), $headers );
            if ( $code >= 200 && $code < 300 && is_array( $data ) && isset( $data['id'] ) && is_scalar( $data['id'] ) && preg_match( '/^[0-9]+$/D', (string) $data['id'] ) ) {
                $job['container_id'] = (string) $data['id'];
                return self::result( $code, false, true );
            }
            return self::result( $code, false, false, array(), ! empty( $data['error']['is_transient'] ), $data );
        }
        if ( ! preg_match( '/^[0-9]+$/D', (string) $job['container_id'] ) ) { return self::result( 400, false ); }
        list( $code, $data ) = self::call( 'GET', add_query_arg( 'fields', 'status_code', $base . $job['container_id'] ), array(), $headers );
        $status = is_array( $data ) ? ( $data['status_code'] ?? '' ) : '';
        if ( 200 !== $code || ! is_string( $status ) || '' === $status ) { return self::result( $code, false, false, array(), false, $data ); }
        if ( 'PUBLISHED' === $status ) { return self::result( $code, true, false, array( 'container_id' => $job['container_id'] ) ); }
        if ( 'IN_PROGRESS' === $status ) { return self::result( $code, false, true ); }
        if ( 'FINISHED' !== $status ) { $job['container_terminal'] = true; return self::result( 400, false, false, array(), false, $data ); }
        list( $code, $data ) = self::call( 'POST', $base . $settings['target'] . '/media_publish', array( 'creation_id' => $job['container_id'] ), $headers );
        $accepted = $code >= 200 && $code < 300 && is_array( $data ) && ! empty( $data['id'] ) && is_scalar( $data['id'] );
        // Reuse this same container after transient failures; never create another post.
        return self::result( $code, $accepted, false, $accepted ? array( 'id' => (string) $data['id'] ) : array(), ! empty( $data['error']['is_transient'] ), $accepted ? array() : $data );
    }
}
