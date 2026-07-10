<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator(new Registry());
    }

    // -- Valid IBANs -----------------------------------------------------

    public function testValidIbanIsValid(): void
    {
        $result = $this->validator->validate('ES9121000418450200051332');

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    public function testValidIbanWithSpacesAndLowercaseNormalizesAndIsValid(): void
    {
        $result = $this->validator->validate('  es91 2100 0418 4502 0005 1332  ');

        self::assertTrue($result->isValid());
    }

    public function testIsValidReturnsTrueForValidIban(): void
    {
        self::assertTrue($this->validator->isValid('ES9121000418450200051332'));
    }

    // -- One test per reachable ViolationCode -----------------------------

    public function testBlankIbanIsReportedAsBlank(): void
    {
        $result    = $this->validator->validate('   ');
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::Blank, $violation->code);
        self::assertSame('iban.violation.blank', $violation->messageKey);
        self::assertSame('The IBAN is empty.', $violation->message);
    }

    public function testEmptyStringIsReportedAsBlank(): void
    {
        $result = $this->validator->validate('');

        self::assertSame(ViolationCode::Blank, $result->firstViolation()?->code);
    }

    public function testTooShortIbanIsReportedAsTooShort(): void
    {
        $result    = $this->validator->validate('ES9');
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::TooShort, $violation->code);
        self::assertSame('iban.violation.too_short', $violation->messageKey);
        self::assertSame('The IBAN is too short.', $violation->message);
    }

    public function testIllegalCharactersIsReported(): void
    {
        $result    = $this->validator->validate('ES91-2100');
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::IllegalCharacters, $violation->code);
        self::assertSame('iban.violation.illegal_characters', $violation->messageKey);
        self::assertSame('The IBAN contains illegal characters.', $violation->message);
    }

    public function testUnknownCountryIsReported(): void
    {
        // 24 chars, all [A-Z0-9], but "ZZ" is not a registered country.
        $result    = $this->validator->validate('ZZ9121000418450200051332');
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::UnknownCountry, $violation->code);
        self::assertSame('iban.violation.unknown_country', $violation->messageKey);
        self::assertSame('Unknown or unsupported IBAN country code.', $violation->message);
    }

    public function testBadLengthIsReported(): void
    {
        // 23 chars: one digit short of the 24-char ES length.
        $result    = $this->validator->validate('ES912100041845020005133');
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::BadLength, $violation->code);
        self::assertSame('iban.violation.bad_length', $violation->messageKey);
        self::assertSame('The IBAN length is invalid for its country.', $violation->message);
    }

    public function testMalformedStructureIsReportedWhenALetterReplacesADigitInTheBban(): void
    {
        // 24 chars (correct ES length), legal [A-Z0-9] chars, but the 'X'
        // sits in a digit-only slot of the BBAN structure (account field).
        $result    = $this->validator->validate('ES9121000418450200051X32');
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::MalformedStructure, $violation->code);
        self::assertSame('iban.violation.malformed_structure', $violation->messageKey);
        self::assertSame('The IBAN structure is malformed.', $violation->message);
    }

    public function testMalformedStructureIsReportedWhenCheckDigitsAreNotNumeric(): void
    {
        // 24 chars (correct ES length), legal [A-Z0-9] chars, but the check
        // digits ("XX") are not numeric.
        $result = $this->validator->validate('ESXX21000418450200051332');

        self::assertFalse($result->isValid());
        self::assertSame(ViolationCode::MalformedStructure, $result->firstViolation()?->code);
    }

    public function testChecksumFailedIsReported(): void
    {
        // Correct length/structure, but check digits 91 -> 90 (invalid MOD-97).
        $result    = $this->validator->validate('ES9021000418450200051332');
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::ChecksumFailed, $violation->code);
        self::assertSame('iban.violation.checksum_failed', $violation->messageKey);
        self::assertSame('The IBAN check digits are invalid.', $violation->message);
    }

    // -- Pipeline ORDER (first violation wins) ----------------------------

    public function testIllegalCharactersTakesPrecedenceOverBadLength(): void
    {
        // 'ES91-2100' is a known country (ES) with a length that mismatches
        // the required 24 -- which would be BadLength -- but the illegal
        // '-' character must be reported first per the pipeline order.
        $result = $this->validator->validate('ES91-2100');

        self::assertSame(ViolationCode::IllegalCharacters, $result->firstViolation()?->code);
    }

    public function testTooShortTakesPrecedenceOverUnknownCountry(): void
    {
        // 'ZZ' is both too short (2 < 4) and an unknown country; TooShort
        // must win per the pipeline order.
        $result = $this->validator->validate('ZZ');

        self::assertSame(ViolationCode::TooShort, $result->firstViolation()?->code);
    }

    public function testBlankTakesPrecedenceOverTooShort(): void
    {
        // An all-whitespace input normalizes to '' (length 0, which would
        // also satisfy TooShort's `< 4` check) -- Blank must win.
        $result = $this->validator->validate('    ');

        self::assertSame(ViolationCode::Blank, $result->firstViolation()?->code);
    }

    // -- Never throws ------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function garbageProvider(): array
    {
        return [
            'empty'                  => [''],
            'only whitespace'        => ["\t\n  \r"],
            'binary-ish junk'        => ["\x00\x01\x02"],
            'emoji'                  => ['🚀🚀🚀🚀'],
            'sql-injection-looking'  => ["'; DROP TABLE ibans; --"],
            'extremely long'         => [str_repeat('A', 5000)],
            'null-byte embedded'     => ["ES91\x00 2100"],
            'just IBAN prefix'       => ['IBAN:'],
            'lowercase unicode'      => ['és91²100'],
        ];
    }

    #[DataProvider('garbageProvider')]
    public function testValidateNeverThrowsOnGarbageInput(string $garbage): void
    {
        try {
            $result = $this->validator->validate($garbage);
        } catch (Throwable $e) {
            self::fail('validate() threw ' . $e::class . ': ' . $e->getMessage());
        }

        self::assertInstanceOf(ValidationResult::class, $result);
    }

    #[DataProvider('garbageProvider')]
    public function testIsValidNeverThrowsOnGarbageInput(string $garbage): void
    {
        try {
            $isValid = $this->validator->isValid($garbage);
        } catch (Throwable $e) {
            self::fail('isValid() threw ' . $e::class . ': ' . $e->getMessage());
        }

        self::assertFalse($isValid);
    }

    // -- National check hook (empty map => silent skip) -------------------

    public function testCheckNationalIsSilentlySkippedWhenNoValidatorIsRegisteredForCountry(): void
    {
        // Since T-27, the default $nationalValidators map wires in ES, so
        // this test builds its own Validator with an explicitly empty map
        // to keep testing the "no validator registered => silent skip"
        // behaviour in isolation: requesting the national check must not
        // turn a structurally/checksum-valid IBAN invalid.
        $validator = new Validator(new Registry(), nationalValidators: []);

        $result = $validator->validate('ES9121000418450200051332', checkNational: true);

        self::assertTrue($result->isValid());
    }

    // -- toParsedIban() ------------------------------------------------------

    public function testToParsedIbanExtractsExpectedFieldsForSpain(): void
    {
        $parsed = $this->validator->toParsedIban('ES9121000418450200051332');

        self::assertSame('ES', $parsed->countryCode);
        self::assertSame('91', $parsed->checkDigits);
        self::assertSame('21000418450200051332', $parsed->bban);
        self::assertSame('2100', $parsed->bankIdentifier);
        self::assertSame('0418', $parsed->branchIdentifier);
        self::assertSame('0200051332', $parsed->accountNumber);
        self::assertSame('45', $parsed->nationalCheckDigit);
        self::assertTrue($parsed->sepaCountry);
        self::assertSame('ES9121000418450200051332', $parsed->electronic);
    }
}
