<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

use Daycry\Iban\Enums\ViolationCode;

final readonly class Violation
{
    public function __construct(
        public ViolationCode $code,
        public string $messageKey,
        public string $message
    ) {
    }
}
