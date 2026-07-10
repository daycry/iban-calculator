<?php

declare(strict_types=1);

/**
 * Registry generator (T-28/T-29/T-30).
 *
 * Regenerates `src/Registry/data/countries.php` from the independently
 * authored fact source `bin/data/registry-facts.csv`, so the ~annual
 * refresh of the IBAN structural registry has a repeatable path: edit the
 * CSV, run this script, commit the regenerated file.
 *
 * Usage:
 *   php bin/generate-registry.php              # write src/Registry/data/countries.php
 *   php bin/generate-registry.php --dry-run     # print a diff, write nothing
 *
 * `--dry-run` exits 0 when the generated output matches the committed
 * `countries.php` byte-for-byte, and exits 1 (after printing a diff) when
 * it does not — this is exactly the check that
 * {@see \Tests\Bin\GenerateRegistryTest} runs in-process.
 *
 * Design notes:
 * - `generate_countries_php()` is a pure function of the CSV file's
 *   contents: same CSV in, same PHP string out, every time. No filesystem
 *   writes, no global state. That purity is what makes the drift test
 *   possible (call it twice — from the test, and as committed to disk — and
 *   compare).
 * - This file is guarded so that `require`-ing it (e.g. from the test
 *   suite, to get at `generate_countries_php()`) does NOT run `main()`. Only
 *   direct CLI execution (`php bin/generate-registry.php ...`) does.
 *
 * Cross-check step (documented, not implemented): per
 * docs/registry-authoring.md, newly authored entries should be cross-checked
 * against independent MIT-licensed reference registries
 * (`cmpayments/iban`, `ixnode/php-iban`) to catch transcription errors. If
 * those packages were ever added as dev dependencies, the cross-check would
 * run here, after `load_registry_facts()` and before formatting — comparing
 * `iban_length` / `bban_structure` / offsets per country and reporting
 * discrepancies. Neither package is installed, and this script does not
 * require or call either of them.
 */

/**
 * @phpstan-type CountryFact array{
 *     iban_length: int,
 *     bban_structure: string,
 *     bank: array{0: int, 1: int},
 *     branch: array{0: int, 1: int}|null,
 *     account: array{0: int, 1: int},
 *     national_check: array{0: int, 1: int}|null,
 *     sepa: bool,
 *     example: string,
 * }
 */

/**
 * Expected CSV header, in column order.
 *
 * @return list<string>
 */
function registry_facts_csv_columns(): array
{
    return [
        'code', 'iban_length', 'bban_structure',
        'bank_offset', 'bank_length',
        'branch_offset', 'branch_length',
        'account_offset', 'account_length',
        'national_check_offset', 'national_check_length',
        'sepa', 'example',
    ];
}

/**
 * Parse and validate `bin/data/registry-facts.csv` into a country-fact map.
 *
 * Each row is validated independently: the country code shape, integer
 * offsets/lengths, `sepa` being exactly `0`/`1`, offsets falling inside
 * `[4, iban_length]`, and the example IBAN's prefix/length agreeing with
 * the row's own `code`/`iban_length`. This is deliberately stricter than
 * strictly necessary so that a bad CSV edit fails loudly here rather than
 * silently producing a broken `countries.php`.
 *
 * @return array<string, CountryFact> Keyed by ISO 3166-1 alpha-2 country code.
 */
function load_registry_facts(string $csvPath): array
{
    if (!is_file($csvPath)) {
        throw new RuntimeException(sprintf('registry-facts.csv not found at "%s".', $csvPath));
    }

    $handle = fopen($csvPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException(sprintf('Unable to open "%s" for reading.', $csvPath));
    }

    try {
        $header = fgetcsv($handle, null, ',', '"', '');
        if ($header === false) {
            throw new RuntimeException(sprintf('"%s" is empty; expected a header row.', $csvPath));
        }

        $expected = registry_facts_csv_columns();
        if ($header !== $expected) {
            throw new RuntimeException(sprintf(
                '"%s" header mismatch. Expected: %s. Got: %s.',
                $csvPath,
                implode(',', $expected),
                implode(',', $header),
            ));
        }

        /** @var array<string, CountryFact> $facts */
        $facts   = [];
        $lineNum = 1;

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $lineNum++;

            // Skip fully blank trailing lines some editors/tools append.
            if ($row === [null] || $row === ['']) {
                continue;
            }

            if (count($row) !== count($expected)) {
                throw new RuntimeException(sprintf(
                    '%s:%d: expected %d columns, got %d.',
                    $csvPath,
                    $lineNum,
                    count($expected),
                    count($row),
                ));
            }

            /** @var array<string, string> $cells */
            $cells = array_combine($expected, array_map(
                static fn (?string $v): string => $v ?? '',
                $row,
            ));

            $code = validate_country_code($cells['code'], $csvPath, $lineNum);

            if (array_key_exists($code, $facts)) {
                throw new RuntimeException(sprintf('%s:%d: duplicate country code "%s".', $csvPath, $lineNum, $code));
            }

            $facts[$code] = build_country_fact($code, $cells, $csvPath, $lineNum);
        }
    } finally {
        fclose($handle);
    }

    return $facts;
}

function validate_country_code(string $code, string $csvPath, int $lineNum): string
{
    if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
        throw new RuntimeException(sprintf(
            '%s:%d: "code" must be exactly 2 uppercase letters, got "%s".',
            $csvPath,
            $lineNum,
            $code,
        ));
    }

    return $code;
}

/**
 * @param array<string, string> $cells
 *
 * @return CountryFact
 */
function build_country_fact(string $code, array $cells, string $csvPath, int $lineNum): array
{
    $ibanLength = validate_positive_int($cells['iban_length'], 'iban_length', $csvPath, $lineNum);

    $bbanStructure = trim($cells['bban_structure']);
    if ($bbanStructure === '') {
        throw new RuntimeException(sprintf('%s:%d: "bban_structure" must not be empty.', $csvPath, $lineNum));
    }

    $bank = validate_field(
        $cells['bank_offset'],
        $cells['bank_length'],
        'bank',
        $ibanLength,
        $csvPath,
        $lineNum,
        required: true,
    );
    if ($bank === null) {
        throw new RuntimeException(sprintf('%s:%d: "bank" field is required and cannot be empty.', $csvPath, $lineNum));
    }

    $branch = validate_field(
        $cells['branch_offset'],
        $cells['branch_length'],
        'branch',
        $ibanLength,
        $csvPath,
        $lineNum,
        required: false,
    );

    $account = validate_field(
        $cells['account_offset'],
        $cells['account_length'],
        'account',
        $ibanLength,
        $csvPath,
        $lineNum,
        required: true,
    );
    if ($account === null) {
        throw new RuntimeException(sprintf('%s:%d: "account" field is required and cannot be empty.', $csvPath, $lineNum));
    }

    $nationalCheck = validate_field(
        $cells['national_check_offset'],
        $cells['national_check_length'],
        'national_check',
        $ibanLength,
        $csvPath,
        $lineNum,
        required: false,
    );

    $sepaRaw = trim($cells['sepa']);
    if ($sepaRaw !== '0' && $sepaRaw !== '1') {
        throw new RuntimeException(sprintf(
            '%s:%d: "sepa" must be "0" or "1", got "%s".',
            $csvPath,
            $lineNum,
            $sepaRaw,
        ));
    }

    $example = trim($cells['example']);
    if ($example === '') {
        throw new RuntimeException(sprintf('%s:%d: "example" must not be empty.', $csvPath, $lineNum));
    }

    if (strlen($example) !== $ibanLength) {
        throw new RuntimeException(sprintf(
            '%s:%d: "example" length %d does not match iban_length %d for "%s".',
            $csvPath,
            $lineNum,
            strlen($example),
            $ibanLength,
            $code,
        ));
    }

    if (!str_starts_with($example, $code)) {
        throw new RuntimeException(sprintf(
            '%s:%d: "example" "%s" does not start with country code "%s".',
            $csvPath,
            $lineNum,
            $example,
            $code,
        ));
    }

    return [
        'iban_length' => $ibanLength,
        'bban_structure' => $bbanStructure,
        'bank' => $bank,
        'branch' => $branch,
        'account' => $account,
        'national_check' => $nationalCheck,
        'sepa' => $sepaRaw === '1',
        'example' => $example,
    ];
}

function validate_positive_int(string $value, string $fieldName, string $csvPath, int $lineNum): int
{
    $trimmed = trim($value);
    if (!ctype_digit($trimmed) || (int) $trimmed <= 0) {
        throw new RuntimeException(sprintf(
            '%s:%d: "%s" must be a positive integer, got "%s".',
            $csvPath,
            $lineNum,
            $fieldName,
            $value,
        ));
    }

    return (int) $trimmed;
}

/**
 * Validate an `[offset, length]` pair of CSV cells for one field.
 *
 * Both cells empty => the field is absent (returns `null`, only allowed
 * when `$required` is `false`). Exactly one cell empty is always an error.
 *
 * @return array{0: int, 1: int}|null
 */
function validate_field(
    string $offsetRaw,
    string $lengthRaw,
    string $fieldName,
    int $ibanLength,
    string $csvPath,
    int $lineNum,
    bool $required,
): ?array {
    $offsetRaw = trim($offsetRaw);
    $lengthRaw = trim($lengthRaw);

    if ($offsetRaw === '' && $lengthRaw === '') {
        if ($required) {
            throw new RuntimeException(sprintf('%s:%d: "%s" field is required and cannot be empty.', $csvPath, $lineNum, $fieldName));
        }

        return null;
    }

    if ($offsetRaw === '' || $lengthRaw === '') {
        throw new RuntimeException(sprintf(
            '%s:%d: "%s" must have both offset and length set, or both empty.',
            $csvPath,
            $lineNum,
            $fieldName,
        ));
    }

    if (!ctype_digit($offsetRaw) || !ctype_digit($lengthRaw)) {
        throw new RuntimeException(sprintf(
            '%s:%d: "%s" offset/length must be non-negative integers, got "%s"/"%s".',
            $csvPath,
            $lineNum,
            $fieldName,
            $offsetRaw,
            $lengthRaw,
        ));
    }

    $offset = (int) $offsetRaw;
    $length = (int) $lengthRaw;

    if ($length < 1) {
        throw new RuntimeException(sprintf('%s:%d: "%s" length must be at least 1.', $csvPath, $lineNum, $fieldName));
    }

    if ($offset < 4 || $offset + $length > $ibanLength) {
        throw new RuntimeException(sprintf(
            '%s:%d: "%s" [%d, %d) is out of range [4, %d].',
            $csvPath,
            $lineNum,
            $fieldName,
            $offset,
            $offset + $length,
            $ibanLength,
        ));
    }

    return [$offset, $length];
}

/**
 * Render a PHP single-quoted string literal for `$value`.
 */
function php_string_literal(string $value): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
}

/**
 * Render an `[offset, length]` pair, or `'null'` when absent.
 *
 * @param array{0: int, 1: int}|null $field
 */
function php_field_literal(?array $field): string
{
    if ($field === null) {
        return 'null';
    }

    return sprintf('[%d, %d]', $field[0], $field[1]);
}

/**
 * Format one country entry as canonical, deterministic PHP source — a
 * `'CODE' => [ ... ],` block with keys aligned to a fixed column, in a
 * fixed key order.
 *
 * @param CountryFact $fact
 */
function format_country_entry(string $code, array $fact): string
{
    $fields = [
        'iban_length' => (string) $fact['iban_length'],
        'bban_structure' => php_string_literal($fact['bban_structure']),
        'bank' => php_field_literal($fact['bank']),
        'branch' => php_field_literal($fact['branch']),
        'account' => php_field_literal($fact['account']),
        'national_check' => php_field_literal($fact['national_check']),
        'sepa' => $fact['sepa'] ? 'true' : 'false',
        'example' => php_string_literal($fact['example']),
    ];

    $keyColumnWidth = 0;
    foreach (array_keys($fields) as $key) {
        $keyColumnWidth = max($keyColumnWidth, strlen($key) + 2);
    }

    $lines = sprintf("    '%s' => [\n", $code);

    foreach ($fields as $key => $valueLiteral) {
        $quotedKey = "'" . $key . "'";
        $padding   = str_repeat(' ', $keyColumnWidth - strlen($quotedKey) + 1);
        $lines .= sprintf("        %s%s=> %s,\n", $quotedKey, $padding, $valueLiteral);
    }

    $lines .= "    ],\n";

    return $lines;
}

const REGISTRY_FILE_HEADER = <<<'PHP'
<?php

declare(strict_types=1);

/**
 * Raw IBAN structure registry data, keyed by ISO 3166-1 alpha-2 country code.
 *
 * GENERATED FILE. Produced by `bin/generate-registry.php` from the fact
 * source `bin/data/registry-facts.csv` — do not hand-edit; edit the CSV and
 * regenerate (`php bin/generate-registry.php`) instead. See
 * docs/registry-authoring.md for the annual-refresh workflow.
 *
 * Independently authored structural facts (lengths / field offsets / BBAN
 * tokens) derived from publicly documented IBAN formats — NOT copied or
 * derived from the SWIFT IBAN Registry file. `branch` and `national_check`
 * may be `null` for countries whose BBAN has no such field.
 *
 * sepa flags follow the EPC List of SEPA Scheme Countries (EPC409-09 v8.0,
 * Dec 2025). AL, MD, ME, MK joined SEPA in late 2025 and RS became
 * operational 2026-05, so their sepa => true is current — do not revert to
 * the older 36-country list.
 *
 * @see docs/registry-authoring.md
 *
 * @var array<string, array<string, mixed>>
 */
return [

PHP;

/**
 * Generate the full canonical `countries.php` source from the CSV fact
 * source at `$csvPath`. Pure function: no I/O other than reading
 * `$csvPath`, deterministic (entries sorted by country code, fixed
 * formatting) — calling it twice with the same CSV yields byte-identical
 * output.
 */
function generate_countries_php(string $csvPath): string
{
    $facts = load_registry_facts($csvPath);
    ksort($facts, SORT_STRING);

    $body = '';
    foreach ($facts as $code => $fact) {
        $body .= format_country_entry($code, $fact);
    }

    // REGISTRY_FILE_HEADER is a heredoc: its line breaks are literal bytes
    // read from this very file, so if bin/generate-registry.php were ever
    // checked out with CRLF line endings (e.g. Git `core.autocrlf=true` on
    // Windows without a `.gitattributes` override), the header would pick
    // up "\r\n" while the entry body below (built from "\n" escape
    // sequences, which are immune to the source file's own line endings)
    // would stay "\n"-only. Normalizing here keeps the output deterministic
    // and LF-only regardless of how this source file itself was checked out.
    $header = str_replace(["\r\n", "\r"], "\n", REGISTRY_FILE_HEADER);

    return $header . $body . "];\n";
}

/**
 * Minimal line-based diff for `--dry-run` output: finds the common leading
 * and trailing lines, then reports the differing middle chunk of each side.
 * Not a general-purpose multi-hunk diff, but sufficient to pinpoint drift
 * between the generated output and the committed file.
 *
 * @return string
 */
function describe_diff(string $current, string $generated): string
{
    $currentLines   = explode("\n", $current);
    $generatedLines = explode("\n", $generated);

    $prefix = 0;
    $maxPrefix = min(count($currentLines), count($generatedLines));
    while ($prefix < $maxPrefix && $currentLines[$prefix] === $generatedLines[$prefix]) {
        $prefix++;
    }

    $currentSuffix   = count($currentLines) - 1;
    $generatedSuffix = count($generatedLines) - 1;
    while (
        $currentSuffix >= $prefix
        && $generatedSuffix >= $prefix
        && $currentLines[$currentSuffix] === $generatedLines[$generatedSuffix]
    ) {
        $currentSuffix--;
        $generatedSuffix--;
    }

    $out = sprintf("--- current (line %d)\n+++ generated (line %d)\n", $prefix + 1, $prefix + 1);

    for ($i = $prefix; $i <= $currentSuffix; $i++) {
        $out .= '-' . $currentLines[$i] . "\n";
    }

    for ($i = $prefix; $i <= $generatedSuffix; $i++) {
        $out .= '+' . $generatedLines[$i] . "\n";
    }

    return $out;
}

/**
 * CLI entry point. Not run when this file is `require`-d (e.g. by tests) —
 * see the invocation guard at the bottom of this file.
 *
 * @param list<string> $argv
 */
function main(array $argv): int
{
    $csvPath    = __DIR__ . '/data/registry-facts.csv';
    $targetPath = __DIR__ . '/../src/Registry/data/countries.php';

    $generated = generate_countries_php($csvPath);
    $dryRun    = in_array('--dry-run', $argv, true);

    if (!$dryRun) {
        file_put_contents($targetPath, $generated);
        fwrite(STDOUT, sprintf("Wrote %s (%d bytes).\n", $targetPath, strlen($generated)));

        return 0;
    }

    $current = is_file($targetPath) ? file_get_contents($targetPath) : '';

    if ($current === $generated) {
        fwrite(STDOUT, "No changes: generated output matches src/Registry/data/countries.php.\n");

        return 0;
    }

    fwrite(STDOUT, "Drift detected between bin/data/registry-facts.csv and src/Registry/data/countries.php:\n\n");
    fwrite(STDOUT, describe_diff($current, $generated));

    return 1;
}

// Guard: only run main() on direct CLI execution of this file, never when
// it's require()-d (e.g. by tests/Bin/GenerateRegistryTest.php to reach
// generate_countries_php()).
if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === __FILE__) {
    exit(main($argv));
}
