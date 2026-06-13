<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti;

use Illuminate\Support\ServiceProvider;
use KycAi\Laravel\Support\ExternalDriverRegistry;

final class ShuftiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shufti.php', 'shufti');

        $this->mergeKycDriverConfig();
    }

    public function boot(): void
    {
        $this->mergeKycDriverConfig();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shufti.php' => config_path('shufti.php'),
            ], 'kyc-shufti-config');
        }

        ExternalDriverRegistry::register('shufti', function (array $config): ShuftiExternalVerifier {
            /** @var array<string, mixed> $shuftiConfig */
            $shuftiConfig = array_replace_recursive($this->app['config']->get('shufti', []), $config);

            return new ShuftiExternalVerifier(
                new ShuftiApiClient($shuftiConfig),
                $shuftiConfig,
            );
        });

        if (config('shufti.routes.webhook', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
        }
    }

    private function mergeKycDriverConfig(): void
    {
        if (! $this->app['config']->has('kyc')) {
            return;
        }

        /** @var array<string, mixed> $shufti */
        $shufti = $this->app['config']->get('shufti', []);
        /** @var array<string, mixed> $drivers */
        $drivers = $this->app['config']->get('kyc.external_verification.drivers', []);
        $drivers['shufti'] = array_replace_recursive($drivers['shufti'] ?? [], $shufti);

        $this->app['config']->set('kyc.external_verification.drivers', $drivers);
    }
}
