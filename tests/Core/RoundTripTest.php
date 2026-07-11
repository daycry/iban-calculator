<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Formatter;
use Daycry\Iban\Core\Mod97;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Consolidated round-trip coverage (T-50) over a diverse ~10-country sample
 * (ES/DE/FR/GB/NL/IT/MT/PL/TR/BR -- short/long IBANs, letters-in-BBAN
 * countries, and non-European ones), pulling each country's example
 * straight from the {@see Registry} rather than hand-copying strings, so it
 * stays in sync with the registry.
 *
 * This intentionally does NOT re-test what {@see FormatterTest} and
 * {@see Mod97Test} already cover in depth for a single country (e.g. exact
 * anonymized-string values, MOD-97 arithmetic edge cases): it only asserts
 * the three round-trip *properties* required by T-50, repeated across the
 * sample so they're proven to hold generally, not just for ES.
 *
 * @see .superpowers/sdd/task-45-50-brief.md
 */
final class RoundTripTest extends TestCase
{
    /** @var list<string> */
    private const SAMPLE_COUNTRIES = ['ES', 'DE', 'FR', 'GB', 'NL', 'IT', 'MT', 'PL', 'TR', 'BR'];

    private Formatter $formatter;
    private Mod97 $mod97;

    protected function setUp(): void
    {
        $this->formatter = new Formatter();
        $this->mod97     = new Mod97();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function sampleElectronicProvider(): iterable
    {
        $registry = new Registry();

        foreach (self::SAMPLE_COUNTRIES as $cc) {
            yield $cc => [$registry->get($cc)->ibanExampleElectronic];
        }
    }

    /**
     * Electronic -> Print -> Electronic must be idempotent: formatting to
     * Print only inserts spaces (never alters characters), so stripping
     * them back out must reproduce the original electronic string exactly.
     */
    #[DataProvider('sampleElectronicProvider')]
    public function testElectronicToPrintToElectronicIsIdempotent(string $electronic): void
    {
        $print = $this->formatter->format($electronic, IbanFormat::Print);

        self::assertSame($electronic, str_replace(' ', '', $print));
    }

    /**
     * Anonymized must always be "country code (2) + last 4", with everything
     * in between masked -- regardless of the country's own IBAN length.
     */
    #[DataProvider('sampleElectronicProvider')]
    public function testAnonymizedIsCountryCodePlusLastFour(string $electronic): void
    {
        $anonymized = $this->formatter->format($electronic, IbanFormat::Anonymized);

        self::assertSame(strlen($electronic), strlen($anonymized));
        self::assertSame(substr($electronic, 0, 2), substr($anonymized, 0, 2));
        self::assertSame(substr($electronic, -4), substr($anonymized, -4));
        self::assertSame(str_repeat('*', strlen($electronic) - 6), substr($anonymized, 2, -4));
    }

    /**
     * `Mod97::checkDigits($cc, $bban)` must round-trip through `98 - mod`:
     * regenerating the check digits from a valid IBAN's own country
     * code + BBAN reproduces the IBAN's actual check digits, and plugging
     * them back in makes the whole IBAN validate under MOD-97 again.
     */
    #[DataProvider('sampleElectronicProvider')]
    public function testMod97CheckDigitsRoundTripReproducesAValidIban(string $electronic): void
    {
        $cc   = substr($electronic, 0, 2);
        $bban = substr($electronic, 4);

        $regenerated = $this->mod97->checkDigits($cc, $bban);

        self::assertSame(substr($electronic, 2, 2), $regenerated);
        self::assertTrue($this->mod97->isValid($cc . $regenerated . $bban));
    }
}
