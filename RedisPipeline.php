<?php

class RedisPipeline
{
  private $redis;
  private $commands = array();
  
  function __construct($redis) {
    $this->redis = $redis;
  }
  
  function __call($name, $args) {
    $this->commands[] = array($name, $args);
  }
  
  function execute()
  {
    if (empty($this->commands)) {
      return null;
    }
    return $this->redis->send_command($this->commands);
  }
}

?>
