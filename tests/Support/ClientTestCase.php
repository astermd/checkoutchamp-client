<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests\Support;

use AsterMD\CheckoutChampClient\API;
use AsterMD\CheckoutChampClient\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Shared fixtures for tests that exercise the client end to end.
 */
abstract class ClientTestCase extends TestCase
{
    /**
     * Obviously fake placeholders. Never real credentials.
     */
    public const LOGIN_ID = 'test-login-id-not-a-real-credential';

    public const PASSWORD = 'test-password-not-a-real-credential';

    public const BASE_URL = 'https://api.checkoutchamp.com';

    /**
     * @var MockHttpClient
     */
    protected $http;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new MockHttpClient();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return API
     */
    protected function client(array $options = []): API
    {
        return new API(self::LOGIN_ID, self::PASSWORD, $options, $this->http);
    }

    /**
     * Assert the recorded request matches the expected shape.
     *
     * Every Checkout Champ call is a POST with no headers and no body; all
     * parameters, credentials included, ride in the query string.
     *
     * @param string                $path           Path below the host, e.g. "/order/query/".
     * @param array<string, string> $expectedParams Caller parameters, credentials excluded.
     *
     * @return Request
     */
    protected function assertRequest(string $path, array $expectedParams = []): Request
    {
        $request = $this->http->lastRequest();

        self::assertSame('POST', $request->getMethod());
        self::assertNull($request->getBody());
        self::assertSame([], $request->getHeaders());

        $url       = $request->getUrl();
        $separator = strpos($url, '?');
        self::assertNotFalse($separator, 'The request URL should carry a query string.');

        self::assertSame(self::BASE_URL . $path, substr($url, 0, (int) $separator));

        $query = [];
        parse_str(substr($url, (int) $separator + 1), $query);

        self::assertSame(
            array_merge($expectedParams, [
                'loginId'  => self::LOGIN_ID,
                'password' => self::PASSWORD,
            ]),
            $query
        );

        return $request;
    }
}
