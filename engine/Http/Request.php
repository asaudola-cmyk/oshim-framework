<?php
declare(strict_types=1);

namespace Oshim\Http;

use Oshim\Http\Session\Session;
use Oshim\Http\Exceptions\ValidationException;

/**
 * Immutable HTTP Request parser, inspector, and input validator.
 */
class Request
{
    protected string $method;
    protected string $uri;
    protected string $path;
    protected ?string $queryString = null;
    protected array $query = [];
    protected array $post = [];
    protected mixed $parsedJson = null;
    protected HeaderMap $headers;
    protected array $cookies = [];
    /** @var array<string, UploadedFile|list<UploadedFile>> */
    protected array $files = [];
    protected array $server = [];
    protected string $rawBody = '';
    protected array $routeParams = [];
    protected array $attributes = [];
    protected ?object $user = null;
    protected ?Session $session = null;

    public function __construct(
        string $method = 'GET',
        string $uri = '/',
        array $query = [],
        array $post = [],
        HeaderMap|array $headers = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        string $rawBody = '',
        ?string $body = null
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;

        $parsedUrl = parse_url($uri);
        $this->path = isset($parsedUrl['path']) ? '/' . ltrim($parsedUrl['path'], '/') : '/';
        $this->queryString = $parsedUrl['query'] ?? null;

        $this->query = $query;
        $this->post = $post;
        $this->headers = $headers instanceof HeaderMap ? $headers : new HeaderMap($headers);
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->rawBody = $body !== null ? $body : $rawBody;

        if (!empty($this->rawBody)) {
            $decoded = json_decode($this->rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->parsedJson = $decoded;
            }
        }
    }

    /**
     * Create request instance from superglobals.
     */
    public static function fromGlobals(): static
    {
        return static::capture();
    }

    /**
     * Capture request from PHP superglobals.
     */
    public static function capture(): static
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerName = str_replace('_', '-', strtolower($key));
                $headers[$headerName] = $value;
            }
        }

        // Files
        $files = [];
        foreach ($_FILES as $key => $fileData) {
            if (is_array($fileData['name'] ?? null)) {
                $files[$key] = [];
                foreach (array_keys($fileData['name']) as $i) {
                    $files[$key][] = new UploadedFile(
                        clientFilename: (string)$fileData['name'][$i],
                        clientMediaType: (string)$fileData['type'][$i],
                        tempFilePath: (string)$fileData['tmp_name'][$i],
                        size: (int)$fileData['size'][$i],
                        error: (int)$fileData['error'][$i]
                    );
                }
            } else {
                $files[$key] = UploadedFile::createFromGlobal($fileData);
            }
        }

        $rawBody = (string)file_get_contents('php://input');

        return new static(
            method: $method,
            uri: $uri,
            query: $_GET,
            post: $_POST,
            headers: new HeaderMap($headers),
            cookies: $_COOKIE,
            files: $files,
            server: $_SERVER,
            rawBody: $rawBody
        );
    }

    /**
     * Create synthetic request for testing.
     */
    public static function create(
        string $method,
        string $uri,
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): static {
        $method = strtoupper($method);
        $server['REQUEST_METHOD'] = $method;
        $server['REQUEST_URI'] = $uri;

        $parsed = parse_url($uri);
        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $post = [];
        $rawBody = $content ?? '';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if ($content === null && !empty($parameters)) {
                $post = $parameters;
            }
        } else {
            $query = array_merge($query, $parameters);
        }

        $headers = [];
        foreach ($server as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($k, 5)));
                $headers[$name] = $v;
            } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = str_replace('_', '-', strtolower($k));
                $headers[$name] = $v;
            }
        }

        return new static(
            method: $method,
            uri: $uri,
            query: $query,
            post: $post,
            headers: new HeaderMap($headers),
            cookies: $cookies,
            files: $files,
            server: $server,
            rawBody: $rawBody
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return strcasecmp($this->method, $method) === 0;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function getQueryString(): ?string
    {
        return $this->queryString;
    }

    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function getHost(): string
    {
        return $this->headers->get('host') ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    public function getPort(): int
    {
        return (int)($this->server['SERVER_PORT'] ?? ($this->isSecure() ? 443 : 80));
    }

    public function getClientIp(): string
    {
        return $this->headers->get('x-forwarded-for')
            ?? $this->headers->get('x-real-ip')
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || ($this->server['HTTPS'] ?? '') === '1'
            || strtolower($this->headers->get('x-forwarded-proto') ?? '') === 'https';
    }

    public function isAjax(): bool
    {
        return strtolower($this->headers->get('x-requested-with') ?? '') === 'xmlhttprequest';
    }

    public function isJson(): bool
    {
        $contentType = $this->headers->get('content-type', '');
        return str_contains(strtolower($contentType), '/json') || str_contains(strtolower($contentType), '+json');
    }

    public function wantsJson(): bool
    {
        $acceptable = $this->headers->get('accept', '');
        return str_contains(strtolower($acceptable), '/json') || str_contains(strtolower($acceptable), '+json');
    }

    // --- Headers & Cookies ---
    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers->get($key, $default);
    }

    public function headers(): HeaderMap
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    // --- Inputs ---
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = $this->all();
        if ($key === null) {
            return $all;
        }

        // Support dot notation: 'user.name'
        if (str_contains($key, '.')) {
            $array = $all;
            foreach (explode('.', $key) as $segment) {
                if (is_array($array) && array_key_exists($segment, $array)) {
                    $array = $array[$segment];
                } else {
                    return $default;
                }
            }
            return $array;
        }

        return $all[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->parsedJson === null) {
            return $default;
        }
        if ($key === null) {
            return $this->parsedJson;
        }
        if (is_array($this->parsedJson)) {
            return $this->parsedJson[$key] ?? $default;
        }
        return $default;
    }

    public function all(): array
    {
        $json = is_array($this->parsedJson) ? $this->parsedJson : [];
        return array_merge($this->query, $this->post, $json, $this->routeParams);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        $results = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $results[$key] = $all[$key];
            }
        }
        return $results;
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        foreach ($keys as $key) {
            unset($all[$key]);
        }
        return $all;
    }

    public function has(string $key): bool
    {
        $all = $this->all();
        return array_key_exists($key, $all);
    }

    public function filled(string $key): bool
    {
        $val = $this->input($key);
        return $val !== null && $val !== '' && $val !== [];
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getContent(): string
    {
        return $this->rawBody;
    }

    // --- Files ---
    public function file(string $key): ?UploadedFile
    {
        $file = $this->files[$key] ?? null;
        if ($file instanceof UploadedFile) {
            return $file;
        }
        if (is_array($file) && !empty($file) && $file[0] instanceof UploadedFile) {
            return $file[0];
        }
        return null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]);
    }

    public function files(): array
    {
        return $this->files;
    }

    // --- Route Parameters & Attributes ---
    public function setRouteParams(array $params): static
    {
        $this->routeParams = $params;
        return $this;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function withAttribute(string $key, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    // --- User & Session ---
    public function setUser(?object $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function user(): ?object
    {
        return $this->user;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function session(): ?Session
    {
        return $this->session;
    }

    // --- Validation Subsystem ---
    public function validate(array $rules, array $customMessages = []): array
    {
        $data = $this->all();
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $this->input($field);
            $fieldErrors = [];

            foreach ($ruleList as $rule) {
                $ruleName = $rule;
                $ruleParam = null;

                if (str_contains($rule, ':')) {
                    [$ruleName, $ruleParam] = explode(':', $rule, 2);
                }

                $ruleName = trim($ruleName);

                switch ($ruleName) {
                    case 'required':
                        if ($value === null || $value === '' || $value === []) {
                            $fieldErrors[] = $customMessages["{$field}.required"] ?? "The {$field} field is required.";
                        }
                        break;

                    case 'nullable':
                        if ($value === null || $value === '') {
                            // If nullable and empty, skip further rules for this field
                            break 2;
                        }
                        break;

                    case 'email':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fieldErrors[] = $customMessages["{$field}.email"] ?? "The {$field} must be a valid email address.";
                        }
                        break;

                    case 'min':
                        $min = (float)$ruleParam;
                        if (is_string($value) && mb_strlen($value) < $min) {
                            $fieldErrors[] = $customMessages["{$field}.min"] ?? "The {$field} must be at least {$min} characters.";
                        } elseif (is_numeric($value) && $value < $min) {
                            $fieldErrors[] = $customMessages["{$field}.min"] ?? "The {$field} must be at least {$min}.";
                        } elseif (is_array($value) && count($value) < $min) {
                            $fieldErrors[] = $customMessages["{$field}.min"] ?? "The {$field} must have at least {$min} items.";
                        }
                        break;

                    case 'max':
                        $max = (float)$ruleParam;
                        if (is_string($value) && mb_strlen($value) > $max) {
                            $fieldErrors[] = $customMessages["{$field}.max"] ?? "The {$field} may not be greater than {$max} characters.";
                        } elseif (is_numeric($value) && $value > $max) {
                            $fieldErrors[] = $customMessages["{$field}.max"] ?? "The {$field} may not be greater than {$max}.";
                        } elseif (is_array($value) && count($value) > $max) {
                            $fieldErrors[] = $customMessages["{$field}.max"] ?? "The {$field} may not have more than {$max} items.";
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $fieldErrors[] = $customMessages["{$field}.numeric"] ?? "The {$field} must be a number.";
                        }
                        break;

                    case 'integer':
                        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $fieldErrors[] = $customMessages["{$field}.integer"] ?? "The {$field} must be an integer.";
                        }
                        break;

                    case 'boolean':
                        if ($value !== null && $value !== '' && !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                            $fieldErrors[] = $customMessages["{$field}.boolean"] ?? "The {$field} must be true or false.";
                        }
                        break;

                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            $fieldErrors[] = $customMessages["{$field}.string"] ?? "The {$field} must be a string.";
                        }
                        break;

                    case 'array':
                        if ($value !== null && !is_array($value)) {
                            $fieldErrors[] = $customMessages["{$field}.array"] ?? "The {$field} must be an array.";
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', (string)$ruleParam);
                        if ($value !== null && $value !== '' && !in_array((string)$value, $allowed, true)) {
                            $fieldErrors[] = $customMessages["{$field}.in"] ?? "The selected {$field} is invalid.";
                        }
                        break;

                    case 'not_in':
                        $disallowed = explode(',', (string)$ruleParam);
                        if ($value !== null && $value !== '' && in_array((string)$value, $disallowed, true)) {
                            $fieldErrors[] = $customMessages["{$field}.not_in"] ?? "The selected {$field} is invalid.";
                        }
                        break;

                    case 'regex':
                        if ($value !== null && $value !== '' && !preg_match((string)$ruleParam, (string)$value)) {
                            $fieldErrors[] = $customMessages["{$field}.regex"] ?? "The {$field} format is invalid.";
                        }
                        break;

                    case 'confirmed':
                        $confirmation = $this->input("{$field}_confirmation");
                        if ($value !== $confirmation) {
                            $fieldErrors[] = $customMessages["{$field}.confirmed"] ?? "The {$field} confirmation does not match.";
                        }
                        break;

                    case 'uuid':
                        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
                        if ($value !== null && $value !== '' && !preg_match($pattern, (string)$value)) {
                            $fieldErrors[] = $customMessages["{$field}.uuid"] ?? "The {$field} must be a valid UUID.";
                        }
                        break;

                    case 'ip':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_IP)) {
                            $fieldErrors[] = $customMessages["{$field}.ip"] ?? "The {$field} must be a valid IP address.";
                        }
                        break;

                    case 'url':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                            $fieldErrors[] = $customMessages["{$field}.url"] ?? "The {$field} must be a valid URL.";
                        }
                        break;

                    case 'json':
                        if ($value !== null && $value !== '') {
                            json_decode((string)$value);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $fieldErrors[] = $customMessages["{$field}.json"] ?? "The {$field} must be a valid JSON string.";
                            }
                        }
                        break;

                    case 'file':
                        if (!$this->hasFile($field) || !$this->file($field)?->isValid()) {
                            $fieldErrors[] = $customMessages["{$field}.file"] ?? "The {$field} must be a valid uploaded file.";
                        }
                        break;
                }
            }

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            } else {
                $validated[$field] = $value;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $validated;
    }
}
