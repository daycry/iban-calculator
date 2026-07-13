<?php

declare(strict_types=1);

namespace Tests\DTO;

use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedIban;
use PHPUnit\Framework\TestCase;

final class BankResultTest extends TestCase
{
    private ParsedIban $testIban;

    protected function setUp(): void
    {
        $this->testIban = new ParsedIban(
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
    }

    public function testBankResultWithAllNullFieldsIsNotResolved(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: null,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertFalse($result->isResolved());
    }

    public function testBankResultWithBankNameResolves(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: 'CaixaBank',
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertTrue($result->isResolved());
    }

    public function testBankResultWithBicOnlyResolves(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: null,
            shortName: null,
            bic: 'CAIXESBBXXX',
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertTrue($result->isResolved());
    }

    public function testBankResultWithSepaSctFalseResolves(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: null,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: false,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertTrue($result->isResolved());
    }

    public function testBankResultComposesIban(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: null,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertSame($this->testIban, $result->iban);
        self::assertSame('ES9121000418450200051332', $result->iban->electronic);
    }

    public function testBankResultResolvedByDefaultsToNull(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: null,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertNull($result->resolvedBy);
    }

    public function testBankResultResolvedByIsSetViaNamedArg(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: 'CaixaBank',
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
            resolvedBy: 'database',
        );

        self::assertSame('database', $result->resolvedBy);
    }

    /**
     * Pin: `resolvedBy` is provenance METADATA, not bank data. A BankResult
     * with ONLY `resolvedBy` set — every one of the 12 bank fields still
     * null — must NOT report itself resolved, otherwise a caller could get
     * `isResolved() === true` with no actual bank data at all.
     */
    public function testBankResultWithOnlyResolvedBySetIsStillNotResolved(): void
    {
        $result = new BankResult(
            iban: $this->testIban,
            bankName: null,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
            resolvedBy: 'iban.com',
        );

        self::assertSame('iban.com', $result->resolvedBy);
        self::assertFalse($result->isResolved());
    }

    public function testBankInfoConstructsWithAllNullFields(): void
    {
        $info = new BankInfo(
            bankName: null,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertNull($info->bankName);
        self::assertNull($info->shortName);
        self::assertNull($info->bic);
        self::assertNull($info->city);
        self::assertNull($info->address);
        self::assertNull($info->sepaSct);
        self::assertNull($info->sepaSctInst);
        self::assertNull($info->sepaSddCore);
        self::assertNull($info->sepaSddB2b);
        self::assertNull($info->sourceId);
        self::assertNull($info->sourceVersion);
        self::assertNull($info->sourceLicense);
    }

    public function testBankInfoConstructsWithMixedFields(): void
    {
        $info = new BankInfo(
            bankName: 'CaixaBank',
            shortName: 'CX',
            bic: 'CAIXESBBXXX',
            city: 'Barcelona',
            address: 'Avinguda Diagonal, 629',
            sepaSct: true,
            sepaSctInst: false,
            sepaSddCore: true,
            sepaSddB2b: false,
            sourceId: 'NWABAXXX',
            sourceVersion: '1.0',
            sourceLicense: 'CC-BY-4.0',
        );

        self::assertSame('CaixaBank', $info->bankName);
        self::assertSame('CX', $info->shortName);
        self::assertSame('CAIXESBBXXX', $info->bic);
        self::assertSame('Barcelona', $info->city);
        self::assertSame('Avinguda Diagonal, 629', $info->address);
        self::assertTrue($info->sepaSct);
        self::assertFalse($info->sepaSctInst);
        self::assertTrue($info->sepaSddCore);
        self::assertFalse($info->sepaSddB2b);
        self::assertSame('NWABAXXX', $info->sourceId);
        self::assertSame('1.0', $info->sourceVersion);
        self::assertSame('CC-BY-4.0', $info->sourceLicense);
    }

    public function testBankInfoResolvedByDefaultsToNull(): void
    {
        $info = new BankInfo(
            bankName: null,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        self::assertNull($info->resolvedBy);
    }

    public function testBankInfoResolvedByIsSetViaNamedArg(): void
    {
        $info = new BankInfo(
            bankName: 'CaixaBank',
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
            resolvedBy: 'database',
        );

        self::assertSame('database', $info->resolvedBy);
    }
}
