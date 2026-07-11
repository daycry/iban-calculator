<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Mod97;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Mod97Test extends TestCase
{
    private Mod97 $mod97;

    protected function setUp(): void
    {
        $this->mod97 = new Mod97();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validIbanProvider(): array
    {
        return [
            'ES (24 chars)'                                  => ['ES9121000418450200051332'],
            'DE (22 chars)'                                   => ['DE89370400440532013000'],
            'GB (22 chars)'                                    => ['GB29NWBK60161331926819'],
            'NL (18 chars)'                                    => ['NL91ABNA0417164300'],
            'MT (31 chars, letters + max length, no overflow)' => ['MT84MALT011000012345MTLCAST001S'],
        ];
    }

    #[DataProvider('validIbanProvider')]
    public function testIsValidReturnsTrueForRealValidIbans(string $iban): void
    {
        self::assertTrue($this->mod97->isValid($iban));
    }

    public function testIsValidReturnsFalseWhenADigitIsAltered(): void
    {
        // Last digit of a valid ES IBAN (...1332) changed to 3 (...1333).
        self::assertFalse($this->mod97->isValid('ES9121000418450200051333'));
    }

    public function testIsValidReturnsFalseWhenACheckDigitIsAltered(): void
    {
        self::assertFalse($this->mod97->isValid('DE90370400440532013000'));
    }

    public function testIsValidReturnsFalseForTooShortInput(): void
    {
        self::assertFalse($this->mod97->isValid('ES9'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function checkDigitsProvider(): array
    {
        return [
            'ES' => ['ES', '21000418450200051332', '91'],
            'DE' => ['DE', '370400440532013000', '89'],
            'GB' => ['GB', 'NWBK60161331926819', '29'],
            'NL' => ['NL', 'ABNA0417164300', '91'],
        ];
    }

    #[DataProvider('checkDigitsProvider')]
    public function testCheckDigitsComputesExpectedValue(string $countryCode, string $bban, string $expected): void
    {
        self::assertSame($expected, $this->mod97->checkDigits($countryCode, $bban));
    }

    #[DataProvider('checkDigitsProvider')]
    public function testCheckDigitsRoundTripsThroughIsValid(string $countryCode, string $bban): void
    {
        $check = $this->mod97->checkDigits($countryCode, $bban);

        self::assertTrue($this->mod97->isValid($countryCode . $check . $bban));
    }

    public function testCheckDigitsRoundTripsForLongMaltaLikeBban(): void
    {
        $countryCode = 'MT';
        $bban         = 'MALT011000012345MTLCAST001S';

        $check = $this->mod97->checkDigits($countryCode, $bban);

        self::assertSame('84', $check);
        self::assertTrue($this->mod97->isValid($countryCode . $check . $bban));
    }

    /**
     * `mod97()` is public so 32-bit-unsafe national check-digit validators
     * (Belgian, Slovenian) can reuse this overflow-safe windowed reducer
     * instead of casting long digit strings to (int) directly.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function mod97Provider(): array
    {
        return [
            // BE68539007547034: first 10 BBAN digits '5390075470', mod 97 = 34.
            'BE first10 digits'         => ['5390075470', 34],
            // SI56263300012039086: thirteen digits '2633000120390' with "00"
            // appended, mod 97 = 12 (so the SI check digit is 98 - 12 = 86).
            'SI thirteen digits + "00"' => ['263300012039000', 12],
            'zero'                      => ['0', 0],
            'exact multiple of 97'      => ['9700000000', 0],
        ];
    }

    #[DataProvider('mod97Provider')]
    public function testMod97ReturnsModuloOfPlainDigitString(string $numeric, int $expected): void
    {
        self::assertSame($expected, $this->mod97->mod97($numeric));
    }
}
