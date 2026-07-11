<?php

declare(strict_types=1);

namespace Daycry\Iban\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Publishable configuration for the `daycry/iban` package.
 *
 * Every property is overridable via `.env` using the `iban.<property>`
 * prefix (e.g. `iban.provider = database`), courtesy of
 * {@see BaseConfig}'s environment-variable resolution.
 *
 * @see \Daycry\Iban\Config\Services::iban()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
class Iban extends BaseConfig
{
    /**
     * Bank-data resolution provider.
     *
     * One of `'null'` (no lookups, the safe out-of-the-box default),
     * `'database'` (backed by the `banks` table via
     * {@see \Daycry\Iban\Providers\DatabaseProvider}), or the fully
     * qualified class name of a custom
     * {@see \Daycry\Iban\Contracts\ProviderInterface} implementation.
     */
    public string $provider = 'null';

    /**
     * Default {@see \Daycry\Iban\Enums\IbanFormat} used when callers don't
     * explicitly request one: `'electronic'`, `'print'`, or `'anonymized'`.
     */
    public string $defaultFormat = 'print';

    /**
     * Whether {@see \Daycry\Iban\Core\Validator} runs the national
     * check-digit validation by default.
     */
    public bool $checkNationalByDefault = false;

    /**
     * The `Config\Database` connection group queried by
     * {@see \Daycry\Iban\Providers\DatabaseProvider} / {@see \Daycry\Iban\Models\BankModel}.
     *
     * Defaults to `null`, meaning "no override": {@see BankModel} then leaves
     * its own `$DBGroup` unset, so CI4's environment-aware fallback
     * (`Database\Config::connect(null)`, which resolves to `'tests'` under
     * `ENVIRONMENT === 'testing'`, or the app's `Config\Database::$defaultGroup`
     * otherwise) applies transparently. Set this only to force a specific
     * connection group regardless of environment (e.g. a read replica).
     */
    public ?string $dbGroup = null;

    /**
     * The table name queried by
     * {@see \Daycry\Iban\Providers\DatabaseProvider} / {@see \Daycry\Iban\Models\BankModel}.
     */
    public string $table = 'banks';
}
