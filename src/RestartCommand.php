<?php

namespace Deve_CLI;

use WP_CLI;
use WP_CLI\Process;
use WP_CLI\Utils;
use Deve_CLI\Docker\DockerClient;

class RestartCommand {
	/**
	 * Restart the Nginx and PHP Docker containers.
	 *
	 * Default behavior is to restart the following containers:
	 * - php
	 * - web
   *
	 * @when before_wp_load
	 */
  public function restart( $args, $assoc_args ) {
    WP_CLI::log( 'Trying to restart containers.' );
    
    $client = DockerClient::getInstance();
    $containers =  $client->find_containers( array(
      'status' => array( 'running' ),
      'ancestor' => array( 'nginx:alpine', 'php:fpm-alpine' )
    ) );
    
    foreach ( $containers->getDecodedBody() as $container ) {
      $name = $container->Names[0];
      if ( $client->restart_container( $container->Id )->getStatusCode() === 204 ) {
        WP_CLI::success( "Container {$name} restarted successfully!" );
      } else {
        WP_CLI::error( "Error restarting {$name}!" );
      }
    }
  }
}