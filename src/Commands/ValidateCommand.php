<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Iban\Iban as IbanService;

/**
 * `spark iban:validate <iban> [--national] [--json]`
 *
 * Thin wrapper over `service('iban')->validate()`. Exit code mirrors the
 * validation result: `EXIT_SUCCESS` (0) for a valid IBAN, `EXIT_ERROR` (1)
 * otherwise -- makes the command usable directly in shell scripts/CI.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ValidateCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:validate';
    protected $description = 'Validates an IBAN: structure, MOD-97 checksum, and (optionally) national check digits.';
    protected $usage       = 'iban:validate <iban> [--national] [--json]';

    /** @var array<string, string> */
    protected $arguments = [
        'iban' => 'The IBAN to validate.',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--national' => 'Also run the country-specific national check-digit validator, if one is registered.',
        '--json'     => 'Emit the result as JSON instead of colored CLI text.',
    ];

    public function run(array $params): int
    {
        $iban = $params[0] ?? CLI::prompt('IBAN');

        /** @var IbanService $svc */
        $svc = service('iban');

        $result    = $svc->validate($iban, (bool) CLI::getOption('national'));
        $violation = $result->firstViolation();

        if (CLI::getOption('json')) {
            self::writeJson([
                'valid'     => $result->isValid(),
                'violation' => $violation === null ? null : [
                    'code'    => $violation->code->value,
                    'message' => $violation->message,
                ],
            ]);

            return $result->isValid() ? EXIT_SUCCESS : EXIT_ERROR;
        }

        if ($result->isValid()) {
            CLI::write('VALID', 'green');
        } else {
            CLI::write('INVALID: ' . $violation?->code->value . ' - ' . $violation?->message, 'red');
        }

        return $result->isValid() ? EXIT_SUCCESS : EXIT_ERROR;
    }

    /**
     * `json_encode()` is typed `string|false` by PHPStan (it can fail on
     * unencodable input, e.g. a resource); our payloads here are always
     * plain scalars/arrays, so failure is not a realistic case, but this
     * keeps `CLI::write()` fed a real `string` either way.
     *
     * @param array<string, mixed> $data
     */
    private static function writeJson(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);

        CLI::write($json === false ? '{}' : $json);
    }
}
