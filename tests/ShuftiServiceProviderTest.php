<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Tests;

use KycAi\ExternalShufti\ShuftiExternalVerifier;
use KycAi\Laravel\KycManager;
use KycAi\Laravel\Support\ExternalDriverRegistry;

final class ShuftiServiceProviderTest extends TestCase
{
    public function test_registers_shufti_driver(): void
    {
        config([
            'kyc.external_verification.enabled' => true,
        ]);

        $this->refreshKycContainer();

        $this->assertTrue(ExternalDriverRegistry::has('shufti'));

        $verifier = app(KycManager::class)->externalVerifier('shufti');

        $this->assertInstanceOf(ShuftiExternalVerifier::class, $verifier);
    }

    public function test_merges_shufti_config_into_kyc_drivers(): void
    {
        config([
            'shufti.client_id' => 'client',
            'shufti.secret' => 'secret',
        ]);

        $this->refreshKycContainer();

        $drivers = config('kyc.external_verification.drivers.shufti');

        $this->assertSame('client', $drivers['client_id']);
        $this->assertSame('secret', $drivers['secret']);
    }
}
