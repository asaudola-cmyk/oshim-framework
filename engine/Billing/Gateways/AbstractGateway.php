<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

use Oshim\Http\Request;

abstract class AbstractGateway implements GatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfig(string $key, mixed $value): static
    {
        $this->config[$key] = $value;
        return $this;
    }

    protected function extractPayload(Request|array $request): array
    {
        if ($request instanceof Request) {
            $json = $request->json();
            if (!empty($json)) {
                return $json;
            }
            return array_merge($request->getQueryParams(), $request->getParsedBody());
        }
        return $request;
    }
}
