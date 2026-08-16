<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests\Http;

use AsterMD\CheckoutChampClient\Http\Request;
use AsterMD\CheckoutChampClient\Http\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\CheckoutChampClient\Http\Request::class)]
#[CoversClass(\AsterMD\CheckoutChampClient\Http\Response::class)]
final class RequestTest extends TestCase
{
    public function testItNormalisesTheMethodAndExposesEveryPart(): void
    {
        $request = new Request(
            'post',
            'https://api.checkoutchamp.com/orders',
            ['Content-Type: application/json'],
            '{"a":1}',
            'proxy.example.test:8080',
            'user:placeholder'
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.checkoutchamp.com/orders', $request->getUrl());
        self::assertSame(['Content-Type: application/json'], $request->getHeaders());
        self::assertSame('{"a":1}', $request->getBody());
        self::assertSame('proxy.example.test:8080', $request->getProxyUrl());
        self::assertSame('user:placeholder', $request->getProxyCredentials());
        self::assertTrue($request->hasProxy());
    }

    public function testItDefaultsToNoBodyAndNoProxy(): void
    {
        $request = new Request('GET', 'https://api.checkoutchamp.com/orders');

        self::assertNull($request->getBody());
        self::assertNull($request->getProxyUrl());
        self::assertNull($request->getProxyCredentials());
        self::assertFalse($request->hasProxy());
        self::assertSame([], $request->getHeaders());
    }

    public function testHeaderKeysAreReindexed(): void
    {
        $request = new Request('GET', 'https://api.checkoutchamp.com/orders', [3 => 'A: 1', 7 => 'B: 2']);

        self::assertSame(['A: 1', 'B: 2'], $request->getHeaders());
    }

    public function testAnEmptyProxyUrlDoesNotCountAsAProxy(): void
    {
        $request = new Request('GET', 'https://api.checkoutchamp.com/orders', [], null, '');

        self::assertFalse($request->hasProxy());
    }

    public function testTheResponseExposesEveryPart(): void
    {
        $response = new Response(201, '{"id":1}', ['http_code' => 201]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"id":1}', $response->getBody());
        self::assertSame(['http_code' => 201], $response->getInfo());
        self::assertSame('', $response->getTransportError());
        self::assertFalse($response->hasTransportError());
    }

    public function testTheResponseReportsATransportError(): void
    {
        $response = new Response(0, '', [], 'Could not resolve host');

        self::assertTrue($response->hasTransportError());
        self::assertSame('Could not resolve host', $response->getTransportError());
        self::assertSame([], $response->getInfo());
    }
}
