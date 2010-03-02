<?php

# http://groups.google.com/group/php-standards/web/psr-0-final-proposal
function __autoload($origClassName)
{
  $className = ltrim($origClassName, '\\');
  $fileName  = '';
  $namespace = '';
  
  if ($lastNsPos = strripos($className, '\\'))
  {
    $namespace = substr($className, 0, $lastNsPos);
    $className = substr($className, $lastNsPos + 1);
    $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
  }
  $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';

  if (!include $fileName)
  {
    echo '<pre>';
    echo "\nOops. An error occured while loading $fileName\n";
    debug_print_backtrace();
    echo '</pre>';
    exit;
  }
  
  if (method_exists($origClassName, '__constructStatic')) {
    $origClassName::__constructStatic();
  }
}

$autorunner = new Test\Unit\AutoRunner();

?>
