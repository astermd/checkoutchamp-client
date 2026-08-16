<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Http;

/**
 * Immutable description of an outbound HTTP request.
 *
 * Passing this to the transport gives tests a single object to assert URL,
 * method, headers and body against, and guarantees the debug logger can never
 * mutate what is actually sent.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Request
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $url;

    /**
     * @var list<string> Raw header lines in "Name: value" form.
     */
    private $headers;

    /**
     * @var string|null
     */
    private $body;

    /**
     * @var string|null
     */
    private $proxyUrl;

    /**
     * @var string|null
     */
    private $proxyCredentials;

    /**
     * @param string                   $method
     * @param string                   $url
     * @param array<int|string, string> $headers
     * @param string|null              $body
     * @param string|null              $proxyUrl
     * @param string|null              $proxyCredentials
     */
    public function __construct(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?string $proxyUrl = null,
        ?string $proxyCredentials = null
    ) {
        $this->method           = strtoupper($method);
        $this->url              = $url;
        $this->headers          = array_values($headers);
        $this->body             = $body;
        $this->proxyUrl         = $proxyUrl;
        $this->proxyCredentials = $proxyCredentials;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return list<string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * @return string|null
     */
    public function getProxyUrl(): ?string
    {
        return $this->proxyUrl;
    }

    /**
     * @return string|null
     */
    public function getProxyCredentials(): ?string
    {
        return $this->proxyCredentials;
    }

    /**
     * @return bool
     */
    public function hasProxy(): bool
    {
        return $this->proxyUrl !== null && $this->proxyUrl !== '';
    }
}
