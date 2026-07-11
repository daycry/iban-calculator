<?php

declare(strict_types=1);

use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Iban as IbanService;

/**
 * Procedural convenience wrappers around `service('iban')`
 * ({@see \Daycry\Iban\Iban}), the `daycry/iban` facade.
 *
 * This file is a CI4 helper: no namespace, every function guarded by
 * `function_exists()` so re-inclusion (e.g. a consuming app that also
 * defines an `iban_helper.php`) never fatals with "Cannot redeclare".
 *
 * Loading: CI4's `helper()` locator searches every registered namespace's
 * `Helpers/` directory for a matching filename. Because this package
 * publishes the `Daycry\Iban\` => `src/` PSR-4 mapping, a consuming
 * application gets this file for free just by calling `helper('iban')` —
 * no manual `require` and no `Config\Autoload::$helpers` entry needed
 * (though adding `'iban'` there works too, and makes it always-on instead
 * of load-on-demand). `src/Config/Registrar.php` intentionally does NOT
 * declare an `Autoload()` hook for this: `Config\Autoload` doesn't extend
 * `BaseConfig`, so CI4's Registrar-merging never visits it — such a hook
 * would be dead code.
 *
 * @see \Daycry\Iban\Config\Registrar
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */

if (! function_exists('iban_validate')) {
    /**
     * Validates an IBAN and returns the full result (including violations).
     *
     * Never throws: an invalid IBAN simply produces a non-valid result.
     *
     * @param bool $checkNational Whether to also run national check-digit
     *                             validation, mirroring the facade's
     *                             `validate()` parameter of the same name.
     */
    function iban_validate(string $iban, bool $checkNational = false): ValidationResult
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->validate($iban, $checkNational);
    }
}

if (! function_exists('iban_is_valid')) {
    /**
     * Quick boolean IBAN validity check. Never throws.
     */
    function iban_is_valid(string $iban): bool
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->isValid($iban);
    }
}

if (! function_exists('iban_parse')) {
    /**
     * Parses an IBAN into its structural parts.
     *
     * Never throws: returns `null` for an invalid IBAN instead of raising
     * `InvalidIbanException` (unlike the facade's `parse()`, this always
     * uses `tryParse()` under the hood).
     */
    function iban_parse(string $iban): ?ParsedIban
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->tryParse($iban);
    }
}

if (! function_exists('iban_format')) {
    /**
     * Formats an IBAN string per `$format`: `'electronic'`, `'print'`
     * (default), or `'anonymized'` (case-insensitive). Any other value
     * falls back to `'print'`.
     */
    function iban_format(string $iban, string $format = 'print'): string
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        $f = match (strtolower($format)) {
            'electronic' => IbanFormat::Electronic,
            'anonymized' => IbanFormat::Anonymized,
            default      => IbanFormat::Print,
        };

        return $svc->format($iban, $f);
    }
}

if (! function_exists('iban_resolve')) {
    /**
     * Resolves an IBAN to bank/entity data.
     *
     * Delegates straight to the facade's `resolve()`, which throws
     * `InvalidIbanException` for an invalid IBAN — unlike `bank_name()` /
     * `bank_bic()` below, this function is NOT degradation-safe by design
     * (mirrors `service('iban')->resolve()`'s documented contract).
     */
    function iban_resolve(string $iban): BankResult
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->resolve($iban);
    }
}

if (! function_exists('bank_name')) {
    /**
     * Safe bank-name lookup: `null` for an invalid IBAN or when the
     * configured provider has no matching row (e.g. the default
     * `NullProvider`, or an empty `banks` table). Never throws.
     */
    function bank_name(string $iban): ?string
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        $parsed = $svc->tryParse($iban);

        return $parsed === null ? null : $svc->resolve($parsed)->bankName;
    }
}

if (! function_exists('bank_bic')) {
    /**
     * Safe BIC lookup: `null` for an invalid IBAN or an unresolved entity.
     * Never throws.
     */
    function bank_bic(string $iban): ?string
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        $parsed = $svc->tryParse($iban);

        return $parsed === null ? null : $svc->resolve($parsed)->bic;
    }
}

if (! function_exists('iban_country')) {
    /**
     * Safe country-code lookup: `null` for an invalid IBAN. Never throws.
     */
    function iban_country(string $iban): ?string
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->tryParse($iban)?->countryCode;
    }
}

if (! function_exists('iban_valid')) {
    /**
     * Alias of {@see iban_is_valid()}.
     */
    function iban_valid(string $iban): bool
    {
        return iban_is_valid($iban);
    }
}
