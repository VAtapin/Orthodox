<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

/** Reply images live in private message metadata, never in public uploads. */
final class Request_Media {
    public const MAX_BYTES = 4194304;
    public static function unsupported(): string { return __( 'The image was not sent. Photos and static stickers up to 4 MB are supported. Videos and animated stickers are not supported.', 'georg-kloster-connect-hub' ); }
    public static function telegram( array $message ): array {
        $file = null;
        if ( isset( $message['sticker'] ) ) {
            $file = $message['sticker'];
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ! is_array( $file ) || ! empty( $file['is_animated'] ) || ! empty( $file['is_video'] ) ) { throw new \InvalidArgumentException( self::unsupported() ); }
        } elseif ( isset( $message['photo'] ) && is_array( $message['photo'] ) ) {
            $sizes = array_filter( $message['photo'], static fn( $size ) => is_array( $size ) && ( $size['file_size'] ?? 0 ) <= self::MAX_BYTES && ( $size['width'] ?? 0 ) > 0 && ( $size['height'] ?? 0 ) > 0 && $size['width'] * $size['height'] <= 4000000 );
            usort( $sizes, static fn( $a, $b ) => ( $b['width'] * $b['height'] ) <=> ( $a['width'] * $a['height'] ) );
            $file = $sizes[0] ?? null;
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ! $file ) { throw new \InvalidArgumentException( self::unsupported() ); }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        } elseif ( isset( $message['video'] ) || isset( $message['animation'] ) || isset( $message['document'] ) ) { throw new \InvalidArgumentException( self::unsupported() ); }
        if ( ! $file ) { return array(); }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! is_string( $file['file_id'] ?? null ) || ! preg_match( '/^[A-Za-z0-9_-]{1,512}$/D', $file['file_id'] ) || ( $file['file_size'] ?? 0 ) > self::MAX_BYTES ) { throw new \InvalidArgumentException( self::unsupported() ); }
        $result = Telegram_Request_Settings::api( 'getFile', array( 'file_id' => $file['file_id'] ) );
        if ( is_wp_error( $result ) ) { throw new \RuntimeException( 'Private image download failed.' ); }
        $path = $result['file_path'] ?? '';
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! is_string( $path ) || ! preg_match( '#^(?:photos|stickers)/[A-Za-z0-9_/-]+\.(?:jpg|jpeg|png|webp)$#D', $path ) || str_contains( $path, '..' ) || ( $result['file_size'] ?? 0 ) > self::MAX_BYTES ) { throw new \InvalidArgumentException( self::unsupported() ); }
        $settings = Telegram_Requests::connector()->settings();
        $response = wp_safe_remote_get( 'https://api.telegram.org/file/bot' . $settings['token'] . '/' . $path, array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => self::MAX_BYTES + 1 ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { throw new \RuntimeException( 'Private image download failed.' ); }
        return self::image( wp_remote_retrieve_body( $response ) );
    }
    public static function image( string $bytes ): array {
        $info = strlen( $bytes ) <= self::MAX_BYTES ? @getimagesizefromstring( $bytes ) : false;
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $info || $info[0] * $info[1] > 4000000 || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) { throw new \InvalidArgumentException( self::unsupported() ); }
        if ( 'image/webp' === $info['mime'] ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( str_contains( $bytes, 'ANIM' ) || ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagepng' ) ) { throw new \InvalidArgumentException( self::unsupported() ); }
            $image = @imagecreatefromstring( $bytes );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ! $image ) { throw new \InvalidArgumentException( self::unsupported() ); }
            imagesavealpha( $image, true ); ob_start(); imagepng( $image ); $bytes = ob_get_clean(); unset( $image );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( strlen( $bytes ) > self::MAX_BYTES ) { throw new \InvalidArgumentException( self::unsupported() ); }
            $info['mime'] = 'image/png';
        }
        return array( 'data' => base64_encode( $bytes ), 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1] );
    }
    public static function validated( array $media ): array {
        if ( ! $media ) { return array(); }
        $data = $media['data'] ?? null;
        $bytes = is_string( $data ) && strlen( $data ) <= 5592408 ? base64_decode( $data, true ) : false;
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( false === $bytes ) { throw new \InvalidArgumentException( self::unsupported() ); }
        return self::image( $bytes );
    }
    public static function cid( int $id ): string { return 'gkps-reply-' . $id . '@inline'; }
    public static function admin_markup( int $id ): string {
        if ( ! get_post_meta( $id, '_gkps_reply_media', true ) ) { return ''; }
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=gkps_reply_image&message_id=' . $id ), 'gkps_reply_image_' . $id );
        return '<p><img src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'Image from Telegram', 'georg-kloster-connect-hub' ) . '" style="display:block;max-width:100%;width:auto;height:auto;max-height:420px"></p>';
    }
    public static function can_view( int $id, $nonce ): bool {
        $message = get_post( $id );
        return current_user_can( 'view_gkps_requests' ) && $message && 'gkps_message' === $message->post_type && 'private' === $message->post_status && 'gkps_request' === get_post_type( $message->post_parent ) && 'private' === get_post_status( $message->post_parent ) && is_string( $nonce ) && (bool) wp_verify_nonce( $nonce, 'gkps_reply_image_' . $id );
    }
    public static function serve(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- The private media URL includes an action-specific nonce checked below with the current user's capability.
        $id = is_scalar( $_GET['message_id'] ?? null ) ? absint( wp_unslash( $_GET['message_id'] ) ) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The URL nonce is unslashed, text-sanitized and verified for this message before media is served.
        $raw_nonce = is_string( $_GET['_wpnonce'] ?? null ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
        $nonce = sanitize_text_field( $raw_nonce );
        if ( ! self::can_view( $id, $nonce ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        try { $media = self::validated( (array) get_post_meta( $id, '_gkps_reply_media', true ) ); } catch ( \Throwable $error ) { $media = array(); }
        if ( ! $media ) { status_header( 404 ); exit; }
        nocache_headers(); header( 'Content-Type: ' . $media['mime'] ); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Disposition: inline; filename="reply-image.' . ( 'image/png' === $media['mime'] ? 'png' : 'jpg' ) . '"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This endpoint responds with validated PNG/JPEG bytes, not HTML.
        echo base64_decode( $media['data'], true ); exit;
    }
}
