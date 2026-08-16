<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Http;

/**
 * Transport seam.
 *
 * Implement this to route requests through your own HTTP stack (PSR-18, Guzzle,
 * a queue, a recorded fixture) and pass the implementation to
 * {@see \AsterMD\CheckoutChampClient\API::__construct()}. The package's own test suite
 * uses this seam so no test performs real network I/O.
 *
 * @package AsterMD\CheckoutChampClient
 */
interface HttpClientInterface
{
    /**
     * Perform the request and return the response.
     *
     * Implementations must not throw on transport failure; report it through
     * {@see Response::getTransportError()} instead.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function send(Request $request): Response;
}
