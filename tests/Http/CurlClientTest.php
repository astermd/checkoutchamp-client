<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests\Http;

use AsterMD\CheckoutChampClient\Http\CurlClient;
use AsterMD\CheckoutChampClient\Http\HttpClientInterface;
use AsterMD\CheckoutChampClient\Http\Request;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The transport is exercised with a scheme libcurl rejects before it opens a
 * socket, so this test performs no network I/O and no DNS lookup.
 */
#[CoversClass(\AsterMD\CheckoutChampClient\Http\CurlClient::class)]
final class CurlClientTest extends TestCase
{
    public function testItImplementsTheTransportInterface(): void
    {
        self::assertInstanceOf(HttpClientInterface::class, new CurlClient(5, 2));
    }

    public function testAnUnsupportedSchemeIsReportedAsATransportError(): void
    {
        $client   = new CurlClient(5, 2);
        $response = $client->send(new Request('GET', 'made-up-scheme://example.invalid/orders'));

        self::assertTrue($response->hasTransportError());
        self::assertNotSame('', $response->getTransportError());
        self::assertSame(0, $response->getStatusCode());
        self::assertSame('', $response->getBody());
    }

    public function testTheTransportErrorPathStillReturnsInfo(): void
    {
        $client   = new CurlClient(5, 2);
        $response = $client->send(new Request('POST', 'made-up-scheme://example.invalid/orders', [], '{"a":1}'));

        self::assertTrue($response->hasTransportError());
        self::assertSame(0, $response->getStatusCode());
    }
}
