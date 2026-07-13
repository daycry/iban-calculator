<?php

declare(strict_types=1);

namespace Daycry\Iban\Providers;

use Daycry\Iban\Contracts\IsoCountryLoaderInterface;
use Daycry\Iban\Models\IsoCountryModel;

/**
 * {@see IsoCountryLoaderInterface} implementation backed by the
 * `iso_countries` DB table via {@see IsoCountryModel}.
 *
 * This is the opt-in CI4 overlay for the ISO 3166-1 registry, selected by
 * `Config\Iban::$isoCountrySource = 'database'`. It lives under
 * `src/Providers/` — NOT under `src/Registry/` alongside its compiled-PHP
 * counterpart {@see \Daycry\Iban\Registry\PhpIsoCountryLoader} — precisely
 * because it depends on CodeIgniter (through {@see IsoCountryModel}), and
 * `src/Registry/` is a framework-free guarded directory (see
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`). `src/Providers/` is
 * deliberately excluded from that guard, mirroring how
 * {@see DatabaseProvider} is the CI4 overlay for the bank-data
 * {@see \Daycry\Iban\Providers\NullProvider}.
 *
 * Using it requires the `CreateIsoCountriesTable` migration to have run AND
 * the `iso_countries` table to be populated (e.g. via
 * {@see \Daycry\Iban\Database\Seeds\IsoCountriesSeeder}); an empty table
 * yields an empty registry.
 *
 * @see \Daycry\Iban\Config\Services::isoCountries()
 * @see \Daycry\Iban\Registry\IsoCountryRegistry
 */
final class DatabaseIsoCountryLoader implements IsoCountryLoaderInterface
{
    public function __construct(private IsoCountryModel $model = new IsoCountryModel())
    {
    }

    /**
     * Load every `iso_countries` row into the alpha-2-keyed shape expected by
     * {@see IsoCountryRegistry}, producing a structure identical to
     * {@see \Daycry\Iban\Registry\PhpIsoCountryLoader::load()} when the table
     * has been seeded from the compiled list.
     *
     * @return array<string, array{name: string, alpha3: string, numeric: string}>
     */
    public function load(): array
    {
        $data = [];

        foreach ($this->model->findAll() as $row) {
            $alpha2 = strtoupper($row['alpha2']);

            if ($alpha2 === '') {
                continue;
            }

            $data[$alpha2] = [
                'name'    => $row['name'] ?? '',
                'alpha3'  => $row['alpha3'] ?? '',
                'numeric' => $row['numeric'] ?? '',
            ];
        }

        return $data;
    }
}
