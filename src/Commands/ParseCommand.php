<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Iban\Iban as IbanService;

/**
 * `spark iban:parse <iban> [--json]`
 *
 * Thin wrapper over `service('iban')->tryParse()`. Prints the resulting
 * `ParsedIban` fields as a CLI table (default) or JSON (`--json`); an
 * invalid IBAN prints an error and exits non-zero instead of throwing.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ParseCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:parse';
    protected $description = 'Parses an IBAN into its structural parts (country, check digits, BBAN, bank/branch/account, ...).';
    protected $usage       = 'iban:parse <iban> [--json]';

    /** @var array<string, string> */
    protected $arguments = [
        'iban' => 'The IBAN to parse.',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--json' => 'Emit the parsed fields as JSON instead of a CLI table.',
    ];

    public function run(array $params): int
    {
        // PHPStan-visibility fallback for EXIT_SUCCESS/EXIT_ERROR; see the
        // full rationale on ValidateCommand::run().
        defined('EXIT_SUCCESS') || define('EXIT_SUCCESS', 0); // @codeCoverageIgnore
        defined('EXIT_ERROR') || define('EXIT_ERROR', 1); // @codeCoverageIgnore

        $iban = $params[0] ?? CLI::prompt('IBAN');

        /** @var IbanService $svc */
        $svc = service('iban');

        $parsed = $svc->tryParse($iban);

        if ($parsed === null) {
            CLI::error('Invalid IBAN');

            return EXIT_ERROR;
        }

        $fields = [
            'countryCode'        => $parsed->countryCode,
            'checkDigits'        => $parsed->checkDigits,
            'bban'               => $parsed->bban,
            'bankIdentifier'     => $parsed->bankIdentifier,
            'branchIdentifier'   => $parsed->branchIdentifier,
            'accountNumber'      => $parsed->accountNumber,
            'nationalCheckDigit' => $parsed->nationalCheckDigit,
            'sepaCountry'        => $parsed->sepaCountry,
            'electronic'         => $parsed->electronic,
        ];

        if (CLI::getOption('json')) {
            self::writeJson($fields);

            return EXIT_SUCCESS;
        }

        $rows = [];

        foreach ($fields as $field => $value) {
            $rows[] = [$field, self::stringify($value)];
        }

        CLI::table($rows, ['Field', 'Value']);

        return EXIT_SUCCESS;
    }

    private static function stringify(string|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_bool($value) ? ($value ? 'true' : 'false') : $value;
    }

    /**
     * `json_encode()` is typed `string|false` by PHPStan (it can fail on
     * unencodable input); our payloads here are always plain scalars/arrays,
     * so failure is not a realistic case, but this keeps `CLI::write()` fed
     * a real `string` either way.
     *
     * @param array<string, mixed> $data
     */
    private static function writeJson(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);

        CLI::write($json === false ? '{}' : $json);
    }
}
