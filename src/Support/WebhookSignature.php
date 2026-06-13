<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Support;

use Illuminate\Http\Request;

final class WebhookSignature
{
    public static function isValid(Request $request, ?string $secret): bool
    {
        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $signature = $request->header('Signature')
            ?? $request->header('sp_signature')
            ?? $request->input('signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $payload = $request->getContent();
        $expected = hash('sha256', $payload.hash('sha256', $secret));

        return hash_equals($expected, $signature);
    }
}
