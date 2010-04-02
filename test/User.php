<?php

class User extends RedisRecord\Base
{
  static function init()
  {
    static::columns(array(
      'email'      => array('type' => 'string',    'unique' => true),
      'nickname'   => array('type' => 'string',    'unique' => true),
      'password'   => array('type' => 'string'),
      'role'       => array('type' => 'string',    'searchable' => true),
      'active'     => array('type' => 'boolean',   'searchable' => true),
#      'created_at' => array('type' => 'timestamp', 'searchable' => true),
#      'updated_at' => array('type' => 'timestamp', 'searchable' => true),
    ));
    
#    static::validates_presence_of('email');
#    static::validates_presence_of('nickname');
#    static::validates_presence_of('password');
#    
#    static::validates_length_of('nickname', array('min' => 5, 'max' => 20));
#    
#    static::validates_inclusion_of('role', array(
#      'in' => array('member', 'moderator', 'editor', 'admin')));
  }
}

User::init();

?>
