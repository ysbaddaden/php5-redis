<?php
namespace Test\Unit;

# :nodoc:
function is_hash($arr)
{
  if (!is_array($arr)) return false;
  foreach(array_keys($arr) as $k) {
    if (!is_int($k)) return true;
  }
  return false;
}

# :nodoc:
function array_sort_recursive(&$ary)
{
  sort($ary);
  foreach($ary as $k => $v)
  {
    if (is_array($v) and !is_hash($v)) {
      array_sort_recursive($ary[$k]);
    }
  }
}

abstract class Assertions
{
  # Base assertion that is used by all other assertions. Passed block
  # must return a boolean: true if assertion is succesful, false otherwise.
  protected function assert_block($message, $block)
  {
    if (!$block()) {
      throw new AssertionFailedError($message);
    }
    $this->_result->add_assertion();
  }
  
  protected function assert($test, $message='') {
    return $this->assert_equal($test, true, $message);
  }
  
  # Alias for <tt>assert</tt>.
  protected function assert_true($test, $message='') {
    $this->assert($test, $message);
  }
  
  protected function assert_false($test, $message='') {
    return $this->assert_equal($test, false, $message);
  }
  
  protected function assert_null($test, $message='') {
    return $this->assert_equal($test, null, $message);
  }
  
  protected function assert_not_null($test, $message='') {
    return $this->assert_not_equal($test, null, $message);
  }
  
  protected function assert_equal($test, $expected, $message='')
  {
    $message = $this->build_message($message,
      "expected %s but was %s", $expected, $test);
    
    $this->assert_block($message, function() use($test, $expected)
    {
      return (is_array($test) or is_object($test)) ?
        ($test == $expected) : ($test === $expected);
    });
  }
  
  protected function assert_not_equal($test, $expected, $message='')
  {
    $message = $this->build_message($message,
      "expected %s to be != to %s", $expected, $test);
    
    $this->assert_block($message, function() use($test, $expected)
    {
      return (is_array($expected) or is_object($expected)) ?
        ($test != $expected) : ($test !== $expected);
    });
  }
  
  protected function assert_instance_of($object, $class_name, $message='')
  {
    $message = $this->build_message($message,
      "expected instance of %s but got %s", $class_name, get_class($object));
    
    $this->assert_block($message, function() use($object, $class_name) {
      return ($object instanceof $class_name);
    });
  }
  
  protected function assert_type($var, $type, $message='') {
    $this->assert_equal(gettype($var), $type, $message);
  }
  
  protected function assert_match($pattern, $text, $message='')
  {
    $message = $this->build_message($message, "%s expected to match:\n%s", $text, $pattern);
    $this->assert_block($message, function() use($pattern, $text) {
      return preg_match($pattern, $text);
    });
  }
  
  protected function assert_no_match($pattern, $text, $message='')
  {
    $message = $this->build_message($message, "%s expected to not match:\n%s", $text, $pattern);
    $this->assert_block($message, function() use($pattern, $text) {
      return !preg_match($pattern, $text);
    });
  }
  
  #   assert_throws('TestException' 'OtherTestException', function() {
  #     throw new TestException('test');
  #   }, "some message");
  protected function assert_throws()
  {
    $args    = func_get_args();
    $message = (isset($args[count($args) - 1]) and is_string($args[count($args) - 1])) ? array_pop($args) : null;
    $block   = array_pop($args);
    
    $message = (count($args) == 1) ?
      $this->build_message($message, "expected a %s exception but got none", $args[0]) :
      $this->build_message($message, "expected any of those exceptions but got none:\n%s", implode(', ', $args));
    
    
    $this->assert_block($message, function() use($args, $block)
    {
      try {
        $block();
      }
      catch(\Exception $e)
      {
        foreach($args as $exception_name)
        {
          if ($e instanceof $exception_name) {
            return true;
          }
        }
        throw $e;
      }
      return false;
    });
  }
  
  #   assert_nothing_thrown(function() {
  #     // code that won't throw any exception
  #   }, "some message");
  protected function assert_nothing_thrown()
  {
    $args    = func_get_args();
    $message = (isset($args[count($args) - 1]) and is_string($args[count($args) - 1])) ? array_pop($args) : null;
    $block   = array_pop($args);
    
    $this->assert_block($message, function() use($args, $block)
    {
      try {
        $block();
      }
      catch(Exception $e) {
        return false;
      }
      return true;
    });
  }
  
  protected function flunk($message='')
  {
    throw new AssertionFailedError($message);
    #$this->add_failure($message, null);
  }
  
  protected function build_message($head, $template=null)
  {
    if (!empty($template))
    {
      $args = func_get_args();
      $args = array_slice($args, 1);
      foreach($args as $i => $arg)
      {
        switch(gettype($arg))
        {
          case 'NULL':    $args[$i] = 'null'; break;
          case 'boolean': $args[$i] = $arg ? 'true' : 'false'; break;
          case 'string':  break;
          default:        $args[$i] = print_r($arg, true);
        }
      }
      return (empty($head) ? '' : $head."\n").
        call_user_func_array('sprintf', $args);
    }
    return $head;
  }
}

?>
