<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;

/**
 * Immutable connection settings for the Checkout Champ API.
 *
 * The consumer supplies a bare hostname — never a full URL. This keeps the
 * scheme, path assembly and query encoding under the package's control.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class ClientConfig
{
    /**
     * Host used when the consumer does not supply one.
     */
    public const DEFAULT_HOST = 'api.checkoutchamp.com';

    /**
     * Default transfer timeout in seconds.
     */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * Default connection timeout in seconds.
     */
    public const DEFAULT_CONNECT_TIMEOUT = 10;

    /**
     * @var string
     */
    private $loginId;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $connectTimeout;

    /**
     * @param string               $loginId  Checkout Champ API login ID, supplied by the caller.
     * @param string               $password Checkout Champ API password, supplied by the caller.
     * @param array<string, mixed> $options  {
     *     @type string $host           Bare hostname, e.g. "api.checkoutchamp.com". No scheme, no path.
     *     @type string $basePath       Optional path prefix appended to the host.
     *     @type int    $timeout        Transfer timeout in seconds.
     *     @type int    $connectTimeout Connection timeout in seconds.
     * }
     *
     * @throws CheckoutChampException When a credential is empty or the host is not a bare hostname.
     */
    public function __construct(string $loginId, string $password, array $options = [])
    {
        if (trim($loginId) === '' || trim($password) === '') {
            throw new CheckoutChampException(Config::get('Messages.invalidApiAuth'));
        }

        $host = trim(self::stringOption($options, 'host', ''));
        if ($host === '') {
            $host = self::DEFAULT_HOST;
        }
        $this->assertBareHost($host);

        $this->loginId        = $loginId;
        $this->password       = $password;
        $this->host           = $host;
        $this->basePath       = trim(self::stringOption($options, 'basePath', ''), '/');
        $this->timeout        = self::intOption($options, 'timeout', self::DEFAULT_TIMEOUT);
        $this->connectTimeout = self::intOption($options, 'connectTimeout', self::DEFAULT_CONNECT_TIMEOUT);
    }

    /**
     * @return string
     */
    public function getLoginId(): string
    {
        return $this->loginId;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Absolute base URL with no trailing slash, e.g. "https://api.checkoutchamp.com".
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        $url = 'https://' . $this->host;
        if ($this->basePath !== '') {
            $url .= '/' . $this->basePath;
        }

        return $url;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return int
     */
    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * Reject anything that is not a bare hostname.
     *
     * @param string $host
     *
     * @return void
     *
     * @throws CheckoutChampException
     */
    private function assertBareHost(string $host): void
    {
        $looksLikeUrl = strpos($host, '://') !== false
            || strpos($host, '/') !== false
            || strpos($host, ' ') !== false
            || strpos($host, '?') !== false;

        if ($looksLikeUrl) {
            throw new CheckoutChampException(Config::get('Messages.invalidHost'));
        }
    }

    /**
     * Read a scalar option as a string, falling back when it is absent or not
     * scalar.
     *
     * @param array<string, mixed> $options
     * @param string               $key
     * @param string               $default
     *
     * @return string
     */
    private static function stringOption(array $options, string $key, string $default): string
    {
        if (!isset($options[$key]) || !is_scalar($options[$key])) {
            return $default;
        }

        return (string) $options[$key];
    }

    /**
     * @param array<string, mixed> $options
     * @param string               $key
     * @param int                  $default
     *
     * @return int
     */
    private static function intOption(array $options, string $key, int $default): int
    {
        if (!isset($options[$key]) || !is_numeric($options[$key])) {
            return $default;
        }

        return (int) $options[$key];
    }
}
