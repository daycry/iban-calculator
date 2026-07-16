<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\EstonianBankingAssociationImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see EstonianBankingAssociationImporter} in isolation (plain
 * PHPUnit, framework-free) against a hand-written HTML fixture reproducing the
 * Eesti Pangaliit "bank-codes" table: a 2-digit code -> name + BIC, including
 * the leading-zero code (TBB `00`, which must survive as a string) and a
 * double-code cell (Luminor `96` and `17`, which must emit two rows sharing
 * the same name + BIC).
 *
 * @see \Daycry\Iban\Import\Importers\EstonianBankingAssociationImporter
 */
final class EstonianBankingAssociationImporterTest extends TestCase
{
    private const FIXTURE_HTML = <<<'HTML'
        <html><body>
        <h1>Bank codes</h1>
        <table>
            <thead>
                <tr><th>Bank</th><th>BIC / SWIFT</th><th>IBAN identifier</th></tr>
            </thead>
            <tbody>
                <tr><td>Swedbank AS</td><td>HABAEE2X</td><td>22</td></tr>
                <tr><td>AS SEB Pank</td><td>EEUHEE2X</td><td>10</td></tr>
                <tr><td>Luminor Bank AS</td><td>RIKOEE22</td><td>96, 17</td></tr>
                <tr><td>AS TBB pank</td><td>TABUEE22</td><td>00</td></tr>
            </tbody>
        </table>
        </body></html>
        HTML;

    private EstonianBankingAssociationImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new EstonianBankingAssociationImporter();
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
        self::assertSame('EE', $this->importer->countryCode());
        self::assertSame('pangaliit', $this->importer->sourceId());
        self::assertStringContainsString('Pangaliit', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://www.pangaliit.ee/', $this->importer->sourceUrl());
    }

    public function testRowsMapTwoDigitCodesPreservingLeadingZeroAndSplittingDoubleCodes(): void
    {
        $this->fixturePath = $this->writeFixture(self::FIXTURE_HTML);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // Swedbank, SEB, Luminor x2 (96 and 17), TBB = 5 rows.
        self::assertCount(5, $rows);

        self::assertSame('22', $rows[0]['bank_code']);
        self::assertSame('Swedbank AS', $rows[0]['name']);
        self::assertSame('HABAEE2X', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        self::assertSame('10', $rows[1]['bank_code']);
        self::assertSame('AS SEB Pank', $rows[1]['name']);

        // Luminor's "96, 17" cell yields two rows, same name + BIC.
        self::assertSame('96', $rows[2]['bank_code']);
        self::assertSame('Luminor Bank AS', $rows[2]['name']);
        self::assertSame('RIKOEE22', $rows[2]['bic']);
        self::assertSame('17', $rows[3]['bank_code']);
        self::assertSame('Luminor Bank AS', $rows[3]['name']);
        self::assertSame('RIKOEE22', $rows[3]['bic']);

        // TBB's leading zero must survive (string, not int).
        self::assertSame('00', $rows[4]['bank_code']);
        self::assertSame('AS TBB pank', $rows[4]['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/ee.html'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenNoRecognizableTableIsPresent(): void
    {
        $this->fixturePath = $this->writeFixture('<html><body><p>No table here.</p></body></html>');

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }

    private function writeFixture(string $html): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'iban_ee_html_');
        file_put_contents($path, $html);

        return $path;
    }
}
