<?php
namespace Test\Unit;

class TestResult
{
  const FAULT = 'TestResult::FAULT';
  
  private $runner;
  private $assertion_count = 0;
  private $error_count     = 0;
  private $failure_count   = 0;
  private $run_count       = 0;
  
  function __construct($runner) {
    $this->runner = $runner;
  }
  
  function add_assertion() {
    $this->assertion_count++;
  }
  
  function add_error($error)
  {
    $this->error_count++;
    $this->runner->fault($error);
  }
  
  function add_failure($failure)
  {
    $this->failure_count++;
    $this->runner->fault($failure);
  }
  
  function add_run() {
    $this->run_count++;
  }
  
  function passed()
  {
    return ($this->error_count == 0
      and $this->failure_count == 0);
  }
  
  function __toString()
  {
    return "{$this->run_count} tests, {$this->assertion_count} assertions, ".
      "{$this->failure_count} failures, {$this->error_count} errors";
  }
}

?>
