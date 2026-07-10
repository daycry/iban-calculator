<?php

declare(strict_types=1);

namespace Tests\Registry;

use Daycry\Iban\Registry\CountryStructure;
use PHPUnit\Framework\TestCase;

final class CountryStructureTest extends TestCase
{
    public function testConstructsWithAllPropertiesForES(): void
    {
        $structure = new CountryStructure(
            countryCode: 'ES',
            ibanLength: 24,
            bbanStructure: '4!n4!n2!n10!n',
            bank: [4, 4],
            branch: [8, 4],
            account: [14, 10],
            nationalCheck: [12, 2],
            sepa: true,
            ibanExampleElectronic: 'ES9121000418450200051332',
        );

        self::assertSame('ES', $structure->countryCode);
        self::assertSame(24, $structure->ibanLength);
        self::assertSame('4!n4!n2!n10!n', $structure->bbanStructure);
        self::assertSame([4, 4], $structure->bank);
        self::assertSame([8, 4], $structure->branch);
        self::assertSame([14, 10], $structure->account);
        self::assertSame([12, 2], $structure->nationalCheck);
        self::assertTrue($structure->sepa);
        self::assertSame('ES9121000418450200051332', $structure->ibanExampleElectronic);
    }

    public function testConstructsWithNullBranchAndNationalCheck(): void
    {
        $structure = new CountryStructure(
            countryCode: 'GB',
            ibanLength: 22,
            bbanStructure: '4!a2!n4!n8!n1!n1!c',
            bank: [4, 4],
            branch: null,
            account: [12, 8],
            nationalCheck: null,
            sepa: false,
            ibanExampleElectronic: 'GB82WEST12345698765432',
        );

        self::assertSame('GB', $structure->countryCode);
        self::assertSame(22, $structure->ibanLength);
        self::assertSame('4!a2!n4!n8!n1!n1!c', $structure->bbanStructure);
        self::assertSame([4, 4], $structure->bank);
        self::assertNull($structure->branch);
        self::assertSame([12, 8], $structure->account);
        self::assertNull($structure->nationalCheck);
        self::assertFalse($structure->sepa);
        self::assertSame('GB82WEST12345698765432', $structure->ibanExampleElectronic);
    }
}
