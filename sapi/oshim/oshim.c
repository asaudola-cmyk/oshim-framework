/*
  +----------------------------------------------------------------------+
  | 👑 Sovereign OSHIM Native SAPI & Bare-Metal Zend VM Driver           |
  +----------------------------------------------------------------------+
  | WHY: By embedding Zend VM in a dedicated C SAPI, OSHIM eliminates   |
  | all middleware (Nginx, FPM, Apache) and runs directly on the metal.  |
  +----------------------------------------------------------------------+
*/

#include "php_oshim.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static int oshim_startup(sapi_module_struct *sapi_module)
{
    if (php_module_startup(sapi_module, NULL) == FAILURE) {
        return FAILURE;
    }
    return SUCCESS;
}

static size_t oshim_ub_write(const char *str, size_t str_length)
{
    return fwrite(str, 1, str_length, stdout);
}

static void oshim_flush(void *server_context)
{
    fflush(stdout);
}

static void oshim_log_message(const char *message, int syslog_type_int)
{
    fprintf(stderr, "[OSHIM Engine] %s\n", message);
}

/* 🚀 Sovereign OSHIM SAPI Definition */
sapi_module_struct oshim_sapi_module = {
    "oshim",                        /* name */
    "Sovereign OSHIM Engine SAPI",  /* pretty name */

    oshim_startup,                  /* startup */
    php_module_shutdown_wrapper,    /* shutdown */

    NULL,                           /* activate */
    NULL,                           /* deactivate */

    oshim_ub_write,                 /* unbuffered write */
    oshim_flush,                    /* flush */
    NULL,                           /* get uid */
    NULL,                           /* getenv */

    php_error,                      /* error handler */

    NULL,                           /* header handler */
    NULL,                           /* send headers handler */
    NULL,                           /* send header handler */

    NULL,                           /* read POST data */
    NULL,                           /* read Cookies */

    NULL,                           /* register server variables */
    oshim_log_message,              /* Log message */
    NULL,                           /* Get request time */
    NULL,                           /* Child terminate */

    STANDARD_SAPI_MODULE_PROPERTIES
};

static void print_banner(void)
{
    printf("\033[1;36m====================================================\033[0m\n");
    printf("\033[1;32m  👑 OSHIM Sovereign C Engine (Native Zend VM Runtime) \033[0m\n");
    printf("\033[1;36m====================================================\033[0m\n");
    printf("  Direct Hardware Execution | Zero Middleware | C-Level SAPI\n\n");
}

int main(int argc, char *argv[])
{
    zend_file_handle file_handle;

    print_banner();

    if (argc < 2) {
        printf("Usage: oshim <script.php> [args...]\n");
        printf("       oshim -v\n");
        return 0;
    }

    if (strcmp(argv[1], "-v") == 0 || strcmp(argv[1], "--version") == 0) {
        printf("OSHIM Sovereign Engine v3.5 (Core: PHP %s, Zend Engine v%s)\n", 
               PHP_VERSION, ZEND_LOGICAL_COMPILER_VERSION);
        return 0;
    }

    /* Initialize SAPI */
    sapi_startup(&oshim_sapi_module);
    oshim_sapi_module.startup(&oshim_sapi_module);

    /* Initialize Zend Execution Context */
    if (php_request_startup() == FAILURE) {
        php_module_shutdown();
        sapi_shutdown();
        return 1;
    }

    /* Prepare Script File Handle */
    zend_stream_init_filename(&file_handle, argv[1]);

    /* Execute the script natively inside Zend VM */
    php_execute_script(&file_handle);

    /* Clean Shutdown */
    php_request_shutdown(NULL);
    php_module_shutdown();
    sapi_shutdown();

    return 0;
}
