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

        $provider = new IbanComProvider('secret', $this->clientReturning($this->cannedResponse(200, (string) $body)));

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
