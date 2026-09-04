<?php
declare(strict_types=1);

namespace Plugins\OshimBilling;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Container\Container;
use Exception;

class WebhookController
{
    public function handle(Request $request): Response
    {
        $client = Container::getInstance()->get(StripeClient::class);
        $payload = $request->getContent();
        $sigHeader = $request->server('HTTP_STRIPE_SIGNATURE') ?? '';
        $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_test';

        try {
            if (!$client->verifyWebhookSignature($payload, $sigHeader, $secret)) {
                return Response::json(['error' => 'Invalid signature'], 400);
            }

            $event = json_decode($payload, true);
            $type = $event['type'] ?? 'unknown';

            // Fire an OSHIM event here if EventDispatcher is available, 
            // so developers can hook into it without changing this controller.
            
            return Response::json(['status' => 'success', 'event' => $type]);
            
        } catch (Exception $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }
    }
}
