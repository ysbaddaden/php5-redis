#include "php_redis.h"

PHP_MINIT_FUNCTION(redis) {
  return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(redis) {
  return SUCCESS;
}

zend_module_entry redis_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
    STANDARD_MODULE_HEADER,
#endif
    PHP_REDIS_EXTNAME,
    NULL,                  /* Functions */
    PHP_MINIT(redis),
    PHP_MSHUTDOWN(redis),
    NULL,                  /* RINIT */
    NULL,                  /* RSHUTDOWN */
    NULL,                  /* MINFO */
#if ZEND_MODULE_API_NO >= 20010901
    PHP_REDIS_EXTVER,
#endif
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_REDIS
extern "C" {
ZEND_GET_MODULE(redis)
}
#endif

