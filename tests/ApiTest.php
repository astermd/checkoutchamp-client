<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\API;
use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use AsterMD\CheckoutChampClient\Tests\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(API::class)]
final class ApiTest extends ClientTestCase
{
    public function testALoginIdIsRequired(): void
    {
        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('A login ID and password are required');

        new API('', self::PASSWORD, [], new MockHttpClient());
    }

    public function testAPasswordIsRequired(): void
    {
        $this->expectException(CheckoutChampException::class);

        new API(self::LOGIN_ID, '', [], new MockHttpClient());
    }

    public function testWhitespaceOnlyCredentialsAreRejected(): void
    {
        $this->expectException(CheckoutChampException::class);

        new API('  ', '  ', [], new MockHttpClient());
    }

    public function testTheDefaultHostIsUsedWhenNoneIsGiven(): void
    {
        $this->client()->orderQuery();

        self::assertStringStartsWith(
            'https://api.checkoutchamp.com/order/query/?',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testAnExplicitHostIsHonoured(): void
    {
        $this->client(['host' => 'crm.example.test'])->orderQuery();

        self::assertStringStartsWith(
            'https://crm.example.test/order/query/?',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testABasePathIsPrependedToEveryRoute(): void
    {
        $this->client(['basePath' => 'v1'])->orderQuery();

        self::assertStringStartsWith(
            'https://api.checkoutchamp.com/v1/order/query/?',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testUnknownMethodsAreRejected(): void
    {
        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('No such method found');

        /** @phpstan-ignore-next-line Intentionally calling a method that does not exist. */
        $this->client()->thisMethodDoesNotExist();
    }

    public function testAccessorsFailBeforeAnyMethodIsInvoked(): void
    {
        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('No API method has been invoked yet');

        $this->client()->get();
    }

    public function testResourceCallsAreChainable(): void
    {
        $client = $this->client();

        self::assertSame($client, $client->orderQuery());
    }

    public function testPayloadInfoIsOmittedByDefault(): void
    {
        $result = $this->client()->orderQuery(['orderId' => '123'])->get();

        self::assertArrayNotHasKey('payload', $result);
        self::assertArrayHasKey('response', $result);
    }

    public function testPayloadInfoReturnsTheEndpointAndCallerParameters(): void
    {
        $result = $this->client()->orderQuery(['orderId' => '123'])->get(true);

        self::assertSame(
            [
                'endPoint' => 'https://api.checkoutchamp.com/order/query/',
                'orderId'  => '123',
            ],
            $result['payload']
        );
    }

    public function testPayloadInfoNeverContainsTheCredentials(): void
    {
        $client = $this->client();
        $client->importOrder(['sessionId' => 'sess_1']);

        $encoded = json_encode($client->get(true));

        self::assertIsString($encoded);
        self::assertStringNotContainsString(self::PASSWORD, $encoded);
        self::assertStringNotContainsString(self::LOGIN_ID, $encoded);
        self::assertStringNotContainsString('password', $encoded);
        self::assertStringNotContainsString('loginId', $encoded);
    }

    public function testPayloadInfoIsAnObjectForGetInObject(): void
    {
        $result = $this->client()->orderQuery()->getInObject(true);

        self::assertInstanceOf(stdClass::class, $result['payload']);

        /** @var stdClass $payload */
        $payload = $result['payload'];

        self::assertSame('https://api.checkoutchamp.com/order/query/', $payload->endPoint);
    }

    public function testCredentialsAreSentAsQueryParameters(): void
    {
        $this->client()->orderQuery(['orderId' => '123']);

        $url = $this->http->lastRequest()->getUrl();

        self::assertStringContainsString('loginId=' . rawurlencode(self::LOGIN_ID), $url);
        self::assertStringContainsString('password=' . rawurlencode(self::PASSWORD), $url);
    }

    public function testWithProxyAppliesToTheNextCallOnly(): void
    {
        $client = $this->client();

        $client->withProxy('proxy.example.test:8080', 'proxyuser', 'proxypass-placeholder')->orderQuery();
        $first = $this->http->lastRequest();

        self::assertTrue($first->hasProxy());
        self::assertSame('proxy.example.test:8080', $first->getProxyUrl());
        self::assertSame('proxyuser:proxypass-placeholder', $first->getProxyCredentials());

        $client->orderQuery();

        self::assertFalse($this->http->lastRequest()->hasProxy());
    }

    public function testWithProxyIsChainableAndIgnoresAnEmptyAddress(): void
    {
        $client = $this->client();

        self::assertSame($client, $client->withProxy(''));

        $client->orderQuery();

        self::assertFalse($this->http->lastRequest()->hasProxy());
    }

    public function testSupportedMethodsListsEveryResourceMethod(): void
    {
        $methods = API::supportedMethods();

        self::assertCount(14, $methods);
        self::assertContains('importOrder', $methods);
        self::assertContains('confirmPaypal', $methods);
        self::assertNotContains('get', $methods);
    }
}
