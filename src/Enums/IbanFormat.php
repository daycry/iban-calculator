<?php

declare(strict_types=1);

namespace Daycry\Iban\Enums;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
enum IbanFormat
{
    case Electronic;
    case Print;
    case Anonymized;
}
