<?php

declare(strict_types=1);

use Daycry\Iban\Config\Services;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedBic;
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
     * @param bool|null $checkNational Whether to also run national check-digit
     *                                  validation, mirroring the facade's
     *                                  `validate()` parameter of the same
     *                                  name. `null` (the default) defers to
     *                                  {@see \Daycry\Iban\Config\Iban::$checkNationalByDefault}
     *                                  -- the facade itself keeps its own
     *                                  explicit `false` default; the config
     *                                  is consulted here, at the CI4 helper
     *                                  layer.
     */
    function iban_validate(string $iban, ?bool $checkNational = null): ValidationResult
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        $checkNational ??= Services::config()->checkNationalByDefault;

        return $svc->validate($iban, $checkNational);
    }
}

if (! function_exists('iban_is_valid')) {
    /**
     * Quick boolean IBAN validity check. Never throws.
     *
     * Unlike the facade's own `isValid()` (which, by frozen contract, takes
     * no `$checkNational` parameter and always validates with it `false`),
     * this delegates to {@see iban_validate()} so the same
     * `$checkNational`/config-default behavior applies here too.
     *
     * @param bool|null $checkNational Same meaning and config fallback as
     *                                  {@see iban_validate()}'s parameter of
     *                                  the same name.
     */
    function iban_is_valid(string $iban, ?bool $checkNational = null): bool
    {
        return iban_validate($iban, $checkNational)->isValid();
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
     * Formats an IBAN string per `$format`: `'electronic'`, `'print'`, or
     * `'anonymized'` (case-insensitive). Any other value falls back to
     * `'print'`.
     *
     * `null` (the default) defers to
     * {@see \Daycry\Iban\Config\Iban::$defaultFormat} -- the facade's own
     * `format()` keeps its own explicit `IbanFormat::Print` default; the
     * config is consulted here, at the CI4 helper layer.
     */
    function iban_format(string $iban, ?string $format = null): string
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        $format ??= Services::config()->defaultFormat;

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
     * Alias of {@see iban_is_valid()}, exposing the same `$checkNational`
     * parameter (and `Config\Iban::$checkNationalByDefault` fallback).
     */
    function iban_valid(string $iban, ?bool $checkNational = null): bool
    {
        return iban_is_valid($iban, $checkNational);
    }
}

if (! function_exists('bic_validate')) {
    /**
     * Validates a BIC (ISO 9362 / SWIFT code) and returns the full result
     * (including violations). Never throws.
     *
     * A BIC has no checksum, so a valid result only means "well-formed AND its
     * country code is recognised", never "this BIC exists on the SWIFT network".
     */
    function bic_validate(string $bic): ValidationResult
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->validateBic($bic);
    }
}

if (! function_exists('bic_is_valid')) {
    /**
     * Quick boolean BIC validity check. Never throws.
     */
    function bic_is_valid(string $bic): bool
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->isValidBic($bic);
    }
}

if (! function_exists('bic_parse')) {
    /**
     * Parses a BIC into its structural parts (institution/country/location/
     * branch). Never throws: returns `null` for a malformed BIC instead of
     * raising `InvalidBicException` (uses `tryParseBic()` under the hood).
     */
    function bic_parse(string $bic): ?ParsedBic
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->tryParseBic($bic);
    }
}

if (! function_exists('bic_format')) {
    /**
     * Normalizes a BIC to its canonical form: uppercase, whitespace stripped.
     *
     * Unlike an IBAN (Electronic/Print/Anonymized), a BIC has a single
     * canonical representation, so this takes no format argument. It does NOT
     * validate — a malformed input is still normalized (and can then be fed to
     * {@see bic_is_valid()}).
     */
    function bic_format(string $bic): string
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->normalizeBic($bic);
    }
}

if (! function_exists('bic_resolve')) {
    /**
     * Resolves a BIC to bank/entity data via the configured provider.
     *
     * Degradation-safe: returns `null` for a malformed BIC or when the
     * provider has no matching entry (e.g. the default `NullProvider`, an empty
     * `banks` table, or a provider without BIC support). Never throws.
     */
    function bic_resolve(string $bic): ?BankInfo
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->resolveBic($bic);
    }
}

if (! function_exists('bic_bank_name')) {
    /**
     * Safe bank-name lookup from a BIC: `null` for a malformed BIC or an
     * unresolved entity. Never throws.
     */
    function bic_bank_name(string $bic): ?string
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->resolveBic($bic)?->bankName;
    }
}

if (! function_exists('iban_bic_validate')) {
    /**
     * Combined IBAN + BIC validation: validate an IBAN, a BIC, or both, and —
     * when both are supplied AND each is individually valid — also cross-check
     * them for mutual coherence (country and, where structurally possible, bank
     * code). Delegates to the facade's `validateIbanAndBic()`. Never throws.
     *
     * Passing neither (both `null`/blank) yields a single
     * `ViolationCode::NothingToValidate`.
     */
    function iban_bic_validate(?string $iban, ?string $bic): ValidationResult
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        return $svc->validateIbanAndBic($iban, $bic);
    }
}
