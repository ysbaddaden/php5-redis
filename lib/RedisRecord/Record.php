<?php
namespace RedisRecord;

abstract class Record extends Object
{
  private static $columns = array();
  private $_attributes = array();
  
  function __get($property)
  {
    if (array_key_exists(static::columns(), $property))
    {
      if (!array_key_exists($this->_attributes, $property)) {
        $this->_attributes[$property] = $this->_read_attribute($property);
      }
      return $this->_attributes[$property];
    }
    return parent::__get($property);
  }
  
  function __set($property, $value)
  {
    if (array_key_exists(static::columns(), $property))
    {
      switch(static::$columns[$property]['type'])
      {
        case 'integer':   $value = (int)$value;           break;
        case 'string':    $value = (string)$value;        break;
        case 'float':     $value = (double)$value;        break;
        case 'boolean':   $value = (bool)$value;          break;
        case 'timestamp': $value = new Timestamp($value); break;
      }
      return $this->_attributes[$property] = $value;
    }
    return parent::__set($property, $value);
  }
  
  protected static function columns($columns=null)
  {
    if ($columns !== null) {
      self::$columns[get_called_class()] = $columns;
    }
    return self::$columns[get_called_class()];
  }
  
  protected static function column_names() {
    return array_keys(self::$columns[get_called_class()]);
  }
  
  protected function attributes() {
    return $this->_attributes;
  }
  
  protected function attributes_set($attributes)
  {
    foreach($this->attributes as $k => $v) {
      $this->$k = $v;
    }
  }
  
  abstract function _read_attribute($attribute);
  abstract function _read_attributes($id);
}

?>
