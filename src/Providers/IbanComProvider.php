<?php

declare(strict_types=1);

namespace Daycry\Iban\Providers;

use CodeIgniter\HTTP\CURLRequest;
use Daycry\Iban\Contracts\BicProviderInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Contracts\VerificationProviderInterface;
use Daycry\Iban\Core\BicValidator;
use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ExternalVerification;
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
 * **BIC resolution** ({@see BicProviderInterface}): iban.com publishes a
 * SEPARATE BIC/SWIFT Validation API (`.../swiftv2/bic/`, distinct from the
 * IBAN endpoint above) with its OWN response shape — `bic_valid`, `bic_active`,
 * and a `directory_results` object (`name`, `bic`, `city`, `address`,
 * `iso_country_code`, …) rather than the IBAN endpoint's `bank_data`/
 * `sepa_data`. {@see self::findByBic()} calls that endpoint and maps
 * `directory_results` to a {@see BankInfo}; the BIC API returns no SEPA scheme
 * flags, so those fields are left `null`. Same key-gating, timeout, and
 * never-throws discipline as {@see self::findByIban()}.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class IbanComProvider implements BicProviderInterface, ProviderInterface, VerificationProviderInterface
{
    private const ENDPOINT = 'https://api.iban.com/clients/api/v4/iban/';

    private const BIC_ENDPOINT = 'https://api.iban.com/clients/api/swiftv2/bic/';

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

    /** Read the API's coded checks, rather than inferring validity from bank_data. */
    public function verifyIban(string $iban): ExternalVerification
    {
        $decoded = $this->verificationResponse(self::ENDPOINT, ['iban' => (new Normalizer())->normalize($iban)]);
        if ($decoded === null || !isset($decoded['errors']) || !is_array($decoded['errors']) || $decoded['errors'] !== []) {
            return new ExternalVerification(null, 'iban.com');
        }
        $checks = $decoded['validations'] ?? null;
        if (!is_array($checks)) {
            return new ExternalVerification(null, 'iban.com');
        }
        $rules = [
            'chars' => ['006', '206'],
            'iban' => ['001', '202'],
            'length' => ['003', '203'],
            'structure' => ['005', '205'],
            'country_support' => ['007', '207'],
            'account' => ['002', '004', '201'],
        ];
        $valid = true;
        foreach ($rules as $name => $allowed) {
            $check = $checks[$name] ?? null;
            $code = is_array($check) ? self::verificationCode($check['code'] ?? null) : null;
            if ($code === null || !in_array($code, $allowed, true)) {
                return new ExternalVerification(null, 'iban.com');
            }
            if (str_starts_with($code, '2')) {
                $valid = false;
            }
        }

        // These checks establish identifier integrity, never a live customer's account.
        return new ExternalVerification($valid, 'iban.com');
    }

    public function verifyBic(string $bic): ExternalVerification
    {
        $normalized = (new BicValidator())->normalize($bic);
        $decoded = $this->verificationResponse(self::BIC_ENDPOINT, ['bic' => $normalized]);
        $query = $decoded['query'] ?? null;
        if ($decoded === null || !is_array($query) || ($query['success'] ?? null) !== true || ($query['bic'] ?? null) !== $normalized) {
            return new ExternalVerification(null, 'iban.com');
        }
        $error = $decoded['error'] ?? null;
        if (!is_array($error)) {
            return new ExternalVerification(null, 'iban.com');
        }
        $valid = $decoded['bic_valid'] ?? null;
        if ($valid === false) {
            $code = self::verificationCode($error['code'] ?? null);
            if (in_array($code, ['801', '802', '803'], true)) {
                return new ExternalVerification(false, 'iban.com', exists: $code === '803' ? false : null);
            }

            return new ExternalVerification(null, 'iban.com');
        }
        $directory = $decoded['directory_results'] ?? null;
        $canonical = strlen($normalized) === 8 ? $normalized . 'XXX' : $normalized;
        if ($valid !== true || !is_bool($decoded['bic_active'] ?? null)
            || $error !== [] || !is_array($directory) || ($directory['bic'] ?? null) !== $canonical) {
            return new ExternalVerification(null, 'iban.com');
        }

        return new ExternalVerification(true, 'iban.com', exists: true, active: $decoded['bic_active']);
    }

    /** @param array<string, string> $fields
     *  @return array<string, mixed>|null
     */
    private function verificationResponse(string $endpoint, array $fields): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }
        try {
            $response = $this->client->post($endpoint, [
                'form_params' => ['format' => 'json', 'api_key' => $this->apiKey] + $fields,
                'timeout' => $this->timeout,
                'http_errors' => false,
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $decoded = json_decode((string) $response->getBody(), true);

            return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function verificationCode(mixed $code): ?string
    {
        if ((!is_string($code) && !is_int($code)) || preg_match('/^[0-9]{1,3}$/D', (string) $code) !== 1) {
            return null;
        }

        return str_pad((string) $code, 3, '0', STR_PAD_LEFT);
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
     * Resolves a bank from a BIC via iban.com's dedicated BIC/SWIFT
     * Validation API ({@see self::BIC_ENDPOINT}). Never throws: any transport,
     * status, JSON, `error`, or missing-`directory_results` problem folds to
     * `null`, exactly like {@see self::findByIban()}.
     */
    public function findByBic(string $bic): ?BankInfo
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            $response = $this->client->post(self::BIC_ENDPOINT, [
                'form_params' => [
                    'format'  => 'json',
                    'api_key' => $this->apiKey,
                    'bic'     => strtoupper((string) preg_replace('/\s+/', '', $bic)),
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

            $error = $decoded['error'] ?? null;

            if (! empty($error)) {
                return null;
            }

            $directory = $decoded['directory_results'] ?? null;

            if (! is_array($directory) || $directory === []) {
                return null;
            }

            // The endpoint may return a single result object or a list of
            // them; normalize to the first associative row either way.
            if (array_is_list($directory) && is_array($directory[0])) {
                $directory = $directory[0];
            }

            return new BankInfo(
                bankName: self::toNullableString($directory['name'] ?? $directory['institution'] ?? null),
                shortName: null,
                bic: self::toNullableString($directory['bic'] ?? null),
                city: self::toNullableString($directory['city'] ?? null),
                address: self::toNullableString($directory['address'] ?? null),
                sepaSct: null,
                sepaSctInst: null,
                sepaSddCore: null,
                sepaSddB2b: null,
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
