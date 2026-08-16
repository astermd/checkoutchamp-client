<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests\Support;

use AsterMD\CheckoutChampClient\Http\HttpClientInterface;
use AsterMD\CheckoutChampClient\Http\Request;
use AsterMD\CheckoutChampClient\Http\Response;
use RuntimeException;

/**
 * Recording transport used by the whole test suite.
 *
 * Nothing here touches the network. Every request is captured so tests can
 * assert the URL, method, headers and body that would have been sent.
 */
final class MockHttpClient implements HttpClientInterface
{
    /**
     * @var Request[]
     */
    private $requests = [];

    /**
     * @var Response[]
     */
    private $queue = [];

    /**
     * @var Response
     */
    private $fallback;

    /**
     * @param Response|null $fallback Returned once the queue is empty.
     */
    public function __construct(?Response $fallback = null)
    {
        $this->fallback = $fallback ?? new Response(200, '{"ok":true}', ['http_code' => 200]);
    }

    /**
     * @param Response $response
     *
     * @return self
     */
    public function queue(Response $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        return $this->fallback;
    }

    /**
     * @return Request
     */
    public function lastRequest(): Request
    {
        if ($this->requests === []) {
            throw new RuntimeException('No request was recorded.');
        }

        return $this->requests[count($this->requests) - 1];
    }

    /**
     * @return Request[]
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->requests);
    }
}
