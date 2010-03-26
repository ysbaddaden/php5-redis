<?php

# Shards keys within multiple Redis servers.
# 
# =Key Hashing
# 
# For commands like SORT to work, all matching keys are expected to be in a
# single Redis server. For assigning the same type of keys to the same server
# you must specify your own hashing method.
# 
# For instance the following method will only encode the begining of the key
# (whatever is before +:+), so that all +webcomic:*+ keys will be on the same
# servers, while +chapters:*+ could be on another one:
# 
#   $redis = new RedisCluster(array($server1, $server2), function($key)
#   {
#     $hkey = explode(':', $key, 2);
#     return md5($hkey[0]);
#   });
# 
# TODO: Proper MULTI/EXEC functionality across servers.
# TODO: Support for PIPELINE across servers.
class RedisCluster
{
  private $servers;
  private $hash_method;
  
  function __construct($configs=array(), $hash_method='md5', $debug=false)
  {
    $this->servers     = new RedisServers($configs, $debug);
    $this->hash_method = $hash_method;
  }
  
  function __call($func, $args)
  {
    $server = $this->server_for($func, $args);
    return $this->send_command($server, $func, $args);
  }
  
  function & mget($keys)
  {
    if (!is_array($keys)) {
      $keys = func_get_args();
    }
    
    $keys_by_server = array();
    foreach($keys as $key)
    {
      $server = $this->hash('mget', $key);
      $keys_by_server[$server][] = $key;
    }
    
    $result = array();
    foreach($keys_by_server as $server => $args)
    {
      $server_result = $this->send_command($server, 'mget', $args);
      $result = array_merge($result, $server_result);
    }
    return $result;
  }
  
  # FIXME: MGET in PIPELINE won't return replies in the right order.
  function pipeline($closure)
  {
    $pipe = new RedisPipeline($this);
    $closure($pipe);
    
    # executes the commands and dispatches results by their original index
    $commands_by_server = $this->dispatch_commands_by_server($pipe->commands());
    $rs = array();
    foreach($commands_by_server as $server => $commands)
    {
      $replies = $this->send_commands($server, $commands);
      
      if (!is_array($replies)) {
        $this->set_dispatched_reply($rs, key($commands), $replies);
      }
      else
      {
        foreach($replies as $reply)
        {
          $this->set_dispatched_reply($rs, key($commands), $reply);
          next($commands);
        }
      }
    }
    ksort($rs);
    return $rs;
  }
  
  function mset($keys) {
    return $this->_mset($keys);
  }
  
  function msetnx($keys) {
    return $this->_mset($keys, true);
  }
  
  private function _mset($keys, $nx=false)
  {
    $cmd = "mset".($nx ? 'nx' : '');
    $keys_by_server = array();
    foreach($keys as $key => $value)
    {
      $server = $this->hash($cmd, $key);
      $keys_by_server[$server][$key] = $value;
    }
    
    $rs = true;
    foreach($keys_by_server as $server => $args) {
      $rs = $rs && !!$this->send_command($server, $cmd, array($args));
    }
    return $rs;
  }
  
  private function set_dispatched_reply(&$rs, $i, $reply)
  {
    if (!isset($rs[$i])) {
      $rs[$i] = $reply;
    }
    elseif (is_bool($reply)) {
      $rs[$i] = $rs[$i] and $reply;
    }
    elseif (is_array($reply)) {
      $rs[$i] = array_merge($rs[$i], $reply);
    }
    else {
      trigger_error("Unknown reply type '".gettype($reply)."'", E_USER_WARNING);
    }
  }
  
  # Dispatches commands by server (keeping the index for merging the replies correctly).
  private function & dispatch_commands_by_server(&$commands)
  {
    $commands_by_server = array();
    foreach($commands as $i => $cmd)
    {
      switch($cmd[0])
      {
        case 'mget':
          $keys = is_array($cmd[1][0]) ? $cmd[1][0] : $cmd[1];
          
          $mgets_by_server = array();
          foreach($keys as $key)
          {
            $server = $this->hash($cmd[0], $key);
            $mgets_by_server[$server][] = $key;
          }
          
          foreach($mgets_by_server as $server => $keys) {
            $commands_by_server[$server][$i] = array($cmd[0], $keys);
          }
        break;
        
        case 'mset': case 'msetnx': 
          $msets_by_server = array();
          foreach($cmd[1][0] as $key => $value)
          {
            $server = $this->hash($cmd[0], $key);
            $msets_by_server[$server][$key] = $value;
          }
          foreach($msets_by_server as $server => $keys) {
            $commands_by_server[$server][$i] = array($cmd[0], $keys);
          }
        break;
        
        default:
          $server = $this->server_for($cmd[0], $cmd[1]);
          $commands_by_server[$server][$i] = $cmd;
      }
    }
    return $commands_by_server;
  }
  
  # Sends a command to a specific server.
  function send_command($server, $command, $args=array()) {
    return call_user_func_array(array($this->servers[$server], $command), $args);
  }
  
  private function send_commands($server, $commands) {
    return call_user_func_array(array($this->servers[$server], 'send_command'), array($commands));
  }
  
  private function server_for($func, $args) {
    return isset($args[0]) ? $this->hash($func, $args[0]) : 0;
  }
  
  private function hash($func, $key)
  {
    if (count($this->servers) > 1)
    {
      $cmd  = Redis::lookup_command($func);
      $hash = call_user_func($this->hash_method, $key);
      return $hash % count($this->servers);
    }
    return 0;
  }
}

?>
