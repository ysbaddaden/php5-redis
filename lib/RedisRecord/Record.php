<?php
namespace RedisRecord;

abstract class Record extends Object
{
  private static $columns = array();
  private $_attributes = array();
  
  function __get($property)
  {
    if (in_array($property, static::column_names()))
    {
      if (!array_key_exists($property, $this->_attributes)) {
        $this->_attributes[$property] = $this->_read_attribute($property);
      }
      return $this->_attributes[$property];
    }
    return parent::__get($property);
  }
  
  function __set($property, $value)
  {
    if (in_array($property, static::column_names()))
    {
      $column = static::column($property);
      switch($column['type'])
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
  
  protected static function column($column_name) {
    return self::$columns[get_called_class()][$column_name];
  }
  
  protected function attributes() {
    return $this->_attributes;
  }
  
  protected function attributes_set($attributes)
  {
    foreach($attributes as $k => $v) {
      $this->$k = $v;
    }
  }
  
  abstract protected function _read_attribute($attribute);
  abstract protected static function & _read_attributes($id);
}

?>
