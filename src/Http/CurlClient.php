<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Http;

/**
 * Default cURL-backed transport.
 *
 * TLS peer and host verification are always on. The package deliberately
 * exposes no switch to disable them: this client carries API credentials and
 * order data, and an unverified connection would make both interceptable.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class CurlClient implements HttpClientInterface
{
    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $connectTimeout;

    /**
     * @param int $timeout        Transfer timeout in seconds.
     * @param int $connectTimeout Connection timeout in seconds.
     */
    public function __construct(int $timeout = 30, int $connectTimeout = 10)
    {
        $this->timeout        = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * {@inheritDoc}
     */
    public function send(Request $request): Response
    {
        $url    = $request->getUrl();
        $method = $request->getMethod();

        if ($url === '' || $method === '') {
            return new Response(0, '', [], 'A request needs both a URL and an HTTP method');
        }

        $handle = curl_init();
        if ($handle === false) {
            return new Response(0, '', [], 'Unable to initialise a cURL handle');
        }

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $request->getHeaders());
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handle, CURLOPT_MAXREDIRS, 10);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

        $body = $request->getBody();
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        if ($request->hasProxy()) {
            curl_setopt($handle, CURLOPT_PROXY, (string) $request->getProxyUrl());
            $credentials = $request->getProxyCredentials();
            if ($credentials !== null && $credentials !== '') {
                curl_setopt($handle, CURLOPT_PROXYUSERPWD, $credentials);
            }
        }

        $content = curl_exec($handle);
        $info    = curl_getinfo($handle);
        $error   = curl_error($handle);

        // No curl_close(): it has been a no-op since PHP 8.0 and is deprecated
        // from 8.5. The handle is released when it goes out of scope.

        $status = isset($info['http_code']) ? (int) $info['http_code'] : 0;

        return new Response(
            $status,
            is_string($content) ? $content : '',
            is_array($info) ? $info : [],
            $error
        );
    }
}
