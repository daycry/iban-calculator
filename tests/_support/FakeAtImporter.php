<?php

declare(strict_types=1);

namespace Tests\_support;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Fake `ImporterInterface` implementation used to exercise
 * `Daycry\Iban\Import\ImportRunner` / `Daycry\Iban\Import\ImporterRegistry`
 * / `Daycry\Iban\Commands\UpdateCommand` end-to-end, without any network
 * access or a real official-source importer (those arrive in v1.1's V-7).
 *
 * Always reports `countryCode() === 'AT'` / `sourceId() === 'fake'`. Yields
 * {@see self::defaultRows()} (3 fixture rows) unless a caller passes an
 * override to the constructor -- used by re-run/upsert tests that need to
 * change a field on a second run.
 *
 * @see \Tests\Import\ImportRunnerTest
 * @see \Tests\Import\ImporterRegistryTest
 */
final class FakeAtImporter implements ImporterInterface
{
    /**
     * @param list<array<string, mixed>>|null $rows Overrides {@see self::defaultRows()} when non-null.
     */
    public function __construct(private readonly ?array $rows = null)
    {
    }

    public function countryCode(): string
    {
        return 'AT';
    }

    public function sourceId(): string
    {
        return 'fake';
    }

    public function sourceName(): string
    {
        return 'Fake Test Importer';
    }

    public function license(): string
    {
        return 'Public Domain (test fixture)';
    }

    public function sourceUrl(): string
    {
        return 'https://example.test/fake-at-importer';
    }

    public function rows(?string $localFile = null): iterable
    {
        foreach ($this->rows ?? self::defaultRows() as $row) {
            yield $row;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultRows(): array
    {
        return [
            [
                'bank_code'     => '12000',
                'branch_code'   => null,
                'bic'           => 'BKAUATWWXXX',
                'name'          => 'Bank Austria',
                'short_name'    => 'BA',
                'city'          => 'Wien',
                'address'       => null,
                'sepa_sct'      => true,
                'sepa_sct_inst' => true,
                'sepa_sdd_core' => true,
                'sepa_sdd_b2b'  => false,
            ],
            [
                'bank_code'     => '20111',
                'branch_code'   => null,
                'bic'           => 'GIBAATWWXXX',
                'name'          => 'Erste Bank',
                'short_name'    => 'Erste',
                'city'          => 'Wien',
                'address'       => null,
                'sepa_sct'      => true,
                'sepa_sct_inst' => false,
                'sepa_sdd_core' => true,
                'sepa_sdd_b2b'  => null,
            ],
            [
                'bank_code'     => '32000',
                'branch_code'   => '00001',
                'bic'           => 'RLNWATWWXXX',
                'name'          => 'Raiffeisen Landesbank',
                'short_name'    => 'RLB',
                'city'          => 'Wien',
                'address'       => 'Am Stadtpark 9',
                'sepa_sct'      => true,
                'sepa_sct_inst' => null,
                'sepa_sdd_core' => true,
                'sepa_sdd_b2b'  => true,
            ],
        ];
    }
}
