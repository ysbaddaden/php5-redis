<?php
# Copyright (c) 2010 Julien Portalier <ysbaddaden@gmail.com>
# Distributed as-is under the MIT license.

namespace Redis;

# :nodoc:
class Pipeline
{
  public $commands = array();
  
  function call($command, $args)
  {
    if (is_array($args)) {
      array_unshift($args, $command);
    }
    else {
      $args = func_get_args();
    }
    $this->commands[] = $args;
  }
}

?>
