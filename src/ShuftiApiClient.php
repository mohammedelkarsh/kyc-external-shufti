<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use KycAi\Laravel\Data\ExternalVerificationRequest;

final class ShuftiApiClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function submit(ExternalVerificationRequest $request, string $reference): array
    {
        $document = $request->document();

        if ($document === null) {
            return ['event' => 'request.invalid', 'error' => 'document_missing'];
        }

        $payload = [
            'reference' => $reference,
            'country' => strtoupper($request->country()),
            'document' => [
                'proof' => $document->base64(),
                'supported_types' => ['id_card', 'passport', 'driving_license'],
                'document_number' => $request->nationalId(),
            ],
        ];

        $callbackUrl = $this->config['callback_url'] ?? null;

        if (is_string($callbackUrl) && $callbackUrl !== '') {
            $payload['callback_url'] = $callbackUrl;
        }

        return $this->decode($this->post('', $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $reference): array
    {
        return $this->decode($this->post('/status', ['reference' => $reference]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): Response
    {
        $clientId = $this->config['client_id'] ?? null;
        $secret = $this->config['secret'] ?? null;
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://api.shuftipro.com'), '/');

        return Http::withBasicAuth((string) $clientId, (string) $secret)
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->post($baseUrl.($path === '' ? '' : $path), $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            return [
                'event' => 'request.invalid',
                'http_status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
