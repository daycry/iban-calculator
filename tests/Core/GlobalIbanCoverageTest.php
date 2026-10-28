<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Mod97;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Iban;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GlobalIbanCoverageTest extends TestCase
{
    public function testBrazilAcceptsTheAlphanumericBankAndAccountTypeIntroducedIn2026(): void
    {
        $bban = '12AB5678' . '00001' . '1234567890' . '1' . 'A';
        $iban = 'BR' . (new Mod97())->checkDigits('BR', $bban) . $bban;
        $service = new Iban();
        self::assertTrue($service->isValid($iban));
        self::assertSame('12AB5678', $service->parse($iban)->bankIdentifier);
        self::assertTrue($service->isValid('BR9700360305000010009795493P1'));
    }

    /**
     * Synthetic components, not customer accounts or copies of registry examples.
     * @return iterable<string, array{string, string, ?string, string, ?string}>
     */
    public static function additionalCountryProvider(): iterable
    {
        yield 'Burundi' => ['BI', '20001', '10001', '00012345678', '12'];
        yield 'Djibouti' => ['DJ', '00010', '00001', '00012345678', '34'];
        yield 'Falkland Islands' => ['FK', 'SC', null, '123456789012', null];
        yield 'Honduras' => ['HN', 'TEST', null, '00000000000012345678', null];
        yield 'Mongolia' => ['MN', '0001', null, '123456789012', null];
        yield 'Nicaragua' => ['NI', 'TEST', null, '00000000000012345678', null];
        yield 'Oman' => ['OM', '001', null, '000012345678ABCD', null];
        yield 'Russia' => ['RU', '044525225', '40817', '1234567890ABCDE', null];
        yield 'Sudan' => ['SD', '04', null, '123456789012', null];
        yield 'Somalia' => ['SO', '0001', '001', '123456789012', null];
        yield 'Yemen' => ['YE', 'TEST', '0001', '123456789012345ABC', null];
    }

    #[DataProvider('additionalCountryProvider')]
    public function testAdditionalCountriesValidateAndParseTheirComponents(
        string $country,
        string $bank,
        ?string $branch,
        string $account,
        ?string $national
    ): void {
        $bban = $bank . $branch . $account . $national;
        $iban = $country . (new Mod97())->checkDigits($country, $bban) . $bban;
        $service = new Iban();
        $result = $service->validate($iban);
        self::assertTrue($result->isValid(), $result->firstViolation()?->code->value ?? '');
        $parsed = $service->parse($iban);
        self::assertSame($bank, $parsed->bankIdentifier);
        self::assertSame($branch, $parsed->branchIdentifier);
        self::assertSame($account, $parsed->accountNumber);
        self::assertSame($national, $parsed->nationalCheckDigit);
        self::assertFalse($parsed->sepaCountry);

        $badChecksum = $country . '00' . $bban;
        self::assertSame(ViolationCode::ChecksumFailed, $service->validate($badChecksum)->firstViolation()?->code);
        self::assertSame(ViolationCode::BadLength, $service->validate(substr($iban, 0, -1))->firstViolation()?->code);

        $badBban = (ctype_alpha($bank) ? '0' : 'A') . substr($bban, 1);
        $badStructure = $country . (new Mod97())->checkDigits($country, $badBban) . $badBban;
        self::assertSame(ViolationCode::MalformedStructure, $service->validate($badStructure)->firstViolation()?->code);
    }
}
