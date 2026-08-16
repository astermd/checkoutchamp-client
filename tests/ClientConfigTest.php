<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\ClientConfig;
use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientConfig::class)]
final class ClientConfigTest extends TestCase
{
    private const LOGIN_ID = 'test-login-id-not-a-real-credential';

    private const PASSWORD = 'test-password-not-a-real-credential';

    public function testItDefaultsToTheProviderHost(): void
    {
        $config = new ClientConfig(self::LOGIN_ID, self::PASSWORD);

        self::assertSame('api.checkoutchamp.com', $config->getHost());
        self::assertSame('https://api.checkoutchamp.com', $config->getBaseUrl());
    }

    public function testItAcceptsABareHost(): void
    {
        $config = new ClientConfig(self::LOGIN_ID, self::PASSWORD, ['host' => 'crm.example.test']);

        self::assertSame('https://crm.example.test', $config->getBaseUrl());
    }

    public function testABasePathIsAppendedWithoutDoubledSlashes(): void
    {
        $config = new ClientConfig(self::LOGIN_ID, self::PASSWORD, ['basePath' => '/v1/']);

        self::assertSame('https://api.checkoutchamp.com/v1', $config->getBaseUrl());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedHostProvider(): array
    {
        return [
            'scheme'         => ['https://api.checkoutchamp.com'],
            'plain scheme'   => ['http://api.checkoutchamp.com'],
            'path included'  => ['api.checkoutchamp.com/v1'],
            'query string'   => ['api.checkoutchamp.com?debug=1'],
            'embedded space' => ['api.checkoutchamp.com /v1'],
        ];
    }

    #[DataProvider('rejectedHostProvider')]
    public function testFullUrlsAreRejected(string $host): void
    {
        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('bare hostname');

        new ClientConfig(self::LOGIN_ID, self::PASSWORD, ['host' => $host]);
    }

    public function testAnEmptyLoginIdIsRejected(): void
    {
        $this->expectException(CheckoutChampException::class);

        new ClientConfig('', self::PASSWORD);
    }

    public function testAnEmptyPasswordIsRejected(): void
    {
        $this->expectException(CheckoutChampException::class);

        new ClientConfig(self::LOGIN_ID, '');
    }

    public function testAnEmptyHostFallsBackToTheDefault(): void
    {
        $config = new ClientConfig(self::LOGIN_ID, self::PASSWORD, ['host' => '  ']);

        self::assertSame(ClientConfig::DEFAULT_HOST, $config->getHost());
    }

    public function testTimeoutDefaultsAndOverrides(): void
    {
        $defaults = new ClientConfig(self::LOGIN_ID, self::PASSWORD);

        self::assertSame(ClientConfig::DEFAULT_TIMEOUT, $defaults->getTimeout());
        self::assertSame(ClientConfig::DEFAULT_CONNECT_TIMEOUT, $defaults->getConnectTimeout());

        $custom = new ClientConfig(self::LOGIN_ID, self::PASSWORD, ['timeout' => 5, 'connectTimeout' => 2]);

        self::assertSame(5, $custom->getTimeout());
        self::assertSame(2, $custom->getConnectTimeout());
    }

    public function testCredentialsAreReturnedVerbatim(): void
    {
        $config = new ClientConfig(self::LOGIN_ID, self::PASSWORD);

        self::assertSame(self::LOGIN_ID, $config->getLoginId());
        self::assertSame(self::PASSWORD, $config->getPassword());
    }
}
