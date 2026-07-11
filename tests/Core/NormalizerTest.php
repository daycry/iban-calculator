<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Normalizer;
use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase
{
    private Normalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new Normalizer();
    }

    public function testStripsSpacesLowercaseAndLeadingIbanPrefix(): void
    {
        self::assertSame(
            'ES9121000418450200051332',
            $this->normalizer->normalize('  iban es91 2100 0418 4502 0005 1332')
        );
    }

    public function testStripsLeadingIbanPrefixWithColonSeparator(): void
    {
        self::assertSame(
            'DE89370400440532013000',
            $this->normalizer->normalize('IBAN: DE89 3704 0044 0532 0130 00')
        );
    }

    public function testUppercasesLowercaseInput(): void
    {
        self::assertSame(
            'DE89370400440532013000',
            $this->normalizer->normalize('de89370400440532013000')
        );
    }

    public function testPreservesInvalidCharacters(): void
    {
        self::assertSame('ES91-2100', $this->normalizer->normalize('ES91-2100'));
    }

    public function testIsIdempotentOnAlreadyNormalizedInput(): void
    {
        self::assertSame(
            'ES9121000418450200051332',
            $this->normalizer->normalize('ES9121000418450200051332')
        );
    }

    public function testDoesNotStripIbanOccurringInTheMiddle(): void
    {
        self::assertSame('GB29NWBKIBAN', $this->normalizer->normalize('GB29NWBKIBAN'));
    }
}
