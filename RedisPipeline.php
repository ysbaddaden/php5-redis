<?php

class RedisPipeline
{
  private $redis;
  private $commands = array();
  
  function __construct($redis) {
    $this->redis = $redis;
  }
  
  function __call($func, $args)
  {
    $cmd = $this->redis->lookup_command($func);
    $this->commands[] = array($cmd, $args);
  }
  
  function execute()
  {
    if (empty($this->commands)) {
      return null;
    }
    
    $commands = array();
    foreach($this->commands as $cmd) {
      $commands[] = $this->redis->format_command($cmd[0], $cmd[1]);
    }
    $this->redis->send_command($commands);
    
    $rs = array();
    foreach($this->commands as $cmd) {
      $rs[] = $this->redis->read_reply($cmd[0]);
    }
    return $rs;
  }
}

?>
