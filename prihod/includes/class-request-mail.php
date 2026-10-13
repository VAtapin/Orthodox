<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

/** Per-message HTML; delivery, retries and privacy stay in Requests. */
final class Request_Mail {
    public static function render( \WP_Post $request, \WP_Post $reply, array $sender ): string { return self::compose( $request, $reply, $sender )['message']; }
    public static function compose( \WP_Post $request, \WP_Post $reply, array $sender ): array {
        $locale = get_post_meta( $request->ID, '_prihod_request_locale', true ) ?: Profile::get()['language'];
        $switched = is_string( $locale ) && in_array( $locale, array( 'ru_RU', 'de_DE', 'pl_PL', 'uk' ), true ) && switch_to_locale( $locale );
        try {
            $headers = array( 'Content-Type: text/html; charset=UTF-8' );
            $profile = Profile::get(); $email = $profile['email'];
            if ( is_email( $email ) ) {
                $brand = trim( preg_replace( '/[<>\r\n]/', '', sanitize_text_field( $profile['name'] ?: get_bloginfo( 'name' ) ) ) );
                $headers[] = 'From: ' . $brand . ' <' . $email . '>'; $headers[] = 'Reply-To: ' . $email;
            }
            $media = Request_Media::validated( (array) ( get_post_meta( $reply->ID, '_prihod_reply_media', true ) ?: array() ) );
            return array( 'subject' => __( 'Ответ на обращение:', 'prihod' ) . ' ' . sanitize_text_field( $request->post_title ), 'message' => self::markup( $request, $reply, $sender ), 'headers' => $headers, 'inline_media' => $media, 'cid' => Request_Media::cid( $reply->ID ), 'text' => $reply->post_content . ( $media ? "\n[" . __( 'Изображение из Telegram', 'prihod' ) . ']' : '' ) . "\n\n" . __( 'Ваше обращение', 'prihod' ) . ":\n" . $request->post_content . "\n\n" . $profile['name'] . "\n" . home_url( '/' ) );
        } finally { if ( $switched ) { restore_previous_locale(); } }
    }
    /** Set the PHP-mail envelope for this reply only; leave other plugins' mail alone. */
    public static function send( string $recipient, array $mail, array &$transport ): bool {
        $email = Profile::get()['email']; $mailer_used = null; $previous_sender = ''; $previous_text = ''; $attachments = null;
        $envelope = static function ( $mailer ) use ( $email, $mail, &$mailer_used, &$previous_sender, &$previous_text, &$attachments, &$transport ): void {
            $mailer_used = $mailer; $previous_sender = $mailer->Sender; $previous_text = $mailer->AltBody ?? '';
            if ( is_email( $email ) && 'mail' === $mailer->Mailer ) { $mailer->Sender = $email; }
            $mailer->AltBody = $mail['text'] ?? '';
            if ( ! empty( $mail['inline_media'] ) ) {
                $media = Request_Media::validated( $mail['inline_media'] ); $attachments = $mailer->getAttachments();
                $mailer->addStringEmbeddedImage( base64_decode( $media['data'], true ), $mail['cid'], 'reply-image.' . ( 'image/png' === $media['mime'] ? 'png' : 'jpg' ), 'base64', $media['mime'] );
            }
            $transport = array( 'method' => sanitize_key( $mailer->Mailer ), 'from' => sanitize_email( $mailer->From ), 'envelope' => sanitize_email( $mailer->Sender ) );
        };
        $failure = static function ( $error ) use ( &$transport ): void { $transport['error_code'] = $error instanceof \WP_Error ? sanitize_key( $error->get_error_code() ) : 'mail_error'; };
        add_action( 'phpmailer_init', $envelope, PHP_INT_MAX ); add_action( 'wp_mail_failed', $failure );
        try {
            $accepted = wp_mail( $recipient, $mail['subject'], $mail['message'], $mail['headers'] );
            $transport['attempted_at_gmt'] = gmdate( DATE_ATOM ); $transport['accepted'] = (bool) $accepted;
            if ( ! $accepted && empty( $transport['error_code'] ) ) { $transport['error_code'] = 'mail_not_accepted'; }
            return $accepted;
        } catch ( \Throwable $error ) { $transport['error_code'] = 'mail_exception'; $transport['accepted'] = false; return false; }
        finally {
            remove_action( 'phpmailer_init', $envelope, PHP_INT_MAX ); remove_action( 'wp_mail_failed', $failure );
            if ( $mailer_used ) {
                $mailer_used->Sender = $previous_sender; $mailer_used->AltBody = $previous_text;
                if ( null !== $attachments ) {
                    $mailer_used->clearAttachments();
                    foreach ( $attachments as $a ) {
                        if ( $a[5] && 'inline' === $a[6] ) { $mailer_used->addStringEmbeddedImage( $a[0], $a[7], $a[2], $a[3], $a[4], $a[6] ); }
                        elseif ( $a[5] ) { $mailer_used->addStringAttachment( $a[0], $a[2], $a[3], $a[4], $a[6] ); }
                        elseif ( 'inline' === $a[6] ) { $mailer_used->addEmbeddedImage( $a[0], $a[7], $a[2], $a[3], $a[4], $a[6] ); }
                        else { $mailer_used->addAttachment( $a[0], $a[2], $a[3], $a[4], $a[6] ); }
                    }
                }
            }
        }
    }
    private static function markup( \WP_Post $request, \WP_Post $reply, array $sender ): string {
        $profile = Profile::get();
        $name = $profile['name'] ?: get_bloginfo( 'name' );
        $staff = get_userdata( $reply->post_author );
        $html = '<!doctype html><html lang="' . esc_attr( substr( determine_locale(), 0, 2 ) ) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f4f1eb;color:#243343;font-family:Arial,sans-serif"><table role="presentation" style="width:100%;border-collapse:collapse"><tr><td style="padding:24px 12px"><table role="presentation" style="width:100%;max-width:640px;margin:auto;border-collapse:collapse;background:#fff"><tr><td style="padding:28px;background:#243343;color:#fff;border-top:4px solid #b28b4b"><p style="margin:0;font-size:18px">' . esc_html( $name ) . '</p><h1 style="font-size:25px;line-height:1.3;margin:16px 0 0">' . esc_html__( 'Ответ на ваше обращение', 'prihod' ) . '</h1></td></tr><tr><td style="padding:28px;font-size:16px;line-height:1.65">';
        $html .= '<p>' . esc_html( $sender['name'] ?? '' ) . ',</p><h2 style="font-size:19px">' . esc_html( $request->post_title ) . '</h2><div>' . nl2br( esc_html( $reply->post_content ) ) . '</div>';
        if ( get_post_meta( $reply->ID, '_prihod_reply_media', true ) ) { $html .= '<p><img src="cid:' . esc_attr( Request_Media::cid( $reply->ID ) ) . '" alt="' . esc_attr__( 'Изображение из Telegram', 'prihod' ) . '" style="display:block;width:auto;max-width:100%;height:auto;max-height:640px"></p>'; }
        $signature = $staff && $staff->display_name !== $staff->user_login ? esc_html( $staff->display_name ) . '<br>' : '';
        $html .= '<p style="margin-top:24px">' . $signature . esc_html( $name ) . '</p>';
        $html .= '<hr style="border:0;border-top:1px solid #ded8cd;margin:28px 0"><h2 style="font-size:16px">' . esc_html__( 'Ваше обращение', 'prihod' ) . '</h2><div style="padding:16px;background:#f8f6f2;border-left:3px solid #b28b4b">' . nl2br( esc_html( $request->post_content ) ) . '</div><p><a style="color:#76582e" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Перейти на сайт', 'prihod' ) . '</a></p></td></tr><tr><td style="padding:20px 28px;background:#f8f6f2;font-size:13px;line-height:1.6">';
        foreach ( array( 'address', 'phone' ) as $key ) { if ( ! empty( $profile[$key] ) ) { $html .= '<p style="margin:4px 0">' . esc_html( $profile[$key] ) . '</p>'; } }
        if ( ! empty( $profile['email'] ) && is_email( $profile['email'] ) ) { $html .= '<p style="margin:4px 0"><a href="' . esc_url( 'mailto:' . $profile['email'] ) . '">' . esc_html( $profile['email'] ) . '</a></p>'; }
        return $html . '</td></tr></table></td></tr></table></body></html>';
    }
}
