<?php

namespace Deve_CLI\Docker;

use Deve_CLI\Docker\DockerResponse;

class DockerClient {
  /**
   * Docker Socket
   */
  const DOCKER_SOCKET = '/var/run/docker.sock';
  
  /**
   * Docker Client Instance
   */
  private $instance;
  
  private function __construct() {}
  
  public static function getInstance() {
    if ( $instance === null ) {
      $instance = new DockerClient();
    }
    return $instance;
  }
  
  public function restart_container( $container_id ) {
    return $this->request( "/containers/{$container_id}/restart", 'POST' );
  }
  
  public function find_containers( $filters = array() ) {
    return $this->request( '/containers/json?filters=' . json_encode( $filters ) );
  }
  
  private function request( $endpoint, $method = 'GET', $data = null ) {
    $ch = curl_init();
    
    curl_setopt( $ch, CURLOPT_UNIX_SOCKET_PATH, static::DOCKER_SOCKET );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $ch, CURLOPT_URL, "http:/v1.26{$endpoint}" );
    curl_setopt( $ch, CURLOPT_VERBOSE, false );
    
    if ( 'POST' === $method ) {
      curl_setopt( $ch, CURLOPT_POST, 1 );
      curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
    }
    
    $response = new DockerResponse();

    try {
      $response->setBody( curl_exec( $ch ) );
      $response->setStatusCode( curl_getinfo( $ch, CURLINFO_HTTP_CODE ) );
    } catch (Exception $e) {
      $response = null;
    }
    
    curl_close( $ch );
    
    return $response;
  }
}