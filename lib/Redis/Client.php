<?php
# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.
# 
# Heavily inspired by the redis-rb API.

namespace Redis;

class ProtocolError
{
  function __construct($reply_type) {
    parent::__construct("Protocol error: got '$reply_type' as initial reply byte");
  }
}

class Client
{
  const ERR_CONNECT   = 1;
  const ERR_SOCKET    = 2;
  const ERR_REPLY     = 3;
  const ERR_TIMEOUT   = 4;
  
  private $db;
  private $host;
  private $port;
  private $password;
#  private $timeout;
  
  private $sock;
  
  function __construct($options=array())
  {
    $this->host     = isset($options['host'])     ? $options['host']         : 'localhost';
    $this->port     = isset($options['port'])     ? (int)$options['port']    : 6379;
    $this->db       = isset($options['db'])       ? (int)$options['db']      : 0;
#    $this->timeout  = isset($options['timeout'])  ? (float)$options['timeout'] : 5;
    $this->password = isset($options['password']) ? $options['password']     : null;
  }
  
  function __destruct() {
    $this->disconnect();
  }
  
  function id() {
    return "redis://{$this->host}:{$this->port}/{$this->db}";
  }
  
  function connect()
  {
    if (($this->sock = fsockopen($this->host, $this->port, $errno, $errstr)) === false)
    {
      throw new Exception("Unable to connect to Redis server on ".
        "{$this->host}:{$this->port} ($errno $errstr).", self::ERR_CONNECT);
    }
#    $this->timeout($this->timeout);
    
    if (isset($this->password)) {
      $this->call('auth', $this->password);
    } 
    if ($this->db) {
      $this->call('select', $this->db);
    }
  }
  
#  function timeout()
#  {
#    $secs  = (int)$this->timeout;
#    $usecs = (int)(($this->timeout - $secs) * 1000000);
#    stream_set_timeout($this->sock, $secs, $usecs);
#  }
  
  function disconnect()
  {
    if ($this->connected())
    {
      fclose($this->sock);
      unset($this->sock);
    }
  }
  
  function reconnect()
  {
    $this->disconnect();
    $this->connect();
  }
  
  function connected() {
    return !!$this->sock;
  }
  
  
  function call($command, $args=null)
  {
    if (is_array($args)) {
      array_unshift($args, $command);
    }
    else {
      $args = func_get_args();
    }
    $this->process($this->build_command($args));
    return $this->read();
  }
  
  function call_pipelined($commands)
  {
    $rs  = array();
    
    if (!empty($commands))
    {
      $this->process($this->join_commands($commands));
      
      $len = count($commands);
      for($i=0; $i<$len; $i++) {
        $rs[] = $this->read();
      }
    }
    return $rs;
  }
  
  function process($command)
  {
    if (!$this->connected()) $this->connect();
    if (!fwrite($this->sock, "$command\r\n")) {
      throw new Exception("can't write to server socket", Client::ERR_SOCKET);
    }
  }
  
  function read()
  {
    if (($reply_type = fgetc($this->sock)) === false)
    {
      $this->disconnect();
      
#      $info = stream_get_meta_data($this->sock);
#      if ($info['timed_out']) {
#        throw new Exception('timeout while reading from socket', self::ERR_TIMEOUT);
#      }
      throw new Exception("connection lost", self::ERR_SOCKET);
    }
    
    switch($reply_type)
    {
      case '+': return $this->read_single_line_reply('+');
      case ':': return (int)$this->read_single_line_reply(':');
      case '$': return $this->read_bulk_reply();
      case '*': return $this->read_multibulk_reply();
      case '-': throw new Exception($this->read_single_line_reply('-'), self::ERR_REPLY);
      default: throw new ProtocolError($reply_type);
    }
  }
  
  # Transforms a {key => value} hash into a [key, value] array.
  static function hash_to_array($hash)
  {
    $ary = array();
    foreach($hash as $key => $value)
    {
      $ary[] = $key;
      $ary[] = $value;
    }
    return $ary;
  }
  
  # Transforms a [key, value] array into a {key => value} hash.
  static function array_to_hash($ary)
  {
    $hash = array();
    $len  = count($ary);
    for($i=0; $i<$len; $i++) {
      $hash[$ary[$i]] = $ary[++$i];
    }
    return $hash;
  }
  
  private function build_command($args)
  {
    $command = '*'.count($args);
    foreach($args as $arg) {
      $command .= "\r\n$".strlen($arg)."\r\n".$arg;
    }
    return $command;
  }
  
  private function join_commands($commands)
  {
    foreach($commands as $i => $args) {
      $commands[$i] = $this->build_command($args);
    }
    return implode("\r\n", $commands);
  }
  
  private function read_single_line_reply($c) {
    return rtrim(fgets($this->sock), "\r\n");
  }
  
  private function read_bulk_reply()
  {
    $len = (int)fgets($this->sock);
    if ($len == -1) {
      return null;
    }
    
    $rs  = '';
    while(strlen($rs) < $len) {
      $rs .= fread($this->sock, $len);
    }
    fread($this->sock, 2);
    
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
