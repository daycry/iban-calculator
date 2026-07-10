<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Formatter;
use Daycry\Iban\Enums\IbanFormat;
use PHPUnit\Framework\TestCase;

final class FormatterTest extends TestCase
{
    public function testFormatElectronic(): void
    {
        $formatter = new Formatter();
        $electronic = 'ES9121000418450200051332';

        self::assertSame(
            'ES9121000418450200051332',
            $formatter->format($electronic, IbanFormat::Electronic)
        );
    }

    public function testFormatPrint(): void
    {
        $formatter = new Formatter();
        $electronic = 'ES9121000418450200051332';

        self::assertSame(
            'ES91 2100 0418 4502 0005 1332',
            $formatter->format($electronic, IbanFormat::Print)
        );
    }

    public function testFormatPrintDefault(): void
    {
        $formatter = new Formatter();
        $electronic = 'ES9121000418450200051332';

        self::assertSame(
            'ES91 2100 0418 4502 0005 1332',
            $formatter->format($electronic)
        );
    }

    public function testFormatAnonymized(): void
    {
        $formatter = new Formatter();
        $electronic = 'ES9121000418450200051332';

        self::assertSame(
            'ES******************1332',
            $formatter->format($electronic, IbanFormat::Anonymized)
        );
    }

    public function testFormatAnonymizedShortInput(): void
    {
        $formatter = new Formatter();
        $electronic = 'ES12';

        self::assertSame(
            'ES12',
            $formatter->format($electronic, IbanFormat::Anonymized)
        );
    }

    public function testRoundTripElectronicPrint(): void
    {
        $formatter = new Formatter();
        $electronic = 'ES9121000418450200051332';
        $print = $formatter->format($electronic, IbanFormat::Print);

        self::assertSame(
            $electronic,
            str_replace(' ', '', $print)
        );
    }
}
