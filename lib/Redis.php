<?php
# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.
# 
# Heavily inspired by the redis-rb API.

class Redis
{
  protected $client;
  
  function __construct($options) {
    $this->client = new Redis\Client($options);
  }
  
  # Closes the connection.
  function quit()
  {
    if (isset($this->sock))
    {
      fclose($this->sock);
      unset($this->sock);
    }
  }
  
  # Returns true if a key exists.
  function exists($key) {
    return (bool)$this->client->call('EXISTS', $key);
  }
  
  # Deletes one or many keys.
  # 
  #   del('key1');
  #   del('key1', 'key2', ..., 'keyn');
  #   del(array('key1', 'key2', ..., 'keyn'));
  # 
  # Returns how many keys where actually deleted (0 means none existed).
  function del($key)
  {
    $args = is_array($key) ? $key : func_get_args();
    return $this->client->call('DEL', $args);
  }
  
  # Returns the type of the value stored at key.
  function type($key) {
    return $this->client->call('TYPE', $key);
  }
  
  # Returns all the keys matching a given pattern.
  # 
  # - +h?llo+ will match +hello+ +hallo+ +hhllo+
  # - +h*llo+ will match +hllo+ +heeeello+
  # - +h[ae]llo+ will match +hello+ and +hallo+, but not +hillo+
  # 
  function keys($pattern) {
    return $this->client->call('KEYS', $pattern);
  }
  
  # Returns a random key from the key space.
  function randomkey() {
    return $this->client->call('RANDOMKEY', $pattern);
  }
  
  # Renames the old key in the new one, destroing the newname key if it already exists.
  function rename($oldname, $newname) {
    $this->client->call('RENAME', $oldname, $newname);
  }
  
  # Renames the old key in the new one, if the newname key does not already exist.
  # Returns true if the key has been renamed, false otherwise.
  function renamenx($oldname, $newname) {
    return (bool)$this->client->call('RENAMENX', $oldname, $newname);
  }
  
  # Returns the number of keys in the currently selected database.
  function dbsize() {
    return $this->client->call('DBSIZE');
  }
  
  # Sets a timeout on the specified key.
  function expire($key, $seconds) {
    return (bool)$this->client->call('EXPIRE', $key, $seconds);
  }
  
  # Sets a timeout on the specified key.
  function expireat($key, $timestamp) {
    return (bool)$this->client->call('EXPIRE', $key, $timestamp);
  }
  
  # Returns the time-to-live in seconds of a key.
  function ttl($key) {
    return (bool)$this->client->call('TTL', $key, $timestamp);
  }
  
  # Selects a database.
  function select($index) {
    $this->client->call('SELECT', $index);
  }
  
  # Moves a key from the currently selected database to another one.
  # Returns false if the key does not exists in the currently selected database,
  # or if the key already exists in the destination database.
  function move($key, $dbindex) {   
    return (bool)$this->client->call('MOVE', $key, $dbindex);
  }
  
  # Removes all the keys from the currently selected database.
  function flushdb() {
    $this->client->call('FLUSHDB');
  }
  
  # Removes all the keys from all the databases.
  function flushall() {
    $this->client->call('FLUSHALL');
  }
  
  
  # Returns the string value of a key.
  function get($key) {
    return $this->client->call('GET', $key);
  }
  
  # Sets the string value of a key, returning the old value of the key.
  function getset($key, $value) {
    return $this->client->call('GETSET', $key, $value);
  }
  
  # Sets the string value of a key.
  function set($key, $value)
  {
    $this->client->call('SET', $key, $value);
    return $value;
  }
  
  # Sets the string value of a key if the key doesn't exist.
  function setnx($key, $value) {
    return (bool)$this->client->call('SETNX', $key, $value);
  }
  
  # Sets the string value of a key, plus its expiration.
  function setex($key, $value, $seconds)
  {
    $this->client->call('SETEX', $key, $value, $seconds);
    return $value;
  }
  
  # Returns the string value of multiple keys.
  # 
  #   $values = mget('key1', 'key2', ..., 'keyn');
  #   $values = mget(array('key1', 'key2', ..., 'keyn'));
  # 
  function mget($key)
  {
    $keys = is_array($key) ? $key : func_get_args();
    return $this->client->call('MGET', $keys);
  }
  
  # Sets the string value of multiple keys.
  # 
  #   mset(array('key1' => 'value1', 'key2' => 'value2'));
  function mset($keys)
  {
    $args = Redis\Client::hash_to_array($keys);
    $this->client->call('MSET', $args);
  }
  
  # Sets the string value of multiple keys.
  # 
  #   mset(array('key1' => 'value1', 'key2' => 'value2'));
  function msetnx($keys)
  {
    $args = Redis\Client::hash_to_array($keys);
    return (bool)$this->client->call('MSETNX', $args);
  }
  
  # Increments the integer value of key. If the key doesn't exist it will
  # be set to 0 before incrementing.
  # 
  # Returns the new value of the key.
  function incr($key, $increment=1)
  {
    return ($increment == 1) ?
      $this->client->call('INCR', $key) :
      $this->client->call('INCRBY', $key, $increment);
  }

  function incrby($key, $increment) {
    return $this->client->call('INCRBY', $key, $increment);
  }
  
  # Decrements the integer value of key. If the key doesn't exist it will
  # be set to 0 before decrementing.
  # 
  # Returns the new value of the key.
  function decr($key, $increment=1)
  {
    return ($increment == 1) ?
      $this->client->call('DECR', $key) :
      $this->client->call('DECRBY', $key, $increment);
  }

  function decrby($key, $increment) {
    return $this->client->call('DECRBY', $key, $increment);
  }
  
  # Append a string to the string stored at key.
  # Returns the new length of string.
  function append($key, $value) {
    return $this->client->call('APPEND', $key, $value);
  }
  
  # Extracts a substring out of a larger string.
  function substr($key, $start, $end) {
    return $this->client->call('SUBSTR', $key, $start, $end);
  }
  
  
  # Appends an element to the tail of the list stored at key.
  function rpush($key, $value) {
    return (bool)$this->client->call('RPUSH', $key, $value);
  }
  
  # Appends an element to the head of the list stored at key.
  function lpush($key, $value) {
    return (bool)$this->client->call('LPUSH', $key, $value);
  }
  
  # Returns the length of a list.
  function llen($key) {
    return $this->client->call('LLEN', $key);
  }
  
  # Returns a range of elements from a list.
  function lrange($key, $start, $end) {
    return $this->client->call('LRANGE', $key, $start, $end);
  }
  
  # Trims a list to the specified range of elements.
  function ltrim($key, $start, $end) {
    $this->client->call('LTRIM', $key, $start, $end);
  }
  
  # Returns the element at index position from a list.
  function lindex($key, $index) {
    return $this->client->call('LINDEX', $key, $index);
  }
  
  # Sets a new value to the element at index position of a list.
  function lset($key, $index, $value) {
    $this->client->call('LSET', $key, $index, $value);
  }
  
  # Removes the first-N, last-N, or all the elements matching value from a list.
  # Returns the number of removed elements.
  function lrem($key, $count, $value) {
    return $this->client->call('LREM', $key, $count, $value);
  }
  
  # Returns and removes (atomically) the last element of a list.
  function rpop($key) {
    return $this->client->call('RPOP', $key);
  }
  
  # Returns and removes (atomically) the first element of a list.
  function lpop($key) {
    return $this->client->call('LPOP', $key);
  }
  
  # Blocking <tt>rpop</tt>.
  # 
  #   $value = brpop('key', 2);
  #   $value = brpop('key1', 'key2', ... 'keyn', 5);
  #   $value = brpop(array('key1', 'key2', ... 'keyn'), 1);
  # 
  function brpop($key, $timeout)
  {
    if (is_array($key))
    {
      $args = $key;
      array_push($args, $timeout);
    }
    else {
      $args = func_get_args();
    }
    return $this->client->call('BRPOP', $args);
  }
  
  # Blocking <tt>lpop</tt>. See <tt>brpop</tt> for syntax.
  function blpop($key, $timeout)
  {
    if (is_array($key))
    {
      $args = $key;
      array_push($args, $timeout);
    }
    else {
      $args = func_get_args();
    }
    return $this->client->call('BLPOP', $args);
  }
  
  # Returns and removes (atomically) the last element of a source list and
  # pushes the same element to a destination list.
  function rpoplpush($srckey, $dstkey) {
    return $this->client->call('RPOPLPUSH', $srckey, $dstkey);
  }
  
#  # Returns and removes (atomically) the first element of a source list and
#  # pushes the same element to a destination list.
#  function lpoprpush($srckey, $dstkey) {
#    return $this->client->call(('LPOPRPUSH', $srckey, $dstkey);
#  }
  
  # Sorts a list, set or sorted set.
  # 
  # Returns an array of integers/strings if getting only one field. Returns
  # an array of arrays (of integers/strings) when getting multiple fields.
  # 
  # Options:
  # 
  # - +by+     - sort by other key
  # - +order+  - either +asc+ (default) or +desc+
  # - +limit+  - 
  # - +offset+ - 
  # - +get+    - an array of keys
  # - +store+  - store result in an external key
  # 
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
    
    $rs = $this->client->call('SORT', $args);
    
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
  
  
  # Adds a member to a set.
  function sadd($key, $member) {
    return (bool)$this->client->call('SADD', $key, $member);
  }
  
  # Removes a member from a set.
  function srem($key, $member) {
    return (bool)$this->client->call('SREM', $key, $member);
  }
  
  # Removes and returns a random element from a set.
  function spop($key) {
    return $this->client->call('SPOP', $key);
  }
  
  # Moves a member from one set to another atomically.
  function smove($srckey, $dstkey, $member) {
    return (bool)$this->client->call('SMOVE', $srckey, $dstkey, $member);
  }
  
  # Returns the number of elements (the cardinality) of a set.
  function scard($key) {
    return $this->client->call('SCARD', $key);
  }
  
  # Tests if the specified value is a member of a set.
  function sismember($key, $member) {
    return (bool)$this->client->call('SISMEMBER', $key, $member);
  }
  
  # Returns the intersection between the sets stored at key1, key2, ..., keyN.
  # 
  #   sinter('key1', 'key2', ..., 'keyN');
  #   sinter(array('key1', 'key2', ..., 'keyN'));
  # 
  function sinter($keys)
  {
    $args = is_array($keys) ? $keys : func_get_args();
    return $this->client->call('SINTER', $args);
  }
  
  # Computes the intersection between the sets stored at key1, key2, ..., keyN,
  # and stores the resulting set at dstkey.
  function sinterstore($dstkey, $key)
  {
    if (is_array($key))
    {
      $args = $key;
      array_unshift($args, $dstkey);
    }
    else {
      $args = func_get_args();
    }
    return $this->client->call('SINTERSTORE', $args);
  }
  
  # Returns the union between the sets stored at key1, key2, ..., keyN.
  function sunion($keys)
  {
    $args = is_array($keys) ? $keys : func_get_args();
    return $this->client->call('SUNION', $args);
  }
  
  # Computes the union between the sets stored at key1, key2, ..., keyN,
  # and stores the resulting set at dstkey.
  function sunionstore($dstkey, $keys)
  {
    if (is_array($keys))
    {
      $args = $keys;
      array_unshift($args, $dstkey);
    }
    else {
      $args = func_get_args();
    }
    return $this->client->call('SUNIONSTORE', $args);
  }
  
  # Returns the difference between the sets stored at key1, key2, ..., keyN.
  function sdiff($keys)
  {
    $args = is_array($keys) ? $keys : func_get_args();
    return $this->client->call('SDIFF', $args);
  }
  
  # Computes the difference between the sets stored at key1, key2, ..., keyN,
  # and stores the resulting set at dstkey.
  function sdiffstore($dstkey, $keys)
  {
    if (is_array($keys))
    {
      $args = $keys;
      array_unshift($args, $dstkey);
    }
    else {
      $args = func_get_args();
    }
    return $this->client->call('SDIFFSTORE', $args);
  }
  
  # Returns all the members of a set.
  function smembers($key) {
    return $this->client->call('SMEMBERS', $key);
  }
  
  # Returns a random member of a set.
  function srandmember($key) {
    $this->client->call('SRANDMEMBER', $key);
  }
  
  
  # Adds a member to a sorted set, or updates the score if it already exists.
  function zadd($key, $member, $score) {
    return (bool)$this->client->call('ZADD', $key, $member, $score);
  }
  
  # Removes a member from a sorted set.
  function zrem($key, $member) {
    return (bool)$this->client->call('ZREM', $key, $member);
  }
  
  function zincrby($key, $member, $increment) {
    return (float)$this->client->call('ZINCRBY', $key, $member, $increment);
  }
  
  # If the member already exists increments its score, otherwise adds the member
  # setting the score at 0 before incrementing.
  function zincr($key, $member, $increment=1) {
    return $this->zincrby($key, $member, $increment);
  }
  
  # If the member already exists decrements its score, otherwise adds the member
  # setting the score at 0 before decrementing.
  function zdecr($key, $member, $increment=1) {
    return $this->zincrby($key, $member, -$increment);
  }
  
  # Return the rank (or index) of a member in a sorted set, with scores being
  # ordered from low to high.
  function zrank($key, $member) {
    return $this->client->call('ZRANK', $key, $member);
  }
  
  # Return the rank (or index) of a member in a sorted set, with scores being
  # ordered from high to low.
  function zrevrank($key, $member) {
    return $this->client->call('ZREVRANK', $key, $member);
  }
  
  # Returns a range of elements from a sorted set.
  function zrange($key, $start, $end) {
    return $this->client->call('ZRANGE', $key, $start, $end);
  }
  
  # Returns a range of elements + scores from a sorted set.
  function zrange_with_scores($key, $start, $end)
  {
    $rs = $this->client->call('ZRANGE', $key, $start, $end, 'WITHSCORES');
    return Redis\Client::array_to_hash($rs);
  }
  
  # Returns a range of elements from a sorted set (in reverse order).
  function zrevrange($key, $start, $end) {
    return $this->client->call('ZREVRANGE', $key, $start, $end);
  }
  
  # Returns a range of elements + scores from a sorted set (in reverse order).
  function zrevrange_with_scores($key, $start, $end)
  {
    $rs = $this->client->call('ZREVRANGE', $key, $start, $end, 'WITHSCORES');
    return Redis\Client::array_to_hash($rs);
  }
  
  # Returns a range of elements from a sorted set.
  function zrangebyscore($key, $min, $max) {
    return $this->client->call('ZRANGEBYSCORE', $key, $min, $max);
  }
  
  # Returns a range of elements + scores from a sorted set.
  function zrangebyscore_with_scores($key, $min, $max)
  {
    $rs = $this->client->call('ZRANGEBYSCORE', $key, $min, $max, 'WITHSCORES');
    return Redis\Client::array_to_hash($rs);
  }
  
  # Returns the number of elements (cardinality) of a sorted set.
  function zcard($key) {
    return $this->client->call('ZCARD', $key);
  }
  
  # Returns the score of a member of a sorted set.
  function zscore($key, $member) {
    return (float)$this->client->call('ZSCORE', $key, $member);
  }
  
  # Removes all the elements with rank >= min and rank <= max from a sorted set.
  function zremrangebyrank($key, $min, $max) {
    return $this->client->call('ZREMRANGEBYRANK', $key, $min, $max);
  }
  
  # Removes all the elements with score >= min and score <= max from a sorted set.
  function zremrangebyscore($key, $min, $max) {
    return $this->client->call('ZREMRANGEBYSCORE', $key, $min, $max);
  }
  
  # Removes all the elements with score >= min and score <= max from a sorted set.
  function zcount($key, $start, $end) {
    return $this->client->call('ZCOUNT', $key, $start, $end);
  }
  
  # Performs a union over a number of sorted sets with optional aggregate.
  # 
  #   $keys = array('key1', 'key2', ... 'keyN');
  #   zunionstore('dstkey', $keys);
  #   zunionstore('dstkey', $keys, 'SUM');
  # 
  # +$aggregate+ may be any of +SUM+, +MIN+ or +MAX+.
  # 
  function zunionstore($dstkey, $keys, $aggregate=null) {
    return $this->_zstore('ZUNIONSTORE', $dstkey, $keys, null, $aggregate);
  }
  
  # Performs a union over a number of sorted sets with weight and optional
  # aggregate.
  # 
  #   $keys = array('key1' => 'weight1', 'key2' => 'weight2', ... 'keyN' => 'weightN');
  #   zunionstore('dstkey', $keys);
  #   zunionstore('dstkey', $keys, 'SUM');
  # 
  function zunionstore_with_weights($dstkey, $keys_with_weights, $aggregate=null) {
    return $this->_zstore_with_weights('ZUNIONSTORE', $dstkey, $keys_with_weights, $aggregate);
  }
  
  # Performs an intersection over a number of sorted sets with optional
  # aggregate. See <tt>zunionstore</tt> for syntax.
  function zinterstore($dstkey, $keys, $aggregate=null) {
    return $this->_zstore('ZINTERSTORE', $dstkey, $keys, null, $aggregate);
  }
  
  # Performs a intersaction over a number of sorted sets with weight and
  # optional aggregate. See <tt>zunionstore_with_weights</tt> for syntax.
  function zinterstore_with_weights($dstkey, $keys_with_weights, $aggregate=null) {
    return $this->_zstore_with_weights('ZINTERSTORE', $dstkey, $keys_with_weights, $aggregate);
  }
  
  private function _zstore_with_weights($command, $dstkey, $keys_with_weights, $aggregate)
  {
    $keys    = array();
    $weights = array();
    foreach($keys_with_weights as $key => $weight)
    {
      $keys[]    = $key;
      $weights[] = $weight;
    }
    return $this->_zstore($command, $dstkey, $keys, $weights, $aggregate);
  }
  
  private function _zstore($command, $dstkey, $keys, $weights=null, $aggregate=null)
  {
    $args = array($dstkey);
    
    # keys
    $args[] = $len = count($keys);
    for($i=0; $i<$len; $i++) {
      $args[] = $keys[$i];
    }
    
    # weigths
    if (!empty($weights))
    {
      $args[] = 'WEIGHTS';
      for($i=0; $i<$len; $i++) {
        $args[] = $weights[$i];
      }
    }
    
    # aggregate
    if ($aggregate !== null)
    {
      array_unshift($args, 'AGGREGATE');
      array_unshift($args, $aggregate);
    }
    return (bool)$this->client->call($command, $args);
  }
  
  
  # 
  function hset($key, $field, $value) {
    return (bool)$this->client->call('HSET', $key, $field, $value);
  }
  
  # 
  function hget($key, $field) {
    return $this->client->call('HGET', $key, $field);
  }
  
  # 
  function hmset($key, $hash)
  {
    $args = Redis\Client::hash_to_array($hash);
    array_unshift($args, $key);
    return $this->client->call('HMSET', $args);
  }
  
  #
  function hmget($key, $fields)
  {
    if (is_array($fields))
    {
      $args = $fields;
      array_unshift($args, $key);
    }
    else {
      $args = func_get_args();
    }
    return $this->client->call('HMGET', $args);
  }
  
  function hincrby($key, $field, $increment) {
    return $this->client->call('HINCRBY', $key, $field, $increment);
  }
  
  # 
  function hincr($key, $field, $increment=1) {
    return $this->hincrby($key, $field, $increment);
  }
  
  # 
  function hdecr($key, $field, $increment=1) {
    return $this->hincrby($key, $field, -$increment);
  }
  
  # 
  function hexists($key, $field) {
    return (bool)$this->client->call('HEXISTS', $key, $field);
  }
  
  # 
  function hdel($key, $field) {
    return (bool)$this->client->call('HDEL', $key, $field);
  }
  
  # 
  function hlen($key) {
    return $this->client->call('HLEN', $key);
  }
  
  # 
  function hkeys($key) {
    return $this->client->call('HKEYS', $key);
  }
  
  # 
  function hvals($key) {
    return $this->client->call('HVALS', $key);
  }
  
  # 
  function hgetall($key)
  {
    $rs = $this->client->call('HGETALL', $key);
    return Redis\Client::array_to_hash($rs);
  }
  
  
  # 
  function multi($closure=null)
  {
    $this->client->call('MULTI');
    
    if ($closure === null) return;
    
    try {
      $closure($this);
    }
    catch(Exception $e)
    {
      $this->discard();
      throw $e;
    }
    
    return $this->exec();
  }
  
  # 
  function exec() {
    return $this->client->call('EXEC');
  }
  
  # 
  function discard() {
    $this->client->call('DISCARD');
  }
  
  
  function pipelined($closure)
  {
    $original_client = $this->client;
    $this->client = $pipeline = new Redis\Pipeline($this->client);
    $closure($this);
    $this->client = $original_client;
    return $this->client->call_pipelined($pipeline->commands);
  }
  
  
  # 
  function subscribe($channel)
  {
    $channels = is_array($channel) ? $channel : func_get_args();
    return $this->client->call('SUBSCRIBE', $channels);
  }
  
  # 
  function unsubscribe($channel=null)
  {
    $channels = is_array($channel) ? $channel : func_get_args();
    return $this->client->call('UNSUBSCRIBE', $channels);
  }
  
  # 
  function psubscribe($pattern)
  {
    $patterns = is_array($pattern) ? $pattern : func_get_args();
    return $this->client->call('PSUBSCRIBE', $patterns);
  }
  
  # 
  function punsubscribe($pattern)
  {
    $patterns = is_array($pattern) ? $pattern : func_get_args();
    return $this->client->call('PUNSUBSCRIBE', $patterns);
  }
  
  # 
  function publish($channel, $message) {
    return (bool)$this->client->call('PUBLISH', $channel, $message);
  }
  
  
  # 
  function save() {
    $this->client->call('SAVE');
  }
  
  # 
  function bgsave() {
    $this->client->call('BGSAVE');
  }
  
  # 
  function lastsave() {
    return $this->client->call('LASTSAVE');
  }
  
  # 
  function shutdown() {
    $this->client->call('SHUTDOWN');
  }
  
  function bgrewriteaof() {
    $this->client->call('BGREWRITEAOF');
  }
  
  function info()
  {
    $rs = $this->client->call('INFO');
    return Redis\Client::array_to_hash(explode("\r\n", $rs));
  }
  
  function ping() {
    return ($this->client->call('PING') === 'PONG');
  }
  
#  function monitor() {
#    $this->client->call('MONITOR');
#  }
  
  # 
  function slaveof($host=null, $port=null)
  {
    ($host === null) ?
      $this->client->call('SLAVEOF', 'NO', 'ONE') :
      $this->client->call('SLAVEOF', $host, $port);
  }
  
  # 
  function config_get($pattern)
  {
    $rs = $this->client->call('CONFIG', 'GET', $pattern);
    return Redis\Client::array_to_hash($rs);
  }
  
  # 
  function config_set($param, $value) {
    $this->client->call('CONFIG', 'SET', $param, $value);
  }
  
  # Executes a closure atomically (ie. whenever a lock is acquired).
  # 
  #   $count = $redis->lock('incr_users', function($redis) {
  #     return $redis->incr('users');
  #   });
  # 
  # Please note that this example is stupid, since INCR is atomic already.
  function lock($name, $closure, $timeout=5)
  {
    if ($timeout < 1)
    {
      trigger_error("invalid timeout: under 1 second", E_USER_WARNING);
      $timeout = 1;
    }
    $lock_id = "lock:$name";
    
    $locked = $this->setnx($lock_id, gmmktime() + $timeout);
    while(!$locked)
    {
      if ($this->get($lock_id) < gmmktime()
        and $this->getset($lock_id, gmmktime() + $timeout) < gmmktime())
      {
        break;
      }
      sleep(1);
    }
    
    $rs = $closure($this);
    
    if ($this->get($lock_id) >= gmmktime()
      and $this->getset($lock_id, gmmktime() + $timeout + 1) >= gmmktime())
    {
      $this->del($lock_id);
    }
  }
  
#  function lock_unless_exists($key, $closure, $timeout=5)
#  {
#    if ($this->exists($key)) {
#      return true;
#    }
#    
#    if ($timeout < 1)
#    {
#      trigger_error("invalid timeout: under 1 second", E_USER_WARNING);
#      $timeout = 1;
#    }
#    $lock_id = "lock:$key";
#    $time    = gmmktime();
#    
#    if ($this->setnx($lock_id, $time + $timeout)
#      or ($this->get($lock_id) < $time
#        and $this->getset($lock_id, $time + $timeout) < $time))
#    {
#      $closure($this);
#      $this->expire($lock_id, $timeout);
#      return;
#    }
#    
#    while(!$this->exists($key)) {
#      sleep(1);
#    }
#  }
#  
#  # PUB/SUB: reads from the socket until there is a message.
#  function listen() {
#    return $this->read_raw_reply();
#  }
}

?>
