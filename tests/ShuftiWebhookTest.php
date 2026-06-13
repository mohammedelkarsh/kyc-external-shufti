<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Tests;

use Illuminate\Support\Facades\Event;
use KycAi\ExternalShufti\Events\ShuftiVerificationCompleted;
use KycAi\ExternalShufti\Support\WebhookSignature;

final class ShuftiWebhookTest extends TestCase
{
    public function test_webhook_rejects_invalid_signature(): void
    {
        config([
            'shufti.secret' => 'secret',
            'shufti.routes.webhook' => true,
        ]);

        $response = $this->postJson('/kyc/webhooks/shufti', [
            'reference' => 'kyc-1',
            'event' => 'verification.accepted',
        ]);

        $response->assertForbidden();
    }

    public function test_webhook_dispatches_event_with_valid_signature(): void
    {
        Event::fake([ShuftiVerificationCompleted::class]);

        config([
            'shufti.secret' => 'secret',
            'shufti.routes.webhook' => true,
        ]);

        $payload = json_encode([
            'reference' => 'kyc-1',
            'event' => 'verification.accepted',
        ], JSON_THROW_ON_ERROR);

        $signature = hash('sha256', $payload.hash('sha256', 'secret'));

        $response = $this->call(
            'POST',
            '/kyc/webhooks/shufti',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Signature' => $signature,
            ],
            $payload,
        );

        $response->assertOk();
        Event::assertDispatched(ShuftiVerificationCompleted::class, function (ShuftiVerificationCompleted $event): bool {
            return $event->reference === 'kyc-1'
                && $event->event === 'verification.accepted';
        });
    }

    public function test_signature_helper(): void
    {
        $payload = '{"reference":"kyc-1"}';
        $secret = 'secret';
        $signature = hash('sha256', $payload.hash('sha256', $secret));

        $request = \Illuminate\Http\Request::create('/kyc/webhooks/shufti', 'POST', [], [], [], [
            'HTTP_Signature' => $signature,
        ], $payload);

        $this->assertTrue(WebhookSignature::isValid($request, $secret));
    }
}
