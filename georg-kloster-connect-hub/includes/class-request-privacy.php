<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

final class Request_Privacy {
    public static function exporters( array $items ): array {
        $items['georg-kloster-connect-hub'] = array( 'exporter_friendly_name' => __( 'Parish messages', 'georg-kloster-connect-hub' ), 'callback' => array( self::class, 'export' ) ); return $items;
    }
    public static function erasers( array $items ): array {
        $items['georg-kloster-connect-hub'] = array( 'eraser_friendly_name' => __( 'Parish messages', 'georg-kloster-connect-hub' ), 'callback' => array( self::class, 'erase' ) ); return $items;
    }
    private static function find( string $email, int $page = 1 ): array {
        return get_posts( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'numberposts' => 50, 'offset' => ( max( 1, $page ) - 1 ) * 50, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => '_gkps_email', 'meta_value' => $email ) );
    }
    public static function export( string $email, int $page = 1 ): array {
        $posts = self::find( $email, $page ); $data = array();
        foreach ( $posts as $post ) {
            $sender = get_post_meta( $post->ID, '_gkps_sender', true );
            $fields = array( array( 'name' => __( 'Sender name', 'georg-kloster-connect-hub' ), 'value' => $sender['name'] ?? '' ), array( 'name' => __( 'Email', 'georg-kloster-connect-hub' ), 'value' => $sender['email'] ?? '' ), array( 'name' => __( 'Message subject', 'georg-kloster-connect-hub' ), 'value' => $post->post_title ), array( 'name' => __( 'Incoming message', 'georg-kloster-connect-hub' ), 'value' => $post->post_content ) );
            foreach ( Requests::messages( $post->ID ) as $message ) {
                $fields[] = array( 'name' => __( 'Conversation / note', 'georg-kloster-connect-hub' ) . ' ' . $message->post_date, 'value' => $message->post_content );
                $media = get_post_meta( $message->ID, '_gkps_reply_media', true );
                if ( is_array( $media ) && isset( $media['mime'], $media['data'] ) ) { $fields[] = array( 'name' => __( 'Image from Telegram', 'georg-kloster-connect-hub' ) . ' ' . $message->post_date, 'value' => 'data:' . $media['mime'] . ';base64,' . $media['data'] ); }
            }
            $data[] = array( 'group_id' => 'gkps-requests', 'group_label' => __( 'Incoming messages', 'georg-kloster-connect-hub' ), 'item_id' => 'gkps-' . $post->ID, 'data' => $fields );
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
