<?php
declare(strict_types=1);

namespace Oshim\Ui;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Ui\Exceptions\UiException;
use Oshim\Ui\Exceptions\InvalidSignatureException;
use Oshim\Ui\Exceptions\ComponentNotFoundException;
use Throwable;

class UiManager
{
    public function __construct(
        protected ComponentRegistry $registry,
        protected DiffEngine $diffEngine
    ) {
    }

    public function getRegistry(): ComponentRegistry
    {
        return $this->registry;
    }

    public function getDiffEngine(): DiffEngine
    {
        return $this->diffEngine;
    }

    /**
     * Render a component by alias or instance.
     */
    public function render(string|Component $component, array $props = []): string
    {
        if (is_string($component)) {
            $component = $this->registry->resolve($component, $props);
        }

        return $component->render();
    }

    /**
     * Handles incoming reactive action HTTP POST request (/oshim/ui/action or /__oshim_event).
     */
    public function handleAction(Request $request): Response
    {
        try {
            $data = $request->json() ?: $request->post() ?: $request->all() ?: [];
            if (!is_array($data)) {
                $data = [];
            }

            $componentId = (string)($data['id'] ?? ($data['component_id'] ?? ''));
            $componentName = (string)($data['component'] ?? ($data['name'] ?? ''));
            $action = (string)($data['action'] ?? '');
            $payload = (array)($data['payload'] ?? []);
            $rawState = $data['state'] ?? [];
            $sig = (string)($data['sig'] ?? ($data['checksum'] ?? ($data['signature'] ?? '')));

            if ($componentName === '' || $action === '') {
                return Response::json([
                    'success' => false,
                    'error'   => 'Missing required component name or action.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Decode state if base64-encoded string or JSON string
            $state = [];
            if (is_string($rawState) && $rawState !== '') {
                $decoded = base64_decode($rawState, true);
                if (is_string($decoded) && json_validate($decoded)) {
                    $state = json_decode($decoded, true) ?: [];
                } elseif (json_validate($rawState)) {
                    $state = json_decode($rawState, true) ?: [];
                } else {
                    throw new InvalidSignatureException("Malformed state payload.");
                }
            } elseif (is_array($rawState)) {
                $state = $rawState;
            }

            $result = $this->processAction($componentName, $componentId ?: null, $action, $payload, $state, $sig);

            return Response::json($result, Response::HTTP_OK);
        } catch (InvalidSignatureException $e) {
            return Response::json([
                'success' => false,
                'error'   => 'State signature verification failed. Property tampering detected.',
                'code'    => 'INVALID_SIGNATURE',
            ], Response::HTTP_FORBIDDEN);
        } catch (ComponentNotFoundException $e) {
            return Response::json([
                'success' => false,
                'error'   => $e->getMessage(),
                'code'    => 'COMPONENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        } catch (UiException $e) {
            return Response::json([
                'success' => false,
                'error'   => $e->getMessage(),
                'code'    => 'UI_ERROR',
            ], Response::HTTP_BAD_REQUEST);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'error'   => 'Internal server error processing component action.',
                'details' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Internal processor verifying signature, hydrating, dispatching, and diffing.
     */
    public function processAction(
        string $componentName,
        ?string $componentId,
        string $action,
        array $payload,
        array $state,
        string $sig
    ): array {
        /** @var Component $component */
        $component = $this->registry->resolve($componentName, [], $componentId);

        // Security check: verify HMAC signature
        if ($sig === '' || !$component->verifySignature($state, $sig)) {
            throw new InvalidSignatureException("HMAC signature verification failed for component [{$componentName}].");
        }

        // Hydrate verified state
        $component->hydrate($state);

        // Capture previous render for granular diffing
        $oldHtml = $component->render();

        // Dispatch action
        $actionReturn = $component->dispatch($action, $payload);

        // Re-render after state modification
        $newHtml = $component->render();
        $updatedState = $component->getState();
        $newEncodedState = base64_encode(json_encode($updatedState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $newSig = $component->generateSignature($updatedState);

        // Calculate atomic patches
        $patches = $this->diffEngine->diff($oldHtml, $newHtml, $component->getId());

        return [
            'success'      => true,
            'id'           => $component->getId(),
            'component'    => $componentName,
            'action'       => $action,
            'html'         => $newHtml,
            'state'        => $updatedState,
            'statePayload' => $newEncodedState,
            'sig'          => $newSig,
            'patches'      => $patches,
            'emitted'      => $component->getEmittedEvents(),
            'result'       => $actionReturn,
        ];
    }

    /**
     * SSE Telemetry Stream Endpoint (/oshim/ui/sse).
     */
    public function handleSse(Request $request): Response
    {
        return Response::sse(function () use ($request) {
            $channel = $request->query('channel', 'global');
            $componentId = $request->query('component_id');

            // Send initial connection ACK
            echo "event: connected\ndata: " . json_encode(['status' => 'connected', 'channel' => $channel, 'component_id' => $componentId]) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        });
    }
}
