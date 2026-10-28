<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Iban;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** @return iterable<string, array{string}> */
    public static function invisibleProvider(): iterable
    {
        foreach ([0x00A0, 0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006,
            0x2007, 0x2008, 0x2009, 0x200A, 0x202F, 0x3000, 0x200B, 0xFEFF] as $codepoint) {
            yield sprintf('U+%04X', $codepoint) => [mb_chr($codepoint, 'UTF-8')];
        }
    }

    #[DataProvider('invisibleProvider')]
    public function testUnicodeSeparatorsAreRemovedAndTheIbanValidates(string $separator): void
    {
        $input = $separator . 'iban:' . $separator . 'es91' . $separator . '21000418450200051332' . $separator;
        self::assertSame('ES9121000418450200051332', $this->normalizer->normalize($input));
        self::assertTrue((new Iban())->isValid($input));
    }

    public function testMalformedUtf8IsPreservedAndRejectedRatherThanDiscarded(): void
    {
        $input = "ES91\xC2 21000418450200051332";
        self::assertSame("ES91\xC221000418450200051332", $this->normalizer->normalize($input));
        self::assertFalse((new Iban())->isValid($input));
    }
}
