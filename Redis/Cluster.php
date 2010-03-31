<?php
namespace Redis;

# Shards keys within multiple Redis servers.
# 
# =Key Hashing
# 
# For commands like +SORT+ to work, all matching keys are expected to be in a
# single Redis server. For assigning the same type of keys to the same server
# you must specify your own hashing method.
# 
# For instance the following method will only encode the begining of the key
# (whatever is before +:+), so that all +webcomic:*+ keys will be on the same
# server, while +chapters:*+ could be on another one:
# 
#   $redis = new Redis\Cluster(array($server1, $server2), function($key)
#   {
#     $hkey = explode(':', $key, 2);
#     return md5($hkey[0]);
#   });
# 
# TODO: Proper MULTI/EXEC functionality across servers.
class Cluster
{
  private $servers;
  private $hash_method;
  
  function __construct($configs=array(), $hash_method='md5', $debug=false)
  {
    $this->servers     = new Servers($configs, $debug);
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
  
  function del($keys)
  {
    if (!is_array($keys)) {
      $keys = func_get_args();
    }
    
    $keys_by_server = array();
    foreach($keys as $key)
    {
      $server = $this->hash('del', $key);
      $keys_by_server[$server][] = $key;
    }
    
    $rs = 0;
    foreach($keys_by_server as $server => $args) {
      $rs += $this->send_command($server, 'del', $args);
    }
    return $rs;
  }
  
  # Supports all commands, except for +MGET+ and +MSETNX+.
  function pipeline($closure)
  {
    $pipe = new Pipeline($this);
    $closure($pipe);
    
    $commands_by_server = $this->dispatch_commands_by_server($pipe->commands());
    $rs = array();
    foreach($commands_by_server as $server => $commands)
    {
      $replies = $this->send_commands($server, $commands);
      
      if (!is_array($replies)) {
        $this->set_dispatched_reply($rs, key($commands), current($commands), $replies);
      }
      else
      {
        foreach($replies as $reply)
        {
          $this->set_dispatched_reply($rs, key($commands), current($commands), $reply);
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
    
    if ($nx and count($keys_by_server) > 1) {
      throw new \ErrorException("MSETNX cannot be sharded between servers. You must ensure all keys are on a single server.", 0, E_USER_WARNING);
    }
    
    foreach($keys_by_server as $server => $args) {
      $this->send_command($server, $cmd, array($args));
    }
    return 'OK';
  }
  
  # Dispatches commands by server (keeping the index for merging the replies correctly).
  private function & dispatch_commands_by_server(&$commands, $pipe=false)
  {
    $commands_by_server = array();
    foreach($commands as $i => $cmd)
    {
      switch($cmd[0])
      {
        case 'del':
          $dels_by_server = array();
          $keys = is_array($cmd[1][0]) ? $cmd[1][0] : $cmd[1];
          foreach($keys as $key)
          {
            $server = $this->hash($cmd[0], $key);
            $dels_by_server[$server][] = $key;
          }
          foreach($dels_by_server as $server => $keys) {
            $commands_by_server[$server][$i] = array($cmd[0], $keys);
          }
        break;
        
        case 'mset':
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
        
        case 'mget': case 'msetnx':
          trigger_error("Redis\Cluster->pipeline() doesn't support the {$cmd[0]} command.", E_USER_ERROR);
        break;
        
        default:
          $server = $this->server_for($cmd[0], $cmd[1]);
          $commands_by_server[$server][$i] = $cmd;
      }
    }
    return $commands_by_server;
  }
  
  private function set_dispatched_reply(&$rs, $i, $cmd, $reply)
  {
    if (isset($rs[$i]))
    {
      if (is_bool($rs[$i])) {
        $rs[$i] = $rs[$i] and $reply;
      }
      elseif (is_int($reply)) {
        $rs[$i] += $reply;
      }
#      else {
#        trigger_error("Skipping merge of this type '".gettype($rs[$i])."'.", E_USER_NOTICE);
#      }
    }
    else {
      $rs[$i] = $reply;
    }
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
      $cmd  = Client::lookup_command($func);
      $hash = call_user_func($this->hash_method, $key);
      return $hash % count($this->servers);
    }
    return 0;
  }
}

?>
