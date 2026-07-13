<?php

declare(strict_types=1);

namespace Tests\DTO;

use Daycry\Iban\DTO\ParsedBic;
use PHPUnit\Framework\TestCase;

final class ParsedBicTest extends TestCase
{
    public function testEightCharBicHasNullBranchAndIsPrimaryOffice(): void
    {
        $bic = new ParsedBic('CHASUS33', 'CHAS', 'US', '33', null);

        self::assertSame('CHASUS33', $bic->bic);
        self::assertSame('CHAS', $bic->institutionCode);
        self::assertSame('US', $bic->countryCode);
        self::assertSame('33', $bic->locationCode);
        self::assertNull($bic->branchCode);
        self::assertTrue($bic->isPrimaryOffice());
    }

    public function testElevenCharBicWithXxxBranchIsPrimaryOffice(): void
    {
        $bic = new ParsedBic('CAIXESBBXXX', 'CAIX', 'ES', 'BB', 'XXX');

        self::assertSame('XXX', $bic->branchCode);
        self::assertTrue($bic->isPrimaryOffice());
    }

    public function testElevenCharBicWithRealBranchIsNotPrimaryOffice(): void
    {
        $bic = new ParsedBic('DEUTDEFF500', 'DEUT', 'DE', 'FF', '500');

        self::assertSame('500', $bic->branchCode);
        self::assertFalse($bic->isPrimaryOffice());
    }

    public function testBic8ReturnsFirstEightCharactersOfEitherForm(): void
    {
        self::assertSame('CHASUS33', (new ParsedBic('CHASUS33', 'CHAS', 'US', '33', null))->bic8());
        self::assertSame('CAIXESBB', (new ParsedBic('CAIXESBBXXX', 'CAIX', 'ES', 'BB', 'XXX'))->bic8());
    }

    public function testStringableReturnsTheBic(): void
    {
        self::assertSame('CHASUS33', (string) new ParsedBic('CHASUS33', 'CHAS', 'US', '33', null));
    }
}
