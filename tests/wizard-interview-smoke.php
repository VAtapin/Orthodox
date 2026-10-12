<?php
$wizard_before = get_user_meta( 1, 'prihod_wizard_' . get_current_blog_id(), true );
$wizard_resources_before = get_option( 'prihod_resources' );
$wizard_pages_before = wp_count_posts( 'page' );
$wizard_site_before = array( get_stylesheet(), get_option( 'page_on_front' ), get_option( 'show_on_front' ), get_nav_menu_locations(), Prihod\Profile::get() );
$interview = array( 'step' => 0, 'answers' => $answers, 'revision' => 0 );
prihod_assert( 6 === count( Prihod\Wizard::steps() ) && 0 === Prihod\Wizard_Interview::resume( $interview )['stage'], 'Wizard has six real stages, starting with welcome.' );
foreach ( array( 4 => 1, 6 => 2, 10 => 2, 11 => 3, 12 => 4, 13 => 5 ) as $legacy_step => $stage ) {
    $legacy = $interview; $legacy['step'] = $legacy_step; $resumed = Prihod\Wizard_Interview::resume( $legacy );
    prihod_assert( $stage === $resumed['stage'] && $legacy['answers'] === $resumed['answers'], 'Legacy interview resumes in the matching real stage without losing answers: ' . $legacy_step );
}
$interview = Prihod\Wizard_Interview::advance( $interview, 'start', array() );
$welcome_back = Prihod\Wizard_Interview::advance( $interview, 'back', array( 'profile' => array( 'name' => 'Unfinished entered name', 'timezone' => 'Unfinished/Zone' ) ) );
prihod_assert( 0 === $welcome_back['stage'] && 'Unfinished entered name' === $welcome_back['profile_input']['name'] && $interview['answers'] === $welcome_back['answers'], 'Back from profile reaches welcome and retains unfinished fields without applying invalid data.' );
$welcome_return = Prihod\Wizard_Interview::advance( $welcome_back, 'start', array() );
ob_start(); Prihod\Wizard_View::render( $welcome_return ); $profile_screen = ob_get_clean();
prihod_assert( str_contains( $profile_screen, 'value="Unfinished entered name"' ) && str_contains( $profile_screen, 'value="back"' ) && str_contains( $profile_screen, 'parish-symbols.svg#monastery' ) && str_contains( $profile_screen, 'parish-symbols.svg#chapel' ), 'Returning from welcome restores profile controls and all church silhouettes.' );
$interview = Prihod\Wizard_Interview::advance( $interview, 'next', array( 'profile' => array( 'short_name' => 'Saved interview name' ) ) );
prihod_assert( 2 === $interview['stage'] && 'Saved interview name' === $interview['answers']['profile']['short_name'] && Prihod\Profile::get() === $wizard_site_before[4], 'Profile stage saves interview answers without changing site profile.' );
ob_start(); Prihod\Wizard_View::render( $interview ); $feature_screen = ob_get_clean();
prihod_assert( 6 === substr_count( $feature_screen, 'role="switch"' ) && str_contains( $feature_screen, 'features[integrations]' ) && str_contains( $feature_screen, 'features[clergy]' ), 'Features stage presents the full module list with real controls.' );
prihod_assert( str_contains( $feature_screen, '<details class="prihod-feature-details"' ) && ! str_contains( $feature_screen, 'themes.php?action=activate' ), 'Optional details are inside the same stage and no theme activation action is offered.' );
$enabled_input = array( 'features_present' => '1', 'features' => array( 'schedule' => '1', 'locations' => '1' ), 'locations' => array( array( 'name' => 'Interview new location', 'address' => 'Fixture address' ) ), 'service' => array( 'title' => 'Interview service', 'date' => '2026-12-01', 'time' => '09:00', 'location_id' => '0' ) );
$with_service = Prihod\Wizard_Interview::advance( $interview, 'next', $enabled_input );
prihod_assert( 3 === $with_service['stage'] && 'Interview service' === $with_service['answers']['service']['title'], 'First service can refer to a new interview location before it exists as a WordPress record.' );
$bad_directory = $enabled_input; $bad_directory['locations'][0]['name'] = array( 'invalid name' );
try { Prihod\Wizard_Interview::advance( $interview, 'next', $bad_directory ); prihod_assert( false, 'Directory fields must reject nested arrays.' ); }
catch ( InvalidArgumentException $error ) { prihod_assert( true, 'Selected directory validates text fields before saving interview answers.' ); }
$legacy_bad_directory = $interview; $legacy_bad_directory['answers']['locations'] = $bad_directory['locations'];
ob_start(); Prihod\Wizard_View::render( $legacy_bad_directory ); $legacy_directory_html = ob_get_clean();
prihod_assert( str_contains( $legacy_directory_html, 'locations[0][name]' ), 'Previously malformed directory field can render for correction without losing the interview.' );
$invalid_service_input = $enabled_input; $invalid_service_input['service']['date'] = 'invalid';
try { Prihod\Wizard_Interview::advance( $interview, 'next', $invalid_service_input ); prihod_assert( false, 'Selected service must validate its date.' ); }
catch ( InvalidArgumentException $error ) { prihod_assert( true, 'Selected service rejects invalid date before producing a plan.' ); }
$off = Prihod\Wizard_Interview::advance( $interview, 'next', array( 'features_present' => '1', 'features' => array( 'publications' => '1' ), 'service' => array( 'title' => array( 'invalid disabled field' ) ) ) );
prihod_assert( 3 === $off['stage'] && ! $off['answers']['modules']['schedule'] && $interview['answers']['clergy'] === $off['answers']['clergy'], 'Disabled function ignores its fields while preserving saved directory answers.' );
$plan_answers = Prihod\Wizard_Interview::plan_answers( $off['answers'] );
prihod_assert( empty( $plan_answers['clergy'] ) && empty( $plan_answers['locations'] ) && ! isset( $plan_answers['pages']['schedule'] ), 'Disabled preparation is excluded from the plan without deleting saved answers.' );
$back = Prihod\Wizard_Interview::advance( $off, 'back', array() );
prihod_assert( 2 === $back['stage'] && $off['answers'] === $back['answers'], 'Back returns to features and retains choices.' );
$skipped = Prihod\Wizard_Interview::advance( $back, 'skip', array( 'features_present' => '1', 'features' => array( 'schedule' => '1' ) ) );
prihod_assert( 3 === $skipped['stage'] && $back['answers'] === $skipped['answers'], 'Configure later advances without replacing saved answers with unfinished controls.' );
$confirmed = Prihod\Wizard_Interview::advance( $skipped, 'next', array( 'pages_present' => '1', 'pages' => array( 'contacts' => '1' ), 'reuse' => array( 'contacts' => $existing ) ) );
prihod_assert( 4 === $confirmed['stage'] && 'reuse' === $confirmed['plan']['pages']['contacts']['action'], 'Pages stage produces a real confirmation plan that preserves existing content.' );
foreach ( array( array( 'apply', array() ), array( 'publish', array( 'confirm' => '1', 'publish_keys' => array( 'contacts' ) ) ) ) as $action ) {
    try { Prihod\Wizard_Interview::advance( $confirmed, $action[0], $action[1] ); prihod_assert( false, 'Unconfirmed or premature action must fail.' ); }
    catch ( RuntimeException $error ) { prihod_assert( true, 'Unconfirmed apply and premature publication are rejected.' ); }
}
$restart = Prihod\Wizard_Interview::advance( $confirmed, 'restart', array() );
prihod_assert( 1 === $restart['stage'] && $confirmed['answers'] === $restart['answers'] && ! isset( $restart['plan'] ), 'Safe restart retains answers and removes the obsolete plan.' );
update_user_meta( 1, 'prihod_wizard_' . get_current_blog_id(), $confirmed );
$restored = Prihod\Wizard::state();
prihod_assert( $confirmed == $restored, 'Unfinished real-stage interview restores exactly from user metadata.' );
$completed_invitation = $confirmed; $completed_invitation['stage'] = 5;
update_user_meta( 1, 'prihod_wizard_' . get_current_blog_id(), $completed_invitation );
ob_start(); Prihod\Wizard::invitation(); $invitation = ob_get_clean();
prihod_assert( '' === $invitation, 'Completed wizard no longer invites the owner to begin setup again.' );
if ( $wizard_before ) { update_user_meta( 1, 'prihod_wizard_' . get_current_blog_id(), $wizard_before ); } else { delete_user_meta( 1, 'prihod_wizard_' . get_current_blog_id() ); }
prihod_assert( $wizard_resources_before === get_option( 'prihod_resources' ) && $wizard_pages_before == wp_count_posts( 'page' ) && $wizard_site_before === array( get_stylesheet(), get_option( 'page_on_front' ), get_option( 'show_on_front' ), get_nav_menu_locations(), Prihod\Profile::get() ), 'Interview, previews and rejected actions do not change pages, resources, theme, front page or menus.' );
$theme_options_before = get_option( 'prihod_theme_options', array() );
$theme_answers = array( 'profile' => Prihod\Profile::get(), 'modules' => get_option( 'prihod_modules' ), 'pages' => array(), 'theme_choice' => 'prihod-theme', 'scheme_choice' => 'blue' );
$theme_plan = Prihod\Site_Plan::plan( $theme_answers );
prihod_assert( $wizard_site_before[0] === get_stylesheet() && $theme_options_before === get_option( 'prihod_theme_options', array() ), 'Theme and scheme preview do not activate or save the chosen design.' );
$invalid_theme_answers = $theme_answers; $invalid_theme_answers['scheme_choice'] = 'unsupported';
try { Prihod\Site_Plan::plan( $invalid_theme_answers ); prihod_assert( false, 'Unsupported design must be rejected.' ); }
catch ( InvalidArgumentException $error ) { prihod_assert( true, 'Only the five supported schemes can enter the plan.' ); }
wp_set_current_user( $staff_id );
try { Prihod\Site_Plan::plan( $theme_answers ); prihod_assert( false, 'Staff cannot authorize a WordPress theme switch.' ); }
catch ( RuntimeException $error ) { prihod_assert( true, 'Theme selection retains native WordPress theme permissions.' ); }
wp_set_current_user( 1 );
Prihod\Site_Plan::apply( $theme_plan );
prihod_assert( 'prihod-theme' === get_stylesheet() && 'blue' === get_option( 'prihod_theme_options' )['scheme'], 'Only confirmed apply activates the already-installed theme and chosen scheme.' );
switch_theme( $wizard_site_before[0] ); update_option( 'prihod_theme_options', $theme_options_before );
