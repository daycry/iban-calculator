<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Offline {@see CURLRequest} test double that RECORDS the URL and options of
 * the last `post()` call before returning a canned response.
 *
 * Unlike a fire-and-forget double, this lets a test pin the endpoint and the
 * request shape (form fields) a provider actually sends — so an endpoint or
 * form-field swap is caught instead of shipping green under a double that
 * ignores its arguments.
 *
 * The real {@see CURLRequest} constructor needs the `curl` extension plus a
 * live `App` config/URI; this override skips it entirely.
 */
final class RecordingCurlRequest extends CURLRequest
{
    public string $lastUrl = '';

    /**
     * @var array<string, mixed>
     */
    public array $lastOptions = [];

    public function __construct(private readonly ResponseInterface $canned)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function post(string $url, array $options = []): ResponseInterface
    {
        $this->lastUrl     = $url;
        $this->lastOptions = $options;

        return $this->canned;
    }
}
