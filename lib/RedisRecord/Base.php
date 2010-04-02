<?php
namespace RedisRecord;

if (!defined('REDIS_RECORD_HOST')) define('REDIS_RECORD_HOST', 'localhost');
if (!defined('REDIS_RECORD_PORT')) define('REDIS_RECORD_PORT', 6379);
if (!defined('REDIS_RECORD_DB'))   define('REDIS_RECORD_DB',   0);

class Base extends Record
{
  private   static $redis;
  protected static $hash_name;
  
  protected $id;
  protected $new_record  = true;
  
  function __construct($attributes_or_id=null)
  {
    if ($attributes_or_id !== null)
    {
      if (is_array($attributes_or_id)) {
        $this->attributes_set($attributes_or_id);
      }
      else
      {
        $this->attributes_set(static::_read_attributes($attributes_or_id));
        $this->new_record = false;
      }
    }
  }
  
  # Returns record's ID.
  function id() {
    return $this->id;
  }
  
  function new_record() {
    return $this->new_record;
  }
  
  # Creates a new record.
  static function create($attributes)
  {
    $record = new static($attributes);
    $record->save();
    return $record;
  }
  
  # Updates an existing record.
  static function update($id, $attributes)
  {
    $record = new static($id);
    $record->attributes_set($attributes);
    $record->save();
    return $record;
  }
  
  # Saves the record.
  function save()
  {
    if ($this->new_record) {
      $this->new_id();
    }
    
    $hash = static::hash_name($this->id);
    foreach($this->attributes() as $field => $value)
    {
      if (defined('REDIS_RECORD_USE_HASHES')) {
        static::redis()->hset($hash, $field, $value);
      }
      else {
        static::redis()->set("$hash:$field", $value);
      }
    }
    
    if ($this->new_record)
    {
      static::redis()->sadd(static::hash_name(), $this->id);
      $this->new_record = false;
    }
    
    return true;
  }
  
  # Finds one or many records.
  # 
  # Scopes:
  # 
  # - +:all+   - returns all records.
  # - +:first+ - returns the first record.
  # - +:last+  - returns the last record [TODO].
  # 
  # Options:
  # 
  # - +select+ - a collection of fields to get
  # - +order+  - sort by a field (eg: +{order => 'name desc'}+)
  # - +limit+  - limits the number of records
  # - +offset+ - index to start from
  # 
  # TODO: find :last.
  # TODO: find {:conditions => ''} (requires to have indexes).
  static function find($scope, $options)
  {
    if (is_numeric($scope))
    {
      $attributes = array();
      $record = new static($attributes);
      $record->new_record = false;
      return $record;
    }
    elseif (is_array($scope))
    {
      $options = $scope;
      $scope   = ':all';
    }
    
    if ($scope == ':first') $options['limit'] = 1;
    
    $hash_name = static::hash_name();
    $limit     = '';
    $by        = '';
    $order     = '';
    
    # select
    $columns = isset($options['select']) ?
      array_collection($options['select']) : static::column_names();
    $gets = array_map(function($field) use($hash_name)
    {
      $field = defined('REDIS_RECORD_USE_HASHES') ?
        "$hash_name:*->$field" : "$hash_name:*:$field";
      return "GET $field";
    }, $columns);
    
    array_unshift($columns, 'id');
    array_unshift($gets,    'GET #');
    
    # limit
    if (isset($options['limit']))
    {
      $start = isset($options['offset']) ? $options['offset'] : 0;
      $end   = $start + $limit;
      $limit = "LIMIT $start $end";
    }
    
    # order
    if (isset($options['order'])
      and preg_match('/^\s*(.+)\s+?(ASC|DESC)\s*?$/i', $options['order'], $match))
    {
      $by = defined('REDIS_RECORD_USE_HASHES') ?
        "$hash_name:*->{$match[1]}" : "$hash_name:*:{$match[1]}";
      $order = $match[2];
    }
    
    # sort
    $redis   = static::redis();
    $results = $redis->sort($hash_name, $by, $order, $limit, implode(' ', $gets));
    
    $records = array();
    foreach($results as $values)
    {
      $attributes = array_combine($columns, $values);
      $record = new static($attributes);
      $record->new_record = false;
      $records[] = $record;
    }
    return ($scope == ':first') ? $records[0] : $records;
  }
  
  # TODO: RedisRecord\Base::paginate().
  static function paginate($scope=':all', $options=array())
  {
    
  }
  
  function reload() {
    $this->attributes_set($this->_read_attributes($this->id));
  }
  
  protected function _read_attribute($attribute)
  {
    if ($this->new_member) {
      return null;
    }
    return defined('REDIS_RECORD_USE_HASHES') ?
      static::redis()->hget(static::hash_name($this->id), $attribute) :
      static::redis()->get(static::hash_name($this->id).":$attribute");
  }
  
  protected static function & _read_attributes($id)
  {
    if (defined('REDIS_RECORD_USE_HASHES')) {
      $rs = static::redis()->hgetall(static::hash_name($id));
    }
    else
    {
      $hash    = static::hash_name($id);
      $columns = array_keys(static::columns());
      $keys    = array_map(function($key) use($hash) { return "$hash:$key"; }, $columns);
      $values  = static::redis()->mget($keys);
      $rs      = array_combine($columns, $values);
    }
    $rs['id'] = $id;
    return $rs;
  }
  
  private function new_id() {
    return $this->id = static::redis()->incr(static::hash_name().':id_inc');
  }
  
  protected static function hash_name($id=null)
  {
    $hash_name = empty(static::$hash_name) ? get_called_class() : static::$hash_name;
    if ($id !== null) {
      $hash_name .= ":$id";
    }
    return $hash_name;
  }
  
  protected static function redis()
  {
    if (!isset(self::$redis))
    {
      self::$redis = new \Redis\Client(array(
        'host' => REDIS_RECORD_HOST,
        'port' => REDIS_RECORD_PORT,
        'db'   => REDIS_RECORD_DB,
      ));
    }
    return self::$redis;
  }
}

?>
