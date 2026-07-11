<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Iban\Iban as IbanService;

/**
 * `spark iban:resolve <iban> [--json]`
 *
 * Thin wrapper over `service('iban')->resolve()`. With the default (empty)
 * bank database / `NullProvider`, the bank fields resolve to `null` and the
 * CLI table is annotated with a note that only structural fields are
 * available -- v1.0 ships no bundled bank data (see `iban:update`).
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ResolveCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:resolve';
    protected $description = 'Resolves an IBAN to bank/entity data via the configured provider (structural fields only when the bank DB is empty).';
    protected $usage       = 'iban:resolve <iban> [--json]';

    /** @var array<string, string> */
    protected $arguments = [
        'iban' => 'The IBAN to resolve.',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--json' => 'Emit the result as JSON instead of a CLI table.',
    ];

    public function run(array $params): int
    {
        $iban = $params[0] ?? CLI::prompt('IBAN');

        /** @var IbanService $svc */
        $svc = service('iban');

        $parsed = $svc->tryParse($iban);

        if ($parsed === null) {
            CLI::error('Invalid IBAN');

            return EXIT_ERROR;
        }

        $result = $svc->resolve($parsed);

        $fields = [
            'countryCode'      => $parsed->countryCode,
            'checkDigits'      => $parsed->checkDigits,
            'bban'             => $parsed->bban,
            'bankIdentifier'   => $parsed->bankIdentifier,
            'branchIdentifier' => $parsed->branchIdentifier,
            'accountNumber'    => $parsed->accountNumber,
            'electronic'       => $parsed->electronic,
            'bankName'         => $result->bankName,
            'shortName'        => $result->shortName,
            'bic'              => $result->bic,
            'city'             => $result->city,
            'address'          => $result->address,
            'sepaSct'          => $result->sepaSct,
            'sepaSctInst'      => $result->sepaSctInst,
            'sepaSddCore'      => $result->sepaSddCore,
            'sepaSddB2b'       => $result->sepaSddB2b,
            'sourceId'         => $result->sourceId,
            'sourceVersion'    => $result->sourceVersion,
            'sourceLicense'    => $result->sourceLicense,
            'isResolved'       => $result->isResolved(),
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

        if (! $result->isResolved()) {
            CLI::write('Note: no provider data (empty bank DB) — structural fields only.', 'yellow');
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
