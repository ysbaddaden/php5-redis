<?php
namespace Test\Unit;

class AutoRunner
{
  static $has_run = false;
  
  function __destruct() {
    static::run();
  }
  
  static function run($name='AutoRunner')
  {
    if (static::$has_run) return;
    
    $suite = new TestSuite($name);
    $class_names = get_declared_classes();
    
    foreach($class_names as $class_name)
    {
      if (is_subclass_of($class_name, 'Test\Unit\TestCase'))
      {
        $reflection = new \ReflectionClass($class_name);
        if (!$reflection->isAbstract()) {
          $suite[] = new $class_name();
        }
      }
    }
    
    $runner = new UI\Console\TestRunner($suite);
    $runner->start();
  }
}

?>
