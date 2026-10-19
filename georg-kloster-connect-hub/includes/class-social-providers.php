<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Official publishing transports. Network calls run only in the existing queue. */
final class Social_Providers {
    public static function supports( string $id ): bool { return in_array( $id, array( 'instagram', 'vk', 'ok' ), true ); }

    public static function valid( string $id, array $settings ): bool {
        foreach ( array( 'token', 'target', 'api_version', 'application_key', 'application_secret' ) as $field ) { if ( isset( $settings[$field] ) && ! is_string( $settings[$field] ) ) { return false; } }
        if ( empty( $settings['enabled'] ) || ! is_string( $settings['token'] ) || ! preg_match( '/^[A-Za-z0-9._~-]+$/D', $settings['token'] ) ) { return false; }
        if ( 'vk' === $id ) { return (bool) preg_match( '/^-?[1-9][0-9]*$/D', $settings['target'] ) && (bool) preg_match( '/^[0-9]+\.[0-9]+$/D', $settings['api_version'] ); }
        if ( ! preg_match( '/^[1-9][0-9]*$/D', $settings['target'] ) ) { return false; }
        if ( 'instagram' === $id ) { return (bool) preg_match( '/^v[0-9]+\.[0-9]+$/D', $settings['api_version'] ); }
        return 'ok' === $id && (bool) preg_match( '/^[A-Za-z0-9]+$/D', $settings['application_key'] ?? '' ) && (bool) preg_match( '/^[A-Za-z0-9._-]+$/D', $settings['application_secret'] ?? '' );
    }

    public static function image( int $post_id ): string {
        $id = get_post_thumbnail_id( $post_id );
        $url = $id && 'image/jpeg' === get_post_mime_type( $id ) ? wp_get_attachment_url( $id ) : '';
        return is_string( $url ) && wp_http_validate_url( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ? $url : '';
    }

    public static function help( string $id ): string {
        if ( 'instagram' === $id ) { return __( 'Use a professional Instagram account linked to a Facebook Page and a token with instagram_basic and instagram_content_publish permissions. A public HTTPS JPEG featured image is required. The container is processed in the background before publication.', 'georg-kloster-connect-hub' ); }
        if ( 'vk' === $id ) { return __( 'Use a VK token with wall publication permission. Enter a negative community ID to publish as the community, or a positive user ID. Enter the API version from your application, for example 5.199.', 'georg-kloster-connect-hub' ); }
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

    private static function result( int $code, bool $accepted, bool $pending = false, array $response = array(), bool $retry = false ): array {
        return array( 'http_code' => $code, 'accepted' => $accepted, 'pending' => $pending, 'response' => $response, 'retry' => $retry || 0 === $code || 429 === $code || $code >= 500, 'error' => $accepted || $pending ? '' : __( 'The remote API did not confirm publication. Check permissions, credentials and account limits.', 'georg-kloster-connect-hub' ) );
    }

    public static function send( string $id, array $settings, array &$job ): array {
        if ( 'instagram' === $id ) { return self::instagram( $settings, $job ); }
        $text = $job['text'] . "\n" . $job['url'];
        if ( 'vk' === $id ) {
            $body = array( 'access_token' => $settings['token'], 'v' => $settings['api_version'], 'owner_id' => $settings['target'], 'from_group' => str_starts_with( $settings['target'], '-' ) ? 1 : 0, 'message' => $text, 'guid' => substr( hash( 'sha256', $settings['target'] . $job['source'] . $text ), 0, 32 ) );
            list( $code, $data ) = self::call( 'POST', 'https://api.vk.com/method/wall.post', $body );
            $remote_id = is_array( $data ) ? ( $data['response']['post_id'] ?? null ) : null;
            $accepted = 200 === $code && is_array( $data ) && ! isset( $data['error'] ) && is_scalar( $remote_id ) && preg_match( '/^[1-9][0-9]*$/D', (string) $remote_id );
            $retry = is_array( $data ) && in_array( $data['error']['error_code'] ?? null, array( 6, 10, 29 ), true );
            return self::result( $code, $accepted, false, $accepted ? array( 'id' => (string) $data['response']['post_id'] ) : array(), $retry );
        }
        if ( 'ok' !== $id ) { return self::result( 0, false ); }
        $body = array( 'method' => 'mediatopic.post', 'application_key' => $settings['application_key'], 'format' => 'json', 'type' => 'GROUP_THEME', 'gid' => $settings['target'], 'attachment' => wp_json_encode( array( 'media' => array( array( 'type' => 'text', 'text' => $job['text'] ), array( 'type' => 'link', 'url' => $job['url'] ) ), 'onBehalfOfGroup' => 'true' ) ) );
        ksort( $body, SORT_STRING ); $signature = '';
        foreach ( $body as $key => $value ) { $signature .= $key . '=' . $value; }
        $body['sig'] = md5( $signature . md5( $settings['token'] . $settings['application_secret'] ) );
        $body['access_token'] = $settings['token'];
        list( $code, $data ) = self::call( 'POST', 'https://api.ok.ru/fb.do', $body );
        $accepted = 200 === $code && ( is_string( $data ) || is_int( $data ) ) && preg_match( '/^[1-9][0-9]*$/D', (string) $data );
        $result = self::result( $code, (bool) $accepted, false, $accepted ? array( 'id' => (string) $data ) : array() );
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
            return self::result( $code, false, false, array(), ! empty( $data['error']['is_transient'] ) );
        }
        if ( ! preg_match( '/^[0-9]+$/D', (string) $job['container_id'] ) ) { return self::result( 400, false ); }
        list( $code, $data ) = self::call( 'GET', add_query_arg( 'fields', 'status_code', $base . $job['container_id'] ), array(), $headers );
        $status = is_array( $data ) ? ( $data['status_code'] ?? '' ) : '';
        if ( 200 !== $code || ! is_string( $status ) || '' === $status ) { return self::result( $code, false ); }
        if ( 'PUBLISHED' === $status ) { return self::result( $code, true, false, array( 'container_id' => $job['container_id'] ) ); }
        if ( 'IN_PROGRESS' === $status ) { return self::result( $code, false, true ); }
        if ( 'FINISHED' !== $status ) { $job['container_terminal'] = true; return self::result( 400, false ); }
        list( $code, $data ) = self::call( 'POST', $base . $settings['target'] . '/media_publish', array( 'creation_id' => $job['container_id'] ), $headers );
        $accepted = $code >= 200 && $code < 300 && is_array( $data ) && ! empty( $data['id'] ) && is_scalar( $data['id'] );
        // Reuse this same container after transient failures; never create another post.
        return self::result( $code, $accepted, false, $accepted ? array( 'id' => (string) $data['id'] ) : array(), ! empty( $data['error']['is_transient'] ) );
    }
}
