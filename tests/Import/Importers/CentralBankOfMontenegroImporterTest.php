<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\CentralBankOfMontenegroImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see CentralBankOfMontenegroImporter} in isolation (plain
 * PHPUnit, framework-free) against a hand-written HTML fixture reproducing the
 * CBCG RTGS participants table: a 3-digit code -> name + BIC, mixing
 * commercial banks with public entities in the 714-931 range that must be
 * filtered out.
 *
 * @see \Daycry\Iban\Import\Importers\CentralBankOfMontenegroImporter
 */
final class CentralBankOfMontenegroImporterTest extends TestCase
{
    private const FIXTURE_HTML = <<<'HTML'
        <html><body>
        <h2>RTGS participants</h2>
        <table>
            <tr><th>Code</th><th>Name</th><th>BIC</th></tr>
            <tr><td>510</td><td>Crnogorska komercijalna banka AD</td><td>CKBCMEPG</td></tr>
            <tr><td>520</td><td>Hipotekarna banka AD</td><td>HBBACMEPG</td></tr>
            <tr><td>555</td><td>Addiko Bank AD Podgorica</td><td>HAABMEPG</td></tr>
            <tr><td>833</td><td>State Treasury (public entity)</td><td></td></tr>
            <tr><td>907</td><td>Central Bank of Montenegro</td><td>CBCGMEPG</td></tr>
        </table>
        </body></html>
        HTML;

    private CentralBankOfMontenegroImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new CentralBankOfMontenegroImporter();
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
        self::assertSame('ME', $this->importer->countryCode());
        self::assertSame('cbcg', $this->importer->sourceId());
        self::assertStringContainsString('Montenegro', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://www.cbcg.me/', $this->importer->sourceUrl());
    }

    public function testRowsMapThreeDigitCodesAndFilterPublicEntities(): void
    {
        $this->fixturePath = $this->writeFixture(self::FIXTURE_HTML);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // CKB (510), Hipotekarna (520), Addiko (555) kept; State Treasury
        // (833) and the central bank itself (907) filtered as 714-931 public
        // entities.
        self::assertCount(3, $rows);

        self::assertSame('510', $rows[0]['bank_code']);
        self::assertSame('Crnogorska komercijalna banka AD', $rows[0]['name']);
        self::assertSame('CKBCMEPG', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        self::assertSame('520', $rows[1]['bank_code']);
        self::assertSame('Hipotekarna banka AD', $rows[1]['name']);

        self::assertSame('555', $rows[2]['bank_code']);

        $codes = array_column($rows, 'bank_code');
        self::assertNotContains('833', $codes);
        self::assertNotContains('907', $codes);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/me.html'), false);

        self::assertSame([], $rows);
    }

    private function writeFixture(string $html): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'iban_me_html_');
        file_put_contents($path, $html);

        return $path;
    }
}
