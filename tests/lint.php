<?php
$root = dirname( __DIR__ );
$failed = false;
foreach ( array( 'prihod', 'prihod-theme', 'extensions', 'tests', 'scripts' ) as $directory ) {
    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory ) );
    foreach ( $files as $file ) {
        if ( $file->isFile() && 'php' === $file->getExtension() ) {
            passthru( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ), $status );
            $failed = $failed || 0 !== $status;
        }
    }
}
exit( $failed ? 1 : 0 );
