<?php
declare(strict_types=1);

namespace Oshim\Ui\Router;

use Oshim\Http\Request;
use Oshim\Http\Response;

/**
 * Next.js-style App Router with Nested Layout Preservation and Soft Navigation support.
 */
class AppRouter
{
    /** @var array<string, Page> */
    private array $pages = [];
    private ?Layout $rootLayout = null;

    public function setRootLayout(Layout $layout): self
    {
        $this->rootLayout = $layout;
        return $this;
    }

    public function page(string $path, callable $renderFn, ?Layout $layout = null, string $title = 'OSHIM App'): self
    {
        $effectiveLayout = $layout ?? $this->rootLayout;
        $this->pages[$path] = new Page($path, $renderFn, $effectiveLayout, $title);
        return $this;
    }

    public function resolve(string $uri): ?array
    {
        $parsed = parse_url($uri, PHP_URL_PATH);
        $parsedUri = ($parsed !== false && $parsed !== null) ? $parsed : '/';
        $path = rtrim($parsedUri, '/');
        if ($path === '') {
            $path = '/';
        }

        // Exact match
        if (isset($this->pages[$path])) {
            return [
                'page' => $this->pages[$path],
                'params' => [],
            ];
        }

        // Dynamic segment matching: e.g. /vps/[id]
        foreach ($this->pages as $pagePath => $page) {
            $pattern = preg_replace('/\[([a-zA-Z0-9_]+)\]/', '(?P<$1>[^/]+)', $pagePath);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $params = array_map('urldecode', $params);
                return [
                    'page' => $page,
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Dispatch request and return Response with partial or full layout rendering.
     */
    public function dispatch(Request|string $request): ?Response
    {
        $uri = is_string($request) ? $request : $request->getUri();
        $isSoftNav = false;
        if ($request instanceof Request) {
            $isSoftNav = $request->header('X-Oshim-Soft-Nav') === '1';
        }

        $resolved = $this->resolve($uri);
        if ($resolved === null) {
            return null;
        }

        /** @var Page $page */
        $page = $resolved['page'];
        $params = $resolved['params'];

        $html = $page->renderFull($params);

        return Response::html($html);
    }
}
