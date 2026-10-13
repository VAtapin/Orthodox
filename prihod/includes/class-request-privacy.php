<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Request_Privacy {
    public static function exporters( array $items ): array {
        $items['prihod'] = array( 'exporter_friendly_name' => __( 'Обращения «Прихода»', 'prihod' ), 'callback' => array( self::class, 'export' ) ); return $items;
    }
    public static function erasers( array $items ): array {
        $items['prihod'] = array( 'eraser_friendly_name' => __( 'Обращения «Прихода»', 'prihod' ), 'callback' => array( self::class, 'erase' ) ); return $items;
    }
    private static function find( string $email, int $page = 1 ): array {
        return get_posts( array( 'post_type' => 'prihod_request', 'post_status' => 'private', 'numberposts' => 50, 'offset' => ( max( 1, $page ) - 1 ) * 50, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => '_prihod_email', 'meta_value' => $email ) );
    }
    public static function export( string $email, int $page = 1 ): array {
        $posts = self::find( $email, $page ); $data = array();
        foreach ( $posts as $post ) {
            $sender = get_post_meta( $post->ID, '_prihod_sender', true );
            $fields = array( array( 'name' => __( 'Имя', 'prihod' ), 'value' => $sender['name'] ?? '' ), array( 'name' => 'Email', 'value' => $sender['email'] ?? '' ), array( 'name' => __( 'Тема', 'prihod' ), 'value' => $post->post_title ), array( 'name' => __( 'Обращение', 'prihod' ), 'value' => $post->post_content ) );
            foreach ( Requests::messages( $post->ID ) as $message ) {
                $fields[] = array( 'name' => __( 'Переписка / заметка', 'prihod' ) . ' ' . $message->post_date, 'value' => $message->post_content );
                $media = get_post_meta( $message->ID, '_prihod_reply_media', true );
                if ( is_array( $media ) && isset( $media['mime'], $media['data'] ) ) { $fields[] = array( 'name' => __( 'Изображение из Telegram', 'prihod' ) . ' ' . $message->post_date, 'value' => 'data:' . $media['mime'] . ';base64,' . $media['data'] ); }
            }
            $data[] = array( 'group_id' => 'prihod-requests', 'group_label' => __( 'Обращения', 'prihod' ), 'item_id' => 'prihod-' . $post->ID, 'data' => $fields );
        }
        return array( 'data' => $data, 'done' => count( $posts ) < 50 );
    }
    public static function erase( string $email, int $page = 1 ): array {
        // Always first batch: deleting shifts subsequent rows.
        $posts = self::find( $email );
        foreach ( $posts as $post ) { Requests::delete( $post->ID ); }
        return array( 'items_removed' => (bool) $posts, 'items_retained' => false, 'messages' => array(), 'done' => count( $posts ) < 50 );
    }
}
