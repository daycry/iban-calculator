<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NationalBankOfUkraineImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see NationalBankOfUkraineImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/nbu_sample.json` -- built from the National Bank
 * of Ukraine's DOCUMENTED `typ=0&json` response shape (the live portal
 * blocks automated fetches with a 403, so this fixture was authored from
 * the documented field names rather than a confirmed live response --
 * see {@see NationalBankOfUkraineImporter}'s class docblock).
 *
 * @see \Daycry\Iban\Import\Importers\NationalBankOfUkraineImporter
 */
final class NationalBankOfUkraineImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/nbu_sample.json';

    private NationalBankOfUkraineImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NationalBankOfUkraineImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('UA', $this->importer->countryCode());
        self::assertSame('nbu', $this->importer->sourceId());
        self::assertSame('National Bank of Ukraine', $this->importer->sourceName());
        self::assertSame('National Bank of Ukraine (open data)', $this->importer->license());
        self::assertStringStartsWith('https://bank.gov.ua/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsMapsGlmfoAndNgolovnToBankLevelRows(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        self::assertCount(3, $rows);

        // The registry's own example bank code (see the IBAN example
        // UA903052992990004149123456789 -> bank code '305299').
        $privatBank = $rows[0];
        self::assertSame('305299', $privatBank['bank_code']);
        self::assertSame('АКЦІОНЕРНЕ ТОВАРИСТВО КОМЕРЦІЙНИЙ БАНК "ПРИВАТБАНК"', $privatBank['name']); // UTF-8 Cyrillic round trip
        self::assertNull($privatBank['branch_code']);

        $oschadbank = $rows[1];
        self::assertSame('300465', $oschadbank['bank_code']);
        self::assertSame('АКЦІОНЕРНЕ ТОВАРИСТВО "ОЩАДНИЙ БАНК УКРАЇНИ"', $oschadbank['name']);

        $nbu = $rows[2];
        self::assertSame('300001', $nbu['bank_code']);
        self::assertSame('НАЦІОНАЛЬНИЙ БАНК УКРАЇНИ', $nbu['name']);
    }

    public function testRowsFallsBackToFullnameWhenNgolovnIsBlank(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_nbu_fallback_');
        self::assertIsString($path);
        file_put_contents($path, json_encode([
            ['GLMFO' => '999999', 'NGOLOVN' => '', 'FULLNAME' => 'TEST BANK'],
        ]));

        try {
            $rows = iterator_to_array($this->importer->rows($path), false);

            self::assertCount(1, $rows);
            self::assertSame('999999', $rows[0]['bank_code']);
            self::assertSame('TEST BANK', $rows[0]['name']);
        } finally {
            unlink($path);
        }
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/nbu.json'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableForMalformedJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_nbu_malformed_');
        self::assertIsString($path);
        file_put_contents($path, '[ this is not valid JSON');

        try {
            $rows = iterator_to_array($this->importer->rows($path), false);

            self::assertSame([], $rows);
        } finally {
            unlink($path);
        }
    }
}
