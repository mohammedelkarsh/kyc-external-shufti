<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti;

use KycAi\ExternalShufti\Support\ReferenceGenerator;
use KycAi\Laravel\Contracts\ExternalVerifier;
use KycAi\Laravel\Data\ExternalVerificationRequest;
use KycAi\Laravel\Results\ExternalVerificationResult;

final class ShuftiExternalVerifier implements ExternalVerifier
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly ShuftiApiClient $client,
        private readonly array $config = [],
    ) {}

    public function verify(ExternalVerificationRequest $request): ExternalVerificationResult
    {
        $clientId = $this->config['client_id'] ?? null;
        $secret = $this->config['secret'] ?? null;

        if (! is_string($clientId) || $clientId === '' || ! is_string($secret) || $secret === '') {
            return $this->failed('kyc.external.not_configured');
        }

        if ($request->document() === null) {
            return $this->failed('kyc.external.document_required');
        }

        $reference = ReferenceGenerator::make();

        $payload = $this->client->submit($request, $reference);

        if (isset($payload['http_status'])) {
            return $this->failed(
                'kyc.external.provider_error',
                $reference,
                (string) ($payload['event'] ?? ''),
                $payload,
            );
        }

        $event = (string) ($payload['event'] ?? '');

        if ($this->isAccepted($event)) {
            return $this->passed($reference, $event, $payload);
        }

        if ($this->isDeclined($event)) {
            return $this->failed('kyc.external.rejected', $reference, $event, $payload);
        }

        if ($event === 'request.pending' || $event === 'request.received') {
            $payload = $this->poll($reference);

            $event = (string) ($payload['event'] ?? '');

            if ($this->isAccepted($event)) {
                return $this->passed($reference, $event, $payload);
            }

            if ($this->isDeclined($event)) {
                return $this->failed('kyc.external.rejected', $reference, $event, $payload);
            }

            return $this->failed('kyc.external.timeout', $reference, $event, $payload);
        }

        return $this->failed('kyc.external.rejected', $reference, $event, $payload);
    }

    public function sendsDataExternally(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function poll(string $reference): array
    {
        $timeout = (int) ($this->config['poll_seconds'] ?? 30);
        $interval = (int) ($this->config['poll_interval'] ?? 2);
        $deadline = time() + max(1, $timeout);
        $sleeper = $this->config['sleeper'] ?? static function (int $seconds): void {
            sleep($seconds);
        };

        while (time() < $deadline) {
            $sleeper(max(0, $interval));

            $payload = $this->client->status($reference);
            $event = (string) ($payload['event'] ?? '');

            if ($this->isAccepted($event) || $this->isDeclined($event)) {
                return $payload;
            }
        }

        return ['event' => 'request.timeout', 'reference' => $reference];
    }

    private function isAccepted(string $event): bool
    {
        return in_array($event, ['verification.accepted', 'request.accepted'], true);
    }

    private function isDeclined(string $event): bool
    {
        return in_array($event, ['verification.declined', 'request.invalid', 'verification.cancelled'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function passed(string $reference, string $event, array $payload): ExternalVerificationResult
    {
        return new ExternalVerificationResult(
            passed: true,
            provider: 'shufti',
            meta: [
                'reference' => $reference,
                'event' => $event,
                'payload' => $payload,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function failed(
        string $reason,
        ?string $reference = null,
        string $event = '',
        array $payload = [],
    ): ExternalVerificationResult {
        return new ExternalVerificationResult(
            passed: false,
            provider: 'shufti',
            meta: array_filter([
                'reference' => $reference,
                'event' => $event !== '' ? $event : null,
                'payload' => $payload !== [] ? $payload : null,
            ]),
            failureReason: $reason,
        );
    }
}
