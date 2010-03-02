<?php
namespace Test\Unit;

class TestSuite extends \ArrayObject
{
  const STARTED  = 'TestSuite::STARTED';
  const FINISHED = 'TestSuite::FINISHED';
  
  private $name;
  private $listeners;
  
  function __construct($name)
  {
    parent::__construct(array());
    $this->name = $name;
  }
  
  function run($result)
  {
    AutoRunner::$has_run = true;
    
    $this->notify(TestSuite::STARTED, $this->name);
    foreach($this as $test) {
      $test->run($result, array($this, 'notify'));
    }
    $this->notify(TestSuite::FINISHED, $this->name);
  }
  
  # :private
  function add_listener($type, $callback) {
    $this->listeners[$type] = $callback;
  }
  
  # :private:
  function notify($type, $message)
  {
    if (isset($this->listeners[$type])) {
      call_user_func($this->listeners[$type], $message);
    }
  }
  
  # Counts all test methods from all test classes added to this suite.
  function size()
  {
    $size = 0;
    foreach($this as $test) {
      $size += $test->size();
    }
    return $size;
  }
  
  function __toString()
  {
    return $this->name;
  }
}

?>
