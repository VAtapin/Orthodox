<?php
/** Synthetic records shared by fresh-install and old-ZIP upgrade tests only. */
if ( ! get_option( 'gkpc_merge_fixture' ) ) {
    $place = wp_insert_post( array( 'post_type' => 'gkpc_location', 'post_status' => 'publish', 'post_title' => 'Existing parish chapel', 'post_content' => 'Existing chapel description' ) );
    $old_place = wp_insert_post( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'post_title' => 'Existing schedule church', 'post_content' => 'Existing church address' ) );
    update_post_meta( $place, '_gkpc_details', array( 'address' => 'Existing address', 'email' => 'church@example.org' ) );
    update_post_meta( $old_place, '_gkpsch_details', array( 'timezone' => 'Europe/Berlin' ) );
    $clergy = wp_insert_post( array( 'post_type' => 'gkpc_clergy', 'post_status' => 'publish', 'post_title' => 'Existing clergy', 'post_content' => 'Existing biography' ) );
    update_post_meta( $clergy, '_gkpc_details', array( 'rank' => 'Existing rank' ) );
    $service = wp_insert_post( array( 'post_type' => 'gkpsch_service', 'post_status' => 'publish', 'post_title' => 'Existing service' ) );
    $rule = GeorgKlosterPrihodSchedule\Schedule::validate( array( 'date' => wp_date( 'Y-m-d', time() + 86400 ), 'time' => '10:00', 'location_id' => $old_place, 'vestment_color' => 'gold' ) );
    update_post_meta( $service, '_gkpsch_service', $rule );
    update_option( 'gkpc_profile', array( 'name' => 'Existing owner parish', 'type' => 'parish', 'email' => 'owner@example.org' ) );
    update_option( 'gkpsch_calendar_provider', array( 'origin' => 'https://obsolete-calendar.example', 'profile' => 'parish' ) );
    update_option( 'gkpc_merge_fixture', compact( 'place', 'old_place', 'clergy', 'service', 'rule' ) );
}
