<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Iban\Config\Services;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Iban as IbanService;

/**
 * `spark iban:validate [<iban>] [--national] [--json] [--bic=<bic>]`
 *
 * Thin wrapper over `service('iban')->validate()`. Exit code mirrors the
 * validation result: `EXIT_SUCCESS` (0) for a valid IBAN, `EXIT_ERROR` (1)
 * otherwise -- makes the command usable directly in shell scripts/CI.
 *
 * When `--bic=<bic>` is supplied the command switches to the COMBINED
 * IBAN+BIC entry point (`service('iban')->validateIbanAndBic()`), covering all
 * three modes: IBAN only, BIC only (the `<iban>` argument becomes optional),
 * or both (with the IBAN↔BIC cross-check). Without `--bic`, behavior is
 * exactly as before.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ValidateCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:validate';
    protected $description = 'Validates an IBAN (structure, MOD-97, optional national check digits); with --bic, validates an IBAN and/or BIC and cross-checks them.';
    protected $usage       = 'iban:validate [<iban>] [--national] [--json] [--bic=<bic>]';

    /** @var array<string, string> */
    protected $arguments = [
        'iban' => 'The IBAN to validate. Optional when --bic is given (BIC-only mode).',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--national' => 'Also run the country-specific national check-digit validator, if one is registered. '
            . 'When omitted, the effective value comes from Config\\Iban::$checkNationalByDefault. Ignored in combined --bic mode.',
        '--json'     => 'Emit the result as JSON instead of colored CLI text.',
        '--bic'      => 'Also validate this BIC and, together with a valid IBAN, cross-check the two. '
            . 'Given alone (no <iban>), validates just the BIC.',
    ];

    public function run(array $params): int
    {
        $bicOption = CLI::getOption('bic');

        if ($bicOption !== null) {
            return $this->runCombined($params, is_string($bicOption) ? $bicOption : null);
        }

        $iban = $params[0] ?? CLI::prompt('IBAN');

        /** @var IbanService $svc */
        $svc = service('iban');

        $nationalOption = CLI::getOption('national');
        $checkNational  = $nationalOption === null
            ? Services::config()->checkNationalByDefault
            : (bool) $nationalOption;

        $result    = $svc->validate($iban, $checkNational);
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
     * Combined IBAN+BIC mode (`--bic` present): delegates to
     * `service('iban')->validateIbanAndBic()`, which handles all three cases
     * (IBAN only / BIC only / both, incl. the cross-check). The `<iban>`
     * argument is optional here — its absence means "BIC only", so this branch
     * never prompts for an IBAN. Unlike the single-IBAN path, the combined
     * result can carry MORE than one violation, so every violation is emitted.
     *
     * @param array<int|string, string|null> $params
     */
    private function runCombined(array $params, ?string $bic): int
    {
        /** @var IbanService $svc */
        $svc = service('iban');

        $iban = $params[0] ?? null;

        $result = $svc->validateIbanAndBic($iban, $bic);

        if (CLI::getOption('json')) {
            self::writeJson([
                'valid'      => $result->isValid(),
                'violations' => array_map(
                    static fn (Violation $v): array => [
                        'code'    => $v->code->value,
                        'message' => $v->message,
                    ],
                    $result->violations(),
                ),
            ]);

            return $result->isValid() ? EXIT_SUCCESS : EXIT_ERROR;
        }

        if ($result->isValid()) {
            CLI::write('VALID', 'green');
        } else {
            foreach ($result->violations() as $violation) {
                CLI::write('INVALID: ' . $violation->code->value . ' - ' . $violation->message, 'red');
            }
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
