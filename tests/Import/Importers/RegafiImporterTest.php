<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\RegafiImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see RegafiImporter} (parameterized per country, FR and MC)
 * against a hand-written JSON fixture reproducing the REGAFI Opendatasoft
 * `exports/json` shape: a flat array of records, each carrying a
 * JSON-*serialized* `cib` array (`"[\"30003\"]"` / `"[{\"code\":\"20041\"}]"`
 * / `"[]"`), a `denomination` and a `pays` field. Monaco entities live in the
 * same dataset and carry a CIB, so both countries resolve from one class,
 * filtered by `pays`.
 *
 * @see \Daycry\Iban\Import\Importers\RegafiImporter
 */
final class RegafiImporterTest extends TestCase
{
    private ?string $fixturePath = null;

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
        self::assertInstanceOf(ImporterInterface::class, new RegafiImporter('FR'));
    }

    public function testMetadataIsAsDocumentedAndParameterizedPerCountry(): void
    {
        $fr = new RegafiImporter('FR');
        $mc = new RegafiImporter('MC');

        self::assertSame('FR', $fr->countryCode());
        self::assertSame('MC', $mc->countryCode());
        self::assertSame('regafi', $fr->sourceId());
        self::assertSame('regafi', $mc->sourceId());
        self::assertStringContainsString('Licence Ouverte', $fr->license());
        self::assertLessThanOrEqual(64, strlen($fr->license()));
        self::assertStringContainsString('regafi.fr', $fr->sourceUrl());
    }

    public function testConstructorLowercaseCountryIsUppercased(): void
    {
        self::assertSame('FR', (new RegafiImporter('fr'))->countryCode());
    }

    public function testFranceRowsExpandTheSerializedCibArrayAndZeroPad(): void
    {
        $this->fixturePath = $this->writeFixture();

        $rows = iterator_to_array((new RegafiImporter('FR'))->rows($this->fixturePath), false);

        // 30003 (SG), 20041 + 20042 (La Banque Postale, two CIB), 07788
        // (zero-padded short code). The empty-cib entity and the two Monaco
        // entities are excluded.
        self::assertCount(4, $rows);

        self::assertSame('30003', $rows[0]['bank_code']);
        self::assertSame('Société Générale', $rows[0]['name']);
        self::assertNull($rows[0]['branch_code']);
        self::assertArrayNotHasKey('bic', $rows[0]); // REGAFI carries no BIC

        self::assertSame('20041', $rows[1]['bank_code']);
        self::assertSame('La Banque Postale', $rows[1]['name']);
        self::assertSame('20042', $rows[2]['bank_code']);
        self::assertSame('La Banque Postale', $rows[2]['name']); // same entity, second CIB

        self::assertSame('07788', $rows[3]['bank_code']); // '7788' zero-padded to 5
    }

    public function testMonacoRowsAreFilteredFromTheSameDataset(): void
    {
        $this->fixturePath = $this->writeFixture();

        $rows = iterator_to_array((new RegafiImporter('MC'))->rows($this->fixturePath), false);

        self::assertCount(2, $rows);

        self::assertSame('12739', $rows[0]['bank_code']);
        self::assertSame('CFM Indosuez Wealth', $rows[0]['name']);
        self::assertSame('10160', $rows[1]['bank_code']);
        self::assertSame('Crédit Mobilier de Monaco', $rows[1]['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array((new RegafiImporter('FR'))->rows('/nonexistent/regafi.json'), false);

        self::assertSame([], $rows);
    }

    private function writeFixture(): string
    {
        // Note the DOUBLE encoding: `cib` is itself a JSON string inside the
        // record, exactly as REGAFI serializes it.
        $records = [
            ['cib' => json_encode(['30003']), 'denomination' => 'Société Générale', 'pays' => 'FRANCE'],
            ['cib' => json_encode([['code' => '20041'], ['code' => '20042']]), 'denomination' => 'La Banque Postale', 'pays' => 'FRANCE'],
            ['cib' => '[]', 'denomination' => 'Entity Without CIB', 'pays' => 'FRANCE'],
            ['cib' => json_encode(['7788']), 'denomination' => 'Short Code Bank', 'pays' => 'FRANCE'],
            ['cib' => json_encode(['12739']), 'denomination' => 'CFM Indosuez Wealth', 'pays' => 'MONACO'],
            ['cib' => json_encode(['10160']), 'denomination' => 'Crédit Mobilier de Monaco', 'pays' => 'MONACO'],
        ];

        $path = (string) tempnam(sys_get_temp_dir(), 'iban_regafi_');
        file_put_contents($path, (string) json_encode($records));

        return $path;
    }
}
