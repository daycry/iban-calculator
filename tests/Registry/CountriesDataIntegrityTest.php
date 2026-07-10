<?php

declare(strict_types=1);

namespace Tests\Registry;

use Daycry\Iban\Core\Mod97;
use Daycry\Iban\Registry\CountryStructure;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Data-integrity safety net for the IBAN structural registry.
 *
 * Parametrized over every {@see CountryStructure} in {@see Registry::all()},
 * so it automatically grows as new countries are added to the registry
 * (T-16/17/18) and catches mis-authored entries (e.g. wrong offsets, a
 * mistyped BBAN token, or an example that doesn't check out under MOD-97)
 * without needing a matching test to be written by hand for each country.
 *
 * @see docs/registry-authoring.md
 * @see .superpowers/sdd/task-19-brief.md
 */
final class CountriesDataIntegrityTest extends TestCase
{
    /**
     * @return iterable<string, array{0: CountryStructure}>
     */
    public static function countryProvider(): iterable
    {
        foreach ((new Registry())->all() as $countryCode => $structure) {
            yield $countryCode => [$structure];
        }
    }

    #[DataProvider('countryProvider')]
    public function testCountryStructureIsInternallyConsistent(CountryStructure $country): void
    {
        self::assertSame(
            [],
            self::structuralViolations($country),
            sprintf('Country "%s" has structural integrity violations.', $country->countryCode),
        );
    }

    /**
     * Proves the validation helper actually catches bad entries: each case
     * deliberately breaks exactly one of the five checks (see
     * {@see structuralViolations()}) starting from an otherwise-valid,
     * ES-shaped structure.
     *
     * @return iterable<string, array{0: CountryStructure}>
     */
    public static function inconsistentCountryProvider(): iterable
    {
        yield 'example prefix does not match country code' => [
            new CountryStructure(
                countryCode: 'ES',
                ibanLength: 24,
                bbanStructure: '4!n4!n2!n10!n',
                bank: [4, 4],
                branch: [8, 4],
                account: [14, 10],
                nationalCheck: [12, 2],
                sepa: true,
                ibanExampleElectronic: 'GB9121000418450200051332',
            ),
        ];

        yield 'example length does not match ibanLength' => [
            new CountryStructure(
                countryCode: 'ES',
                ibanLength: 24,
                bbanStructure: '4!n4!n2!n10!n',
                bank: [4, 4],
                branch: [8, 4],
                account: [14, 10],
                nationalCheck: [12, 2],
                sepa: true,
                ibanExampleElectronic: 'ES912100041845020005133',
            ),
        ];

        yield 'bbanStructure token sum does not match BBAN length' => [
            new CountryStructure(
                countryCode: 'ES',
                ibanLength: 24,
                bbanStructure: '4!n4!n2!n9!n',
                bank: [4, 4],
                branch: [8, 4],
                account: [14, 10],
                nationalCheck: [12, 2],
                sepa: true,
                ibanExampleElectronic: 'ES9121000418450200051332',
            ),
        ];

        yield 'offset exceeds ibanLength' => [
            new CountryStructure(
                countryCode: 'ES',
                ibanLength: 24,
                bbanStructure: '4!n4!n2!n10!n',
                bank: [4, 4],
                branch: [8, 4],
                account: [16, 10],
                nationalCheck: [12, 2],
                sepa: true,
                ibanExampleElectronic: 'ES9121000418450200051332',
            ),
        ];

        yield 'offsets overlap' => [
            new CountryStructure(
                countryCode: 'ES',
                ibanLength: 24,
                bbanStructure: '4!n4!n2!n10!n',
                bank: [4, 4],
                branch: [6, 4],
                account: [14, 10],
                nationalCheck: [12, 2],
                sepa: true,
                ibanExampleElectronic: 'ES9121000418450200051332',
            ),
        ];

        yield 'example fails MOD-97 checksum' => [
            new CountryStructure(
                countryCode: 'ES',
                ibanLength: 24,
                bbanStructure: '4!n4!n2!n10!n',
                bank: [4, 4],
                branch: [8, 4],
                account: [14, 10],
                nationalCheck: [12, 2],
                sepa: true,
                ibanExampleElectronic: 'ES9121000418450200051333',
            ),
        ];
    }

    #[DataProvider('inconsistentCountryProvider')]
    public function testStructuralViolationsDetectsInconsistentEntry(CountryStructure $brokenCountry): void
    {
        self::assertNotSame([], self::structuralViolations($brokenCountry));
    }

    /**
     * Runs the five per-country structural checks and collects a
     * human-readable description for each one that fails. An empty return
     * means the structure is internally consistent.
     *
     * @return list<string>
     */
    private static function structuralViolations(CountryStructure $country): array
    {
        return array_merge(
            self::prefixViolations($country),
            self::lengthViolations($country),
            self::bbanTokenSumViolations($country),
            self::offsetViolations($country),
            self::checksumViolations($country),
        );
    }

    /**
     * Check 1: the example IBAN's leading two characters must match the
     * country code.
     *
     * @return list<string>
     */
    private static function prefixViolations(CountryStructure $country): array
    {
        $prefix = substr($country->ibanExampleElectronic, 0, 2);

        if ($prefix === $country->countryCode) {
            return [];
        }

        return [
            sprintf('example prefix "%s" does not match country code "%s"', $prefix, $country->countryCode),
        ];
    }

    /**
     * Check 2: the example IBAN's length must match `ibanLength`.
     *
     * @return list<string>
     */
    private static function lengthViolations(CountryStructure $country): array
    {
        $actual = strlen($country->ibanExampleElectronic);

        if ($actual === $country->ibanLength) {
            return [];
        }

        return [
            sprintf('example length %d does not match ibanLength %d', $actual, $country->ibanLength),
        ];
    }

    /**
     * Check 3: the sum of the `bbanStructure` token lengths must equal the
     * BBAN length (`ibanLength - 4`).
     *
     * @return list<string>
     */
    private static function bbanTokenSumViolations(CountryStructure $country): array
    {
        preg_match_all('/(\d+)!?[nace]/', $country->bbanStructure, $matches);

        /** @var list<string> $tokenLengths */
        $tokenLengths = $matches[1];
        $tokenSum     = array_sum(array_map('intval', $tokenLengths));
        $expected     = $country->ibanLength - 4;

        if ($tokenSum === $expected) {
            return [];
        }

        return [
            sprintf(
                'bbanStructure "%s" token sum %d does not match expected BBAN length %d (ibanLength - 4)',
                $country->bbanStructure,
                $tokenSum,
                $expected,
            ),
        ];
    }

    /**
     * Check 4: every defined field (`bank`, `branch`, `account`,
     * `nationalCheck`) must have an offset within `[4, ibanLength]` and the
     * fields must not overlap each other. Full BBAN coverage is NOT
     * required — some countries leave segments unassigned.
     *
     * @return list<string>
     */
    private static function offsetViolations(CountryStructure $country): array
    {
        /** @var array<string, array{0: int, 1: int}> $fields */
        $fields = array_filter(
            [
                'bank' => $country->bank,
                'branch' => $country->branch,
                'account' => $country->account,
                'nationalCheck' => $country->nationalCheck,
            ],
            static fn (?array $field): bool => $field !== null,
        );

        $violations = [];
        $intervals  = [];

        foreach ($fields as $name => [$offset, $length]) {
            if ($offset < 4 || $offset + $length > $country->ibanLength) {
                $violations[] = sprintf(
                    'field "%s" offset %d length %d is out of range [4, %d]',
                    $name,
                    $offset,
                    $length,
                    $country->ibanLength,
                );

                continue;
            }

            $intervals[$name] = [$offset, $offset + $length];
        }

        uasort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $previousEnd  = null;
        $previousName = null;

        foreach ($intervals as $name => [$start, $end]) {
            if ($previousEnd !== null && $previousName !== null && $start < $previousEnd) {
                $violations[] = sprintf(
                    'field "%s" [%d, %d) overlaps preceding field "%s" ending at %d',
                    $name,
                    $start,
                    $end,
                    $previousName,
                    $previousEnd,
                );
            }

            $previousEnd  = $end;
            $previousName = $name;
        }

        return $violations;
    }

    /**
     * Check 5: the example IBAN must pass MOD-97 (ISO 7064) validation.
     *
     * @return list<string>
     */
    private static function checksumViolations(CountryStructure $country): array
    {
        if ((new Mod97())->isValid($country->ibanExampleElectronic)) {
            return [];
        }

        return [
            sprintf('example "%s" fails MOD-97 checksum validation', $country->ibanExampleElectronic),
        ];
    }
}
