/*
  +----------------------------------------------------------------------+
  | 👑 Sovereign OSHIM Bare-Metal C SAPI & Kernel epoll Event Driver     |
  +----------------------------------------------------------------------+
  | WHY: Completely replaces Nginx, Apache, Node.js, and PHP-FPM by      |
  | directly embedding the Zend Virtual Machine inside a high-throughput |
  | Linux epoll non-blocking event multiplexer written in pure C.        |
  +----------------------------------------------------------------------+
*/

#include "php_oshim.h"

static int oshim_current_client_fd = -1;

/* ==================================================================== */
/* 1. SAPI CALLBACK IMPLEMENTATIONS                                     */
/* ==================================================================== */

static int oshim_startup(sapi_module_struct *sapi_module)
{
    // WHY: Initialize Zend engine and register our intrinsic C module
    extern zend_module_entry oshim_module_entry;
    if (php_module_startup(sapi_module, &oshim_module_entry) == FAILURE) {
        return FAILURE;
    }
    return SUCCESS;
}

static size_t oshim_ub_write(const char *str, size_t str_length)
{
    // WHY: Direct unbuffered write into the active TCP socket descriptor.
    // Bypasses any intermediary proxy buffers for pure zero-copy throughput.
    if (oshim_current_client_fd >= 0) {
        ssize_t sent = write(oshim_current_client_fd, str, str_length);
        return sent > 0 ? (size_t)sent : 0;
    }
    return fwrite(str, 1, str_length, stdout);
}

static void oshim_flush(void *server_context)
{
    if (oshim_current_client_fd >= 0) {
        // TCP flush managed by kernel stack
        return;
    }
    fflush(stdout);
}

static int oshim_send_headers(sapi_headers_struct *sapi_headers)
{
    // WHY: Formulate raw HTTP header frame directly in C and send to socket
    if (oshim_current_client_fd < 0) {
        return SAPI_HEADER_SENT_SUCCESSFULLY;
    }

    char header_buf[2048];
    int code = SG(sapi_headers).http_response_code;
    if (code == 0) code = 200;

    const char *status_text = "OK";
    if (code == 404) status_text = "Not Found";
    else if (code == 500) status_text = "Internal Server Error";
    else if (code == 201) status_text = "Created";

    int len = snprintf(header_buf, sizeof(header_buf),
        "HTTP/1.1 %d %s\r\n"
        "Server: OSHIM Sovereign C-Engine\r\n"
        "Connection: close\r\n\r\n",
        code, status_text
    );

    if (len > 0) {
        write(oshim_current_client_fd, header_buf, (size_t)len);
    }

    return SAPI_HEADER_SENT_SUCCESSFULLY;
}

static void oshim_register_server_variables(zval *track_vars_array)
{
    // WHY: Populate $_SERVER superglobal natively without overhead
    php_register_variable("SERVER_SOFTWARE", "OSHIM Sovereign C-Engine (Zend Native)", track_vars_array);
    php_register_variable("GATEWAY_INTERFACE", "OSHIM/4.0", track_vars_array);
    php_register_variable("SERVER_NAME", "localhost", track_vars_array);
    php_register_variable("SERVER_PORT", "8000", track_vars_array);
}

static void oshim_log_message(const char *message, int syslog_type_int)
{
    fprintf(stderr, "\033[1;31m[OSHIM Core Alert]\033[0m %s\n", message);
}

/* ==================================================================== */
/* 2. SAPI MODULE SPECIFICATION STRUCT                                  */
/* ==================================================================== */

sapi_module_struct oshim_sapi_module = {
    "oshim",
    "Sovereign OSHIM Native C Engine",

    oshim_startup,
    php_module_shutdown_wrapper,

    NULL,
    NULL,

    oshim_ub_write,
    oshim_flush,
    NULL,
    NULL,

    php_error,

    NULL,
    oshim_send_headers,
    NULL,

    NULL,
    NULL,

    oshim_register_server_variables,
    oshim_log_message,
    NULL,
    NULL,

    STANDARD_SAPI_MODULE_PROPERTIES
};

/* ==================================================================== */
/* 3. INTRINSIC C FUNCTIONS EXPOSED DIRECTLY TO PHP                     */
/* ==================================================================== */

PHP_FUNCTION(oshim_version)
{
    RETURN_STRING(OSHIM_VERSION);
}

PHP_FUNCTION(oshim_cpu_cores)
{
    // WHY: Direct kernel sysconf query with zero userland shell execution
    long cores = sysconf(_SC_NPROCESSORS_ONLN);
    RETURN_LONG(cores > 0 ? cores : 1);
}

PHP_FUNCTION(oshim_nanotime)
{
    // WHY: Sub-nanosecond monotonic hardware clock for micro-benchmarking
    struct timespec ts;
    clock_gettime(CLOCK_MONOTONIC, &ts);
    double nano = (double)ts.tv_sec * 1e9 + (double)ts.tv_nsec;
    RETURN_DOUBLE(nano);
}

PHP_FUNCTION(oshim_mmap_allocate)
{
    zend_long size;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(size)
    ZEND_PARSE_PARAMETERS_END();

    if (size <= 0) {
        RETURN_FALSE;
    }

    // WHY: Direct POSIX mmap allocation for 0ms GC memory pages
    void *ptr = mmap(NULL, (size_t)size, PROT_READ | PROT_WRITE, MAP_PRIVATE | MAP_ANONYMOUS, -1, 0);
    if (ptr == MAP_FAILED) {
        RETURN_FALSE;
    }

    RETURN_LONG((zend_long)(uintptr_t)ptr);
}

static const zend_function_entry oshim_functions[] = {
    PHP_FE(oshim_version, NULL)
    PHP_FE(oshim_cpu_cores, NULL)
    PHP_FE(oshim_nanotime, NULL)
    PHP_FE(oshim_mmap_allocate, NULL)
    PHP_FE_END
};

zend_module_entry oshim_module_entry = {
    STANDARD_MODULE_HEADER,
    "oshim_core",
    oshim_functions,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    OSHIM_VERSION,
    STANDARD_MODULE_PROPERTIES
};

/* ==================================================================== */
/* 4. HIGH-PERFORMANCE LINUX EPOLL SERVER & MAIN ENTRYPOINT             */
/* ==================================================================== */

static int set_nonblocking(int fd)
{
    int flags = fcntl(fd, F_GETFL, 0);
    if (flags == -1) return -1;
    return fcntl(fd, F_SETFL, flags | O_NONBLOCK);
}

static void print_banner(int port, int cores)
{
    printf("\033[1;36m=================================================================\033[0m\n");
    printf("\033[1;32m  👑 OSHIM Sovereign C Engine — Bare-Metal Zend VM Runtime       \033[0m\n");
    printf("\033[1;36m=================================================================\033[0m\n");
    printf("  🚀 Native Linux epoll Event Multiplexer Active\n");
    printf("  ⚡ Direct Hardware CPU Cores Detected: \033[1;33m%d Cores\033[0m\n", cores);
    printf("  🌐 HTTP Engine listening directly on: \033[1;32mhttp://0.0.0.0:%d\033[0m\n", port);
    printf("  🛡️ Zero Middleware: No Nginx | No Apache | No PHP-FPM | No Node.js\n\n");
}

int main(int argc, char *argv[])
{
    int port = OSHIM_DEFAULT_PORT;
    const char *script_file = "index.php";

    if (argc >= 2) {
        if (strcmp(argv[1], "-v") == 0 || strcmp(argv[1], "--version") == 0) {
            printf("OSHIM Sovereign C Engine v%s (Core: Zend VM v%s, PHP v%s)\n",
                   OSHIM_VERSION, ZEND_LOGICAL_COMPILER_VERSION, PHP_VERSION);
            return 0;
        }
        script_file = argv[1];
    }

    long cores = sysconf(_SC_NPROCESSORS_ONLN);
    print_banner(port, (int)cores);

    // 1. Initialize SAPI and Zend Module Subsystem
    sapi_startup(&oshim_sapi_module);
    if (oshim_sapi_module.startup(&oshim_sapi_module) == FAILURE) {
        fprintf(stderr, "Failed to initialize OSHIM SAPI module.\n");
        return 1;
    }

    // 2. Create and Bind Native TCP Socket with SO_REUSEPORT
    int server_fd = socket(AF_INET, SOCK_STREAM, 0);
    if (server_fd < 0) {
        perror("Failed to create server socket");
        return 1;
    }

    int opt = 1;
    setsockopt(server_fd, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt));
#ifdef SO_REUSEPORT
    setsockopt(server_fd, SOL_SOCKET, SO_REUSEPORT, &opt, sizeof(opt));
#endif

    struct sockaddr_in address;
    memset(&address, 0, sizeof(address));
    address.sin_family = AF_INET;
    address.sin_addr.s_addr = INADDR_ANY;
    address.sin_port = htons((uint16_t)port);

    if (bind(server_fd, (struct sockaddr *)&address, sizeof(address)) < 0) {
        perror("Bind failed");
        close(server_fd);
        return 1;
    }

    if (listen(server_fd, 4096) < 0) {
        perror("Listen failed");
        close(server_fd);
        return 1;
    }

    set_nonblocking(server_fd);

    // 3. Setup Linux epoll Kernel Event Multiplexer
    int epoll_fd = epoll_create1(0);
    if (epoll_fd < 0) {
        perror("epoll_create1 failed");
        close(server_fd);
        return 1;
    }

    struct epoll_event ev;
    ev.events = EPOLLIN | EPOLLET;
    ev.data.fd = server_fd;
    if (epoll_ctl(epoll_fd, EPOLL_CTL_ADD, server_fd, &ev) < 0) {
        perror("epoll_ctl server_fd failed");
        close(server_fd);
        close(epoll_fd);
        return 1;
    }

    struct epoll_event events[OSHIM_MAX_EVENTS];
    char recv_buffer[OSHIM_BUFFER_SIZE];

    printf("✔ Kernel epoll reactor successfully active. Waiting for traffic...\n\n");

    // 4. Pure C Event Loop
    while (1) {
        int nfds = epoll_wait(epoll_fd, events, OSHIM_MAX_EVENTS, -1);
        if (nfds < 0) {
            if (errno == EINTR) continue;
            perror("epoll_wait interrupted");
            break;
        }

        for (int i = 0; i < nfds; i++) {
            if (events[i].data.fd == server_fd) {
                // Incoming Connection: Accept in non-blocking loop
                while (1) {
                    struct sockaddr_in client_addr;
                    socklen_t client_len = sizeof(client_addr);
                    int client_fd = accept(server_fd, (struct sockaddr *)&client_addr, &client_len);
                    if (client_fd < 0) {
                        if (errno == EAGAIN || errno == EWOULDBLOCK) {
                            break; // All connections drained
                        }
                        break;
                    }

                    set_nonblocking(client_fd);
                    struct epoll_event client_ev;
                    client_ev.events = EPOLLIN | EPOLLET | EPOLLONESHOT;
                    client_ev.data.fd = client_fd;
                    epoll_ctl(epoll_fd, EPOLL_CTL_ADD, client_fd, &client_ev);
                }
            } else {
                // Data ready on client socket
                int client_fd = events[i].data.fd;
                ssize_t bytes_read = read(client_fd, recv_buffer, sizeof(recv_buffer) - 1);

                if (bytes_read > 0) {
                    recv_buffer[bytes_read] = '\0';
                    oshim_current_client_fd = client_fd;

                    // Parse basic HTTP request line directly in C
                    char method[16] = "GET";
                    char uri[1024] = "/";
                    sscanf(recv_buffer, "%15s %1023s", method, uri);

                    // Setup Zend Execution Context
                    if (php_request_startup() == SUCCESS) {
                        SG(request_info).request_method = method;
                        SG(request_info).request_uri = uri;
                        SG(request_info).proto_num = 1001;

                        zend_file_handle file_handle;
                        zend_stream_init_filename(&file_handle, script_file);

                        // Execute script inside Zend Virtual Machine
                        php_execute_script(&file_handle);

                        // Tear down request cleanly
                        php_request_shutdown(NULL);
                    }

                    oshim_current_client_fd = -1;
                    close(client_fd);
                    epoll_ctl(epoll_fd, EPOLL_CTL_DEL, client_fd, NULL);

                } else if (bytes_read == 0 || (bytes_read < 0 && errno != EAGAIN)) {
                    close(client_fd);
                    epoll_ctl(epoll_fd, EPOLL_CTL_DEL, client_fd, NULL);
                }
            }
        }
    }

    // 5. Clean Shutdown
    close(server_fd);
    close(epoll_fd);
    php_module_shutdown();
    sapi_shutdown();

    return 0;
}
