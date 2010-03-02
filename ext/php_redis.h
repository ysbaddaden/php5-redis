#ifndef PHP_REDIS_H
#define PHP_REDIS_H

#define PHP_REDIS_EXTNAME  "redis"
#define PHP_REDIS_EXTVER   "0.1a"

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif 

extern "C" {
#include "php.h"
}

extern zend_module_entry redis_module_entry;
#define phpext_redis_ptr &redis_module_entry;

#endif /* PHP_REDIS_H */
