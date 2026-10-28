<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

/** Provider evidence. Null means not established, never an implicit success or rejection. */
final readonly class ExternalVerification
{
    public function __construct(
        public ?bool $valid,
        public string $provider,
        public ?bool $exists = null,
        public ?bool $active = null,
    ) {
    }
}
