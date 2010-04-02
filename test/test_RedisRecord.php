<?php
require_once __DIR__.'/unit.php';

define('REDIS_RECORD_HOST', 'localhost');
define('REDIS_RECORD_PORT', 6380);
define('REDIS_RECORD_DB',   0xF);
#define('REDIS_RECORD_USE_HASHES', true);

class TestRedisRecord extends Test\Unit\TestCase
{
  function test_new()
  {
    $user = new User();
    $this->assert_true($user->new_record);
    
    $user->email = 'john@example.com';
    $this->assert_equal($user->email, 'john@example.com');
    
    $user->nickname = 'John';
    $user->password = 'azerty';
    $this->assert_nothing_thrown(function() use($user) {
      $user->save();
    });
    $this->assert_false($user->new_record);
    
    $user->reload();
    $this->assert_equal($user->email, 'john@example.com');
    
    $user = new User(array('nickname' => 'Jack'));
    $this->assert_equal($user->nickname, 'Jack');
  }
  
  function test_create()
  {
    $user = User::create(array(
      'email'    => 'john@example.com',
      'nickname' => 'Smith',
      'password' => 'qwerty',
    ));
    
    $this->assert_instance_of($user, 'User');
    $this->assert_false($user->new_record);
  }
  
  function test_update()
  {
    $user = User::create(array(
      'email'    => 'john@example.com',
      'nickname' => 'Smith',
      'password' => 'qwerty',
    ));
    
    $user = new User($user->id);
    $this->assert_false($user->new_record);
    $this->assert_equal($user->password, 'qwerty');
    
    $user2 = User::update($user->id, array('password' => 'poiuyt'));
    
    $this->assert_instance_of($user2, 'User');
    $this->assert_false($user2->new_record);
    $this->assert_equal($user2->password, 'poiuyt');

    $user->reload();
    $this->assert_equal($user->password, 'poiuyt');
  }
  
}

?>
