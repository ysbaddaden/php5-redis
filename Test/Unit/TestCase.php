<?php
namespace Test\Unit;

abstract class TestCase extends Assertions
{
  const FAULT    = 'TestCase::FAULT';
  const STARTED  = 'TestCase::STARTED';
  const TEST     = 'TestCase::TEST';
  const FINISHED = 'TestCase::FINISHED';
  
  protected $_result;
  protected $_test_name;
  
  function setup()    {}
  function teardown() {}
  
  function default_test() {
    $this->flunk("No tests were specified");
  }
  
  function run($result, $notifier)
  {
    $this->_result   = $result;
    $this->_notifier = $notifier;
    
    call_user_func($this->_notifier, TestCase::STARTED, (string)$this);
    $this->try_catch('setup');
    
    foreach($this->method_names() as $method_name) {
      $this->run_test($method_name);
    }
    
    $this->try_catch('teardown');
    call_user_func($this->_notifier, TestCase::FINISHED, (string)$this);
  }
  
  # :nodoc:
  protected function run_test($method_name)
  {
    $this->_test_name = $method_name;
    $this->_result->add_run();
    try
    {
      $this->$method_name();
      call_user_func($this->_notifier, TestCase::TEST, $method_name);
    }
    catch(AssertionFailedError $e) {
      $this->add_failure($e->getMessage(), $e->getTrace());
    }
    catch(\Exception $e) {
      $this->add_error($e);
    }
  }
  
  private function try_catch($method_name)
  {
    try {
      $this->$method_name();
    }
    catch(AssertionFailedError $e) {
      $this->add_failure($e->getMessage(), $e->getTrace());
    }
    catch(\Exception $e) {
      $this->add_error($e);
    }
  }
  
  private function & method_names()
  {
    $tests = array();
    foreach(get_class_methods($this) as $method)
    {
      if (strpos($method, 'test') === 0) {
        $tests[] = $method;
      }
    }
    if (empty($tests)) {
      $tests[] = 'default_test';
    }
    return $tests;
  }
  
  function size() {
    return count($this->method_names());
  }
  
  protected function add_failure($message, $trace)
  {
    $failure = new Failure("$this::{$this->_test_name}", $trace, $message);
    $this->_result->add_failure($failure);
  }
  
  protected function add_error($exception)
  {
    $error = new Error("$this::{$this->_test_name}", $exception);
    $this->_result->add_error($error);
  }
  
  function __toString() {
    return get_class($this);
  }
}

?>
