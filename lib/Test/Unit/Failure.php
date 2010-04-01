<?php
namespace Test\Unit;

class Failure
{
  private $test_name;
  private $location;
  private $message;
  
  function __construct($test_name, $location, $message)
  {
    $this->test_name = $test_name;
    $this->location  = $location;
    $this->message   = $message;
  }
  
  function long_display() {
    return "Failure: {$this->test_name}\n{$this->message}";
  }
  
  function short_display()
  {
    $message = explode("\n", $this->message);
    return "Failure: {$this->test_name}\n{$message[0]}";
  }
  
  function single_character_display() {
    return 'F';
  }
  
  function __toString()
  {
    return $this->long_display();
  }
}

?>
