<?php
require __DIR__ . '/bootstrap.php';

use Prihod\Capabilities;
use Prihod\Profile;
use Prihod\Directory;
use Prihod\Integration;
use Prihod\Integration_Registry;
use Prihod\Admin;

$count = 0;
function check( bool $condition, string $message ): void {
    global $count;
    ++$count;
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

$GLOBALS['options'][ Profile::OPTION ] = array( 'name' => 'Existing', 'email' => 'valid@example.org', 'timezone' => 'Europe/Berlin' );
$profile = Profile::sanitize( array(
    'name' => '<b>Parish</b>', 'email' => 'broken', 'timezone' => '+02:00',
    'type' => 'unknown', 'language' => array( 'ru' ), 'website' => 'javascript:alert(1)',
    'injected' => 'ignored',
) );
check( 'Parish' === $profile['name'], 'Profile removes markup.' );
check( 'valid@example.org' === $profile['email'], 'Invalid email preserves saved value.' );
check( 'Europe/Berlin' === $profile['timezone'], 'Fixed offsets cannot replace DST-aware timezone.' );
check( 'parish' === $profile['type'] && 'ru_RU' === $profile['language'], 'Invalid enums and nested input are rejected.' );
check( '' === $profile['website'] && ! isset( $profile['injected'] ), 'Unsafe URLs and unknown input cannot persist.' );
check( count( $GLOBALS['errors'] ) === 5, 'Invalid fields report errors.' );
check( 'Existing' === Profile::sanitize( 'invalid' )['name'], 'Malformed payload preserves the profile.' );
$valid = Profile::sanitize( array( 'timezone' => 'America/New_York', 'language' => 'de_DE', 'type' => 'monastery', 'email' => '' ) );
check( 'America/New_York' === $valid['timezone'] && 'de_DE' === $valid['language'] && '' === $valid['email'], 'Valid updates and explicit clearing succeed.' );
$GLOBALS['timezone'] = '+02:00';
check( 'UTC' === Profile::defaults()['timezone'], 'Site offset does not silently become a timezone identifier.' );

$GLOBALS['roles']['administrator'] = new class {
    public array $caps = array();
    public function add_cap( $cap ) { $this->caps[ $cap ] = true; }
};
$GLOBALS['roles']['editor'] = clone $GLOBALS['roles']['administrator'];
Capabilities::install();
check( count( $GLOBALS['roles']['administrator']->caps ) === count( Capabilities::all() ), 'Administrator receives initial grants.' );
check( array() === $GLOBALS['roles']['editor']->caps, 'Editor does not receive parish or request access.' );
$GLOBALS['roles']['administrator']->caps = array();
Capabilities::maybe_upgrade();
check( array() === $GLOBALS['roles']['administrator']->caps, 'Ordinary boot does not restore revoked capabilities.' );
Directory::register();
foreach ( $GLOBALS['types'] as $args ) {
    check( ! $args['public'] && ! $args['show_in_rest'] && ! $args['publicly_queryable'], 'Directory has no accidental public or REST endpoint.' );
    check( array( 'manage_prihod' ) === array_values( array_unique( $args['capabilities'] ) ), 'Every directory operation requires its capability.' );
}

$GLOBALS['posts'][1] = (object) array( 'post_type' => 'prihod_location', 'post_status' => 'publish' );
$GLOBALS['posts'][2] = (object) array( 'post_type' => 'post', 'post_status' => 'publish' );
$GLOBALS['posts'][3] = (object) array( 'post_type' => 'prihod_location', 'post_status' => 'trash' );
$GLOBALS['posts'][4] = (object) array( 'post_type' => 'prihod_location', 'post_status' => 'draft' );
$details = Directory::sanitize( 'prihod_clergy', array( 'rank' => '<b>Priest</b>', 'location_ids' => array( '1', '4', '1' ) ), array() );
check( 'Priest' === $details['rank'] && array( 1, 4 ) === $details['location_ids'], 'Clergy can belong to multiple real locations without duplicates.' );
foreach ( array( array( '2' ), array( '3' ), array( '-1' ), array( array() ), 'invalid' ) as $ids ) {
    $result = Directory::sanitize( 'prihod_clergy', array( 'location_ids' => $ids ), $details );
    check( $details['location_ids'] === $result['location_ids'], 'Invalid relation never overwrites existing locations.' );
}
check( array() === Directory::sanitize( 'prihod_clergy', array(), $details )['location_ids'], 'Empty multi-select clears relationships.' );

$GLOBALS['meta'][1]['_prihod_details'] = array( 'address' => 'Original' );
$_POST = array( 'prihod_directory_nonce' => 'valid-prihod_directory_1', 'prihod_details' => array( 'address' => 'Updated' ) );
Directory::save( 1 );
check( 'Original' === Directory::details( 1 )['address'], 'Unauthorized save is rejected even with nonce.' );
$GLOBALS['caps'] = array( 'manage_prihod' => true, 'edit_post' => true );
$_POST['prihod_directory_nonce'] = 'invalid';
Directory::save( 1 );
check( 'Original' === Directory::details( 1 )['address'], 'Invalid nonce prevents writes.' );
$_POST['prihod_directory_nonce'] = 'valid-prihod_directory_1';
$GLOBALS['autosave'] = true;
Directory::save( 1 );
check( 'Original' === Directory::details( 1 )['address'], 'Autosaves cannot overwrite directory metadata.' );
$GLOBALS['autosave'] = false;
Directory::save( 1 );
check( 'Updated' === Directory::details( 1 )['address'], 'Authorized save with valid nonce succeeds.' );

$registry = new Integration_Registry();
$integration = new class implements Integration {
    public function id(): string { return 'calendar'; }
    public function describe(): array { return array( 'name' => 'Calendar' ); }
    public function health(): array { return array( 'status' => 'ready', 'message' => 'Local status' ); }
};
$registry->register( $integration );
check( $integration === $registry->get( 'calendar' ) && null === $registry->get( 'unknown' ), 'Registered adapters can be discovered.' );
try {
    $registry->register( $integration );
    check( false, 'Duplicate integration should throw.' );
} catch ( LogicException $error ) {
    check( true, 'Duplicate integration rejected.' );
}
$broken = new class implements Integration {
    public function id(): string { return 'broken'; }
    public function describe(): array { throw new RuntimeException( 'secret-api-key' ); }
    public function health(): array { return array(); }
};
check( 'ready' === $registry->report( $integration )['status'], 'Healthy adapter reports its state.' );
$report = $registry->report( $broken );
check( 'unavailable' === $report['status'] && ! str_contains( $report['message'], 'secret-api-key' ), 'Adapter failure is contained without leaking exception text.' );
$invalid = new class implements Integration {
    public function id(): string { return '../invalid'; }
    public function describe(): array { return array(); }
    public function health(): array { return array(); }
};
try {
    $registry->register( $invalid );
    check( false, 'Invalid ID should throw.' );
} catch ( InvalidArgumentException $error ) {
    check( true, 'Invalid ID rejected.' );
}

$admin = new Admin( $registry );
$admin->hooks();
check( 'manage_prihod' === $GLOBALS['hooks']['option_page_capability_prihod_profile'][0](), 'Profile Settings API uses its own capability.' );
$GLOBALS['caps'] = array();
foreach ( array( 'profile', 'configuration', 'integrations', 'overview' ) as $page ) {
    try {
        $admin->$page();
        check( false, 'Direct admin page access should be denied.' );
    } catch ( RuntimeException $error ) {
        check( 403 === $error->getCode(), 'Direct admin page access requires authorization.' );
    }
}
require __DIR__ . '/schedule-unit.php';
$request_input = array( 'name' => 'Visitor', 'email' => 'visitor@example.org', 'subject' => 'Question', 'body' => '<b>Text</b>' );
check( 'Text' === Prihod\Requests::validate( $request_input )['body'], 'Request payload strips markup.' );
foreach ( array( array( 'email' => 'invalid' ), array( 'name' => array() ), array( 'body' => str_repeat( 'a', 10001 ) ), array( 'subject' => '<b></b>' ) ) as $change ) {
    try { Prihod\Requests::validate( array_merge( $request_input, $change ) ); check( false, 'Invalid request should throw.' ); }
    catch ( InvalidArgumentException $error ) { check( true, 'Invalid request rejected.' ); }
}


check( 8 === count( Prihod\Calendar_Colors::labels() ), 'Seven vestment colors plus neutral choice.' );
check( 'blue' === Prihod\Calendar_Colors::recommendation( array( 'Покров Пресвятой Богородицы' ) )['color'], 'Pokrov recommends blue.' );
check( 'green' === Prihod\Calendar_Colors::recommendation( array( 'Pfingsten' ) )['color'], 'Pentecost recommendation supports German.' );
check( null === Prihod\Calendar_Colors::recommendation( array( 'Unknown feast' ) ) && null === Prihod\Calendar_Colors::recommendation( array( 'Покров', 'Троица' ) ), 'Unknown and conflicting feasts never invent a color.' );
try { Prihod\Calendar_Colors::validate( 'javascript:red' ); check( false, 'Invalid color rejected.' ); } catch ( InvalidArgumentException $e ) { check( true, 'Invalid color rejected.' ); }

echo "Passed {$count} assertions (isolated unit tests).\n";
