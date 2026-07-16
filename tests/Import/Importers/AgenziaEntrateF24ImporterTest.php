<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\AgenziaEntrateF24Importer;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see AgenziaEntrateF24Importer} in isolation (plain PHPUnit,
 * framework-free) against a hand-written HTML fixture reproducing the Agenzia
 * delle Entrate F24 "elenco banche convenzionate" table: `Codice ABI` (shown
 * WITHOUT leading zeros) -> `Denominazione`. The pinned behaviours are the
 * zero-pad of the code to 5 digits and the absence of a BIC (this source
 * carries none).
 *
 * @see \Daycry\Iban\Import\Importers\AgenziaEntrateF24Importer
 */
final class AgenziaEntrateF24ImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/agenzia_entrate_sample.html';

    private AgenziaEntrateF24Importer $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new AgenziaEntrateF24Importer();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('IT', $this->importer->countryCode());
        self::assertSame('agenzia-entrate', $this->importer->sourceId());
        self::assertStringContainsString('Agenzia', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://', $this->importer->sourceUrl());
    }

    public function testRowsMapAbiZeroPaddedToFiveDigitsWithNoBic(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // Three data rows; the blank-code row is skipped.
        self::assertCount(3, $rows);

        self::assertSame('03069', $rows[0]['bank_code']);
        self::assertSame('Intesa Sanpaolo S.p.A.', $rows[0]['name']);
        self::assertNull($rows[0]['branch_code']);
        // This source carries no BIC.
        self::assertArrayHasKey('bic', $rows[0]);
        self::assertNull($rows[0]['bic']);

        self::assertSame('02008', $rows[1]['bank_code']);
        self::assertSame('UniCredit S.p.A.', $rows[1]['name']);

        // The registry's IT example ABI (05428), shown without a leading zero.
        self::assertSame('05428', $rows[2]['bank_code']);
        self::assertSame('Banco BPM S.p.A. (fixture — IT registry example ABI)', $rows[2]['name']);

        // Every code is zero-padded to a 5-digit string, never coerced to int.
        foreach ($rows as $row) {
            self::assertIsString($row['bank_code']);
            self::assertMatchesRegularExpression('/^\d{5}$/', $row['bank_code']);
            self::assertNull($row['bic']);
        }
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/it.html'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenNoRecognizableTableIsPresent(): void
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'iban_it_html_');
        file_put_contents($tmp, '<html><body><p>Nessuna tabella qui.</p></body></html>');

        try {
            $rows = iterator_to_array($this->importer->rows($tmp), false);
            self::assertSame([], $rows);
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
