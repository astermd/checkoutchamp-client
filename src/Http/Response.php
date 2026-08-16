<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Http;

/**
 * Immutable result of an HTTP exchange.
 *
 * A transport failure is carried as $transportError rather than thrown, so the
 * debug logger can record the attempt before the caller decides what to do.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Response
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string
     */
    private $body;

    /**
     * @var array<string, mixed>
     */
    private $info;

    /**
     * @var string
     */
    private $transportError;

    /**
     * @param int                  $statusCode
     * @param string               $body
     * @param array<string, mixed> $info           Transport metadata (curl_getinfo() output).
     * @param string               $transportError Empty string when the transfer succeeded.
     */
    public function __construct(int $statusCode, string $body, array $info = [], string $transportError = '')
    {
        $this->statusCode     = $statusCode;
        $this->body           = $body;
        $this->info           = $info;
        $this->transportError = $transportError;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * @return string
     */
    public function getTransportError(): string
    {
        return $this->transportError;
    }

    /**
     * @return bool
     */
    public function hasTransportError(): bool
    {
        return $this->transportError !== '';
    }
}
