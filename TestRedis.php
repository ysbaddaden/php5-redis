<?php
require_once 'Test/Unit.php';

class TestRedis extends Test\Unit\TestCase
{
  public $redis;
  
  function setup()
  {
    $this->redis = new Redis(array('db' => 0xF, 'port' => 6380));
    $this->redis->debug = in_array('-d', $_SERVER['argv']);
  }
  
  function teardown() {
    $this->redis->flushdb();
  }
  
  function test_connect_errors()
  {
    $this->assert_throws('RedisException', function()
    {
      $r = new Redis(array('host' => 'localhost', 'port' => '1234567890'));
      @$r->connect();
    }, 'Redis::ERR_CONNECT');
  }
  
  function test_string_commands()
  {
    # get / set
    $this->assert_null($this->redis->get('mykey'));
    $this->assert_true($this->redis->set('mykey', 'foobar'));
    $this->assert_equal($this->redis->get('mykey'), 'foobar');
    
    $this->assert_null($this->redis->get('other'));
    $this->assert_true($this->redis->set('other', 'barfoo'));
    $this->assert_equal($this->redis->get('other'), 'barfoo');
    
    # mget / mset
    $this->assert_equal($this->redis->mget('keyA', 'keyB'), array(null, null));
    $this->assert_true($this->redis->set('keyA', 'foobar'));
    $this->assert_equal($this->redis->mget('keyA', 'keyB'), array('foobar', null));
    
    $this->assert_true($this->redis->mset(array('keyA' => 'blabla', 'keyB' => 'foobar')));
    $this->assert_equal($this->redis->mget(array('keyA', 'keyB')), array('blabla', 'foobar'));
    
    # setnx
    $this->assert_true($this->redis->setnx('keyC', 'foo'));
    $this->assert_false($this->redis->setnx('keyC', 'bar'));
    $this->assert_equal($this->redis->get('keyC'), 'foo');
    
    # msetnx
    if (get_class($this) == 'TestRedis')
    {
      $this->assert_true($this->redis->msetnx(array('keyD' => 'foo', 'keyE' => 'bar')));
      $this->assert_false($this->redis->msetnx(array('keyD' => 'bar', 'keyE' => 'foo')));
      $this->assert_equal($this->redis->mget(array('keyD', 'keyE')), array('foo', 'bar'));
    }
    
    # getset
    $this->assert_null($this->redis->getset('some_key', 'old_value'));
    $this->assert_equal($this->redis->getset('some_key', 'new_value'), 'old_value');
    $this->assert_equal($this->redis->get('some_key'), 'new_value');
    
    # exists / incr / decr
    $this->assert_false($this->redis->exists('counter'));
    $this->assert_equal($this->redis->incr('counter'), 1);
    $this->assert_equal($this->redis->incrby('counter', 3), 4);
    $this->assert_equal($this->redis->decr('counter'), 3);

    $this->assert_false($this->redis->exists('another_counter'));
    $this->assert_equal($this->redis->incr('another_counter'), 1);
    
    # del
    $this->assert_equal($this->redis->del('counter', 'another_counter'), 2);
    $this->assert_equal($this->redis->mget('counter', 'another_counter'), array(null, null));
  }
  
  function test_list_commands()
  {
    # lpush / rpush / llen
    $this->assert_false($this->redis->exists('mylist'));
    $this->assert_true($this->redis->lpush('mylist', 'a'));
    $this->assert_equal($this->redis->llen('mylist'), 1);
    
    $this->redis->rpush('mylist', 'b');
    $this->redis->lpush('mylist', 'c');
    $this->assert_equal($this->redis->llen('mylist'), 3);
    
    # lrange
    $this->assert_equal($this->redis->lrange('mylist', 0, 2), array('c', 'a', 'b'));
    $this->assert_equal($this->redis->lrange('mylist', 2, 2), array('b'));
    $this->assert_equal($this->redis->lrange('mylist', 2, 1), array());
    
    # ltrim
    $this->assert_true($this->redis->ltrim('mylist', 0, 1));
    $this->assert_equal($this->redis->lrange('mylist', 0, 2), array('c', 'a'));
    
    # lindex
    $this->assert_equal($this->redis->lindex('mylist', 0), 'c');
    $this->assert_equal($this->redis->lindex('mylist', 1), 'a');
    
    # lset
    $this->assert_true($this->redis->lset('mylist', 0, 'g'));
    $this->assert_equal($this->redis->lindex('mylist', 0), 'g');
    
    # lrem
    $this->redis->lpush('mylist', 'a');
    $this->assert_equal($this->redis->lrange('mylist', 0, 2), array('a', 'g', 'a'));
    $this->assert_equal($this->redis->lrem('mylist', 0, 'a'), 2);
    $this->assert_equal($this->redis->lrange('mylist', 0, 2), array('g'));

    $this->redis->lpush('mylist', 'a');
    $this->redis->rpush('mylist', 'a');
    $this->assert_equal($this->redis->lrem('mylist', -1, 'a'), 1);
    $this->assert_equal($this->redis->lrange('mylist', 0, 2), array('a', 'g'));
    
    $this->redis->rpush('mylist', 'a');
    $this->assert_equal($this->redis->lrem('mylist', 1, 'a'), 1);
    $this->assert_equal($this->redis->lrange('mylist', 0, 2), array('g', 'a'));
    
    # lpop / rpop
    $this->assert_equal($this->redis->lpop('mylist'), 'g');
    $this->assert_equal($this->redis->rpop('mylist'), 'a');
    
    # rpoplpush
    $this->redis->rpush('from_list', 'a');
    $this->redis->rpush('from_list', 'b');
    $this->redis->rpush('from_list', 'c');
    $this->redis->rpush('to_list', 'd');
    
    $this->assert_equal($this->redis->rpoplpush('from_list', 'to_list'), 'c');
    $this->assert_equal($this->redis->lrange('from_list', 0, 2), array('a', 'b'));
    $this->assert_equal($this->redis->lrange('to_list', 0, 2), array('c', 'd'));
  }
  
  function test_sets_commands()
  {
    # sadd / spop
    $this->assert_true($this->redis->sadd('slist', 'sazorué"àç(tèéàuzgv)"'));
    $this->assert_equal($this->redis->spop('slist'), 'sazorué"àç(tèéàuzgv)"');
    
    # srem
    $this->redis->sadd('slist', 'a');
    $this->assert_true($this->redis->srem('slist', 'a'));
    
    # smove / smembers / sismember
    $this->redis->sadd('srclist', 'a');
    $this->assert_true($this->redis->smove('srclist', 'destlist', 'a'));
    $this->assert_equal($this->redis->smembers('srclist'), array());
    $this->assert_false($this->redis->sismember('srclist', 'a'));
    $this->assert_true($this->redis->sismember('destlist', 'a'));
    
    # scard
    $this->assert_equal($this->redis->scard('srclist'), 0);
    $this->assert_equal($this->redis->scard('destlist'), 1);
    
    # sinter
    $this->redis->sadd('s1', 'a');
    $this->redis->sadd('s2', 'a');
    $this->redis->sadd('s3', 'a');
    $this->redis->sadd('s1', 'b');
    $this->redis->sadd('s2', 'b');
    $this->redis->sadd('s1', 'c');
    $this->redis->sadd('s3', 'c');
    $this->assert_equal($this->redis->sinter('s1', 's2'), array('a', 'b'));
    $this->assert_equal($this->redis->sinter('s1', 's3'), array('c', 'a'));
    $this->assert_equal($this->redis->sinter('s1', 's2', 's3'), array('a'));
    
    # sinterstore
    $this->assert_equal($this->redis->sinterstore('s1s2', 's1', 's2'), 2);
    $this->assert_equal($this->redis->sinterstore('s1s2s3', 's1', 's2', 's3'), 1);
    $this->assert_equal($this->redis->smembers('s1s2'), array('a', 'b'));
    $this->assert_equal($this->redis->smembers('s1s2s3'), array('a'));
    
    # sdiff
    $this->assert_equal($this->redis->sdiff('s1', 's2'), array('c'));
    $this->assert_equal($this->redis->sdiff('s1', 's3'), array('b'));
    $this->assert_equal($this->redis->sdiff('s1', 's2', 's3'), array());
    
    # sdiffstore
    $this->assert_equal($this->redis->sdiffstore('s1s2', 's1', 's2'), 1);
    $this->assert_equal($this->redis->sdiffstore('s1s2s3', 's1', 's2', 's3'), 0);
    
    # sunion
    $this->assert_equal($this->redis->sunion('s1', 's2'), array('c', 'a', 'b'));
    $this->assert_equal($this->redis->sunion('s1', 's3'), array('c', 'a', 'b'));
    $this->assert_equal($this->redis->sunion('s1', 's2', 's3'), array('c', 'a', 'b'));
    
    # sunionstore
    $this->assert_equal($this->redis->sunionstore('s1s2', 's1', 's2'), 3);
    $this->assert_equal($this->redis->sunionstore('s1s2s3', 's1', 's2', 's3'), 3);
  }
  
  function test_hashes()
  {
    # REDIS >= 1.3 only
    try { $this->redis->hlen('profile:1'); } catch(RedisException $e) { return; }
    
    # hexists / hset / hlen
    $this->assert_false($this->redis->hexists('profile:1', 'name'));
    
    $this->assert_true($this->redis->hset('profile:1', 'name', 'John Doe'));
    $this->assert_equal($this->redis->hlen('profile:1'), 1);
    $this->assert_true($this->redis->hexists('profile:1', 'name'));
    
    $this->assert_true($this->redis->hset('profile:1', 'login', 'john'));
    $this->assert_true($this->redis->hset('profile:1', 'password', 'doe'));
    $this->assert_equal($this->redis->hlen('profile:1'), 3);
    
    # hkeys / hvals / hgetall
    $this->assert_equal($this->redis->hkeys('profile:1'), array('name', 'login', 'password'));
    $this->assert_equal($this->redis->hvals('profile:1'), array('John Doe', 'john', 'doe'));
    $this->assert_equal($this->redis->hgetall('profile:1'),
      array('name' => 'John Doe', 'login' => 'john', 'password' => 'doe'));
    
    # hdel
    $this->assert_true($this->redis->hdel('profile:1', 'login'));
    $this->assert_false($this->redis->hexists('profile:1', 'login'));
  }
  
  function test_pipeline()
  {
    $this->assert_null($this->redis->pipeline(function() {}));
    $this->assert_equal($this->redis->pipeline(function($pipe)
    {
      $pipe->mset(array('key1' => 1, 'key2' => 4));
      $pipe->set('key3', 45);
      $pipe->setnx('key1', 2);
      $pipe->incr('key1');
      $pipe->decr('key2');
      $pipe->incr('key3');
      $pipe->incr('key4');
      $pipe->decr('key5');
      $pipe->del('key1', 'key2');
    }), array(true, true, false, 2, 3, 46, 1, -1, 2));
  }
  
  function test_server_commands()
  {
    # ping
    $this->assert_true($this->redis->ping());
    
    # flushdb / dbsize
    $this->assert_not_equal($this->redis->dbsize(), 0);
    $this->assert_true($this->redis->flushdb());
    $this->assert_equal($this->redis->dbsize(), 0);
  }
}

class TestRedisCluster extends TestRedis
{
  function setup()
  {
    $server1 = array('db' => 0xF, 'port' => 6380);
    $server2 = array('db' => 0xF, 'port' => 6381);
    $debug   = in_array('-d', $_SERVER['argv']);
    
    $this->redis = new RedisCluster(array($server1, $server2), function($key) {
      return ($key == 'keyA' or $key == 'key2' or $key == 'key7') ? 1 : 0;
    }, $debug);
  }
  
  function teardown()
  {
    $this->redis->send_command(0, 'flushdb');
    $this->redis->send_command(1, 'flushdb');
  }
  
  function test_server_commands()
  {
    $this->assert_true($this->redis->send_command(0, 'ping'));
    $this->assert_true($this->redis->send_command(1, 'ping'));
  }
  
  function test_del_command()
  {
    $this->redis->mset(array('key1' => 1, 'key2' => 2));
    $this->assert_equal($this->redis->del('key1', 'key2', 'key3'), 2);
    $this->assert_false($this->redis->exists('key2'));
  }
  
  function test_msetnx_command()
  {
    $this->redis->del('key1', 'key2', 'key3');
    $this->redis->msetnx(array('key1' => 1, 'key3' => 3));
    
    $r = $this->redis;
    $this->assert_throws('ErrorException', function() use($r) {
      $r->msetnx(array('key1' => 1, 'key2' => 2));
    });
  }
}

?>
