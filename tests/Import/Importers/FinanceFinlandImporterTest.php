<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\FinanceFinlandImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see FinanceFinlandImporter} in isolation (plain PHPUnit,
 * framework-free) against a reduced fixture reproducing an operator-prepared
 * semicolon CSV of Finance Finland's (Finanssiala ry) "Suomalaiset
 * rahalaitostunnukset ja BIC-koodit" directory: a title/preamble line, a
 * `Rahalaitos;Rahalaitostunnus;BIC` header row, then institutions whose
 * `Rahalaitostunnus` is a single value, a `ja`/comma list, a range, or a
 * (deliberately unsupported) 4-digit code.
 *
 * The point under test is the RANGE-EXPANSION MAPPER: the variable-length
 * source code is expanded into the fixed 3-digit `bank_code`s of the Finnish
 * IBAN, and the 4-digit post-2024 code is dropped (surfaced as a
 * `bank_code`-less row so the ImportRunner can count it as skipped).
 *
 * @see \Daycry\Iban\Import\Importers\FinanceFinlandImporter
 */
final class FinanceFinlandImporterTest extends TestCase
{
    private const CLEAN_FIXTURE = __DIR__ . '/../../Fixtures/import/finanssiala_sample.csv';

    private FinanceFinlandImporter $importer;

    private ?string $tempFixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new FinanceFinlandImporter();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tempFixture !== null && is_file($this->tempFixture)) {
            unlink($this->tempFixture);
        }

        $this->tempFixture = null;
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('FI', $this->importer->countryCode());
        self::assertSame('finanssiala', $this->importer->sourceId());
        self::assertStringContainsString('Finanssiala', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://www.finanssiala.fi/', $this->importer->sourceUrl());
    }

    public function testRowsExpandTheVariableLengthCodesToFixedThreeDigitBankCodes(): void
    {
        $byCode = $this->rowsByBankCode();

        // 1-digit token `1` (Nordea) -> 100..199 (100 codes); `2` -> 200..299.
        self::assertCount(100 + 100 + 100 + 110 + 10 + 10 + 1, $byCode);

        // The registry's own FI example IBAN carries bank field '123', covered
        // by Nordea's `1` -> 1xx expansion.
        self::assertSame('Nordea Bank Abp', $this->rowFor($byCode, '123')['name']);
        self::assertSame('NDEAFIHH', $this->rowFor($byCode, '123')['bic']);
        self::assertNull($this->rowFor($byCode, '123')['branch_code']);

        // `1 ja 2`: the `2` token expands the 200 range to Nordea too.
        self::assertSame('Nordea Bank Abp', $this->rowFor($byCode, '250')['name']);

        // Single-digit `5` -> OP covers all of 5xx.
        self::assertSame('OP Osuuskunta', $this->rowFor($byCode, '540')['name']);
        self::assertSame('OKOYFIHH', $this->rowFor($byCode, '540')['bic']);

        // `8 ja 34` (Danske): the 1-digit `8` -> 8xx and the 2-digit `34` -> 34x.
        self::assertSame('Danske Bank A/S, Suomen sivuliike', $this->rowFor($byCode, '835')['name']);
        self::assertSame('Danske Bank A/S, Suomen sivuliike', $this->rowFor($byCode, '345')['name']);
        self::assertArrayHasKey('340', $byCode);
        self::assertArrayHasKey('349', $byCode);
        self::assertArrayNotHasKey('350', $byCode); // the 2-digit `34` covers only 340..349

        // 2-digit `33` (SEB) -> 330..339, distinct from Danske's 34x.
        self::assertSame('Skandinaviska Enskilda Banken AB (publ) Helsingin sivukonttori', $this->rowFor($byCode, '335')['name']);
        self::assertArrayNotHasKey('329', $byCode);
    }

    public function testRowsExpandAThreeDigitRangeInclusiveOfBothEndpoints(): void
    {
        $byCode = $this->rowsByBankCode();

        // POP `470-479` -> every code in [470, 479].
        self::assertArrayHasKey('470', $byCode);
        self::assertArrayHasKey('479', $byCode);
        self::assertSame('POP Pankki -ryhmä', $this->rowFor($byCode, '475')['name']);
        self::assertSame('POPFFI22', $this->rowFor($byCode, '475')['bic']);

        // The range is bounded: 469 and 480 are not covered.
        self::assertArrayNotHasKey('469', $byCode);
        self::assertArrayNotHasKey('480', $byCode);
    }

    public function testRowsKeepASingleThreeDigitCodeAsIs(): void
    {
        $byCode = $this->rowsByBankCode();

        self::assertArrayHasKey('717', $byCode);
        self::assertSame('Bigbank AS Suomen sivuliike', $this->rowFor($byCode, '717')['name']);
        self::assertSame('BIGKFI2H', $this->rowFor($byCode, '717')['bic']);
    }

    public function testFourDigitCodeIsSkippedAndSurfacedAsABankCodelessRow(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::CLEAN_FIXTURE), false);

        $skipped = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['bank_code'] === null,
        ));

        // Exactly one row is dropped: the 4-digit `7180` (post-2024) code that
        // cannot be keyed on a fixed 3-digit bank_code.
        self::assertCount(1, $skipped);
        self::assertSame('Uusi maksulaitos Oy', $skipped[0]['name']);

        // The 4-digit code produced no 3-digit bank_code (neither '718' nor
        // any other prefix leaked in).
        $byCode = $this->rowsByBankCode();
        self::assertArrayNotHasKey('718', $byCode);
    }

    public function testEveryYieldedBankCodeIsAThreeDigitStringOrNull(): void
    {
        foreach ($this->importer->rows(self::CLEAN_FIXTURE) as $row) {
            if ($row['bank_code'] === null) {
                continue; // the documented 4-digit skip
            }

            self::assertIsString($row['bank_code']);
            self::assertMatchesRegularExpression('/^\d{3}$/', $row['bank_code']);
        }
    }

    public function testDeDuplicatesOverlappingClaimsWithFirstInstitutionWinning(): void
    {
        // Two institutions both cover 150: the first (Alpha, via `1` -> 1xx)
        // must win; the later single-code `150` claim (Beta) is dropped.
        $this->tempFixture = (string) tempnam(sys_get_temp_dir(), 'iban_fi_');
        file_put_contents(
            $this->tempFixture,
            "Rahalaitos;Rahalaitostunnus;BIC\n"
            . "Alpha Bank Oyj;1;ALPHFIHH\n"
            . "Beta Bank Oyj;150;BETAFIHH\n",
        );

        $byCode = $this->rowsByBankCode($this->tempFixture);

        self::assertSame('Alpha Bank Oyj', $this->rowFor($byCode, '150')['name']);
        self::assertSame('ALPHFIHH', $this->rowFor($byCode, '150')['bic']);
    }

    public function testRowsDecodeWindows1252AccentsToUtf8(): void
    {
        // An operator export that kept a Windows-1252 codepage (pdftotext
        // without `-enc UTF-8`) must still decode the Finnish "ä"/"ö" correctly.
        $utf8   = (string) file_get_contents(self::CLEAN_FIXTURE);
        $latin1 = (string) mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');

        self::assertFalse(mb_check_encoding($latin1, 'UTF-8'));

        $this->tempFixture = (string) tempnam(sys_get_temp_dir(), 'iban_fi_');
        file_put_contents($this->tempFixture, $latin1);

        $byCode = $this->rowsByBankCode($this->tempFixture);

        self::assertSame('POP Pankki -ryhmä', $this->rowFor($byCode, '475')['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/finanssiala.csv'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenNoHeaderCanBeLocated(): void
    {
        $this->tempFixture = (string) tempnam(sys_get_temp_dir(), 'iban_fi_');
        file_put_contents($this->tempFixture, "col1;col2;col3\na;b;c\n");

        $rows = iterator_to_array($this->importer->rows($this->tempFixture), false);

        self::assertSame([], $rows);
    }

    /**
     * Collects the importer's rows keyed by their 3-digit `bank_code`,
     * dropping the `bank_code`-less (skipped) rows.
     *
     * @return array<string, array<string, mixed>>
     */
    private function rowsByBankCode(?string $file = null): array
    {
        $byCode = [];

        foreach ($this->importer->rows($file ?? self::CLEAN_FIXTURE) as $row) {
            if ($row['bank_code'] === null) {
                continue;
            }

            self::assertIsString($row['bank_code']);
            $byCode[$row['bank_code']] = $row;
        }

        return $byCode;
    }

    /**
     * Asserts `$code` is present in `$byCode` and returns its row (a safe,
     * PHPStan-friendly offset access on the general string-keyed map).
     *
     * @param array<string, array<string, mixed>> $byCode
     *
     * @return array<string, mixed>
     */
    private function rowFor(array $byCode, string $code): array
    {
        self::assertArrayHasKey($code, $byCode);

        return $byCode[$code] ?? [];
    }
}
