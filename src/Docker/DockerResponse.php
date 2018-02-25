<?php

namespace Deve_CLI\Docker;

class DockerResponse {
  /**
   * body
   */
  private $body;
  private $status_code;
  
  public function setBody($body) {
    $this->body = $body;
    return $this;
  }
  
  public function getBody() {
    return $this->body;
  }
  
  public function getDecodedBody() {
    return json_decode($this->body);
  }
  
  public function setStatusCode($status_code) {
    $this->status_code = $status_code;
    return $this;
  }
  
  public function getStatusCode() {
    return $this->status_code;
  }
}