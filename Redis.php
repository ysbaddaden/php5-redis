<?php
# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.

# Redis client.
# 
# = Commands
# 
# See http://code.google.com/redis for the full list of commands
# and their documentation.
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
# TODO: Properly handle MULTI/EXEC (QUEUED replies, EXEC with all replies and DISCARD).
class Redis
{
  const ERR_CONNECT   = 1;
  const ERR_SOCKET    = 2;
  const ERR_AUTH      = 3;
  const ERR_ARG_COUNT = 4;
  const ERR_REPLY     = 5;
  
  const CMD_INLINE    = 1;
  const CMD_BULK      = 2;
  const CMD_MULTIBULK = 3;
  
  const REP_OK        = '+OK';
  const REP_STRING    = 2;
  const REP_FLOAT     = 4;
  const REP_BOOL      = 5;
  const REP_QUEUED    = '+QUEUED';
  const REP_PONG      = '+PONG';
  const REP_ARRAY     = 8;
  const REP_ASSOC     = 9;
  
  public  $debug = false;
  private $sock;
  
  private static $commands = array(
    # connection
    'auth'         => array(self::CMD_INLINE,    self::REP_OK),
    
    # multi/exec (untested)
    'multi'        => array(self::CMD_INLINE,    self::REP_OK),
    'exec'         => array(self::CMD_INLINE),
    'discard'      => array(self::CMD_INLINE,    self::REP_OK),
    
    # generics
    'exists'       => array(self::CMD_INLINE,    self::REP_BOOL),
    'del'          => array(self::CMD_INLINE),
    'type'         => array(self::CMD_INLINE),
    'keys'         => array(self::CMD_INLINE),
    'randomkey'    => array(self::CMD_INLINE),
    'rename'       => array(self::CMD_INLINE,    self::REP_OK),
    'renamenx'     => array(self::CMD_INLINE,    self::REP_BOOL),
    'dbsize'       => array(self::CMD_INLINE),
    'expire'       => array(self::CMD_INLINE,    self::REP_BOOL),
    'expireat'     => array(self::CMD_INLINE,    self::REP_BOOL),
    'ttl'          => array(self::CMD_INLINE),
    'select'       => array(self::CMD_INLINE,    self::REP_OK),
    'move'         => array(self::CMD_INLINE,    self::REP_BOOL),
    'flushdb'      => array(self::CMD_INLINE,    self::REP_OK),
    'flushall'     => array(self::CMD_INLINE,    self::REP_OK),
    
    # strings
    'set'          => array(self::CMD_BULK,      self::REP_OK),
    'get'          => array(self::CMD_INLINE),
    'getset'       => array(self::CMD_BULK),
    'setnx'        => array(self::CMD_BULK,      self::REP_BOOL),
    'mget'         => array(self::CMD_INLINE),
    'mset'         => array(self::CMD_MULTIBULK, self::REP_OK),
    'msetnx'       => array(self::CMD_MULTIBULK, self::REP_BOOL),
    'incr'         => array(self::CMD_INLINE),
    'incrby'       => array(self::CMD_INLINE),
    'decr'         => array(self::CMD_INLINE),
    'decrby'       => array(self::CMD_INLINE),
    
    # lists
    'lpush'        => array(self::CMD_BULK,      self::REP_BOOL),
    'rpush'        => array(self::CMD_BULK,      self::REP_BOOL),
    'llen'         => array(self::CMD_INLINE),
    'lrange'       => array(self::CMD_INLINE),
    'ltrim'        => array(self::CMD_INLINE,    self::REP_OK),
    'lindex'       => array(self::CMD_INLINE),
    'lset'         => array(self::CMD_BULK,      self::REP_OK),
    'lrem'         => array(self::CMD_BULK),
    'lpop'         => array(self::CMD_INLINE),
    'rpop'         => array(self::CMD_INLINE),
    'rpoplpush'    => array(self::CMD_INLINE),
    'blpop'        => array(self::CMD_INLINE),
    'brpop'        => array(self::CMD_INLINE),
    
    # sets
    'sadd'         => array(self::CMD_BULK,      self::REP_BOOL),
    'srem'         => array(self::CMD_BULK,      self::REP_BOOL),
    'spop'         => array(self::CMD_INLINE),
    'smove'        => array(self::CMD_BULK,      self::REP_BOOL),
    'scard'        => array(self::CMD_INLINE),
    'sismember'    => array(self::CMD_BULK,      self::REP_BOOL),
    'sinter'       => array(self::CMD_INLINE),
    'sinterstore'  => array(self::CMD_INLINE),
    'sunion'       => array(self::CMD_INLINE),
    'sunionstore'  => array(self::CMD_INLINE),
    'sdiff'        => array(self::CMD_INLINE),
    'sdiffstore'   => array(self::CMD_INLINE),
    'smembers'     => array(self::CMD_INLINE,    self::REP_ARRAY),
    'srandmember'  => array(self::CMD_INLINE),
    
    # zsets (sorted sets)
    'zadd'             => array(self::CMD_BULK,   self::REP_BOOL),
    'zrem'             => array(self::CMD_INLINE, self::REP_BOOL),
    'zincrby'          => array(self::CMD_INLINE),
    'zrange'           => array(self::CMD_INLINE),
    'zrevrange'        => array(self::CMD_INLINE),
    'zrangebyscore'    => array(self::CMD_INLINE),
    'zcard'            => array(self::CMD_INLINE),
    'zscore'           => array(self::CMD_INLINE, self::REP_FLOAT),
    'zremrangebyscore' => array(self::CMD_INLINE),
    
    # sorting
    'sort'         => array(self::CMD_INLINE),
    
    # hashes
    'hset'         => array(self::CMD_MULTIBULK,  self::REP_BOOL),
    'hget'         => array(self::CMD_BULK),
    'hdel'         => array(self::CMD_BULK,       self::REP_BOOL),
    'hlen'         => array(self::CMD_INLINE),
    'hkeys'        => array(self::CMD_INLINE),
    'hvals'        => array(self::CMD_INLINE),
    'hgetall'      => array(self::CMD_INLINE,     self::REP_ASSOC),
    'hexists'      => array(self::CMD_BULK,       self::REP_BOOL),
    'hincrby'      => array(self::CMD_MULTIBULK),
    
    # persistence
    'save'         => array(self::CMD_INLINE,    self::REP_OK),
    'bgsave'       => array(self::CMD_INLINE,    self::REP_OK),
    'bgrewriteaof' => array(self::CMD_INLINE,    self::REP_OK),
    'lastsave'     => array(self::CMD_INLINE),
    
    # server
    'ping'         => array(self::CMD_INLINE,    self::REP_PONG),
    'shutdown'     => array(self::CMD_INLINE),
    'info'         => array(self::CMD_INLINE),
    'slaveof'      => array(self::CMD_INLINE,    self::REP_OK),
  );
  
  function __construct($config=null) {
    $this->config = $config;
  }
  
  function __destruct() {
    $this->quit();
  }
  
  function __call($name, $args) {
    return $this->send_command(array(array($name, $args)));
  }
  
  function connect()
  {
    $host = isset($this->config['host'])     ? $this->config['host']     : 'localhost';
    $port = isset($this->config['port'])     ? $this->config['port']     : '6379';
    
    if (($this->sock = fsockopen($host, $port, $errno, $errstr)) === false)
    {
      throw new RedisException("Unable to connect to Redis server on $host:$port ".
        "($errno $errstr).", Redis::ERR_CONNECT);
    }
    
    if (isset($this->config['password'])
      and !$this->auth($password))
    {
      throw new RedisException("Unable to auth on Redis server: wrong password?",
        Redis::ERR_AUTH);
    }
    
    if (isset($this->config['db'])) {
      $this->select($this->config['db']);
    }
  }
  
  function quit()
  {
    if ($this->sock)
    {
      $this->send_raw_command('quit');
      $this->sock = null;
    }
  }
  
  function pipeline($closure)
  {
    $pipe = new RedisPipeline($this);
    $closure($pipe);
    return $pipe->execute();
  }
  
  # :nodoc:
  function lookup_command($name)
  {
    $cmd = isset(self::$commands[$name]) ?
      self::$commands[$name] : array(self::CMD_MULTIBULK);
    
    return array(
      'name'  => $name,
      'type'  => $cmd[0],
      'reply' => isset($cmd[1]) ? $cmd[1] : null,
    );
  }
  
  # :nodoc:
  function format_command($cmd, $args)
  {
    if (isset($args[0]) and is_array($args[0])) {
      $args = $args[0];
    }
    
    switch($cmd['type'])
    {
      case self::CMD_INLINE:
        return $this->format_inline_command($cmd['name'], $args);
      
      case self::CMD_BULK:
        return $this->format_bulk_command($cmd['name'], $args);
      
      case self::CMD_MULTIBULK:
        return $this->format_multibulk_command($cmd['name'], $args);
    }
  }
  
  private function format_inline_command($name, $args=array())
  {
    $cmd = $name;
    foreach($args as $arg) {
      $cmd .= ' '.(is_array($arg) ? implode(' ', $arg) : $arg);
    }
    return $cmd;
  }
  
  private function format_bulk_command($name, $args=array())
  {
    $bulk_data = array_pop($args);
    $cmd  = "$name ".implode(' ', $args).' ';
    $cmd .= sprintf("%lu\r\n", strlen($bulk_data));
    $cmd .= $bulk_data;
    return $cmd;
  }
  
  private function format_multibulk_command($name, $args=array())
  {
    if ($name == 'mset'
      or $name == "msetnx")
    {
      $_args = array();
      foreach($args as $k => $v)
      {
        $_args[] = $k;
        $_args[] = $v;
      }
      $args = $_args;
    }
    if ($name !== null) {
      array_unshift($args, $name);
    }
    
    $cmd = '*'.count($args);
    foreach($args as $arg) {
      $cmd .= "\r\n$".strlen($arg)."\r\n".$arg;
    }
    return $cmd;
  }
  
  function send_command($commands)
  {
    # format
    $cmd_str = array();
    $cmd     = array();
    foreach($commands as $i => $c)
    {
      list($name, $args) = $c;
      $cmd[$i]   = $this->lookup_command($name);
      $cmd_str[] = $this->format_command($cmd[$i], $args);
    }
    
    # call
    $cmd_str = implode("\r\n", $cmd_str);
    $this->send_raw_command($cmd_str);
    
    #reply
    $rs = array();
    foreach($cmd as $c) {
      $rs[] = $this->read_reply($c);
    }
    return (count($commands) == 1) ? $rs[0] : $rs;
  }
  
  private function send_raw_command($cmd)
  {
    if ($this->debug) echo "\n> \"$cmd\"\n";
    
    if ($this->sock === null) {
      $this->connect();
    }
    
    if (!fwrite($this->sock, "$cmd\r\n")) {
      throw new RedisException("Cannot write to server socket.", Redis::ERR_SOCKET);
    }
  }
  
  # :nodoc:
  function read_reply($cmd)
  {
    $rs = $this->read_raw_reply();
    
    switch($cmd['reply'])
    {
      case null:             return $rs;
      case self::REP_BOOL:   return (bool)$rs;
      case self::REP_OK:     return ($rs == self::REP_OK);
      case self::REP_FLOAT:  return (double)$rs;
      case self::REP_PONG:   return ($rs == self::REP_PONG);
      case self::REP_ARRAY:  return ($rs !== null) ? $rs : array();
      case self::REP_ASSOC:
        $ary = array();
        for ($i=0; $i<count($rs); $i+=2) {
          $ary[$rs[$i]] = $rs[$i+1];
        }
        return $ary;
    }
  }
  
  private function read_raw_reply()
  {
    switch(fgetc($this->sock))
    {
      case '+': return '+'.$this->read_single_line_reply();
      case ':': return (int)$this->read_single_line_reply();
      case '$': return $this->read_bulk_reply();
      case '*': return $this->read_multibulk_reply();
      case '-': throw new RedisException($this->read_single_line_reply(), self::ERR_REPLY);
    }
  }
  
  private function read_single_line_reply()
  {
    $line = rtrim(fgets($this->sock), "\r\n");
    if ($this->debug) echo "< \"$line\"\n";
    return $line;
  }
  
  private function read_bulk_reply()
  {
    $len = (int)fgets($this->sock);
    if ($len == -1)
    {
      if ($this->debug) echo "< NULL\n";
      return null;
    }
    
    # gets the bulk response (and discards the last CRLF)
    $rs  = '';
    while(strlen($rs) < $len) {
      $rs .= fread($this->sock, $len);
    }
    fread($this->sock, 2);
    
    if ($this->debug) echo "< \"$rs\"\n";
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

?>
