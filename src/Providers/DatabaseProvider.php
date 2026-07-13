<?php

declare(strict_types=1);

namespace Daycry\Iban\Providers;

use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Models\BankModel;

/**
 * {@see ProviderInterface} implementation backed by the `banks` DB table via
 * {@see BankModel}.
 *
 * `supports()` always returns `true`: this provider queries any country's
 * data and simply returns `null` when nothing is seeded for it, letting
 * {@see \Daycry\Iban\Resolver\Resolver} fall back to unresolved bank fields
 * gracefully instead of skipping the lookup outright.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §7
 */
final class DatabaseProvider implements ProviderInterface
{
    public function __construct(private BankModel $model = new BankModel())
    {
    }

    public function supports(string $countryCode): bool
    {
        return true;
    }

    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
    {
        $row = $this->model->findByNaturalKey($countryCode, $bankCode, $branchCode);

        if ($row === null) {
            return null;
        }

        return new BankInfo(
            bankName: self::toNullableString($row['name'] ?? null),
            shortName: self::toNullableString($row['short_name'] ?? null),
            bic: self::toNullableString($row['bic'] ?? null),
            city: self::toNullableString($row['city'] ?? null),
            address: self::toNullableString($row['address'] ?? null),
            sepaSct: self::toNullableBool($row['sepa_sct'] ?? null),
            sepaSctInst: self::toNullableBool($row['sepa_sct_inst'] ?? null),
            sepaSddCore: self::toNullableBool($row['sepa_sdd_core'] ?? null),
            sepaSddB2b: self::toNullableBool($row['sepa_sdd_b2b'] ?? null),
            sourceId: self::toNullableString($row['source_id'] ?? null),
            sourceVersion: self::toNullableString($row['source_version'] ?? null),
            sourceLicense: self::toNullableString($row['source_license'] ?? null),
            resolvedBy: 'database',
        );
    }

    public function findByIban(ParsedIban $iban): ?BankInfo
    {
        return $this->findByBankCode($iban->countryCode, $iban->bankIdentifier, $iban->branchIdentifier);
    }

    /**
     * Maps a raw DB column value to a nullable string, leaving `null` as-is.
     */
    private static function toNullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * Maps a raw TINYINT column value (0/1, possibly returned as int or
     * numeric string depending on the DB driver) to a nullable bool,
     * leaving `null` as-is.
     */
    private static function toNullableBool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) (int) $value;
    }
}
