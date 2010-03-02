<?php
namespace Test\Unit;

class Error
{
  private $test_name;
  private $exception;
  
  function __construct($test_name, $exception)
  {
    $this->test_name = $test_name;
    $this->exception = $exception;
  }
  
  function long_display()
  {
    $message = $this->message();
    $trace   = $this->exception->getTraceAsString();
    return "Error: {$this->test_name}\n$message\n$trace";
  }
  
  function short_display()
  {
    $message = $this->message();
    return "Error: {$this->test_name}\n{$message}";
  }
  
  function single_character_display() {
    return 'E';
  }
  
  function message()
  {
    return sprintf("[%d] %s\nOccured at line %d in file %s",
      $this->exception->getCode(), $this->exception->getMessage(),
      $this->exception->getLine(), $this->exception->getFile());
  }
  
  function __toString() {
    return $this->long_display();
  }
}

?>
