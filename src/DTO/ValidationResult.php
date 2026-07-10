<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

final readonly class ValidationResult
{
    /**
     * @param Violation[] $violations
     */
    public function __construct(
        public bool $valid,
        public array $violations
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return Violation[]
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function firstViolation(): ?Violation
    {
        return $this->violations[0] ?? null;
    }
}
