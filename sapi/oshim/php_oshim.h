/*
  +----------------------------------------------------------------------+
  | 👑 Sovereign OSHIM C Engine SAPI Header                              |
  +----------------------------------------------------------------------+
  | WHY: Embeds the Zend Virtual Machine directly into the OSHIM runtime |
  | bypassing standard CGI/FPM overhead for native bare-metal execution. |
  +----------------------------------------------------------------------+
*/

#ifndef PHP_OSHIM_H
#define PHP_OSHIM_H

#include "main/php.h"
#include "main/SAPI.h"
#include "Zend/zend.h"
#include "Zend/zend_API.h"
#include "Zend/zend_compile.h"
#include "Zend/zend_execute.h"

extern sapi_module_struct oshim_sapi_module;

#endif /* PHP_OSHIM_H */
