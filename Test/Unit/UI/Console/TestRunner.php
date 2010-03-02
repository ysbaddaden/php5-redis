<?php
namespace Test\Unit\UI\Console;
use Test\Unit;

class TestRunner
{
  private $suite;
  private $result;
  private $faults = array();
  
  # :nodoc:
  public  $suite_time;
  
  # :nodoc:
  public  $testcase_time;
  
  function __construct($suite)
  {
    $this->suite = $suite;
  }
  
  function start()
  {
    $this->result = new Unit\TestResult($this);
    
    $this->suite->add_listener(Unit\TestSuite::STARTED,  array($this, 'suite_started'));
    $this->suite->add_listener(Unit\TestCase::STARTED,   array($this, 'test_started'));
    $this->suite->add_listener(Unit\TestCase::TEST,      array($this, 'test_test'));
    $this->suite->add_listener(Unit\TestCase::FINISHED,  array($this, 'test_finished'));
    $this->suite->add_listener(Unit\TestSuite::FINISHED, array($this, 'suite_finished'));
    
    $this->suite->run($this->result);
  }
  
  # :nodoc:
  function suite_started($name)
  {
    $this->display("Loaded suite $name\n", 'ANOUNCE');
    $this->suite_time = microtime(true);
  }
  
  # :nodoc:
  function test_started($name)
  {
    $this->display("$name ");
    $this->testcase_time = microtime(true);
  }
  
  # :nodoc:
  function test_test($name)
  {
    $this->display('.');
  }
  
  # :nodoc:
  function test_finished($name)
  {
    $time = (microtime(true) - $this->testcase_time);
    $this->display(sprintf(" %.3fs\n", $time));
  }
  
  # :nodoc:
  function suite_finished($name)
  {
    $time = (microtime(true) - $this->suite_time);
    $this->display(sprintf("Finished in %.3f seconds.\n", $time));
    $this->display("{$this->result}\n\n", $this->result->passed() ? 'SUCCESS' : 'FAULT');
    
    foreach($this->faults as $fault) {
      $this->display($fault->long_display()."\n\n", 'FAULT');
    }
  }
  
  function fault($object)
  {
    $this->faults[] = $object;
    $this->display($object->single_character_display());
  }
  
  # :nodoc:
  function display($message, $severity=null)
  {
    switch($severity)
    {
      case 'FAULT':
        $message = explode("\n",  $message, 2);
        $message = chr(27).'[1;31m'.$message[0].chr(27)."[0m\n".
          (isset($message[1]) ? $message[1]: '');
      break;
      
      case 'SUCCESS':
        $message = chr(27).'[0;32m'.$message.chr(27).'[0m';
      break;
      
      case 'ANOUNCE':
        $message = chr(27).'[1;01m'.$message.chr(27).'[0m';
      break;
    }
    echo "$message";
  }
}

?>
