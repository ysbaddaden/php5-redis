<?php
# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.

namespace Redis;

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
class Client
{
  const ERR_CONNECT   = 1;
  const ERR_SOCKET    = 2;
  const ERR_AUTH      = 3;
  const ERR_ARG_COUNT = 4;
  const ERR_REPLY     = 5;
  
  const REP_FLOAT     = 1;
  const REP_BOOL      = 2;
  const REP_ARRAY     = 3;
  const REP_ASSOC     = 4;
  const REP_QUEUED    = '+QUEUED';
  
  public  $debug = false;
  private $sock;
  
  private static $commands = array(
    # connection
#    'select'       => array(),
#    'auth'         => array(),
    
    # multi/exec (untested)
#    'multi'        => array(),
#    'exec'         => array(),
#    'discard'      => array(),
    
    # generics
    'exists'       => array(self::REP_BOOL),
#    'del'          => array(),
#    'type'         => array(),
#    'keys'         => array(),
#    'randomkey'    => array(),
#    'rename'       => array(),
    'renamenx'     => array(self::REP_BOOL),
#    'dbsize'       => array(),
    'expire'       => array(self::REP_BOOL),
    'expireat'     => array(self::REP_BOOL),
#    'ttl'          => array(),
#    'select'       => array(),
    'move'         => array(self::REP_BOOL),
#    'flushdb'      => array(),
#    'flushall'     => array(),
    
    # strings
#    'set'          => array(),
#    'get'          => array(),
#    'getset'       => array(),
    'setnx'        => array(self::REP_BOOL),
#    'mget'         => array(),
#    'mset'         => array(),
    'msetnx'       => array(self::REP_BOOL),
#    'incr'         => array(),
#    'incrby'       => array(),
#    'decr'         => array(),
#    'decrby'       => array(),
    
    # lists
    'lpush'        => array(self::REP_BOOL),
    'rpush'        => array(self::REP_BOOL),
#    'llen'         => array(),
#    'lrange'       => array(),
#    'ltrim'        => array(),
#    'lindex'       => array(),
#    'lset'         => array(),
#    'lrem'         => array(),
#    'lpop'         => array(),
#    'rpop'         => array(),
#    'rpoplpush'    => array(),
#    'blpop'        => array(),
#    'brpop'        => array(),
    
    # sets
    'sadd'         => array(self::REP_BOOL),
    'srem'         => array(self::REP_BOOL),
#    'spop'         => array(),
    'smove'        => array(self::REP_BOOL),
#    'scard'        => array(),
    'sismember'    => array(self::REP_BOOL),
#    'sinter'       => array(),
#    'sinterstore'  => array(),
#    'sunion'       => array(),
#    'sunionstore'  => array(),
#    'sdiff'        => array(),
#    'sdiffstore'   => array(),
    'smembers'     => array(self::REP_ARRAY),
#    'srandmember'  => array(),
    
    # zsets (sorted sets)
    'zadd'                     => array(self::REP_BOOL),
    'zrem'                     => array(self::REP_BOOL),
    'zincrby'                  => array(self::REP_FLOAT),
    'zrange'                   => array(self::REP_ARRAY),
    'zrevrange'                => array(self::REP_ARRAY),
    'zrangebyscore'            => array(self::REP_ARRAY),
    'zrange_withscores'        => array(self::REP_ASSOC, 'zrange',        'withscores'),
    'zrevrange_withscores'     => array(self::REP_ASSOC, 'zrevrange',     'withscores'),
    'zrangebyscore_withscores' => array(self::REP_ASSOC, 'zrangebyscore', 'withscores'),
#    'zcard'                    => array(),
    'zscore'                   => array(self::REP_FLOAT),
#    'zremrangebyscore'         => array(),
#    'zremrangebyrank'          => array(),
#    'zrank'                    => array(),
#    'zrevrank'                 => array(),
#    'zcount'                   => array(),
##    'zunion'                   => array(),
##    'zinter'                   => array(),
    
    # sorting
#    'sort'         => array(),
    
    # hashes
    'hset'         => array(self::REP_BOOL),
#    'hmset'        => array(),
#    'hget'         => array(),
    'hdel'         => array(self::REP_BOOL),
#    'hlen'         => array(),
#    'hkeys'        => array(),
#    'hvals'        => array(),
    'hgetall'      => array(self::REP_ASSOC),
    'hexists'      => array(self::REP_BOOL),
#    'hincrby'      => array(),
    
    # persistence
#    'save'         => array(),
#    'bgsave'       => array(),
#    'bgrewriteaof' => array(),
#    'lastsave'     => array(),
    
    # pub/sub
#    'subscribe'    => array(),
#    'unsubscribe'  => array(),
#    'publish'      => array(),
    
    # server
#    'config'       => array(),
#    'ping'         => array(),
#    'shutdown'     => array(),
#    'info'         => array(),
#    'slaveof'      => array(),
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
  
  function hmset($key, $hash)
  {
    $args = array($key);
    foreach($hash as $k => $v)
    {
      $args[] = $k;
      $args[] = $v;
    }
    return $this->send_command(array(array('hmset', $args)));
  }
  
  # Sorts a list, set or sorted set.
  # 
  # Returns an array of integers/strings if getting only one field. Returns
  # an array of arrays (of integers/strings) when getting multiple fields.
  # 
  # Options:
  # 
  # - +by+     - sort by other key
  # - +order+  - either +asc+ (default) or +desc+
  # - +limit+
  # - +offset+
  # - +get+    - an array of keys
  # - +store+  - store result in an external key
  function sort($key, $options=array())
  {
    $args = array($key);
    
    if (isset($options['by'])) {
      $args[] = 'by'; $args[] = $options['by'];
    }
    
    if (isset($options['offset']) and !isset($options['limit'])) {
      $options['limit'] = 0;
    }
    if (isset($options['limit']))
    {
      $args[] = 'limit';
      $args[] = isset($options['offset']) ? $options['offset'] : 0;
      $args[] = $options['limit'];
    }
    
    if (isset($options['alpha']) and $options['alpha']) {
      $args[] = 'alpha';
    }
    
    if (isset($options['order'])) {
      $args[] = $options['order'];
    }
    
    if (isset($options['get']))
    {
      foreach($options['get'] as $v) {
        $args[] = 'get'; $args[] = $v;
      }
    }
    
    if (isset($options['store'])) {
      $args[] = 'store'; $args[] = $options['store'];
    }
    
    $rs = $this->send_command(array(array('sort', $args)));
    
    if (isset($options['get'])
      and count($options['get']) > 1)
    {
      $ary = array();
      for($i=0; $i<count($rs); $i+=$j)
      {
        $a = array();
        for($j=0; $j<count($options['get']); $j++) {
          $a[] = $rs[$i+$j];
        }
        $ary[] = $a;
      }
      return $ary;
    }
    
    return $rs;
  }
  
  # :nodoc:
  static function lookup_command($name)
  {
    $cmd = isset(self::$commands[$name]) ?
      self::$commands[$name] : array();
    
    return array(
      'name'    => isset($cmd[1]) ? $cmd[1] : $name,
      'reply'   => isset($cmd[0]) ? $cmd[0] : null,
      'options' => isset($cmd[2]) ? $cmd[2] : '',
    );
  }
  
  private function format_command($cmd, $args)
  {
    if (isset($args[0]) and is_array($args[0])) {
      $args = $args[0];
    }
    
    if ($cmd['name'] == 'mset'
      or $cmd['name'] == "msetnx")
    {
      $_args = array();
      foreach($args as $k => $v)
      {
        $_args[] = $k;
        $_args[] = $v;
      }
      $args = $_args;
    }
    
    if ($cmd['name'] !== null) {
      array_unshift($args, $cmd['name']);
    }
    
    if (!empty($cmd['options'])) {
      $args[] = $cmd['options'];
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
      if ($this->debug) echo "< NULL\n";
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
      switch(fgetc($this->sock))
      {
        case '$': $ary[] = $this->read_bulk_reply();                break;
        case ':': $ary[] = (int)$this->read_single_line_reply(':'); break;
        case '+': $ary[] = $this->read_single_line_reply('+');      break;
      }
    }
    return $ary;
  }
}

?>
