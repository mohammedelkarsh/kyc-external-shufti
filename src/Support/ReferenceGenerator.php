<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Support;

use Illuminate\Support\Str;

final class ReferenceGenerator
{
    public static function make(?string $prefix = 'kyc'): string
    {
        return ($prefix !== null && $prefix !== '' ? $prefix.'-' : '').Str::uuid()->toString();
    }
}
