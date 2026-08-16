<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use AsterMD\CheckoutChampClient\Http\CurlClient;
use AsterMD\CheckoutChampClient\Http\HttpClientInterface;
use AsterMD\CheckoutChampClient\Logging\DebugLogger;

/**
 * Entry point for the Checkout Champ API.
 *
 * Construct it with your API credentials, call any resource method, then read
 * the result with get(), getInArray() or getInObject():
 *
 *     $api  = new API($loginId, $password);
 *     $data = $api->orderQuery(['orderId' => '123'])->getInArray();
 *
 * The login ID and password are required arguments. This package ships no
 * default credentials and reads none from the environment.
 *
 * @method self orderQuery(array<string, mixed> $params = [])
 * @method self importLeads(array<string, mixed> $params = [])
 * @method self updateOrder(array<string, mixed> $params = [])
 * @method self preauth(array<string, mixed> $params = [])
 * @method self importOrder(array<string, mixed> $params = [])
 * @method self importUpsale(array<string, mixed> $params = [])
 * @method self confirm(array<string, mixed> $params = [])
 * @method self qa(array<string, mixed> $params = [])
 * @method self campaignQuery(array<string, mixed> $params = [])
 * @method self customerQuery(array<string, mixed> $params = [])
 * @method self addnote(array<string, mixed> $params = [])
 * @method self transactionsQuery(array<string, mixed> $params = [])
 * @method self importClick(array<string, mixed> $params = [])
 * @method self confirmPaypal(array<string, mixed> $params = [])
 *
 * @package AsterMD\CheckoutChampClient
 */
final class API
{
    /**
     * Resource method to the class that implements it.
     *
     * @var array<string, class-string<CheckoutChamp>>
     */
    private const METHOD_MAP = [
        'orderQuery'        => Order::class,
        'importLeads'       => Order::class,
        'updateOrder'       => Order::class,
        'preauth'           => Order::class,
        'importOrder'       => Order::class,
        'importUpsale'      => Order::class,
        'confirm'           => Order::class,
        'qa'                => Order::class,
        'campaignQuery'     => Campaign::class,
        'customerQuery'     => Customer::class,
        'addnote'           => Customer::class,
        'transactionsQuery' => Transaction::class,
        'importClick'       => Landers::class,
        'confirmPaypal'     => Landers::class,
    ];

    /**
     * @var ClientConfig
     */
    private $config;

    /**
     * @var HttpClientInterface
     */
    private $http;

    /**
     * @var DebugLogger
     */
    private $logger;

    /**
     * @var array<class-string<CheckoutChamp>, CheckoutChamp>
     */
    private $resources = [];

    /**
     * @var CheckoutChamp|null
     */
    private $desiredInstance;

    /**
     * @var string|null
     */
    private $proxyUrl;

    /**
     * @var string|null
     */
    private $proxyUserName;

    /**
     * @var string|null
     */
    private $proxyPassword;

    /**
     * @param string                   $loginId  Checkout Champ API login ID. Required; there is no default.
     * @param string                   $password Checkout Champ API password. Required; there is no default.
     * @param array<string, mixed>     $options  {
     *     @type string   $host               Bare hostname. Defaults to ClientConfig::DEFAULT_HOST.
     *     @type string   $basePath           Optional path prefix.
     *     @type int      $timeout            Transfer timeout in seconds. Default 30.
     *     @type int      $connectTimeout     Connection timeout in seconds. Default 10.
     *     @type bool     $debug              Enable request/response logging. Default false.
     *     @type bool     $debugRedact        Redact the logged URL, headers and bodies. Default true.
     *     @type string   $debugFile          Base path for the built-in dated file sink.
     *     @type int      $debugRetentionDays Days of log history to keep. Default 7, 0 keeps all.
     *     @type string   $debugTimezone      IANA timezone for log timestamps. Default "UTC".
     *     @type callable $debugSink          Replaces the file sink: function (string $entry): void
     * }
     * @param HttpClientInterface|null $http     Custom transport. Defaults to cURL.
     *
     * @throws CheckoutChampException When a credential is empty, the host is not
     *                                bare, or debug logging has no destination.
     */
    public function __construct(
        string $loginId,
        string $password,
        array $options = [],
        ?HttpClientInterface $http = null
    ) {
        $this->config = new ClientConfig($loginId, $password, $options);
        $this->logger = DebugLogger::fromOptions($options);
        $this->http   = $http ?? new CurlClient(
            $this->config->getTimeout(),
            $this->config->getConnectTimeout()
        );
    }

    /**
     * Route the next call through an HTTP proxy.
     *
     * The setting applies to the next resource call only.
     *
     * @param string $proxyUrl      Proxy address, e.g. "proxy.example.com:8080".
     * @param string $proxyUserName Proxy username, when the proxy requires one.
     * @param string $proxyPassword Proxy password, when the proxy requires one.
     *
     * @return self
     */
    public function withProxy(string $proxyUrl, string $proxyUserName = '', string $proxyPassword = ''): self
    {
        if ($proxyUrl !== '') {
            $this->proxyUrl      = $proxyUrl;
            $this->proxyUserName = $proxyUserName !== '' ? $proxyUserName : null;
            $this->proxyPassword = $proxyPassword !== '' ? $proxyPassword : null;
        }

        return $this;
    }

    /**
     * Dispatch a resource method to the class that implements it.
     *
     * @param string       $method
     * @param array<mixed> $arguments
     *
     * @return self
     *
     * @throws CheckoutChampException When no resource implements the method.
     */
    public function __call(string $method, array $arguments): self
    {
        $resource = $this->resourceFor($method);

        if ($this->proxyUrl !== null) {
            $resource->setProxy($this->proxyUrl, $this->proxyUserName, $this->proxyPassword);
            $this->proxyUrl      = null;
            $this->proxyUserName = null;
            $this->proxyPassword = null;
        }

        $callable = [$resource, $method];
        if (!is_callable($callable)) {
            throw new CheckoutChampException(Config::get('Messages.methodNotFound'));
        }

        $this->desiredInstance = $resource;
        $callable(...$arguments);

        return $this;
    }

    /**
     * Raw response body from the most recent call.
     *
     * @param bool $payloadFlag Include the endpoint and parameters alongside the response.
     *
     * @return array<string, mixed>
     *
     * @throws CheckoutChampException When no resource method has been called yet.
     */
    public function get(bool $payloadFlag = false): array
    {
        $resource = $this->requireInstance();

        $result = ['response' => $resource->getResponse()];
        if ($payloadFlag) {
            $result['payload'] = $resource->getPayloadInfo();
        }

        return $result;
    }

    /**
     * Response from the most recent call, decoded into an array.
     *
     * @param bool $payloadFlag Include the endpoint and parameters alongside the response.
     *
     * @return array<string, mixed>
     *
     * @throws CheckoutChampException When no resource method has been called yet.
     */
    public function getInArray(bool $payloadFlag = false): array
    {
        $resource = $this->requireInstance();

        $result = ['response' => $resource->getArrayResponse()];
        if ($payloadFlag) {
            $result['payload'] = $resource->getPayloadInfo();
        }

        return $result;
    }

    /**
     * Response from the most recent call, decoded into an object.
     *
     * @param bool $payloadFlag Include the endpoint and parameters alongside the response.
     *
     * @return array<string, mixed>
     *
     * @throws CheckoutChampException When no resource method has been called yet.
     */
    public function getInObject(bool $payloadFlag = false): array
    {
        $resource = $this->requireInstance();

        $result = ['response' => $resource->getObjectResponse()];
        if ($payloadFlag) {
            $result['payload'] = (object) $resource->getPayloadInfo();
        }

        return $result;
    }

    /**
     * Resource methods this client can dispatch.
     *
     * @return string[]
     */
    public static function supportedMethods(): array
    {
        return array_keys(self::METHOD_MAP);
    }

    /**
     * @param string $method
     *
     * @return CheckoutChamp
     *
     * @throws CheckoutChampException
     */
    private function resourceFor(string $method): CheckoutChamp
    {
        if (!isset(self::METHOD_MAP[$method])) {
            throw new CheckoutChampException(Config::get('Messages.methodNotFound'));
        }

        $class = self::METHOD_MAP[$method];
        if (!isset($this->resources[$class])) {
            $this->resources[$class] = new $class($this->config, $this->http, $this->logger);
        }

        return $this->resources[$class];
    }

    /**
     * @return CheckoutChamp
     *
     * @throws CheckoutChampException
     */
    private function requireInstance(): CheckoutChamp
    {
        if ($this->desiredInstance === null) {
            throw new CheckoutChampException(Config::get('Messages.apiInvokedFailure'));
        }

        return $this->desiredInstance;
    }
}
