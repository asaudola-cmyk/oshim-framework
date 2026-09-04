<?php
declare(strict_types=1);

namespace Oshim\Http\Router;

class RouteGroup
{
    public function __construct(
        protected string $prefix = '',
        protected array $middlewares = [],
        protected ?string $as = null,
        protected ?string $namespace = null,
        protected array $wheres = []
    ) {}

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function getAs(): ?string
    {
        return $this->as;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getWheres(): array
    {
        return $this->wheres;
    }

    /**
     * Merge parent group with child attributes to construct new nested group.
     */
    public static function merge(RouteGroup $parent, array $child): RouteGroup
    {
        // 1. Prefix: /admin + /users -> /admin/users
        $childPrefix = trim($child['prefix'] ?? '', '/');
        $parentPrefix = trim($parent->getPrefix(), '/');
        $prefix = '/' . trim("{$parentPrefix}/{$childPrefix}", '/');
        if ($prefix !== '/') {
            $prefix = rtrim($prefix, '/');
        }

        // 2. Middlewares
        $childMw = (array)($child['middleware'] ?? $child['middlewares'] ?? []);
        $middlewares = array_merge($parent->getMiddlewares(), $childMw);

        // 3. Name prefix (as): admin. + users. -> admin.users.
        $childAs = $child['as'] ?? null;
        $parentAs = $parent->getAs();
        $as = ($parentAs !== null ? $parentAs : '') . ($childAs !== null ? $childAs : '');
        $as = $as !== '' ? $as : null;

        // 4. Namespace
        $childNs = $child['namespace'] ?? null;
        $parentNs = $parent->getNamespace();
        $namespace = $childNs ?? $parentNs;

        // 5. Wheres
        $wheres = array_merge($parent->getWheres(), (array)($child['where'] ?? $child['wheres'] ?? []));

        return new RouteGroup(
            prefix: $prefix,
            middlewares: $middlewares,
            as: $as,
            namespace: $namespace,
            wheres: $wheres
        );
    }
}
