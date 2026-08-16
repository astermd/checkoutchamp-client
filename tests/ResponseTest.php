<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\CheckoutChamp;
use AsterMD\CheckoutChampClient\ClientConfig;
use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use AsterMD\CheckoutChampClient\Http\Response;
use AsterMD\CheckoutChampClient\Logging\DebugLogger;
use AsterMD\CheckoutChampClient\Order;
use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use AsterMD\CheckoutChampClient\Tests\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(CheckoutChamp::class)]
final class ResponseTest extends ClientTestCase
{
    public function testTheProviderBodyIsReturnedVerbatim(): void
    {
        $body = '{"result":"SUCCESS","message":{"orderId":"123"}}';
        $this->http->queue(new Response(200, $body, ['http_code' => 200]));

        self::assertSame($body, $this->client()->orderQuery()->get()['response']);
    }

    public function testTheResponseDecodesIntoAnArray(): void
    {
        $body = '{"result":"SUCCESS","message":{"orderId":"123"}}';
        $this->http->queue(new Response(200, $body, ['http_code' => 200]));

        /** @var array<string, mixed> $decoded */
        $decoded = $this->client()->orderQuery()->getInArray()['response'];

        self::assertSame('SUCCESS', $decoded['result']);
        self::assertSame(['orderId' => '123'], $decoded['message']);
    }

    public function testTheResponseDecodesIntoAnObject(): void
    {
        $body = '{"result":"ERROR","message":"Invalid order"}';
        $this->http->queue(new Response(200, $body, ['http_code' => 200]));

        /** @var stdClass $decoded */
        $decoded = $this->client()->orderQuery()->getInObject()['response'];

        self::assertSame('ERROR', $decoded->result);
        self::assertSame('Invalid order', $decoded->message);
    }

    public function testANonJsonResponseIsRejected(): void
    {
        $this->http->queue(new Response(502, 'Bad Gateway', ['http_code' => 502]));

        $client = $this->client();
        $client->orderQuery();

        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('API response is not valid JSON');

        $client->getInArray();
    }

    public function testATransportErrorIsSurfacedAsCurlError(): void
    {
        $this->http->queue(new Response(0, '', [], 'Could not resolve host'));

        $client = $this->client();
        $client->orderQuery();

        self::assertSame('Could not resolve host', $client->get()['response']);
        self::assertSame(['curlError' => 'Could not resolve host'], $client->getInArray()['response']);

        /** @var stdClass $object */
        $object = $client->getInObject()['response'];
        self::assertSame('Could not resolve host', $object->curlError);
    }

    public function testHeaderRequiredAttachesTransportMetadata(): void
    {
        $http = new MockHttpClient();
        $http->queue(new Response(200, '{"result":"SUCCESS"}', ['http_code' => 200, 'total_time' => 0.2]));

        $order = new Order(
            new ClientConfig(self::LOGIN_ID, self::PASSWORD),
            $http,
            new DebugLogger(false)
        );
        $order->headerRequired = true;
        $order->orderQuery();

        /** @var array{content: string, header: array<string, mixed>} $raw */
        $raw = $order->getResponse();
        self::assertJson($raw['content']);
        self::assertSame(200, $raw['header']['http_code']);

        /** @var array{content: array<string, mixed>, header: array<string, mixed>} $decoded */
        $decoded = $order->getArrayResponse();
        self::assertSame('SUCCESS', $decoded['content']['result']);
        self::assertSame(0.2, $decoded['header']['total_time']);

        /** @var stdClass $wrapper */
        $wrapper = $order->getObjectResponse();
        /** @var stdClass $content */
        $content = $wrapper->content;
        /** @var stdClass $header */
        $header = $wrapper->header;

        self::assertSame('SUCCESS', $content->result);
        self::assertSame(200, $header->http_code);
    }

    public function testSetProxyAppliesToTheNextRequestOnly(): void
    {
        $http  = new MockHttpClient();
        $order = new Order(
            new ClientConfig(self::LOGIN_ID, self::PASSWORD),
            $http,
            new DebugLogger(false)
        );

        $order->setProxy('proxy.example.test:3128', 'proxyuser', 'proxypass-placeholder');
        $order->orderQuery();

        self::assertSame('proxy.example.test:3128', $http->lastRequest()->getProxyUrl());
        self::assertSame('proxyuser:proxypass-placeholder', $http->lastRequest()->getProxyCredentials());

        $order->orderQuery();

        self::assertFalse($http->lastRequest()->hasProxy());
    }

    public function testAProxyWithoutCredentialsCarriesNoUserPassword(): void
    {
        $http  = new MockHttpClient();
        $order = new Order(
            new ClientConfig(self::LOGIN_ID, self::PASSWORD),
            $http,
            new DebugLogger(false)
        );

        $order->setProxy('proxy.example.test:3128');
        $order->orderQuery();

        self::assertTrue($http->lastRequest()->hasProxy());
        self::assertNull($http->lastRequest()->getProxyCredentials());
    }

    public function testPayloadInfoExcludesCredentials(): void
    {
        $http  = new MockHttpClient();
        $order = new Order(
            new ClientConfig(self::LOGIN_ID, self::PASSWORD),
            $http,
            new DebugLogger(false)
        );

        $order->orderQuery(['orderId' => '123']);

        self::assertSame(
            [
                'endPoint' => 'https://api.checkoutchamp.com/order/query/',
                'orderId'  => '123',
            ],
            $order->getPayloadInfo()
        );
    }
}
