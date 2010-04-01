<?php
# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.

namespace Redis;

# Handles the list of servers for <tt>Redis\Cluster</tt>.
# :nodoc:
class Servers implements \Countable, \ArrayAccess
{
  private $servers = array();
  private $configs;
  private $count;
  public  $debug = false;
  
  function __construct($configs, $debug=false)
  {
    $this->configs = $configs;
    $this->count   = empty($configs) ? 1 : count($configs);
    $this->debug   = $debug;
  }
  
  function count() {
    return $this->count;
  }
  
  function offsetExists($index) {
    return isset($this->configs[$index]);
  }
  
  function offsetSet($index, $value) {
    trigger_error("Operation is disabled.", E_USER_ERROR);
  }
  
  function offsetGet($index)
  {
    if (!isset($this->servers[$index]))
    {
      if (!isset($this->configs[$index]))
      {
        trigger_error("No configuration for server $index, using localhost:6379.", E_USER_NOTICE);
        $config = array('host' => 'localhost', 'port' => 6379);
      }
      else {
        $config = $this->configs[$index];
      }
      $this->servers[$index] = new Client($config);
      $this->servers[$index]->debug = $this->debug;
    }
    return $this->servers[$index];
  }
  
  function offsetUnset($index) {
    unset($this->servers[$index]);
  }
}

?>
