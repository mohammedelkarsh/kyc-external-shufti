<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Tests;

use Illuminate\Support\Facades\Http;
use KycAi\ExternalShufti\ShuftiApiClient;
use KycAi\ExternalShufti\ShuftiExternalVerifier;
use KycAi\Laravel\Data\ExternalVerificationRequest;
use KycAi\Laravel\Kyc;
use KycAi\Laravel\KycLevel;
use KycAi\Laravel\Support\DocumentSource;

final class ShuftiExternalVerifierTest extends TestCase
{
    public function test_not_configured_without_credentials(): void
    {
        $verifier = new ShuftiExternalVerifier(new ShuftiApiClient([]), []);

        $result = $verifier->verify(new ExternalVerificationRequest(
            'sa',
            '1001244084',
            document: DocumentSource::fromMixed($this->tempFile('id.jpg')),
        ));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.not_configured', $result->failureReason());
        $this->assertTrue($verifier->sendsDataExternally());
    }

    public function test_document_required_when_missing(): void
    {
        $verifier = new ShuftiExternalVerifier(new ShuftiApiClient([
            'client_id' => 'id',
            'secret' => 'secret',
        ]), [
            'client_id' => 'id',
            'secret' => 'secret',
        ]);

        $result = $verifier->verify(new ExternalVerificationRequest('sa', '1001244084'));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.document_required', $result->failureReason());
    }

    public function test_submit_accepted_passes(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::response(['event' => 'verification.accepted', 'reference' => 'kyc-1']),
        ]);

        $verifier = $this->makeVerifier();

        $result = $verifier->verify(new ExternalVerificationRequest(
            'sa',
            '1001244084',
            document: DocumentSource::fromMixed($this->tempFile('id.jpg')),
        ));

        $this->assertTrue($result->passed());
        $this->assertSame('shufti', $result->provider());
    }

    public function test_submit_declined_fails(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::response(['event' => 'verification.declined']),
        ]);

        $result = $this->makeVerifier()->verify(new ExternalVerificationRequest(
            'sa',
            '1001244084',
            document: DocumentSource::fromMixed($this->tempFile('id.jpg')),
        ));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.rejected', $result->failureReason());
    }

    public function test_provider_error_on_http_failure(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::response(['error' => 'bad'], 500),
        ]);

        $result = $this->makeVerifier()->verify(new ExternalVerificationRequest(
            'sa',
            '1001244084',
            document: DocumentSource::fromMixed($this->tempFile('id.jpg')),
        ));

        $this->assertFalse($result->passed());
        $this->assertSame('kyc.external.provider_error', $result->failureReason());
    }

    public function test_pending_then_accepted_via_poll(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::sequence()
                ->push(['event' => 'request.pending'])
                ->push(['event' => 'verification.accepted']),
        ]);

        $result = $this->makeVerifier([
            'poll_seconds' => 2,
            'poll_interval' => 0,
            'sleeper' => static function (): void {},
        ])->verify(new ExternalVerificationRequest(
            'sa',
            '1001244084',
            document: DocumentSource::fromMixed($this->tempFile('id.jpg')),
        ));

        $this->assertTrue($result->passed());
    }

    public function test_full_pipeline_integration(): void
    {
        Http::fake([
            'https://api.shuftipro.com*' => Http::response(['event' => 'verification.accepted']),
        ]);

        config([
            'kyc.external_verification.enabled' => true,
            'kyc.external_verification.default' => 'shufti',
            'shufti.client_id' => 'id',
            'shufti.secret' => 'secret',
        ]);

        $this->refreshKycContainer();

        app(Kyc::class)->fake()->willExtractId('1001244084');

        $result = app(Kyc::class)->document($this->tempFile('1001244084.jpg'))
            ->country('sa')
            ->level(KycLevel::Full)
            ->verify();

        $this->assertTrue($result->passed());
        $this->assertTrue($result->external()?->passed());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVerifier(array $overrides = []): ShuftiExternalVerifier
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
}
