<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_profile_fields(): array {
    return array( 'name' => __( 'Название', 'georg-kloster-prihod' ), 'short_name' => __( 'Краткое название', 'georg-kloster-prihod' ), 'description' => __( 'Описание', 'georg-kloster-prihod' ), 'address' => __( 'Адрес', 'georg-kloster-prihod' ), 'phone' => __( 'Телефон', 'georg-kloster-prihod' ), 'email' => __( 'Email', 'georg-kloster-prihod' ) );
}

function prihod_theme_profile_sanitize( $input ): array {
    $input = is_array( $input ) ? $input : array(); $out = array();
    foreach ( prihod_theme_profile_fields() as $key => $label ) {
        $value = is_string( $input[$key] ?? null ) ? $input[$key] : '';
        $out[$key] = 'email' === $key ? sanitize_email( $value ) : ( in_array( $key, array( 'description', 'address' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
    }
    $out['type'] = in_array( $input['type'] ?? null, array( 'parish', 'monastery' ), true ) ? $input['type'] : '';
    return $out;
}

function prihod_theme_profile_settings_panel( array $options ): void {
    $profile = prihod_theme_profile_sanitize( $options['profile'] );
    echo '<section id="prihod-option-profile" class="prihod-option-section"><h2>' . esc_html__( 'Данные сайта', 'georg-kloster-prihod' ) . '</h2><p>' . esc_html__( 'Название, описание и контакты можно задать здесь без плагинов. Заполненные поля имеют приоритет. Если название или описание не заданы, используются общие настройки WordPress; подключённый Core может дополнить пустые поля.', 'georg-kloster-prihod' ) . '</p>';
    foreach ( prihod_theme_profile_fields() as $key => $label ) {
        echo '<p><label>' . esc_html( $label ) . '<br>';
        $name = 'georg_kloster_prihod_options[profile][' . $key . ']';
        if ( in_array( $key, array( 'description', 'address' ), true ) ) { echo '<textarea class="large-text" rows="3" name="' . esc_attr( $name ) . '">' . esc_textarea( $profile[$key] ) . '</textarea>'; }
        else { echo '<input class="regular-text" type="' . ( 'email' === $key ? 'email' : ( 'phone' === $key ? 'tel' : 'text' ) ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $profile[$key] ) . '">'; }
        echo '</label></p>';
    }
    echo '<p><label>' . esc_html__( 'Тип общины', 'georg-kloster-prihod' ) . '<br><select name="georg_kloster_prihod_options[profile][type]">';
    foreach ( array( '' => __( 'Автоматически', 'georg-kloster-prihod' ), 'parish' => __( 'Приход', 'georg-kloster-prihod' ), 'monastery' => __( 'Монастырь', 'georg-kloster-prihod' ) ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $profile['type'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
    echo '</select></label></p></section>';
}
