<?php
# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.

# Redis client class.
# 
# = Commands
# 
# See http://code.google.com/redis for the full list of commands.
# 
# = Examples
# 
#   $r = new Redis();
#   
#   $r->set('mykey', 'foobar');  # => true
#   $r->get('mykey');            # => 'foobar'
#   $r->setnx('mykey', 'foo');   # => false (mykey is already defined)
#   $r->getset('mykey', 'foo');  # => 'foobar'
#   
#   $r->incr('counter');         # => 1
#   $r->incrby('counter', 3);    # => 4
#   $r->decr('counter');         # => 3
#   
#   $r->del('counter');          # => true
#   $r->get('counter');          # => null
#   
# = Replies
# 
# == Errors
# 
# If the server returns an error, this class will throw a catchable
# <tt>RedisException</tt> with the error message returned by the server.
# 
# == Status replies
# 
# +Redis+ returns a bool if the status corresponds to what the command expects.
# For instance +ping()+ awaits +'PONG'+ and will return true if the status reply
# is 'PONG'.
# 
# Please note that it will always return true and never false, since a
# <tt>RedisException</tt> will be throwned when the result is an error.
# 
# == Integer replies
# 
# This class returns a PHP integer when the Redis server says the value is one.
# Except when a command returns only 0 or 1 integers, which are to be
# interpreted as booleans, this class then returns a boolean.
# 
# == String replies
# 
# Methods like +get()+ will return a string if the key is declared as is.
# 
# == Array replies
# 
# Methods like +mget()+ will always return an array, containing either strings
# or integers depending on the type of the keys, or nulls if a key doesn't
# exist.
# 
class Redis
{
  const ERR_CONNECT       = 1;
  const ERR_NOT_CONNECTED = 2;
  const ERR_AUTH          = 3;
  const ERR_REPLY         = 4;
  const ERR_ARG_COUNT     = 5;
  
  const CMD_INLINE    = 1;
  const CMD_BULK      = 2;
  const CMD_MULTIBULK = 3;
  
  const REP_OK     = 1;
  const REP_STRING = 2;
  const REP_INT    = 3;
  const REP_FLOAT  = 4;
  const REP_BOOL   = 5;
  const REP_QUEUED = 6;
  const REP_PONG   = 7;
  
  public  $debug = false;
  private $sock;
  
  private static $commands = array(
    # connection
    'auth'         => array(0,  self::CMD_INLINE,    self::REP_OK),
    
    # multi/exec (redis >= 1.3)
    'multi'        => array(0,  self::CMD_INLINE,    self::REP_QUEUED),
    'exec'         => array(0,  self::CMD_INLINE),
    
    # generics
    'exists'       => array(1,  self::CMD_INLINE,    self::REP_BOOL),
    'del'          => array(-1, self::CMD_INLINE,    self::REP_INT),
    'type'         => array(1,  self::CMD_INLINE),
    'keys'         => array(1,  self::CMD_INLINE),
    'randomkey'    => array(1,  self::CMD_INLINE),
    'rename'       => array(2,  self::CMD_INLINE,    self::REP_OK),
    'renamenx'     => array(2,  self::CMD_INLINE,    self::REP_BOOL),
    'dbsize'       => array(0,  self::CMD_INLINE,    self::REP_INT),
    'expire'       => array(2,  self::CMD_INLINE,    self::REP_BOOL),
    'expireat'     => array(2,  self::CMD_INLINE,    self::REP_BOOL),
    'ttl'          => array(1,  self::CMD_INLINE,    self::REP_INT),
    'select'       => array(1,  self::CMD_INLINE,    self::REP_OK),
    'move'         => array(2,  self::CMD_INLINE,    self::REP_BOOL),
    'flushdb'      => array(0,  self::CMD_INLINE,    self::REP_OK),
    'flushall'     => array(0,  self::CMD_INLINE,    self::REP_OK),
    
    # strings
    'set'          => array(2,  self::CMD_BULK,      self::REP_OK),
    'get'          => array(1,  self::CMD_INLINE),
    'getset'       => array(2,  self::CMD_BULK),
    'setnx'        => array(2,  self::CMD_BULK,      self::REP_BOOL),
    'mget'         => array(-1, self::CMD_INLINE),
    'mset'         => array(-2, self::CMD_MULTIBULK, self::REP_OK),
    'msetnx'       => array(-2, self::CMD_MULTIBULK, self::REP_BOOL),
    'incr'         => array(1,  self::CMD_INLINE,    self::REP_INT),
    'incrby'       => array(2,  self::CMD_INLINE,    self::REP_INT),
    'decr'         => array(1,  self::CMD_INLINE,    self::REP_INT),
    'decrby'       => array(2,  self::CMD_INLINE,    self::REP_INT),
    
    # lists
    'lpush'        => array(2,  self::CMD_BULK,      self::REP_OK),
    'rpush'        => array(2,  self::CMD_BULK,      self::REP_OK),
    'llen'         => array(1,  self::CMD_INLINE,    self::REP_INT),
    'lrange'       => array(3,  self::CMD_INLINE),
    'ltrim'        => array(3,  self::CMD_INLINE,    self::REP_OK),
    'lindex'       => array(2,  self::CMD_INLINE),
    'lset'         => array(3,  self::CMD_BULK,      self::REP_OK),
    'lrem'         => array(3,  self::CMD_BULK,      self::REP_INT),
    'lpop'         => array(1,  self::CMD_INLINE),
    'rpop'         => array(1,  self::CMD_INLINE),
    'rpoplpush'    => array(2,  self::CMD_INLINE),
    
    # sets
    'sadd'         => array(2,  self::CMD_BULK,      self::REP_BOOL),
    'srem'         => array(2,  self::CMD_BULK,      self::REP_BOOL),
    'spop'         => array(1,  self::CMD_INLINE),
    'smove'        => array(3,  self::CMD_BULK,      self::REP_BOOL),
    'scard'        => array(1,  self::CMD_INLINE,    self::REP_INT),
    'sismember'    => array(2,  self::CMD_BULK,      self::REP_BOOL),
    'sinter'       => array(-1, self::CMD_INLINE),
    'sinterstore'  => array(-2, self::CMD_INLINE,    self::REP_INT),
    'sunion'       => array(-1, self::CMD_INLINE),
    'sunionstore'  => array(-2, self::CMD_INLINE,    self::REP_INT),
    'sdiff'        => array(-1, self::CMD_INLINE),
    'sdiffstore'   => array(-2, self::CMD_INLINE,    self::REP_INT),
    'smembers'     => array(1,  self::CMD_INLINE),
    'srandmember'  => array(1,  self::CMD_INLINE),
    
    # zsets (sorted sets)
    'zadd'             => array(3,  self::CMD_BULK,   self::REP_BOOL),
    'zrem'             => array(2,  self::CMD_INLINE, self::REP_BOOL),
    'zincrby'          => array(3,  self::CMD_INLINE, self::REP_INT),
    'zrange'           => array(3,  self::CMD_INLINE),
    'zrevrange'        => array(-3, self::CMD_INLINE),
    'zrangebyscore'    => array(-3, self::CMD_INLINE),
    'zcard'            => array(1,  self::CMD_INLINE, self::REP_INT),
    'zscore'           => array(1,  self::CMD_INLINE, self::REP_FLOAT),
    'zremrangebyscore' => array(3,  self::CMD_INLINE, self::REP_INT),
    
    # sorting
    'sort'         => array(-1, self::CMD_INLINE),
    
    # persistence
    'save'         => array(0,  self::CMD_INLINE,    self::REP_OK),
    'bgsave'       => array(0,  self::CMD_INLINE,    self::REP_OK),
    'bgrewriteaof' => array(0,  self::CMD_INLINE,    self::REP_OK),
    'lastsave'     => array(0,  self::CMD_INLINE,    self::REP_INT),
    
    # server
    'ping'         => array(0,  self::CMD_INLINE,    self::REP_PONG),
    'shutdown'     => array(0,  self::CMD_INLINE),
    'info'         => array(0,  self::CMD_INLINE),
    'slaveof'      => array(0,  self::CMD_INLINE,    self::REP_OK),
  );
  
  function __construct() {
  }
  
  function __destruct() {
    $this->quit();
  }
  
  function __call($name, $args)
  {
    $cmd = $this->lookup_command($name);
    $this->send_command($this->format_command($cmd, $args));
    return $this->read_command_reply($cmd);
  }
  
  function connect($host='localhost', $port=6379, $password=null)
  {
    if (($this->sock = fsockopen($host, $port, $errno, $errstr)) === false)
    {
      throw new RedisException("Unable to connect to Redis server on $host:$port ".
        "($errno $errstr).", Redis::ERR_CONNECT);
    }
    if ($password !== null
      and !$this->auth($password))
    {
      throw new RedisException("Unable to auth on Redis server: wrong password?",
        Redis::ERR_AUTH);
    }
  }
  
  function quit()
  {
    if ($this->sock)
    {
      $this->send_command($this->format_inline_command('quit'));
      $this->sock = null;
    }
  }
  
  function mset($keys) {
    return $this->_mset('mset', $keys);
  }
  
  function msetnx($keys) {
    return $this->_mset('msetnx', $keys);
  }
  
  function pipe($closure)
  {
    $pipe = new RedisPipeline($this);
    $closure($pipe);
    return $pipe->send();
  }
  
  
  private function _mset($name, $keys)
  {
    $args = array($name);
    foreach($keys as $k => $v)
    {
      $args[] = $k;
      $args[] = $v;
    }
    $cmd = $this->format_multibulk_command($args);
    $this->send_command($cmd);
    $rs = $this->read_reply();
    return ($name == 'mset') ? ($rs == 'OK') : (bool)$rs;
  }
  
  private function lookup_command($name)
  {
    if (!isset(self::$commands[$name])) {
      return null;
    }
    return array(
      'name'       => $name,
      'arity'      => self::$commands[$name][0],
      'cmd_type'   => self::$commands[$name][1],
      'reply_type' => isset(self::$commands[$name][2]) ?
        self::$commands[$name][2] : null,
    );
  }
  
  # :nodoc:
  function format_command($cmd, $args)
  {
    if (isset($args[0]) and is_array($args[0])) {
      $args = $args[0];
    }
    
    if ($cmd === null) {
      return $this->format_multibulk_command($cmd, $args);
    }
    
    if (( $cmd['arity'] > 0
      and count($args) != $cmd['arity']))
    {
      throw new RedisException(sprintf("Redis command %s takes %d arguments, but got %d.",
        $cmd['name'], $cmd['arity'], count($args)), Redis::ERR_ARG_COUNT);
    }
    elseif ($cmd['arity'] < 0
      and count($args) < -$cmd['arity'])
    {
      throw new RedisException(sprintf("Redis command %s takes at least %d arguments, but got %d.",
        $cmd['name'], $cmd['arity'], count($args)), Redis::ERR_ARG_COUNT);
    }
    
    switch($cmd['cmd_type'])
    {
      case self::CMD_INLINE:    return $this->format_inline_command($cmd['name'], $args);
      case self::CMD_BULK:      return $this->format_bulk_command($cmd['name'], $args);
      case self::CMD_MULTIBULK: return $this->format_multibulk_command($args, $cmd['name']);
    }
  }
  
  protected function format_inline_command($name, $args=array())
  {
    $cmd = $name;
    if (!empty($args))
    {
      foreach($args as $arg)
      {
        $cmd .= ' ';
        $cmd .= is_array($arg) ? implode(' ', $arg) : $arg;
      }
    }
    return $cmd;
  }
  
  protected function format_bulk_command($name, $args=array())
  {
    $bulk_data = array_pop($args);
    $cmd  = "$name ".implode(' ', $args).' ';
    $cmd .= sprintf("%lu\r\n", strlen($bulk_data));
    $cmd .= $bulk_data;
    return $cmd;
  }
  
  protected function format_multibulk_command($args=array(), $name=null)
  {
    if ($name !== null) {
      array_unshift($args, $name);
    }
    $cmd = '*'.count($args);
    foreach($args as $arg) {
      $cmd .= "\r\n$".strlen($arg)."\r\n".$arg;
    }
    return $cmd;
  }
  
  # :private:
  function send_command($cmd, $read_reply=true)
  {
    if ($this->debug) echo "\n-> \"$cmd\"\n";
    
    if (fwrite($this->sock, "$cmd\r\n") !== false) {
      #return $read_reply ? $this->read_reply() : true;
      return true;
    }
    
    throw new RedisException("Error writing to server socket. Are you connected?",
      Redis::ERR_NOT_CONNECTED);
  }
  
  # :private:
  function read_command_reply($cmd)
  {
    $rs = $this->read_reply();
    
    switch($cmd['reply_type'])
    {
      case null:             return $rs;
      case self::REP_INT:    return (int)$rs;
      case self::REP_BOOL:   return (bool)$rs;
      case self::REP_OK:     return ($rs == 'OK');
      case self::REP_FLOAT:  return (double)$rs;
      case self::REP_PONG:   return ($rs == 'PONG');
    }
  }
  
  # :private:
  function read_reply()
  {
    switch(fgetc($this->sock))
    {
      case '+': return $this->read_single_line_reply();
      case ':': return (int)$this->read_single_line_reply();
      case '$': return $this->read_bulk_reply();
      case '*': return $this->read_multibulk_reply();
      case '-': throw new RedisException($this->read_single_line_reply(),
        self::ERR_REPLY);
    }
  }
  
  private function read_single_line_reply()
  {
    $line = rtrim(fgets($this->sock), "\r\n");
    if ($this->debug) echo "<- \"$line\"\n";
    return $line;
  }
  
  private function read_bulk_reply()
  {
    $len = (int)fgets($this->sock);
    if ($len == -1) return null;
    
    # gets the bulk response (and discards the last CRLF)
    $rs  = '';
    while(strlen($rs) < $len) {
      $rs .= fread($this->sock, $len);
    }
    fread($this->sock, 2);
    
    if ($this->debug) echo "<- \"$rs\"\n";
    return $rs;
  }
  
  private function read_multibulk_reply()
  {
    $rows = (int)fgets($this->sock);
    if ($rows == -1) return null;
    
    $ary = array();
    for ($i=0; $i<$rows; $i++)
    {
      fgetc($this->sock);
      $ary[] = $this->read_bulk_reply();
    }
    return $ary;
  }
}

class RedisException extends Exception
{
}

/*
# :nodoc:
class RedisPipeline
{
  private $commands = array();
  
  function __construct($redis) {
    $this->redis = $redis;
  }
  
  function __call($func, $args) {
    $this->commands[$func] = $args;
  }
  
  function & send()
  {
    # sends the list of commands
    $cmd = '';
    foreach($this->commands as $func => $args)
    {
      $cmd .= $this->redis->format_command($func, $args);
      $cmd .= "\r\n";
    }
    $redis->send_command($cmd);
    
    # reads the list of replies
    $rs = array();
    for ($i=0; $i<count($this->commands); $i++) {
      $rs[] = $this->redis->read_reply();
    }
    return $rs;
  }
}
*/

?>
