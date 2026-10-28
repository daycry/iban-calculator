<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Iban\Contracts\VerificationProviderInterface;
use Daycry\Iban\DTO\ExternalVerification;
use Daycry\Iban\Iban;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VerificationTest extends TestCase
{
    public function testOfflineVerificationDistinguishesValidInvalidAndUnsupported(): void
    {
        $iban = new Iban();
        self::assertSame('format_valid', $iban->verifyIban('ES9121000418450200051332')->status->value);
        self::assertSame('format_invalid', $iban->verifyIban('ES9021000418450200051332')->status->value);
        self::assertSame('unsupported_country', $iban->verifyIban('ZZ0012345678901234')->status->value);
        self::assertSame('format_valid', $iban->verifyBic('CHASUS33')->status->value);
        self::assertSame('format_invalid', $iban->verifyBic('NOT-A-BIC')->status->value);
        self::assertNull($iban->verifyBic('CHASUS33')->external);
    }

    public function testExternalVerificationReceivesNormalizedInputAndKeepsExistenceSeparate(): void
    {
        $provider = new class () implements VerificationProviderInterface {
            /** @var list<string> */
            public array $received = [];

            public function verifyIban(string $iban): ExternalVerification
            {
                $this->received[] = $iban;
                return new ExternalVerification(true, 'test-provider');
            }

            public function verifyBic(string $bic): ExternalVerification
            {
                $this->received[] = $bic;
                return new ExternalVerification(true, 'test-provider', exists: true, active: false);
            }
        };
        $iban = new Iban(verificationProvider: $provider);
        $account = $iban->verifyIban("es91\u{00A0}21000418450200051332");
        self::assertSame('externally_validated', $account->status->value);
        self::assertNull($account->external?->exists, 'IBAN validation cannot establish account existence.');
        $bic = $iban->verifyBic("chasus33\u{FEFF}");
        self::assertSame('externally_validated', $bic->status->value);
        self::assertTrue($bic->external?->exists);
        self::assertFalse($bic->external->active);
        self::assertSame(['ES9121000418450200051332', 'CHASUS33'], $provider->received);

        $iban->verifyIban('ES9021000418450200051332');
        $iban->verifyBic('INVALID');
        self::assertCount(2, $provider->received, 'Invalid local inputs must not make paid API calls.');
    }

    public function testExternalFailureIsUnavailableAndDoesNotInvalidateTheLocalFormat(): void
    {
        $provider = new class () implements VerificationProviderInterface {
            public function verifyIban(string $iban): ExternalVerification
            {
                throw new RuntimeException('Provider failed');
            }

            public function verifyBic(string $bic): ExternalVerification
            {
                return new ExternalVerification(false, 'test-provider', exists: false);
            }
        };
        $iban = new Iban(verificationProvider: $provider);
        $result = $iban->verifyIban('ES9121000418450200051332');
        self::assertSame('verification_unavailable', $result->status->value);
        self::assertTrue($result->validation->isValid());
        self::assertSame('external_rejected', $iban->verifyBic('CHASUS33')->status->value);
    }
}
