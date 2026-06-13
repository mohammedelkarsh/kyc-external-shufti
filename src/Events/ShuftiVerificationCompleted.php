<?php

declare(strict_types=1);

namespace KycAi\ExternalShufti\Events;

final class ShuftiVerificationCompleted
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ?string $reference,
        public readonly ?string $event,
        public readonly array $payload,
    ) {}
}
