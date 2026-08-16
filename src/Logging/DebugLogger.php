<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Logging;

use AsterMD\CheckoutChampClient\Config;
use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use AsterMD\CheckoutChampClient\Http\Request;
use AsterMD\CheckoutChampClient\Http\Response;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Opt-in request/response logging, redacted by default.
 *
 * Each entry is a copy-pasteable cURL command plus the response it produced.
 * The logger reads from the Request and Response value objects and writes a
 * formatted string to a sink; it never mutates either object, so enabling
 * logging cannot change what is sent or what the caller receives.
 *
 * Because this API transmits credentials and cardholder data as query string
 * parameters, redaction covers the URL as well as headers and bodies.
 *
 * Logging is off unless `debug` is true. When on, the URL, headers and bodies
 * are redacted unless `debugRedact` is explicitly set to false.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class DebugLogger
{
    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var bool
     */
    private $redact;

    /**
     * @var callable|null
     */
    private $sink;

    /**
     * @var DateTimeZone
     */
    private $timezone;

    /**
     * @var Redactor
     */
    private $redactor;

    /**
     * @param bool          $enabled
     * @param bool          $redact
     * @param callable|null $sink     Receives the formatted entry: function (string $entry): void
     * @param string        $timezone IANA timezone for entry timestamps.
     *
     * @throws CheckoutChampException When the timezone is not recognised.
     */
    public function __construct(bool $enabled, bool $redact = true, ?callable $sink = null, string $timezone = 'UTC')
    {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Throwable $e) {
            throw new CheckoutChampException(Config::get('Messages.invalidTimezone'));
        }

        $this->enabled  = $enabled;
        $this->redact   = $redact;
        $this->sink     = $sink;
        $this->redactor = new Redactor();
    }

    /**
     * Build a logger from the client option array.
     *
     * Recognised options:
     *  - debug              (bool)          Master switch. Default false.
     *  - debugRedact        (bool)          Default true. Setting false logs verbatim.
     *  - debugSink          (callable|null) Replaces the file sink entirely.
     *  - debugFile          (string|null)   Base path for the built-in dated file sink.
     *  - debugRetentionDays (int)           Days of history to keep. 0 keeps everything.
     *  - debugTimezone      (string)        IANA timezone. Default "UTC".
     *
     * @param array<string, mixed> $options
     *
     * @return self
     *
     * @throws CheckoutChampException When debug is enabled but no destination is configured.
     */
    public static function fromOptions(array $options): self
    {
        $enabled  = !empty($options['debug']);
        $redact   = !isset($options['debugRedact']) || (bool) $options['debugRedact'];
        $timezone = self::stringOption($options, 'debugTimezone', 'UTC');

        if (!$enabled) {
            return new self(false, $redact, null, $timezone);
        }

        if (isset($options['debugSink'])) {
            if (!is_callable($options['debugSink'])) {
                throw new CheckoutChampException(Config::get('Messages.invalidDebugSink'));
            }

            return new self(true, $redact, $options['debugSink'], $timezone);
        }

        $file = self::stringOption($options, 'debugFile', '');
        if (trim($file) === '') {
            throw new CheckoutChampException(Config::get('Messages.debugFileRequired'));
        }

        $retention = (isset($options['debugRetentionDays']) && is_numeric($options['debugRetentionDays']))
            ? (int) $options['debugRetentionDays']
            : FileSink::DEFAULT_RETENTION_DAYS;

        return new self(true, $redact, new FileSink($file, $retention, $timezone), $timezone);
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
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return bool
     */
    public function isRedacting(): bool
    {
        return $this->redact;
    }

    /**
     * Record one exchange.
     *
     * Never throws: a logging failure must not break the API call.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return void
     */
    public function log(Request $request, Response $response): void
    {
        if (!$this->enabled || $this->sink === null) {
            return;
        }

        try {
            $sink = $this->sink;
            $sink($this->format($request, $response));
        } catch (Throwable $e) {
            // Intentionally swallowed.
        }
    }

    /**
     * Render one entry without writing it anywhere.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return string
     */
    public function format(Request $request, Response $response): string
    {
        $headers = $this->redact
            ? $this->redactor->redactHeaders($request->getHeaders())
            : $request->getHeaders();

        $body = $this->redact
            ? $this->redactor->redactBody($request->getBody())
            : $request->getBody();

        $responseBody = $this->redact
            ? $this->redactor->redactBody($response->getBody())
            : $response->getBody();

        // This API carries credentials and cardholder data in the query string,
        // so the URL is masked too rather than logged verbatim. Scheme, host and
        // path survive, which keeps the entry readable and reproducible.
        $url = $this->redact
            ? $this->redactor->redactUrl($request->getUrl())
            : $request->getUrl();

        $parts = ["curl --location --request {$request->getMethod()} '{$url}'"];
        foreach ($headers as $headerLine) {
            $parts[] = "  --header '{$headerLine}'";
        }
        if ($body !== null) {
            $parts[] = "  --data '" . str_replace("'", "'\\''", $body) . "'";
        }

        $entry = '[' . $this->timestamp() . "]\n" . implode(" \\\n", $parts) . "\n\n";

        if ($response->hasTransportError()) {
            return $entry . '# Transport error: ' . $response->getTransportError() . "\n\n";
        }

        return $entry . '# Response: HTTP ' . $response->getStatusCode() . "\n" . (string) $responseBody . "\n\n";
    }

    /**
     * @return string
     */
    private function timestamp(): string
    {
        $moment = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
        if ($moment === false) {
            $moment = new DateTimeImmutable('now');
        }

        return $moment->setTimezone($this->timezone)->format('Y-m-d H:i:s.u T');
    }
}
