<?php

declare(strict_types=1);

namespace Tests\Providers;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Providers\IbanComProvider;
use RuntimeException;
use Tests\Support\RecordingCurlRequest;

/**
 * Exercises {@see IbanComProvider} entirely offline: a hand-rolled anonymous
 * {@see CURLRequest} subclass stands in for the real HTTP client, returning
 * (or throwing) canned responses so no test ever hits the live iban.com API.
 *
 * @see \Daycry\Iban\Providers\IbanComProvider
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class IbanComProviderTest extends CIUnitTestCase
{
    public function testSupportsIsTrueOnlyWhenAnApiKeyIsConfigured(): void
    {
        $withKey    = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, '{}')));
        $withoutKey = new IbanComProvider('', $this->clientReturning($this->cannedResponse(200, '{}')));

        self::assertTrue($withKey->supports('ES'));
        self::assertTrue($withKey->supports('ZZ')); // key-gated, not country-gated
        self::assertFalse($withoutKey->supports('ES'));
    }

    public function testFindByIbanMapsACannedSuccessResponseToBankInfo(): void
    {
        $body = json_encode([
            'bank_data' => [
                'bic'     => 'COBADEFFXXX',
                'bank'    => 'Commerzbank',
                'branch'  => 'Berlin',
                'address' => 'Some Street 1',
                'city'    => 'Berlin',
                'state'   => null,
                'zip'     => '10115',
                'country' => 'Germany',
            ],
            'sepa_data' => [
                'SCT'  => 'YES',
                'SDD'  => 'NO',
                'COR1' => 'YES',
                'B2B'  => 'NO',
                'SCC'  => 'NO',
                'SCI'  => 'YES',
            ],
            'validations' => [],
            'errors'      => [],
        ]);

        $client   = $this->recordingClient($this->cannedResponse(200, (string) $body));
        $provider = new IbanComProvider('secret', $client);

        $info = $provider->findByIban($this->parsedIban());

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('Commerzbank', $info->bankName);
        self::assertSame('COBADEFFXXX', $info->bic);
        self::assertSame('Berlin', $info->city);
        self::assertSame('Some Street 1', $info->address);
        self::assertTrue($info->sepaSct);
        self::assertFalse($info->sepaSddCore);
        self::assertTrue($info->sepaSctInst);
        self::assertFalse($info->sepaSddB2b);
        self::assertSame('iban.com', $info->sourceId);
        self::assertSame('iban.com API', $info->sourceLicense);
        self::assertNotNull($info->sourceVersion);
        self::assertSame('iban.com', $info->resolvedBy);

        // Pin the endpoint + request shape: the canned response is returned for
        // ANY call, so without these a swapped endpoint (BIC vs IBAN) or a
        // renamed form field ('iban' -> 'bic') would ship green.
        self::assertSame('https://api.iban.com/clients/api/v4/iban/', $client->lastUrl);
        $formParams = $client->lastOptions['form_params'] ?? null;
        self::assertIsArray($formParams);
        self::assertSame('json', $formParams['format']);
        self::assertSame('secret', $formParams['api_key']);
        self::assertSame('DE89370400440532013000', $formParams['iban']);
    }

    public function testFindByIbanReturnsNullOnNonTwoHundredStatus(): void
    {
        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(500, 'Internal Server Error')));

        self::assertNull($provider->findByIban($this->parsedIban()));
    }

    public function testFindByIbanReturnsNullWhenErrorsArrayIsNonEmpty(): void
    {
        $body = json_encode([
            'bank_data'   => ['bank' => 'Should Not Matter'],
            'sepa_data'   => [],
            'validations' => [],
            'errors'      => [['code' => '203', 'message' => 'IBAN is not valid']],
        ]);

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

        self::assertNull($provider->findByIban($this->parsedIban()));
    }

    public function testFindByIbanReturnsNullWhenBankDataIsEmpty(): void
    {
        $body = json_encode([
            'bank_data'   => [],
            'sepa_data'   => [],
            'validations' => [],
            'errors'      => [],
        ]);

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

        self::assertNull($provider->findByIban($this->parsedIban()));
    }

    public function testFindByIbanReturnsNullWhenBankDataIsMissingEntirely(): void
    {
        $body = json_encode([
            'sepa_data'   => [],
            'validations' => [],
            'errors'      => [],
        ]);

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

        self::assertNull($provider->findByIban($this->parsedIban()));
    }

    public function testFindByIbanReturnsNullOnMalformedJson(): void
    {
        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, '{not valid json')));

        self::assertNull($provider->findByIban($this->parsedIban()));
    }

    public function testFindByIbanReturnsNullWhenTheClientThrows(): void
    {
        $client = new class () extends CURLRequest {
            public function __construct()
            {
            }

            /**
             * @param array<string, mixed> $options
             */
            public function post(string $url, array $options = []): ResponseInterface
            {
                throw new RuntimeException('simulated network failure');
            }
        };

        $provider = new IbanComProvider('secret', $client);

        self::assertNull($provider->findByIban($this->parsedIban()));
    }

    public function testFindByBankCodeAlwaysReturnsNull(): void
    {
        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, '{}')));

        self::assertNull($provider->findByBankCode('DE', '50070010'));
        self::assertNull($provider->findByBankCode('DE', '50070010', '001'));
    }

    // -- findByBic (dedicated iban.com BIC/SWIFT endpoint) -----------------

    public function testFindByBicMapsACannedSuccessResponseToBankInfo(): void
    {
        $body = json_encode([
            'query'             => ['bic' => 'BARCGB22XXX', 'success' => true],
            'bic_valid'         => true,
            'bic_active'        => true,
            'directory_results' => [
                'bic'              => 'BARCGB22XXX',
                'bic8'             => 'BARCGB22',
                'institution'      => 'BARCLAYS BANK PLC',
                'name'             => 'BARCLAYS BANK PLC',
                'city'             => 'LONDON',
                'address'          => '1 CHURCHILL PLACE',
                'iso_country_code' => 'GB',
            ],
            'services' => [['code' => 'FIN', 'description' => 'MANY-TO-MANY FIN PAYMENT SERVICE']],
            'error'    => [],
        ]);

        $client   = $this->recordingClient($this->cannedResponse(200, (string) $body));
        $provider = new IbanComProvider('secret', $client);

        // Spaced/lowercase input also proves the 'bic' field is normalized.
        $info = $provider->findByBic(' barc gb 22 xxx ');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('BARCLAYS BANK PLC', $info->bankName);
        self::assertSame('BARCGB22XXX', $info->bic);
        self::assertSame('LONDON', $info->city);
        self::assertSame('1 CHURCHILL PLACE', $info->address);
        // The BIC API carries no SEPA scheme flags.
        self::assertNull($info->sepaSct);
        self::assertNull($info->sepaSddCore);
        self::assertSame('iban.com', $info->sourceId);
        self::assertSame('iban.com API', $info->sourceLicense);
        self::assertNotNull($info->sourceVersion);
        self::assertSame('iban.com', $info->resolvedBy);

        // Pin the SEPARATE BIC endpoint + request shape: the canned response is
        // returned for ANY call, so without these a swapped endpoint (IBAN vs
        // BIC) or a renamed form field ('bic' -> 'iban') would ship green. The
        // 'bic' value also pins the strip-whitespace + uppercase normalization.
        self::assertSame('https://api.iban.com/clients/api/swiftv2/bic/', $client->lastUrl);
        $formParams = $client->lastOptions['form_params'] ?? null;
        self::assertIsArray($formParams);
        self::assertSame('json', $formParams['format']);
        self::assertSame('secret', $formParams['api_key']);
        self::assertSame('BARCGB22XXX', $formParams['bic']);
    }

    public function testFindByBicUsesInstitutionWhenNameIsAbsent(): void
    {
        // A directory_results row that carries 'institution' but NOT 'name'
        // must fall back to the institution field (a real BIC-API field), not
        // yield a null bankName.
        $body = json_encode([
            'bic_valid'         => true,
            'directory_results' => [
                'bic'         => 'BARCGB22XXX',
                'institution' => 'BARCLAYS BANK PLC',
                'city'        => 'LONDON',
            ],
            'error' => [],
        ]);

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

        $info = $provider->findByBic('BARCGB22XXX');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('BARCLAYS BANK PLC', $info->bankName);
    }

    public function testFindByBicAcceptsADirectoryResultsListAndUsesTheFirstRow(): void
    {
        $body = json_encode([
            'bic_valid'         => true,
            'directory_results' => [
                ['name' => 'BARCLAYS BANK PLC', 'bic' => 'BARCGB22XXX', 'city' => 'LONDON'],
            ],
            'error' => [],
        ]);

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

        $info = $provider->findByBic('BARCGB22XXX');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('BARCLAYS BANK PLC', $info->bankName);
    }

    public function testFindByBicReturnsNullWhenApiKeyIsEmpty(): void
    {
        $provider = new IbanComProvider('', $this->clientReturning($this->cannedResponse(200, '{}')));

        self::assertNull($provider->findByBic('BARCGB22XXX'));
    }

    public function testFindByBicReturnsNullOnNonTwoHundredStatus(): void
    {
        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(500, 'Internal Server Error')));

        self::assertNull($provider->findByBic('BARCGB22XXX'));
    }

    public function testFindByBicReturnsNullWhenErrorIsNonEmpty(): void
    {
        $body = json_encode([
            'bic_valid'         => false,
            'directory_results' => [],
            'error'             => ['code' => '301', 'message' => 'BIC does not exist'],
        ]);

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

        self::assertNull($provider->findByBic('BARCGB22XXX'));
    }

    public function testFindByBicReturnsNullWhenDirectoryResultsIsEmpty(): void
    {
        $body = json_encode([
            'bic_valid'         => true,
            'directory_results' => [],
            'error'             => [],
        ]);

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

        self::assertNull($provider->findByBic('BARCGB22XXX'));
    }

    public function testFindByBicReturnsNullOnMalformedJson(): void
    {
        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, '{not valid json')));

        self::assertNull($provider->findByBic('BARCGB22XXX'));
    }

    public function testFindByBicReturnsNullWhenTheClientThrows(): void
    {
        $client = new class () extends CURLRequest {
            public function __construct()
            {
            }

            /**
             * @param array<string, mixed> $options
             */
            public function post(string $url, array $options = []): ResponseInterface
            {
                throw new RuntimeException('simulated network failure');
            }
        };

        $provider = new IbanComProvider('secret', $client);

        self::assertNull($provider->findByBic('BARCGB22XXX'));
    }

    /**
     * Builds a real {@see Response} (not a mock) carrying the given status
     * code and body, so `getStatusCode()`/`getBody()` behave exactly like a
     * genuine HTTP response would.
     */
    private function cannedResponse(int $status, string $body): ResponseInterface
    {
        $response = new Response(config('App'));
        $response->setStatusCode($status);
        $response->setBody($body);

        return $response;
    }

    /**
     * Hand-rolled anonymous {@see CURLRequest} subclass: skips the real
     * constructor (which requires the `curl` extension + a live `App`
     * config/URI) entirely, and returns the canned response from `post()`
     * instead of ever touching the network.
     */
    private function clientReturning(ResponseInterface $response): CURLRequest
    {
        return new class ($response) extends CURLRequest {
            public function __construct(private readonly ResponseInterface $canned)
            {
            }

            /**
             * @param array<string, mixed> $options
             */
            public function post(string $url, array $options = []): ResponseInterface
            {
                return $this->canned;
            }
        };
    }

    /**
     * Like {@see clientReturning()}, but returns a double that records the URL
     * and options of the `post()` call so a test can pin the endpoint and the
     * form fields the provider sends.
     */
    private function recordingClient(ResponseInterface $response): RecordingCurlRequest
    {
        return new RecordingCurlRequest($response);
    }

    private function parsedIban(): ParsedIban
    {
        return new ParsedIban(
            countryCode: 'DE',
            checkDigits: '89',
            bban: '370400440532013000',
            bankIdentifier: '37040044',
            branchIdentifier: null,
            accountNumber: '0532013000',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'DE89370400440532013000',
        );
    }
}
