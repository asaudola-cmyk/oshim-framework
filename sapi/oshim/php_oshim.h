/*
  +----------------------------------------------------------------------+
  | 👑 Sovereign OSHIM Bare-Metal C SAPI Interface                       |
  +----------------------------------------------------------------------+
  | WHY: Replaces Nginx, Apache, PHP-FPM, and Node.js with a direct C    |
  | event-loop engine that embeds the Zend Virtual Machine natively.     |
  +----------------------------------------------------------------------+
*/

#ifndef PHP_OSHIM_H
#define PHP_OSHIM_H

#include "main/php.h"
#include "main/SAPI.h"
#include "main/php_main.h"
#include "main/php_variables.h"
#include "main/php_ini.h"
#include "Zend/zend.h"
#include "Zend/zend_API.h"
#include "Zend/zend_compile.h"
#include "Zend/zend_execute.h"
#include "Zend/zend_interfaces.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <fcntl.h>
#include <errno.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <sys/epoll.h>
#include <sys/mman.h>
#include <time.h>

#define OSHIM_VERSION "4.0.0-SOVEREIGN"
#define OSHIM_DEFAULT_PORT 8000
#define OSHIM_MAX_EVENTS 1024
#define OSHIM_BUFFER_SIZE 65536

extern sapi_module_struct oshim_sapi_module;

/* Intrinsic OSHIM C Functions exposed to Zend VM */
PHP_FUNCTION(oshim_version);
PHP_FUNCTION(oshim_cpu_cores);
PHP_FUNCTION(oshim_nanotime);
PHP_FUNCTION(oshim_mmap_allocate);

#endif /* PHP_OSHIM_H */
