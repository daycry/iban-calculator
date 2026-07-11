<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\StructureCompiler;
use PHPUnit\Framework\TestCase;

final class StructureCompilerTest extends TestCase
{
    private StructureCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new StructureCompiler();
    }

    public function testMatchesAllDigitStructureAgainstValidEsBban(): void
    {
        self::assertTrue($this->compiler->matches('4!n4!n2!n10!n', '21000418450200051332'));
    }

    public function testDoesNotMatchWhenALetterReplacesADigit(): void
    {
        self::assertFalse($this->compiler->matches('4!n4!n2!n10!n', '2100041845020005133X'));
    }

    public function testDoesNotMatchWhenLengthIsWrong(): void
    {
        self::assertFalse($this->compiler->matches('4!n4!n2!n10!n', '2100'));
    }

    public function testClassALettersOnlyMatches(): void
    {
        self::assertTrue($this->compiler->matches('4!a', 'ABCD'));
    }

    public function testClassARejectsDigits(): void
    {
        self::assertFalse($this->compiler->matches('4!a', 'AB12'));
    }

    public function testClassCAcceptsUppercaseAlphanumeric(): void
    {
        self::assertTrue($this->compiler->matches('4!c', 'AB12'));
    }

    public function testClassCRejectsLowercase(): void
    {
        self::assertFalse($this->compiler->matches('4!c', 'ab12'));
    }

    public function testMatchesMixedBulgarianStructure(): void
    {
        self::assertTrue($this->compiler->matches('4!a4!n2!n8!c', 'BNBG96611020345678'));
    }

    public function testToRegexCachesResultForSameStructure(): void
    {
        $first  = $this->compiler->toRegex('4!n4!n2!n10!n');
        $second = $this->compiler->toRegex('4!n4!n2!n10!n');

        self::assertSame($first, $second);
    }

    public function testToRegexProducesAnchoredRegexForFixedLengthDigits(): void
    {
        self::assertSame('#^([0-9]){4}$#', $this->compiler->toRegex('4!n'));
    }

    public function testToRegexProducesVariableLengthQuantifierWithoutBang(): void
    {
        self::assertSame('#^([0-9]){1,4}$#', $this->compiler->toRegex('4n'));
    }

    public function testMatchesLiteralSpaceForClassE(): void
    {
        self::assertTrue($this->compiler->matches('1!e', ' '));
        self::assertFalse($this->compiler->matches('1!e', 'X'));
    }
}
