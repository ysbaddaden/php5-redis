<?php
namespace Redis;

# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.

# Redis client.
# 
# = Commands
# 
# See http://code.google.com/redis for the full list of commands and their
# documentation.
# 
# = Examples
# 
#   $r = new Redis\Client();
#   
#   $r->set('mykey', 'foobar');  # => 'OK'
#   $r->get('mykey');            # => 'foobar'
#   $r->setnx('mykey', 'foo');   # => false
#   $r->getset('mykey', 'foo');  # => 'foobar'
#   
#   $r->incr('counter');         # => 1
#   $r->incrby('counter', 3);    # => 4
#   $r->decr('counter');         # => 3
#   
#   $r->del('counter');          # => 1
#   $r->get('counter');          # => null
# 
# = Replies
# 
# == Errors
# 
# If the server returns an error, this class will throw a catchable
# <tt>Redis\Exception</tt> with the error message returned by the server.
# 
# == Status codes
# 
# Status replies (eg: +OK+, +PONG+) are returned as is, and will always return
# that. If a command fails a <tt>Redis\Exception</tt> will be thrown.
# 
# == Strings
# 
# Methods like +get()+ will return a string, if the key is declared as is
# otherwise it might be +null+ (no such key), or an integer.
# 
# == Integers
# 
# This class returns a PHP integer when the Redis server says the value is one.
# 
# Some commands will only return +0+ and +1+ which means +false+ and +true+.
# In this case, the command will return a boolean.
# 
# == Arrays
# 
# Methods like +mget()+ will always return an array, containing either strings
# or integers depending on the type of the keys, or nulls if a key doesn't
# exist.
# 
# == Associative arrays
# 
# Methods like +hgetall()+ will return an associative array in the form
# +array($field => $value)+.
# 
# TODO: Support SORT.
# TODO: Properly handle MULTI/EXEC.
# TODO: Test Public/Subscribe.
class Client
{
  const ERR_CONNECT   = 1;
  const ERR_SOCKET    = 2;
  const ERR_AUTH      = 3;
  const ERR_ARG_COUNT = 4;
  const ERR_REPLY     = 5;
  
  const CMD_INLINE    = 1;
  const CMD_BULK      = 2;
  const CMD_MULTIBULK = 3;
  
  const REP_FLOAT     = 1;
  const REP_BOOL      = 2;
  const REP_ARRAY     = 3;
  const REP_ASSOC     = 4;
  const REP_QUEUED    = '+QUEUED';
  
  public  $debug = false;
  private $sock;
  
  private static $commands = array(
    # connection
    'auth'         => array(self::CMD_INLINE),
    
    # multi/exec (untested)
    'multi'        => array(self::CMD_INLINE),
    'exec'         => array(self::CMD_INLINE),
    'discard'      => array(self::CMD_INLINE),
    
    # generics
    'exists'       => array(self::CMD_INLINE,    self::REP_BOOL),
    'del'          => array(self::CMD_INLINE),
    'type'         => array(self::CMD_INLINE),
    'keys'         => array(self::CMD_INLINE),
    'randomkey'    => array(self::CMD_INLINE),
    'rename'       => array(self::CMD_INLINE),
    'renamenx'     => array(self::CMD_INLINE,    self::REP_BOOL),
    'dbsize'       => array(self::CMD_INLINE),
    'expire'       => array(self::CMD_INLINE,    self::REP_BOOL),
    'expireat'     => array(self::CMD_INLINE,    self::REP_BOOL),
    'ttl'          => array(self::CMD_INLINE),
    'select'       => array(self::CMD_INLINE),
    'move'         => array(self::CMD_INLINE,    self::REP_BOOL),
    'flushdb'      => array(self::CMD_INLINE),
    'flushall'     => array(self::CMD_INLINE),
    
    # strings
    'set'          => array(self::CMD_BULK),
    'get'          => array(self::CMD_INLINE),
    'getset'       => array(self::CMD_BULK),
    'setnx'        => array(self::CMD_BULK,      self::REP_BOOL),
    'mget'         => array(self::CMD_INLINE),
    'mset'         => array(self::CMD_MULTIBULK),
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
    'ltrim'        => array(self::CMD_INLINE),
    'lindex'       => array(self::CMD_INLINE),
    'lset'         => array(self::CMD_BULK),
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
    'zadd'                     => array(self::CMD_BULK,   self::REP_BOOL),
    'zrem'                     => array(self::CMD_BULK,   self::REP_BOOL),
    'zincrby'                  => array(self::CMD_BULK,   self::REP_FLOAT),
    'zrange'                   => array(self::CMD_INLINE, self::REP_ARRAY),
    'zrevrange'                => array(self::CMD_INLINE, self::REP_ARRAY),
    'zrangebyscore'            => array(self::CMD_INLINE, self::REP_ARRAY),
    'zrange_withscores'        => array(self::CMD_INLINE, self::REP_ASSOC, 'zrange',        'withscores'),
    'zrevrange_withscores'     => array(self::CMD_INLINE, self::REP_ASSOC, 'zrevrange',     'withscores'),
    'zrangebyscore_withscores' => array(self::CMD_INLINE, self::REP_ASSOC, 'zrangebyscore', 'withscores'),
    'zcard'                    => array(self::CMD_INLINE),
    'zscore'                   => array(self::CMD_BULK,   self::REP_FLOAT),
    'zremrangebyscore'         => array(self::CMD_INLINE),
    'zremrangebyrank'          => array(self::CMD_INLINE),
    'zrank'                    => array(self::CMD_BULK),
    'zrevrank'                 => array(self::CMD_BULK),
    'zcount'                   => array(self::CMD_INLINE),
#    'zunion'                   => array(self::CMD_INLINE),
#    'zinter'                   => array(self::CMD_INLINE),
    
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
    'save'         => array(self::CMD_INLINE),
    'bgsave'       => array(self::CMD_INLINE),
    'bgrewriteaof' => array(self::CMD_INLINE),
    'lastsave'     => array(self::CMD_INLINE),
    
    # pub/sub
    'subscribe'    => array(self::CMD_INLINE),
    'unsubscribe'  => array(self::CMD_INLINE),
    'publish'      => array(self::CMD_BULK),
    
    # server
    'config'       => array(self::CMD_BULK),
    'ping'         => array(self::CMD_INLINE),
    'shutdown'     => array(self::CMD_INLINE),
    'info'         => array(self::CMD_INLINE),
    'slaveof'      => array(self::CMD_INLINE),
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
    $host = isset($this->config['host']) ? $this->config['host'] : 'localhost';
    $port = isset($this->config['port']) ? $this->config['port'] : '6379';
    
    if (($this->sock = fsockopen($host, $port, $errno, $errstr)) === false)
    {
      throw new Exception("Unable to connect to Redis server on $host:$port ".
        "($errno $errstr).", self::ERR_CONNECT);
    }
    
    if (isset($this->config['password'])
      and $this->auth($password) !== 'OK')
    {
      throw new Exception("Unable to auth on Redis server: wrong password?",
        self::ERR_AUTH);
    }
    
    if (isset($this->config['db'])) {
      $this->select($this->config['db']);
    }
  }
  
  function quit()
  {
    if ($this->sock)
    {
      fclose($this->sock);
      $this->sock = null;
    }
  }
  
  function pipeline($closure)
  {
    $pipe = new Pipeline($this);
    $closure($pipe);
    return $pipe->execute();
  }
  
  # PUB/SUB: reads from the socket until there is a message.
  function listen() {
    return $this->read_raw_reply();
  }
  
  # :nodoc:
  static function lookup_command($name)
  {
    $cmd = isset(self::$commands[$name]) ?
      self::$commands[$name] : array(self::CMD_MULTIBULK);
    
    return array(
      'name'    => isset($cmd[2]) ? $cmd[2] : $name,
      'type'    => $cmd[0],
      'reply'   => isset($cmd[1]) ? $cmd[1] : null,
      'options' => isset($cmd[3]) ? $cmd[3] : '',
    );
  }
  
  private function format_command($cmd, $args)
  {
    if (isset($args[0]) and is_array($args[0])) {
      $args = $args[0];
    }
    
    switch($cmd['type'])
    {
      case self::CMD_INLINE:
        return $this->format_inline_command($cmd['name'], $args).' '.$cmd['options'];
      
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
    $cmd .= sprintf("%lu\r\n", mb_strlen($bulk_data, '8bit'));
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
  
  # :nodoc:
  function send_command($commands)
  {
    $cmd_str = array();
    $cmd     = array();
    foreach($commands as $i => $c)
    {
      list($name, $args) = $c;
      $cmd[$i]   = $this->lookup_command($name);
      $cmd_str[] = $this->format_command($cmd[$i], $args);
    }
    
    $rs = $this->send_raw_command($cmd_str);
    
    $c = current($cmd);
    foreach($rs as $i => $r)
    {
      switch($c['reply'])
      {
        case self::REP_BOOL:  $rs[$i] = (bool)$r;  break;
        case self::REP_FLOAT: $rs[$i] = (float)$r; break;
        case self::REP_ARRAY: $rs[$i] = ($r !== null) ? $r : array(); break;
        
        case self::REP_ASSOC:
          $ary = array();
          for ($j=0; $j<count($r); $j+=2) {
            $ary[$r[$j]] = $r[$j+1];
          }
          $rs[$i] = $ary;
        break;
      }
      $c = next($cmd);
    }
    
    return (count($commands) == 1) ? $rs[0] : $rs;
  }
  
  # Sends a command (string) or a list of commands (array of strings).
  # 
  # Of course replies will be raw, which means that the following commands,
  # for instance, will not return the same data:
  # 
  #   $r->hgetall('hash_key');
  #   # => array('field1' => 'value1', 'field2' => 'value2')
  #   
  #   $r->send_raw_command('HGETALL hash_key');
  #   # => array('field1', 'value1', 'field2', 'value2')
  function send_raw_command($commands)
  {
    if ($this->sock === null) $this->connect();
    
    $cmd_str = is_array($commands) ? implode("\r\n", $commands) : $commands;
    
    if ($this->debug) echo "\n> \"$cmd_str\"\n";
    
    if (!fwrite($this->sock, "$cmd_str\r\n")) {
      throw new Exception("Cannot write to server socket.", Client::ERR_SOCKET);
    }
    
    if (is_array($commands))
    {
      $rs = array();
      $i  = count($commands);
      while($i--) {
        $rs[] = $this->read_raw_reply();
      }
    }
    else {
      $rs = $this->read_raw_reply();
    }
    return $rs;
  }
  
  private function read_raw_reply()
  {
    switch(fgetc($this->sock))
    {
      case '+': return $this->read_single_line_reply('+');
      case ':': return (int)$this->read_single_line_reply(':');
      case '$': return $this->read_bulk_reply();
      case '*': return $this->read_multibulk_reply();
      case '-': throw new Exception($this->read_single_line_reply('-'), self::ERR_REPLY);
    }
  }
  
  private function read_single_line_reply($c)
  {
    $line = rtrim(fgets($this->sock), "\r\n");
    if ($this->debug) echo "< \"$c$line\"\n";
    return $line;
  }
  
  # Gets the bulk response (and discards the last CRLF).
  private function read_bulk_reply()
  {
    $len = (int)fgets($this->sock);
    if ($len == -1)
    {
      if ($this->debug) echo "< \$NULL\n";
      return null;
    }
    
    $rs  = '';
    while(strlen($rs) < $len) {
      $rs .= fread($this->sock, $len);
    }
    fread($this->sock, 2);
    
    if ($this->debug) echo "< \"\$$rs\"\n";
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
