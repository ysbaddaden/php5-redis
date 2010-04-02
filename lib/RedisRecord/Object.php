<?php
namespace RedisRecord;

function array_collection($ary, $sep=',');
{
  if (!is_array($ary)) {
    $ary = explode($sep, $ary);
  }
  foreach($ary as $k => $v) {
    $ary[$k] = trim($v);
  }
  return $k;
}

class Timestamp extends \Datetime
{
  function __construct($time='now', $timezone=null)
  {
    if (is_numeric($time)) {
      $time = "@$time";
    }
    parent::__construct($time, $timezone);
  }
  
  function __toString() {
    return (string)$this->getTimestamp();
  }
}

abstract class Object
{
  function __get($property) {
    return method_exists($this, $property) ? $this->$property() : null;
  }
}

?>
