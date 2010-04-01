<?php
date_default_timezone_set('Europe/Paris');

ini_set('include_path',
	__DIR__.'/../lib'.PATH_SEPARATOR.
	ini_get('include_path').PATH_SEPARATOR
);

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
  
  require $fileName;
}

$autorunner = new Test\Unit\AutoRunner();

?>
