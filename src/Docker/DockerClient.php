<?php

namespace Deve_CLI\Docker;

use Deve_CLI\Docker\DockerResponse;

class DockerClient {
  /**
   * Docker Socket
   */
  const DOCKER_SOCKET = '/var/run/docker.sock';

  /**
   * Docker Engine Version
   */
  const DOCKER_VERSION = 'v1.36';

  /**
   * Docker Client Instance
   */
  private $instance;
  
  /**
   * Should we debug?
   */
  private $verbose;

  private function __construct( $verbose ) {
    $this->verbose = $verbose;
  }

  public static function getInstance( $verbose = false ) {
    if ( $instance === null ) {
      $instance = new DockerClient( $verbose );
    }
    return $instance;
  }

  public function start_container( $container_id ) {
    return $this->request( "/containers/{$container_id}/start", 'POST' );
  }

  public function restart_container( $container_id ) {
    return $this->request( "/containers/{$container_id}/restart", 'POST' );
  }

  public function stop_container( $container_id ) {
    return $this->request( "/containers/{$container_id}/stop", 'POST' );
  }

  public function find_containers( $filters = array() ) {
    return $this->request( '/containers/json?filters=' . json_encode( $filters ) );
  }

  public function run( $data = array() ) {
    $container = $this->request( '/containers/create', 'POST', $data );
    $container_id = $container->getDecodedBody()->Id;
    $this->request( "/containers/{$container_id}/start", 'POST' );
    $status = $this->request( "/containers/{$container_id}/wait", 'POST' );
    if ( $this->verbose )
      $this->request( "/containers/{$container_id}/logs?follow=true&stdout=true&stderr=true", 'GET' );
    $this->request( "/containers/{$container_id}", 'DELETE' );
    if ( $status->getDecodedBody()->StatusCode === 0 ) {
      return true;
    }
    return false;
  }

  private function request( $endpoint, $method = 'GET', $data = null ) {
    $ch = curl_init();
    $version = static::DOCKER_VERSION;

    curl_setopt( $ch, CURLOPT_UNIX_SOCKET_PATH, static::DOCKER_SOCKET );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $ch, CURLOPT_URL, "http:/{$version}{$endpoint}" );
    curl_setopt( $ch, CURLOPT_VERBOSE, $this->verbose );

    if ( 'POST' === $method ) {
      curl_setopt( $ch, CURLOPT_POST, 1 );
      if ( $this->verbose ) var_dump( json_encode( $data ) );
      curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
    } elseif ( 'GET' !== $method ) {
      curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    }

    $response = new DockerResponse();

    try {
      $response->setBody( curl_exec( $ch ) );
      if ( $this->verbose ) var_dump( $response->getBody() );
      $response->setStatusCode( curl_getinfo( $ch, CURLINFO_HTTP_CODE ) );
    } catch (Exception $e) {
      $response = null;
    }

    curl_close( $ch );
    return $response;
  }
}
