<?php

# Shards keys within multiple Redis servers.
# 
# When using a cluster of servers, some redis commands may lead
# to problems (eg: multi/exec, mset, mget, etc), since keys must be on the
# same servers for these to work.
# 
# IMPROVE: implement mset/mget methods so that keys could still be sharded
# among servers.
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
class RedisCluster
{
  private $redis;
  private $hash_method;
  
  function __construct($configs=array(), $hash_method='md5')
  {
    $this->servers     = new RedisServers($configs);
    $this->hash_method = $hash_method;
  }
  
  function __call($func, $args)
  {
    $server = $this->hash($func, $args);
    return $this->send_command($server, $func, $args);
  }
  
  # Sends a command to a specific server.
  function send_command($server, $command, $args) {
    return call_user_func_array(array($this->servers[$server], $command), $args);
  }
  
  private function hash($func, $args)
  {
    if (count($this->servers) > 1)
    {
      $cmd  = Redis::lookup_command($func, $args);
      $hash = call_user_func_array($this->hash_method, $args[0]);
      return $hash % count($this->servers);
    }
    return 0;
  }
}

?>
