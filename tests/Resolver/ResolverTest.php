<?php

declare(strict_types=1);

namespace Tests\Resolver;

use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Exceptions\InvalidIbanException;
use Daycry\Iban\Registry\Registry;
use Daycry\Iban\Resolver\Resolver;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ResolverTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Validator(new Registry()), new Normalizer());
    }

    public function testResolveWithDefaultNullProviderLeavesBankFieldsNull(): void
    {
        $resolver = new Resolver($this->parser);

        $result = $resolver->resolve('ES9121000418450200051332');

        self::assertInstanceOf(BankResult::class, $result);
        self::assertSame('ES', $result->iban->countryCode);
        self::assertTrue($result->iban->sepaCountry);

        self::assertNull($result->bankName);
        self::assertNull($result->shortName);
        self::assertNull($result->bic);
        self::assertNull($result->city);
        self::assertNull($result->address);
        self::assertNull($result->sepaSct);
        self::assertNull($result->sepaSctInst);
        self::assertNull($result->sepaSddCore);
        self::assertNull($result->sepaSddB2b);
        self::assertNull($result->sourceId);
        self::assertNull($result->sourceVersion);
        self::assertNull($result->sourceLicense);

        self::assertFalse($result->isResolved());
    }

    public function testResolveWithSupportingProviderOverlaysBankInfoFromFindByIban(): void
    {
        $bankInfo = new BankInfo(
            bankName: 'CaixaBank',
            shortName: 'CX',
            bic: 'CAIXESBBXXX',
            city: 'Barcelona',
            address: null,
            sepaSct: true,
            sepaSctInst: null,
            sepaSddCore: true,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        $provider = new class ($bankInfo) implements ProviderInterface {
            public function __construct(private BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return $countryCode === 'ES';
            }

            public function findByIban(ParsedIban $iban): BankInfo
            {
                return $this->bankInfo;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                \PHPUnit\Framework\Assert::fail('findByBankCode should not be called when findByIban already resolved a result.');
            }
        };

        $resolver = new Resolver($this->parser, $provider);

        $result = $resolver->resolve('ES9121000418450200051332');

        self::assertSame('CaixaBank', $result->bankName);
        self::assertSame('CAIXESBBXXX', $result->bic);
        self::assertTrue($result->isResolved());
    }

    public function testResolveFallsBackToFindByBankCodeWhenFindByIbanReturnsNull(): void
    {
        $bankInfo = new BankInfo(
            bankName: 'Banco Fallback',
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

        $provider = new class ($bankInfo) implements ProviderInterface {
            public function __construct(private BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return $countryCode === 'ES';
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): BankInfo
            {
                return $this->bankInfo;
            }
        };

        $resolver = new Resolver($this->parser, $provider);

        $result = $resolver->resolve('ES9121000418450200051332');

        self::assertSame('Banco Fallback', $result->bankName);
        self::assertTrue($result->isResolved());
    }

    public function testResolveDoesNotOverlayWhenProviderDoesNotSupportCountry(): void
    {
        $provider = new class () implements ProviderInterface {
            public function supports(string $countryCode): bool
            {
                return false;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                \PHPUnit\Framework\Assert::fail('findByIban should not be called when supports() is false.');
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                \PHPUnit\Framework\Assert::fail('findByBankCode should not be called when supports() is false.');
            }
        };

        $resolver = new Resolver($this->parser, $provider);

        $result = $resolver->resolve('ES9121000418450200051332');

        self::assertFalse($result->isResolved());
    }

    public function testResolveWithAlreadyParsedIbanDoesNotReparse(): void
    {
        // The electronic field is deliberately invalid (would fail Parser::parse()
        // if re-parsed), so a passing test proves resolve() skips re-parsing when
        // it already receives a ParsedIban instance.
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'NOTAVALIDIBANVALUE',
        );

        $resolver = new Resolver($this->parser);

        $result = $resolver->resolve($parsed);

        self::assertSame($parsed, $result->iban);
        self::assertFalse($result->isResolved());
    }

    public function testResolveThrowsInvalidIbanExceptionOnGarbageInput(): void
    {
        $resolver = new Resolver($this->parser);

        $this->expectException(InvalidIbanException::class);

        $resolver->resolve('basura');
    }
}
