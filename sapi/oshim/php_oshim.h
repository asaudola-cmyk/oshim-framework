/*
  +----------------------------------------------------------------------+
  | 👑 Sovereign OSHIM Bare-Metal C SAPI Interface                       |
  +----------------------------------------------------------------------+
  | WHY: Replaces Nginx, Apache, PHP-FPM, Node.js, and C++ compilers by  |
  | directly embedding the Zend Virtual Machine inside a high-throughput |
  | Linux epoll non-blocking event multiplexer with JIT machine-code     |
  | execution and zero-copy NVMe memory mapping written in pure C.       |
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
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <sys/epoll.h>
#include <sys/mman.h>
#include <time.h>
#include <stdint.h>

#define OSHIM_VERSION "4.5.0-SOVEREIGN-C"
#define OSHIM_DEFAULT_PORT 8000
#define OSHIM_MAX_EVENTS 1024
#define OSHIM_BUFFER_SIZE 65536

extern sapi_module_struct oshim_sapi_module;

/* Intrinsic OSHIM C Functions exposed directly to Zend VM */
PHP_FUNCTION(oshim_version);
PHP_FUNCTION(oshim_cpu_cores);
PHP_FUNCTION(oshim_nanotime);
PHP_FUNCTION(oshim_mmap_allocate);
PHP_FUNCTION(oshim_exec_asm);
PHP_FUNCTION(oshim_mmap_file_open);
PHP_FUNCTION(oshim_mmap_file_read);
PHP_FUNCTION(oshim_mmap_file_write);
PHP_FUNCTION(oshim_mmap_file_close);

#endif /* PHP_OSHIM_H */
