<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use KycAi\ExternalShufti\Http\Controllers\ShuftiWebhookController;

$prefix = (string) config('shufti.routes.prefix', 'kyc/webhooks');

Route::post($prefix.'/shufti', ShuftiWebhookController::class)
    ->name('kyc.webhooks.shufti');
