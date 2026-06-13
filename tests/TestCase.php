<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Tests;

use KycAi\ExternalShufti\ShuftiServiceProvider;
use KycAi\Laravel\Kyc;
use KycAi\Laravel\KycServiceProvider;
use KycAi\Laravel\Support\ExternalDriverRegistry;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            KycServiceProvider::class,
            ShuftiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('kyc', require $this->kycConfigPath());
        $app['config']->set('shufti', require dirname(__DIR__).'/config/shufti.php');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('kyc.extraction.default', 'fake');
        $app['config']->set('kyc.external_verification.enabled', false);
    }

    protected function tearDown(): void
    {
        Kyc::resetFakeState();
        ExternalDriverRegistry::flush();

        parent::tearDown();
    }

    protected function tempFile(string $name, string $contents = 'fake-image'): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('shufti_', true).'_'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    protected function refreshKycContainer(): void
    {
        $this->app->forgetInstance(\KycAi\Laravel\KycManager::class);
        $this->app->forgetInstance(\KycAi\Laravel\KycVerifier::class);
        $this->app->forgetInstance(Kyc::class);

        $provider = new ShuftiServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    private function kycConfigPath(): string
    {
        $paths = [
            dirname(__DIR__, 2).'/vendor/kyc-ai/laravel/config/kyc.php',
            dirname(__DIR__, 2).'/laravel-kyc-ai/config/kyc.php',
            dirname(__DIR__, 2).'/../laravel-kyc-ai/config/kyc.php',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Unable to locate kyc-ai/laravel config. Run composer install.');
    }
}
