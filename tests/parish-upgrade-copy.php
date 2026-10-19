<?php
/** Simulate replacing Core 0.1.5 with the new ZIP in a disposable WordPress only. */
require_once '/wordpress/wp-load.php';
if ( '0.1.5' !== GKPC_VERSION || '0.1.10' !== GKPSCH_VERSION || ! is_dir( '/next-parish/schedule' ) ) { throw new RuntimeException( 'Mount the two old ZIPs and the new Parish package for this fixture.' ); }
require __DIR__ . '/parish-merge-seed.php';
$source = '/next-parish';
$destination = '/wordpress/wp-content/plugins/georg-kloster-prihod-core';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST ) as $file ) {
    $relative = substr( $file->getPathname(), strlen( $source ) );
    $target = $destination . $relative;
    if ( $file->isDir() ) { wp_mkdir_p( $target ); }
    elseif ( ! copy( $file->getPathname(), $target ) ) { throw new RuntimeException( 'Could not copy a fixture package file.' ); }
}
