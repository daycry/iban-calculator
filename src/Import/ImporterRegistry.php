<?php

declare(strict_types=1);

namespace Daycry\Iban\Import;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\AndorranBankingImporter;
use Daycry\Iban\Import\Importers\BancoDeEspanaImporter;
use Daycry\Iban\Import\Importers\BancoDePortugalImporter;
use Daycry\Iban\Import\Importers\BankOfIsraelImporter;
use Daycry\Iban\Import\Importers\BankOfSloveniaImporter;
use Daycry\Iban\Import\Importers\BetaalverenigingImporter;
use Daycry\Iban\Import\Importers\BitsNorwayImporter;
use Daycry\Iban\Import\Importers\BrazilianCentralBankImporter;
use Daycry\Iban\Import\Importers\BulgarianNationalBankImporter;
use Daycry\Iban\Import\Importers\BundesbankImporter;
use Daycry\Iban\Import\Importers\CentralBankOfAzerbaijanImporter;
use Daycry\Iban\Import\Importers\CentralBankOfCyprusImporter;
use Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter;
use Daycry\Iban\Import\Importers\CentralBankOfMontenegroImporter;
use Daycry\Iban\Import\Importers\CroatianNationalBankImporter;
use Daycry\Iban\Import\Importers\CzechNationalBankImporter;
use Daycry\Iban\Import\Importers\EpcRegisterImporter;
use Daycry\Iban\Import\Importers\EstonianBankingAssociationImporter;
use Daycry\Iban\Import\Importers\HellenicBankAssociationImporter;
use Daycry\Iban\Import\Importers\LiechtensteinImporter;
use Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter;
use Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter;
use Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter;
use Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfKazakhstanImporter;
use Daycry\Iban\Import\Importers\NationalBankOfMoldovaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfPolandImporter;
use Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfUkraineImporter;
use Daycry\Iban\Import\Importers\NbrmImporter;
use Daycry\Iban\Import\Importers\OenbImporter;
use Daycry\Iban\Import\Importers\RegafiImporter;
use Daycry\Iban\Import\Importers\SixImporter;
use Daycry\Iban\Import\Importers\SwedenBankInfrastructureImporter;
use Daycry\Iban\Import\Importers\VaticanCityImporter;

/**
 * In-memory catalog of {@see ImporterInterface} instances, keyed by their
 * natural `(countryCode, sourceId)` pair.
 *
 * FRAMEWORK-FREE: no dependency on the framework package this library adapts
 * for, so it can be constructed and queried outside of a framework
 * application too (`iban:update` just happens to be that consumer in this
 * package).
 *
 * {@see self::registerDefaults()} was intentionally empty in v1.1's V-6 --
 * that task only built the framework. v1.1's V-7a registered the first two
 * bundled official-source importers there -- {@see \Daycry\Iban\Import\Importers\OenbImporter}
 * (AT) and {@see \Daycry\Iban\Import\Importers\BundesbankImporter} (DE) --
 * v1.1's V-7b added three more -- {@see \Daycry\Iban\Import\Importers\SixImporter}
 * (CH), {@see \Daycry\Iban\Import\Importers\BetaalverenigingImporter} (NL)
 * and {@see \Daycry\Iban\Import\Importers\BancoDeEspanaImporter} (ES) --
 * v1.2 added four more -- {@see \Daycry\Iban\Import\Importers\CzechNationalBankImporter}
 * (CZ), {@see \Daycry\Iban\Import\Importers\HellenicBankAssociationImporter}
 * (GR), {@see \Daycry\Iban\Import\Importers\BankOfSloveniaImporter} (SI) and
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter} (SK)
 * -- and v1.2's follow-up batch adds four more XML-sourced importers --
 * {@see \Daycry\Iban\Import\Importers\BulgarianNationalBankImporter} (BG),
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfMoldovaImporter} (MD),
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfPolandImporter} (PL) and
 * {@see \Daycry\Iban\Import\Importers\CentralBankOfAzerbaijanImporter} (AZ)
 * -- and this v1.2 BE/HR/LU/MT batch adds four more, XLSX-sourced importers
 * -- {@see \Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter} (BE),
 * {@see \Daycry\Iban\Import\Importers\CroatianNationalBankImporter} (HR),
 * {@see \Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter}
 * (LU) and {@see \Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter}
 * (MT) -- and this v1.2 HU/NO/GE batch adds three more, also XLSX-sourced --
 * {@see \Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter} (HU),
 * {@see \Daycry\Iban\Import\Importers\BitsNorwayImporter} (NO) and
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter} (GE) --
 * and this v1.2 IL/UA/KZ batch adds three more, JSON-sourced importers --
 * {@see \Daycry\Iban\Import\Importers\BankOfIsraelImporter} (IL),
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfUkraineImporter} (UA)
 * and {@see \Daycry\Iban\Import\Importers\NationalBankOfKazakhstanImporter}
 * (KZ) -- and v1.2's BR/LI batch adds two more --
 * {@see \Daycry\Iban\Import\Importers\BrazilianCentralBankImporter} (BR) and
 * {@see \Daycry\Iban\Import\Importers\LiechtensteinImporter} (LI, which
 * shares {@see \Daycry\Iban\Import\Importers\SixImporter}'s `'six'` source
 * ID and CSV source but is keyed separately by country) -- and this v1.2
 * EPC SEPA Register batch registers one supranational, PARAMETERIZED-PER-
 * COUNTRY importer -- {@see \Daycry\Iban\Import\Importers\EpcRegisterImporter} --
 * five times, once each for GB, GI, IE, LV and RO: the SEPA countries whose
 * IBAN `bank_code` is the BIC's 4-letter prefix and that have no dedicated
 * national importer already registered here (BG/MT/NL share that same
 * bank-code shape but already have one, so are not double-registered) -- so
 * `new ImporterRegistry()` picks up all thirty automatically for every
 * consumer (`iban:update` included) without any other call site changing.
 *
 * @see \Daycry\Iban\Commands\UpdateCommand
 * @see ImportRunner
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
class ImporterRegistry
{
    /** @var array<string, ImporterInterface> keyed by `"{COUNTRY}:{source}"` (see {@see self::key()}) */
    private array $importers = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Registers (or replaces, if the same country+source is already
     * registered) an importer.
     */
    public function register(ImporterInterface $importer): void
    {
        $this->importers[self::key($importer->countryCode(), $importer->sourceId())] = $importer;
    }

    /**
     * @return list<ImporterInterface> Every registered importer, in registration order.
     */
    public function all(): array
    {
        return array_values($this->importers);
    }

    /**
     * @return list<ImporterInterface> Registered importers for `$countryCode` (case-insensitive), in registration order.
     */
    public function forCountry(string $countryCode): array
    {
        $needle = strtoupper($countryCode);

        return array_values(array_filter(
            $this->importers,
            static fn (ImporterInterface $importer): bool => strtoupper($importer->countryCode()) === $needle,
        ));
    }

    /**
     * Looks up a single importer by its exact `(countryCode, sourceId)` pair
     * (case-insensitive on both), or `null` if none is registered.
     */
    public function get(string $countryCode, string $sourceId): ?ImporterInterface
    {
        return $this->importers[self::key($countryCode, $sourceId)] ?? null;
    }

    /**
     * Summarizes every registered importer for display (e.g. `iban:update`'s
     * no-selection listing), without exposing the importer instances
     * themselves.
     *
     * @return list<array{country: string, source: string, name: string, license: string}>
     */
    public function sources(): array
    {
        return array_map(
            static fn (ImporterInterface $importer): array => [
                'country' => $importer->countryCode(),
                'source'  => $importer->sourceId(),
                'name'    => $importer->sourceName(),
                'license' => $importer->license(),
            ],
            $this->all(),
        );
    }

    /**
     * Registers this package's bundled default importers.
     *
     * Was deliberately empty in v1.1's V-6 (the importer framework itself
     * had nothing to bundle yet). v1.1's V-7a filled this in with the first
     * two concrete official-source importers -- {@see OenbImporter} (AT) and
     * {@see BundesbankImporter} (DE). v1.1's V-7b added three more --
     * {@see SixImporter} (CH), {@see BetaalverenigingImporter} (NL) and
     * {@see BancoDeEspanaImporter} (ES). v1.2 added four more --
     * {@see CzechNationalBankImporter} (CZ),
     * {@see HellenicBankAssociationImporter} (GR),
     * {@see BankOfSloveniaImporter} (SI) and
     * {@see NationalBankOfSlovakiaImporter} (SK) -- and this v1.2 follow-up
     * batch adds four more XML-sourced importers --
     * {@see BulgarianNationalBankImporter} (BG),
     * {@see NationalBankOfMoldovaImporter} (MD),
     * {@see NationalBankOfPolandImporter} (PL) and
     * {@see CentralBankOfAzerbaijanImporter} (AZ) -- and this v1.2 BE/HR/LU/MT
     * batch adds four more, XLSX-sourced importers --
     * {@see NationalBankOfBelgiumImporter} (BE),
     * {@see CroatianNationalBankImporter} (HR),
     * {@see LuxembourgBankersAssociationImporter} (LU) and
     * {@see CentralBankOfMaltaImporter} (MT) -- and this v1.2 HU/NO/GE batch
     * adds three more, also XLSX-sourced -- {@see MagyarNemzetiBankImporter}
     * (HU), {@see BitsNorwayImporter} (NO) and
     * {@see NationalBankOfGeorgiaImporter} (GE) -- and this v1.2 IL/UA/KZ
     * batch adds three more, JSON-sourced importers --
     * {@see BankOfIsraelImporter} (IL), {@see NationalBankOfUkraineImporter}
     * (UA) and {@see NationalBankOfKazakhstanImporter} (KZ) -- and this
     * v1.2 BR/LI batch adds two more -- {@see BrazilianCentralBankImporter}
     * (BR) and {@see LiechtensteinImporter} (LI) -- and this v1.2 EPC SEPA
     * Register batch registers {@see EpcRegisterImporter} five times -- once
     * each for GB, GI, IE, LV and RO -- so every consumer (`iban:update`
     * included) picks up all thirty automatically without any other call
     * site changing.
     */
    protected function registerDefaults(): void
    {
        $this->register(new OenbImporter());
        $this->register(new BundesbankImporter());
        $this->register(new SixImporter());
        $this->register(new BetaalverenigingImporter());
        $this->register(new BancoDeEspanaImporter());
        $this->register(new CzechNationalBankImporter());
        $this->register(new HellenicBankAssociationImporter());
        $this->register(new BankOfSloveniaImporter());
        $this->register(new NationalBankOfSlovakiaImporter());
        $this->register(new BulgarianNationalBankImporter());
        $this->register(new NationalBankOfMoldovaImporter());
        $this->register(new NationalBankOfPolandImporter());
        $this->register(new CentralBankOfAzerbaijanImporter());
        $this->register(new NationalBankOfBelgiumImporter());
        $this->register(new CroatianNationalBankImporter());
        $this->register(new LuxembourgBankersAssociationImporter());
        $this->register(new CentralBankOfMaltaImporter());
        $this->register(new MagyarNemzetiBankImporter());
        $this->register(new BitsNorwayImporter());
        $this->register(new NationalBankOfGeorgiaImporter());
        $this->register(new BankOfIsraelImporter());
        $this->register(new NationalBankOfUkraineImporter());
        $this->register(new NationalBankOfKazakhstanImporter());
        $this->register(new LiechtensteinImporter());
        $this->register(new BrazilianCentralBankImporter());
        $this->register(new EpcRegisterImporter('GB'));
        $this->register(new EpcRegisterImporter('GI'));
        $this->register(new EpcRegisterImporter('IE'));
        $this->register(new EpcRegisterImporter('LV'));
        $this->register(new EpcRegisterImporter('RO'));

        // v2.x SEPA-coverage batch (Fase 1, tier A -- live fetch):
        $this->register(new SwedenBankInfrastructureImporter());
        $this->register(new RegafiImporter('FR'));
        $this->register(new RegafiImporter('MC'));
        $this->register(new EstonianBankingAssociationImporter());
        $this->register(new CentralBankOfMontenegroImporter());
        $this->register(new CentralBankOfCyprusImporter());

        // v2.x SEPA-coverage batch (Fase 2, tier B -- offline `--file` +
        // curated): AD is curated (no machine-readable source; a tiny, stable
        // 3-bank set), PT/MK are `--file`-only (their landings block bots /
        // sit behind Cloudflare).
        $this->register(new AndorranBankingImporter());
        $this->register(new BancoDePortugalImporter());
        $this->register(new NbrmImporter());

        // v2.x SEPA-coverage batch (Fase 3, tier C -- caveats / curation):
        // VA is curated (the Vatican's whole bank universe is a single
        // institution, the IOR, with no machine-readable directory).
        $this->register(new VaticanCityImporter());
    }

    private static function key(string $countryCode, string $sourceId): string
    {
        return strtoupper($countryCode) . ':' . strtolower($sourceId);
    }
}
