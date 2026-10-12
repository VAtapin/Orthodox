<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Wizard_Result {
    public static function resources(): array {
        $items = array();
        foreach ( get_option( 'prihod_resources', array() ) as $key => $resource ) {
            $post = get_post( $resource['id'] ?? 0 );
            if ( ! $post || ! in_array( $post->post_type, array( 'page', 'prihod_location', 'prihod_clergy', 'prihod_service' ), true ) || ! current_user_can( 'read_post', $post->ID ) ) { continue; }
            $items[$key] = array( 'post' => $post, 'publishable' => 'draft' === $post->post_status && ! empty( $resource['owned'] ) && current_user_can( 'edit_post', $post->ID ) && hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), hash( 'sha256', $post->post_title . '|' . $post->post_content ) ) );
        }
        return $items;
    }
    public static function render(): bool {
        $items = self::resources(); $drafts = array_filter( $items, static fn( $item ) => $item['publishable'] );
        $pending = count( array_filter( $items, static fn( $item ) => ! $item['publishable'] && 'publish' !== $item['post']->post_status ) );
        $published = count( array_filter( $items, static fn( $item ) => 'publish' === $item['post']->post_status ) );
        $title = $drafts ? __( 'Черновики подготовлены', 'prihod' ) : ( $pending ? __( 'Материалы требуют проверки', 'prihod' ) : __( 'Настройка завершена!', 'prihod' ) );
        echo '<div class="prihod-wizard-success"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $drafts ? __( 'Просмотрите страницы и отдельно подтвердите публикацию.', 'prihod' ) : __( 'Вы можете продолжить работу с материалами в редакторе.', 'prihod' ) ) . '</p><p>' . esc_html( sprintf( __( 'Опубликовано: %1$d. Готовых к публикации черновиков: %2$d.', 'prihod' ), $published, count( $drafts ) ) ) . '</p><a class="button button-primary" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Открыть сайт', 'prihod' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=prihod-settings-hub' ) ) . '">' . esc_html__( 'Настройки', 'prihod' ) . '</a></div>';
        if ( $pending ) { echo '<p>' . esc_html( sprintf( __( 'Требуют проверки в редакторе: %d.', 'prihod' ), $pending ) ) . '</p>'; }
        if ( $drafts ) { echo '<div class="prihod-wizard-drafts">'; self::rows( $drafts ); echo '</div>'; }
        echo '<details class="prihod-wizard-resources"><summary>' . esc_html__( 'Посмотреть, что создано', 'prihod' ) . '</summary>';
        self::rows( array_filter( $items, static fn( $item ) => ! $item['publishable'] ) );
        echo '</details>';
        return (bool) $drafts;
    }
    private static function rows( array $items ): void {
        foreach ( $items as $key => $item ) {
            $post = $item['post'];
            echo '<div class="prihod-wizard-resource"><span>';
            if ( $item['publishable'] ) { echo '<label><input type="checkbox" name="publish_keys[]" value="' . esc_attr( $key ) . '"> ' . esc_html( $post->post_title ) . '</label>'; }
            else { echo esc_html( $post->post_title ); }
            echo '</span><span class="prihod-resource-status is-' . esc_attr( $post->post_status ) . '">' . esc_html( Admin_Navigation::status_label( $post->post_status ) ) . '</span>';
            if ( 'page' === $post->post_type && 'trash' !== $post->post_status ) { echo '<a href="' . esc_url( 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Предварительный просмотр', 'prihod' ) . '</a>'; }
            if ( current_user_can( 'edit_post', $post->ID ) ) { echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Редактор', 'prihod' ) . '</a>'; }
            echo '</div>';
        }
    }
}
