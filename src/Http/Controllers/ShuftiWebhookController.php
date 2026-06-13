<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KycAi\ExternalShufti\Events\ShuftiVerificationCompleted;
use KycAi\ExternalShufti\Support\WebhookSignature;

final class ShuftiWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $config */
        $config = config('shufti', []);
        $secret = $config['secret'] ?? null;

        if (! WebhookSignature::isValid($request, is_string($secret) ? $secret : null)) {
            abort(403, 'Invalid Shufti webhook signature.');
        }

        $reference = $request->input('reference');
        $event = $request->input('event');

        event(new ShuftiVerificationCompleted(
            reference: is_string($reference) ? $reference : null,
            event: is_string($event) ? $event : null,
            payload: $request->all(),
        ));

        return response()->json(['received' => true]);
    }
}
