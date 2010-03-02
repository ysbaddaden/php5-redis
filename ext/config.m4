PHP_ARG_ENABLE(redis,
  [Whether to enable the "redis" extension],
  [  --enable-redis      Build "redis" extension])

if test $PHP_REDIS != "no"; then
  PHP_REQUIRE_CXX()
  PHP_SUBST(REDIS_SHARED_LIBADD)
  PHP_ADD_LIBRARY(stdc++, 1, REDIS_SHARED_LIBADD)
  PHP_NEW_EXTENSION(redis, php_redis.cpp, $ext_shared)
fi
