<?php
require_once __DIR__.'/unit.php';

define('REDIS_RECORD_HOST', 'localhost');
define('REDIS_RECORD_PORT', 6380);
define('REDIS_RECORD_DB',   0xF);
#define('REDIS_RECORD_USE_HASHES', true);

class TestRedisRecord extends Test\Unit\TestCase
{
  
}

?>
