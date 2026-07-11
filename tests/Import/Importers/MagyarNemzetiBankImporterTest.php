<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see MagyarNemzetiBankImporter} in isolation (plain PHPUnit,
 * framework-free) against a hand-crafted `.xlsx` fixture generated on the fly
 * with {@see XlsxFixtureFactory} -- reproducing the live MNB GIRO routing
 * table's confirmed layout: no preamble, header row 1 with the real (ENGLISH,
 * confirmed live 2026-07-11) `Branch office code`/`BIC code`/`Name of the
 * branch office` columns, including the per-branch repetition (8-digit codes:
 * 3-digit bank + 4-digit branch + 1-digit trailing digit) this importer must
 * dedup down to one row per 3-digit bank code.
 *
 * @see \Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter
 */
final class MagyarNemzetiBankImporterTest extends TestCase
{
    private MagyarNemzetiBankImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new MagyarNemzetiBankImporter();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->fixturePath !== null && is_file($this->fixturePath)) {
            unlink($this->fixturePath);
        }

        $this->fixturePath = null;
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('HU', $this->importer->countryCode());
        self::assertSame('mnb', $this->importer->sourceId());
        self::assertSame('Magyar Nemzeti Bank', $this->importer->sourceName());
        self::assertSame('Magyar Nemzeti Bank', $this->importer->license());
        self::assertStringStartsWith('https://www.mnb.hu/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsDedupsByTheThreeDigitBankCodeKeepingTheFirstOccurrence(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            [
                'Branch office code',
                'BIC code',
                'Name of the branch office',
                'Address of the branch office',
                'Branch office may send VIBER items',
                'Branch office may receive VIBER items',
            ],
            ['11773011', 'OTPVHUHB', 'OTP Bank Nyrt. (fixture — HU registry example bank code)', '1051 Budapest, Nádor utca 16.', 'S', 'R'],
            // Same 3-digit bank ('117'), a different branch/check digit --
            // must be deduped away, keeping the FIRST occurrence's name/BIC.
            ['11773012', 'OTPVHUHB', 'OTP Budapesti r., II. Széna tér', '1015 Budapest, Széna tér 7.', 'S', 'R'],
            ['10002003', 'MANEHUHB', 'Magyar Államkincstár', '1139 Budapest, Váci út 71.', '', 'R'],
            // Malformed/footer rows -- no digits-only code of at least 3
            // characters -- must be skipped entirely.
            ['Forrás: MNB', '', '', '', '', ''],
            ['', '', '', '', '', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // '117' and '100' -- 2 unique 3-digit bank codes; the '117' dedup
        // sibling and the two malformed rows are all excluded.
        self::assertCount(2, $rows);

        $otp = $rows[0];
        self::assertSame('117', $otp['bank_code']);
        self::assertNull($otp['branch_code']);
        self::assertSame('OTP Bank Nyrt. (fixture — HU registry example bank code)', $otp['name']);
        self::assertSame('OTPVHUHB', $otp['bic']);

        $kincstar = $rows[1];
        self::assertSame('100', $kincstar['bank_code']);
        self::assertSame('Magyar Államkincstár', $kincstar['name']); // UTF-8 accented char round trip
        self::assertSame('MANEHUHB', $kincstar['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/mnb.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['11773011', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
