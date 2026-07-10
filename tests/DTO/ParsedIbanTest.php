<?php

declare(strict_types=1);

namespace Tests\DTO;

use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Enums\IbanFormat;
use PHPUnit\Framework\TestCase;

final class ParsedIbanTest extends TestCase
{
    public function testConstructionWithSpanishData(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertSame('ES', $parsed->countryCode);
        self::assertSame('91', $parsed->checkDigits);
        self::assertSame('21000418450200051332', $parsed->bban);
        self::assertSame('2100', $parsed->bankIdentifier);
        self::assertSame('0418', $parsed->branchIdentifier);
        self::assertSame('450200051332', $parsed->accountNumber);
        self::assertNull($parsed->nationalCheckDigit);
        self::assertTrue($parsed->sepaCountry);
        self::assertSame('ES9121000418450200051332', $parsed->electronic);
    }

    public function testConstructionWithGermanDataBranchIdentifierNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'DE',
            checkDigits: '89',
            bban: '100708487281071483',
            bankIdentifier: '10070848',
            branchIdentifier: null,
            accountNumber: '7281071483',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'DE89100708487281071483',
        );

        self::assertSame('DE', $parsed->countryCode);
        self::assertNull($parsed->branchIdentifier);
        self::assertTrue($parsed->sepaCountry);
        self::assertSame('DE89100708487281071483', (string) $parsed);
        self::assertSame('100708487281071483', $parsed->bankIdentifier . $parsed->accountNumber);
    }

    public function testToStringReturnsElectronic(): void
    {
        $electronic = 'ES9121000418450200051332';
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: $electronic,
        );

        self::assertSame($electronic, (string) $parsed);
    }

    public function testFormatElectronic(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertSame('ES9121000418450200051332', $parsed->format(IbanFormat::Electronic));
    }

    public function testFormatPrint(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertSame('ES91 2100 0418 4502 0005 1332', $parsed->format(IbanFormat::Print));
    }

    public function testFormatPrintDefault(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertSame('ES91 2100 0418 4502 0005 1332', $parsed->format());
    }

    public function testFormatAnonymized(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertSame('ES******************1332', $parsed->format(IbanFormat::Anonymized));
    }

    public function testFormatAnonymizedShortIban(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'XX',
            checkDigits: '00',
            bban: 'AB',
            bankIdentifier: 'B1',
            branchIdentifier: null,
            accountNumber: 'AN',
            nationalCheckDigit: null,
            sepaCountry: false,
            electronic: 'XX00AB',
        );

        self::assertSame('XX00AB', $parsed->format(IbanFormat::Anonymized));
    }
}
