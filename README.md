# kyc-ai/external-shufti

[![Tests](https://github.com/mohammedelkarsh/kyc-external-shufti/actions/workflows/tests.yml/badge.svg)](https://github.com/mohammedelkarsh/kyc-external-shufti/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Shufti Pro driver for [kyc-ai/laravel](https://packagist.org/packages/kyc-ai/laravel).

> **v1.0** · submit + poll · webhook · SA/AE/EG via KYC pipeline

---

## Requirements

- PHP ^8.2
- `kyc-ai/laravel` ^1.1
- Shufti Pro API credentials

---

## Install

```bash
composer require kyc-ai/laravel:^1.1
composer require kyc-ai/external-shufti

php artisan vendor:publish --tag=kyc-shufti-config
```

---

## Configure

```env
KYC_EXTERNAL_ENABLED=true
KYC_EXTERNAL_DRIVER=shufti

SHUFTI_CLIENT_ID=
SHUFTI_SECRET=
SHUFTI_BASE_URL=https://api.shuftipro.com
SHUFTI_CALLBACK_URL="${APP_URL}/kyc/webhooks/shufti"
SHUFTI_POLL_SECONDS=30
SHUFTI_POLL_INTERVAL=2
SHUFTI_WEBHOOK_ROUTE=true
```

---

## Usage

```php
use KycAi\Laravel\Facades\Kyc;
use KycAi\Laravel\KycLevel;

$result = Kyc::document($request->file('id_front'))
    ->country('sa')
    ->level(KycLevel::Full)
    ->verify();

$result->external()?->provider(); // "shufti"
$result->external()?->meta();     // reference, event, payload
```

---

## Webhook

`POST /kyc/webhooks/shufti` validates the Shufti signature and dispatches `ShuftiVerificationCompleted`.

---

## Development

Monorepo setup:

```bash
cp composer.local.json.example composer.local.json
composer install
composer test
```

---

## License

MIT — see [LICENSE](LICENSE).
