<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

WP_CLI::add_command( 'deve site', array( 'Deve_CLI\SiteCommand', 'site' ) );
WP_CLI::add_command( 'deve site-activate', array( 'Deve_CLI\SiteCommand', 'site_activate' ) );
WP_CLI::add_command( 'deve site-deactivate', array( 'Deve_CLI\SiteCommand', 'site_deactivate' ) );
WP_CLI::add_command( 'deve restart', array( 'Deve_CLI\RestartCommand', 'restart' ) );