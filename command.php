<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

WP_CLI::add_command( 'deve site create', array( 'Deve_CLI\SiteCommand', 'site_create' ) );
WP_CLI::add_command( 'deve site delete', array( 'Deve_CLI\SiteCommand', 'site_delete' ) );
WP_CLI::add_command( 'deve site list', array( 'Deve_CLI\SiteCommand', 'site_list' ) );
WP_CLI::add_command( 'deve site activate', array( 'Deve_CLI\SiteCommand', 'site_activate' ) );
WP_CLI::add_command( 'deve site deactivate', array( 'Deve_CLI\SiteCommand', 'site_deactivate' ) );
WP_CLI::add_command( 'deve site ssl', array( 'Deve_CLI\SiteCommand', 'site_ssl' ) );

WP_CLI::add_command( 'deve server restart', array( 'Deve_CLI\RestartCommand', 'restart' ) );