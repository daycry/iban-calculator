<?php

declare(strict_types=1);

namespace Daycry\Iban\Providers;

use CodeIgniter\HTTP\CURLRequest;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;
use Throwable;

/**
 * {@see ProviderInterface} backed by the iban.com Validation API — an
 * opt-in, paid, remote fallback consulted only when a caller supplies an API
 * key ({@see \Daycry\Iban\Config\Iban::$ibanComApiKey}), typically chained
 * AFTER a local provider via {@see ChainProvider} (see
 * {@see \Daycry\Iban\Config\Services::iban()}).
 *
 * **Confirmed API shape** (per `https://www.iban.com/validation-api`, the
 * v4 Validation API):
 *
 * - Endpoint: `POST https://api.iban.com/clients/api/v4/iban/`, form-encoded
 *   (`application/x-www-form-urlencoded`), with request fields `format`
 *   (`'json'`), `api_key`, `iban`, plus the optional `sci` flag (`1`) this
 *   provider always sends to also request the SEPA Instant Credit Transfer
 *   marker.
 * - Response JSON has four top-level keys:
 *   - `bank_data`: an object with (among others) `bank`, `bic`, `branch`,
 *     `bank_code`, `branch_code`, `address`, `city`, `state`, `zip`, `phone`,
 *     `country`, `country_iso`, `account`. Any of these may be absent.
 *   - `sepa_data`: an object of `'YES'`/`'NO'` string flags — `SCT`, `SDD`,
 *     `COR1`, `B2B`, `SCC`, and (with `sci=1`) `SCI` / `SCI_TIPS`.
 *   - `validations`: per-check `{code, message}` objects (not consumed here).
 *   - `errors`: an array, empty (`[]`) on success and non-empty on failure.
 *
 * Every field is extracted defensively (missing/wrong-shaped data maps to
 * `null`, never a warning/exception) because the response shape is an
 * external, versioned contract this package does not control.
 *
 * **Never throws.** The entire request + parse is wrapped in a single
 * `try`/`catch (Throwable)`: a DNS failure, connection refused, timeout,
 * non-200 status, malformed JSON, a non-empty `errors` array, or an empty/
 * missing `bank_data` all fold to `findByIban()` returning `null`, so
 * {@see \Daycry\Iban\Resolver\Resolver::resolve()} degrades gracefully to an
 * unresolved `BankResult` instead of ever propagating a network failure.
 *
 * `findByBankCode()` always returns `null` — the iban.com Validation API
 * resolves a full IBAN, not a bare country+bank(+branch) code, so there is no
 * equivalent lookup to perform; the useful path is exclusively
 * {@see self::findByIban()}.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class IbanComProvider implements ProviderInterface
{
    private const ENDPOINT = 'https://api.iban.com/clients/api/v4/iban/';

    private readonly CURLRequest $client;

    public function __construct(
        private readonly string $apiKey,
        ?CURLRequest $client = null,
        private readonly int $timeout = 5,
    ) {
        $this->client = $client ?? service('curlrequest');
    }

    /**
     * Only active when an API key was configured.
     */
    public function supports(string $countryCode): bool
    {
        return $this->apiKey !== '';
    }

    public function findByIban(ParsedIban $iban): ?BankInfo
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            $response = $this->client->post(self::ENDPOINT, [
                'form_params' => [
                    'format'  => 'json',
                    'api_key' => $this->apiKey,
                    'iban'    => $iban->electronic,
                    'sci'     => 1,
                ],
                'timeout'     => $this->timeout,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $decoded = json_decode((string) $response->getBody(), true);

            if (! is_array($decoded)) {
                return null;
            }

            $errors = $decoded['errors'] ?? null;

            if (! empty($errors)) {
                return null;
            }

            $bankData = $decoded['bank_data'] ?? null;

            if (! is_array($bankData) || $bankData === []) {
                return null;
            }

            $sepaDataRaw = $decoded['sepa_data'] ?? null;
            $sepaData    = is_array($sepaDataRaw) ? $sepaDataRaw : [];

            return new BankInfo(
                bankName: self::toNullableString($bankData['bank'] ?? null),
                shortName: null,
                bic: self::toNullableString($bankData['bic'] ?? null),
                city: self::toNullableString($bankData['city'] ?? null),
                address: self::toNullableString($bankData['address'] ?? null),
                sepaSct: self::toYesNoBool($sepaData['SCT'] ?? null),
                sepaSctInst: self::toYesNoBool($sepaData['SCI'] ?? null),
                sepaSddCore: self::toYesNoBool($sepaData['SDD'] ?? null),
                sepaSddB2b: self::toYesNoBool($sepaData['B2B'] ?? null),
                sourceId: 'iban.com',
                sourceVersion: date('Y-m-d'),
                sourceLicense: 'iban.com API',
                resolvedBy: 'iban.com',
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Always `null`: the iban.com Validation API resolves a full IBAN, not a
     * bare bank/branch code, so there is nothing to query here. See the
     * class docblock.
     */
    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
    {
        return null;
    }

    /**
     * Maps a raw `bank_data` field to a nullable, non-empty string.
     */
    private static function toNullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $string = (string) $value;

        return $string === '' ? null : $string;
    }

    /**
     * Maps an iban.com `sepa_data` `'YES'`/`'NO'` string flag to a nullable
     * bool; anything else (missing key, unexpected value/type) maps to
     * `null` rather than a false negative.
     */
    private static function toYesNoBool(mixed $value): ?bool
    {
        if (! is_string($value)) {
            return null;
        }

        return match (strtoupper($value)) {
            'YES'   => true,
            'NO'    => false,
            default => null,
        };
    }
}
