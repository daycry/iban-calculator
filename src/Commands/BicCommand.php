<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Iban\Iban as IbanService;

/**
 * `spark iban:bic <bic> [--json]`
 *
 * Validates and parses a BIC (ISO 9362 / SWIFT code), then — when the
 * configured provider can — resolves the bank behind it. Exit code mirrors
 * validity: `EXIT_SUCCESS` (0) for a well-formed BIC, `EXIT_ERROR` (1)
 * otherwise, so the command is usable directly in shell scripts/CI (same
 * convention as `iban:validate`).
 *
 * A BIC has no checksum, so a "valid" BIC only means well-formed with a
 * recognised country code — never that it exists on the live SWIFT network.
 * Bank fields are populated only when a provider (e.g. `database`) resolves it;
 * with the default empty DB they stay blank and a note is printed.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BicCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:bic';
    protected $description = 'Validates and parses a BIC (ISO 9362 / SWIFT), and resolves the bank via the configured provider when available.';
    protected $usage       = 'iban:bic <bic> [--json]';

    /** @var array<string, string> */
    protected $arguments = [
        'bic' => 'The BIC (SWIFT code) to validate, parse and resolve.',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--json' => 'Emit the result as JSON instead of colored CLI text / a table.',
    ];

    public function run(array $params): int
    {
        $bic = $params[0] ?? CLI::prompt('BIC');

        /** @var IbanService $svc */
        $svc = service('iban');

        $result    = $svc->validateBic($bic);
        $violation = $result->firstViolation();

        if (! $result->isValid()) {
            if (CLI::getOption('json')) {
                self::writeJson([
                    'valid'     => false,
                    'violation' => $violation === null ? null : [
                        'code'    => $violation->code->value,
                        'message' => $violation->message,
                    ],
                ]);

                return EXIT_ERROR;
            }

            CLI::write('INVALID: ' . $violation?->code->value . ' - ' . $violation?->message, 'red');

            return EXIT_ERROR;
        }

        $parsed = $svc->parseBic($bic);
        $info   = $svc->resolveBic($parsed);

        $fields = [
            'valid'           => true,
            'bic'             => $parsed->bic,
            'institutionCode' => $parsed->institutionCode,
            'countryCode'     => $parsed->countryCode,
            'locationCode'    => $parsed->locationCode,
            'branchCode'      => $parsed->branchCode,
            'primaryOffice'   => $parsed->isPrimaryOffice(),
            'bankName'        => $info?->bankName,
            'shortName'       => $info?->shortName,
            'city'            => $info?->city,
            'address'         => $info?->address,
            'sourceId'        => $info?->sourceId,
            'sourceVersion'   => $info?->sourceVersion,
            'sourceLicense'   => $info?->sourceLicense,
            'resolvedBy'      => $info?->resolvedBy,
            'resolved'        => $info !== null,
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

        if ($info === null) {
            CLI::write('Note: no provider data (empty bank DB or provider without BIC support) — structural fields only.', 'yellow');
        }

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
     * so failure is not a realistic case, but this keeps `CLI::write()` fed a
     * real `string` either way.
     *
     * @param array<string, mixed> $data
     */
    private static function writeJson(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);

        CLI::write($json === false ? '{}' : $json);
    }
}
