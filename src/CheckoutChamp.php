<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use AsterMD\CheckoutChampClient\Http\HttpClientInterface;
use AsterMD\CheckoutChampClient\Http\Request;
use AsterMD\CheckoutChampClient\Logging\DebugLogger;
use stdClass;

/**
 * Base class for every Checkout Champ API resource.
 *
 * Holds the connection settings, assembles the request, hands it to the
 * transport, and exposes the provider's response in three shapes.
 *
 * The API authenticates with `loginId` and `password` sent as query string
 * parameters. They are added at send time and are never returned by
 * {@see self::getPayloadInfo()}.
 *
 * @package AsterMD\CheckoutChampClient
 */
abstract class CheckoutChamp
{
    /**
     * Include transport metadata alongside the response body.
     *
     * @var bool
     */
    public $headerRequired = false;

    /**
     * @var ClientConfig
     */
    protected $config;

    /**
     * @var HttpClientInterface
     */
    protected $http;

    /**
     * @var DebugLogger
     */
    protected $logger;

    /**
     * Caller-supplied parameters for the current call.
     *
     * @var array<string, mixed>
     */
    protected $fields = [];

    /**
     * First path segment, e.g. "order".
     *
     * @var string
     */
    protected $section = '';

    /**
     * Second path segment, e.g. "query".
     *
     * @var string
     */
    protected $method = '';

    /**
     * @var string
     */
    private $rawBody = '';

    /**
     * @var array<string, mixed>
     */
    private $transportInfo = [];

    /**
     * @var bool
     */
    private $checkError = false;

    /**
     * Credential-free snapshot kept for getPayloadInfo() after the reset.
     *
     * @var array<string, mixed>
     */
    private $lastPayload = [];

    /**
     * @var string
     */
    private $lastUrl = '';

    /**
     * @var string|null
     */
    private $proxyUrl;

    /**
     * @var string|null
     */
    private $proxyCredentials;

    /**
     * @param ClientConfig        $config
     * @param HttpClientInterface $http
     * @param DebugLogger         $logger
     */
    public function __construct(ClientConfig $config, HttpClientInterface $http, DebugLogger $logger)
    {
        $this->config = $config;
        $this->http   = $http;
        $this->logger = $logger;
    }

    /**
     * Route the next request through an HTTP proxy.
     *
     * Cleared automatically once the request completes.
     *
     * @param string|null $proxyUrl      Proxy address, e.g. "proxy.example.com:8080".
     * @param string|null $proxyUserName Proxy username, when the proxy requires one.
     * @param string|null $proxyPassword Proxy password, when the proxy requires one.
     *
     * @return void
     */
    public function setProxy(?string $proxyUrl, ?string $proxyUserName = null, ?string $proxyPassword = null): void
    {
        $this->proxyUrl = $proxyUrl;

        $this->proxyCredentials = ($proxyUserName !== null && $proxyUserName !== '')
            ? $proxyUserName . ':' . (string) $proxyPassword
            : null;
    }

    /**
     * The provider's raw response body.
     *
     * With $headerRequired the return is an array of "content" and "header".
     *
     * @return string|array<string, mixed>
     */
    public function getResponse()
    {
        if ($this->headerRequired && !$this->checkError) {
            return [
                'content' => $this->rawBody,
                'header'  => $this->transportInfo,
            ];
        }

        return $this->rawBody;
    }

    /**
     * The provider's response decoded into an array.
     *
     * @return array<string, mixed>
     *
     * @throws CheckoutChampException When the response is not valid JSON.
     */
    public function getArrayResponse(): array
    {
        if ($this->checkError) {
            return ['curlError' => $this->rawBody];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $this->isJson($this->rawBody, true);

        if ($this->headerRequired) {
            return [
                'content' => $decoded,
                'header'  => $this->transportInfo,
            ];
        }

        return $decoded;
    }

    /**
     * The provider's response decoded into an object.
     *
     * @return object
     *
     * @throws CheckoutChampException When the response is not valid JSON.
     */
    public function getObjectResponse(): object
    {
        if ($this->checkError) {
            $error            = new stdClass();
            $error->curlError = $this->rawBody;

            return $error;
        }

        /** @var object $decoded */
        $decoded = $this->isJson($this->rawBody, false);

        if ($this->headerRequired) {
            $wrapper          = new stdClass();
            $wrapper->content = $decoded;
            $wrapper->header  = (object) $this->transportInfo;

            return $wrapper;
        }

        return $decoded;
    }

    /**
     * The endpoint and parameters used by the most recent call.
     *
     * Credentials are deliberately absent — this value is safe to log or return
     * to an end user.
     *
     * @return array<string, mixed>
     */
    public function getPayloadInfo(): array
    {
        return array_merge(['endPoint' => $this->lastUrl], $this->lastPayload);
    }

    /**
     * Issue the call.
     *
     * Every Checkout Champ endpoint is a POST to "{section}/{method}/" with all
     * parameters, credentials included, in the query string.
     *
     * @return void
     */
    protected function sendPost(): void
    {
        $path = trim($this->section, '/') . '/' . trim($this->method, '/') . '/';
        $url  = $this->config->getBaseUrl() . '/' . str_replace(' ', '', $path);

        // Credentials are appended last so a caller-supplied key cannot shadow them.
        $query = array_merge($this->fields, [
            'loginId'  => $this->config->getLoginId(),
            'password' => $this->config->getPassword(),
        ]);

        $this->lastUrl     = $url;
        $this->lastPayload = $this->fields;

        $this->request($url . '?' . http_build_query($query));
    }

    /**
     * Decode a JSON string.
     *
     * @param string $string
     * @param bool   $arrFlag Decode objects into associative arrays.
     *
     * @return mixed
     *
     * @throws CheckoutChampException When the string is not valid JSON.
     */
    protected function isJson(string $string, bool $arrFlag = false)
    {
        /** @var mixed $decoded */
        $decoded = json_decode($string, $arrFlag);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CheckoutChampException(Config::get('Messages.jsonFormatError'));
        }

        return $decoded;
    }

    /**
     * Send the assembled request and record the outcome.
     *
     * @param string $url
     *
     * @return void
     */
    private function request(string $url): void
    {
        $request = new Request(
            'POST',
            $url,
            [],
            null,
            $this->proxyUrl,
            $this->proxyCredentials
        );

        $response = $this->http->send($request);
        $this->logger->log($request, $response);

        $this->transportInfo = $response->getInfo();

        if ($response->hasTransportError()) {
            $this->checkError = true;
            $this->rawBody    = $response->getTransportError();
        } else {
            $this->checkError = false;
            $this->rawBody    = $response->getBody();
        }

        $this->reset();
    }

    /**
     * Clear per-request state so a reused instance cannot leak the previous
     * call's parameters into the next one.
     *
     * @return void
     */
    private function reset(): void
    {
        $this->section          = '';
        $this->method           = '';
        $this->fields           = [];
        $this->proxyUrl         = null;
        $this->proxyCredentials = null;
    }
}
