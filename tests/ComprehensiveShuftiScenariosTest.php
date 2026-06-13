<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Tests;

use Illuminate\Support\Facades\Http;
use KycAi\ExternalShufti\ShuftiApiClient;
use KycAi\ExternalShufti\ShuftiExternalVerifier;
use KycAi\Laravel\Data\ExternalVerificationRequest;
use KycAi\Laravel\Exceptions\KycException;
use KycAi\Laravel\Kyc;
use KycAi\Laravel\KycLevel;
use KycAi\Laravel\Support\DocumentSource;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * End-to-end matrix for Shufti driver + KYC pipeline combinations.
 */
final class ComprehensiveShuftiScenariosTest extends TestCase
{
    #[DataProvider('acceptedEvents')]
    public function test_immediate_accept_events(string $event): void
    {
        Http::fake(['https://api.shuftipro.com*' => Http::response(['event' => $event])]);

        $result = $this->verifier()->verify($this->request('sa', '1001244084'));

        $this->assertTrue($result->passed());
        $this->assertSame($event, $result->meta()['event']);
        $this->assertNotEmpty($result->meta()['reference']);
    }

    #[DataProvider('declinedEvents')]
    public function test_immediate_decline_events(string $event): void
    {
        Http::fake(['https://api.shuftipro.com*' => Http::response(['event' => $event])]);

        $result = $this->verifier()->verify($this->request('sa', '1001244084'));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.rejected', $result->failureReason());
    }

    public function test_request_received_polls_then_accepts(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::sequence()
                ->push(['event' => 'request.received'])
                ->push(['event' => 'verification.accepted']),
        ]);

        $result = $this->verifier(['poll_seconds' => 2, 'poll_interval' => 0])
            ->verify($this->request('sa', '1001244084'));

        $this->assertTrue($result->passed());
    }

    public function test_pending_poll_declined(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::sequence()
                ->push(['event' => 'request.pending'])
                ->push(['event' => 'verification.declined']),
        ]);

        $result = $this->verifier(['poll_seconds' => 2, 'poll_interval' => 0])
            ->verify($this->request('sa', '1001244084'));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.rejected', $result->failureReason());
    }

    public function test_poll_timeout_when_status_never_resolves(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::response(['event' => 'request.pending']),
        ]);

        $result = $this->verifier(['poll_seconds' => 0, 'poll_interval' => 0])
            ->verify($this->request('sa', '1001244084'));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.timeout', $result->failureReason());
    }

    public function test_unknown_event_is_rejected(): void
    {
        Http::fake(['https://api.shuftipro.com*' => Http::response(['event' => 'something.unknown'])]);

        $result = $this->verifier()->verify($this->request('sa', '1001244084'));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.rejected', $result->failureReason());
    }

    #[DataProvider('countryScenarios')]
    public function test_full_pipeline_by_country(string $country, string $nationalId, ?string $requiredPackage = null): void
    {
        if ($requiredPackage !== null && ! class_exists($this->packageClass($requiredPackage))) {
            $this->markTestSkipped("Requires {$requiredPackage}");
        }

        Http::fake(['https://api.shuftipro.com*' => Http::response(['event' => 'verification.accepted'])]);

        $this->enableShufti();

        app(Kyc::class)->fake()->willExtractId($nationalId);

        $result = app(Kyc::class)->document($this->tempFile("{$nationalId}.jpg"))
            ->country($country)
            ->level(KycLevel::Full)
            ->verify();

        $this->assertTrue($result->passed(), "Failed for country {$country}");
        $this->assertTrue($result->internal()?->passed() ?? false);
        $this->assertTrue($result->external()?->passed() ?? false);
    }

    public function test_internal_invalid_id_fails_even_when_shufti_would_accept(): void
    {
        Http::fake(['https://api.shuftipro.com*' => Http::response(['event' => 'verification.accepted'])]);

        $this->enableShufti();

        app(Kyc::class)->fake()->willExtractId('1001244080'); // invalid checksum

        $result = app(Kyc::class)->document($this->tempFile('bad.jpg'))
            ->country('sa')
            ->level(KycLevel::Full)
            ->verify();

        $this->assertFalse($result->passed());
        $this->assertFalse($result->internal()?->passed() ?? true);
    }

    public function test_standard_level_with_explicit_shufti_driver(): void
    {
        Http::fake(['https://api.shuftipro.com*' => Http::response(['event' => 'verification.accepted'])]);

        config([
            'kyc.external_verification.enabled' => true,
            'shufti.client_id' => 'id',
            'shufti.secret' => 'secret',
            'shufti.poll_seconds' => 1,
            'shufti.poll_interval' => 0,
            'shufti.sleeper' => static function (): void {},
        ]);
        $this->refreshKycContainer();

        app(Kyc::class)->fake()->willExtractId('1001244084');

        $result = app(Kyc::class)->document($this->tempFile('id.jpg'))
            ->country('sa')
            ->level(KycLevel::Standard)
            ->verifyWith('shufti')
            ->verify();

        $this->assertTrue($result->passed());
        $this->assertContains('data_sent_for_external_verification', $result->warnings());
    }

    public function test_internal_level_does_not_call_shufti(): void
    {
        Http::fake();

        $this->enableShufti();

        $result = app(Kyc::class)->number('1001244084')
            ->country('sa')
            ->level(KycLevel::Internal)
            ->verify();

        $this->assertTrue($result->passed());
        $this->assertNull($result->external());
        Http::assertNothingSent();
    }

    public function test_full_level_without_document_throws(): void
    {
        $this->enableShufti();

        $this->expectException(KycException::class);

        app(Kyc::class)->number('1001244084')
            ->country('sa')
            ->level(KycLevel::Full)
            ->verify();
    }

    public function test_verify_with_shufti_when_external_disabled_throws(): void
    {
        config(['kyc.external_verification.enabled' => false]);
        $this->refreshKycContainer();

        $this->expectException(KycException::class);

        app(Kyc::class)->number('1001244084')
            ->country('sa')
            ->verifyWith('shufti')
            ->verify();
    }

    public function test_submit_includes_callback_url_when_configured(): void
    {
        Http::fake(['https://api.shuftipro.com*' => Http::response(['event' => 'verification.accepted'])]);

        $config = [
            'client_id' => 'id',
            'secret' => 'secret',
            'callback_url' => 'https://app.test/kyc/webhooks/shufti',
            'poll_seconds' => 1,
            'poll_interval' => 0,
            'sleeper' => static function (): void {},
        ];

        (new ShuftiExternalVerifier(new ShuftiApiClient($config), $config))
            ->verify($this->request('sa', '1001244084'));

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return ($body['callback_url'] ?? null) === 'https://app.test/kyc/webhooks/shufti'
                && strtoupper((string) ($body['country'] ?? '')) === 'SA'
                && isset($body['document']['proof'])
                && ($body['document']['document_number'] ?? null) === '1001244084';
        });
    }

    public function test_webhook_accepts_sp_signature_header(): void
    {
        config(['shufti.secret' => 'secret', 'shufti.routes.webhook' => true]);

        $payload = json_encode(['reference' => 'kyc-2', 'event' => 'verification.accepted'], JSON_THROW_ON_ERROR);
        $signature = hash('sha256', $payload.hash('sha256', 'secret'));

        $response = $this->call(
            'POST',
            '/kyc/webhooks/shufti',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_sp_signature' => $signature],
            $payload,
        );

        $response->assertOk();
    }

    public static function acceptedEvents(): array
    {
        return [
            'verification_accepted' => ['verification.accepted'],
            'request_accepted' => ['request.accepted'],
        ];
    }

    public static function declinedEvents(): array
    {
        return [
            'verification_declined' => ['verification.declined'],
            'request_invalid' => ['request.invalid'],
            'verification_cancelled' => ['verification.cancelled'],
        ];
    }

    public static function countryScenarios(): array
    {
        return [
            'sa' => ['sa', '1001244084', null],
            'ae' => ['ae', '784199000000002', 'validators/ae'],
            'eg' => ['eg', '29001011234564', 'validators/eg'],
        ];
    }

    private function packageClass(string $package): string
    {
        return match ($package) {
            'validators/ae' => \Validators\Ae\EmiratesId::class,
            'validators/eg' => \Validators\Eg\EgyptianNationalId::class,
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function verifier(array $overrides = []): ShuftiExternalVerifier
    {
        $config = array_merge([
            'client_id' => 'id',
            'secret' => 'secret',
            'base_url' => 'https://api.shuftipro.com',
            'poll_seconds' => 1,
            'poll_interval' => 0,
            'sleeper' => static function (): void {},
        ], $overrides);

        return new ShuftiExternalVerifier(new ShuftiApiClient($config), $config);
    }

    private function request(string $country, string $nationalId): ExternalVerificationRequest
    {
        return new ExternalVerificationRequest(
            $country,
            $nationalId,
            document: DocumentSource::fromMixed($this->tempFile('id.jpg')),
        );
    }

    private function enableShufti(): void
    {
        config([
            'kyc.external_verification.enabled' => true,
            'kyc.external_verification.default' => 'shufti',
            'shufti.client_id' => 'id',
            'shufti.secret' => 'secret',
            'shufti.poll_seconds' => 1,
            'shufti.poll_interval' => 0,
            'shufti.sleeper' => static function (): void {},
        ]);

        $this->refreshKycContainer();
    }
}
