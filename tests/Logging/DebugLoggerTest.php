<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests\Logging;

use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use AsterMD\CheckoutChampClient\Http\Request;
use AsterMD\CheckoutChampClient\Http\Response;
use AsterMD\CheckoutChampClient\Logging\DebugLogger;
use AsterMD\CheckoutChampClient\Logging\FileSink;
use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DebugLogger::class)]
final class DebugLoggerTest extends ClientTestCase
{
    /**
     * @var string[]
     */
    private $entries = [];

    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();

        FileSink::resetPruneState();
        $this->entries   = [];
        $this->directory = sys_get_temp_dir() . '/checkoutchamp-logs-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $entries = glob($this->directory . '/*');
        foreach (($entries === false ? [] : $entries) as $entry) {
            unlink($entry);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        FileSink::resetPruneState();
        parent::tearDown();
    }

    public function testLoggingIsOffByDefault(): void
    {
        $this->client()->orderQuery(['orderId' => '123']);

        self::assertSame([], $this->entries);
    }

    public function testADisabledLoggerNeverCallsItsSink(): void
    {
        $logger = new DebugLogger(false, true, $this->sink());
        $logger->log(new Request('POST', 'https://api.checkoutchamp.com/order/query/'), new Response(200, '{}'));

        self::assertFalse($logger->isEnabled());
        self::assertSame([], $this->entries);
    }

    public function testEnablingDebugWithoutADestinationIsRejected(): void
    {
        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('debugFile');

        $this->client(['debug' => true]);
    }

    public function testANonCallableSinkIsRejected(): void
    {
        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('callable');

        $this->client(['debug' => true, 'debugSink' => 'not a callable at all']);
    }

    public function testAnUnknownTimezoneIsRejected(): void
    {
        $this->expectException(CheckoutChampException::class);

        new DebugLogger(true, true, $this->sink(), 'Nowhere/Nothing');
    }

    public function testCredentialsInTheUrlAreMaskedByDefault(): void
    {
        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->orderQuery(['orderId' => '123']);

        $entry = $this->entries[0];

        self::assertStringNotContainsString(self::PASSWORD, $entry);
        self::assertStringNotContainsString(self::LOGIN_ID, $entry);
        self::assertStringContainsString('loginId=[REDACTED]', $entry);
        self::assertStringContainsString('password=[REDACTED]', $entry);
    }

    public function testTheEndpointAndOrdinaryParametersStayReadable(): void
    {
        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->orderQuery(['orderId' => '123', 'campaignId' => '7']);

        $entry = $this->entries[0];

        self::assertStringContainsString('https://api.checkoutchamp.com/order/query/?', $entry);
        self::assertStringContainsString('orderId=123', $entry);
        self::assertStringContainsString('campaignId=7', $entry);
        self::assertStringContainsString('curl --location --request POST', $entry);
    }

    public function testCardDataInTheQueryStringIsMasked(): void
    {
        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->importOrder([
            'sessionId'  => 'sess_1',
            'cardNumber' => '4111111111111111',
            'cardSecurityCode' => '123',
            'expMonth'   => '12',
        ]);

        $entry = $this->entries[0];

        self::assertStringNotContainsString('4111111111111111', $entry);
        self::assertStringContainsString('cardNumber=[REDACTED]', $entry);
        self::assertStringContainsString('expMonth=[REDACTED]', $entry);
        self::assertStringContainsString('sessionId=sess_1', $entry);
    }

    public function testResponseTokensAreRedacted(): void
    {
        $this->http->queue(new Response(200, '{"access_token":"at-placeholder","result":"SUCCESS"}'));

        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->orderQuery();

        self::assertStringContainsString('"access_token":"[REDACTED]"', $this->entries[0]);
        self::assertStringNotContainsString('at-placeholder', $this->entries[0]);
    }

    public function testOptingOutOfRedactionLogsTheUrlVerbatim(): void
    {
        $this->client([
            'debug'       => true,
            'debugRedact' => false,
            'debugSink'   => $this->sink(),
        ])->orderQuery(['orderId' => '123']);

        self::assertStringContainsString('password=' . rawurlencode(self::PASSWORD), $this->entries[0]);
        self::assertStringContainsString('orderId=123', $this->entries[0]);
    }

    public function testRedactionDoesNotAlterTheRealRequestOrResponse(): void
    {
        $providerBody = '{"result":"SUCCESS","access_token":"at-placeholder"}';
        $this->http->queue(new Response(200, $providerBody, ['http_code' => 200]));

        $client = $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ]);
        $client->importOrder(['sessionId' => 'sess_1', 'cardNumber' => '4111111111111111']);

        // What actually went over the wire still carries the real values.
        $sentUrl = $this->http->lastRequest()->getUrl();
        self::assertStringContainsString('cardNumber=4111111111111111', $sentUrl);
        self::assertStringContainsString('password=' . rawurlencode(self::PASSWORD), $sentUrl);

        // What the caller receives is untouched.
        self::assertSame($providerBody, $client->get()['response']);

        // Only the log entry is masked.
        self::assertStringNotContainsString('4111111111111111', $this->entries[0]);
        self::assertStringNotContainsString(self::PASSWORD, $this->entries[0]);
    }

    public function testASinkClosureReplacesTheFileSinkEntirely(): void
    {
        $this->client([
            'debug'     => true,
            'debugFile' => $this->directory . '/should-not-be-written.log',
            'debugSink' => $this->sink(),
        ])->orderQuery();

        self::assertCount(1, $this->entries);
        self::assertSame([], (array) glob($this->directory . '/*'));
    }

    public function testTheBuiltInFileSinkWritesADatedFile(): void
    {
        $this->client([
            'debug'     => true,
            'debugFile' => $this->directory . '/checkoutchamp.log',
        ])->orderQuery();

        $files = (array) glob($this->directory . '/checkoutchamp-*.log');
        self::assertCount(1, $files);

        $contents = (string) file_get_contents((string) $files[0]);
        self::assertStringContainsString('curl --location --request POST', $contents);
        self::assertStringNotContainsString(self::PASSWORD, $contents);
    }

    public function testATransportErrorIsRecorded(): void
    {
        $this->http->queue(new Response(0, '', [], 'Could not resolve host'));

        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->orderQuery();

        self::assertStringContainsString('# Transport error: Could not resolve host', $this->entries[0]);
    }

    public function testAFailingSinkNeverBreaksTheApiCall(): void
    {
        $client = $this->client([
            'debug'     => true,
            'debugSink' => static function (string $entry): void {
                throw new RuntimeException('sink exploded: ' . $entry);
            },
        ]);

        $client->orderQuery();

        self::assertSame(1, $this->http->count());
        self::assertArrayHasKey('response', $client->get());
    }

    public function testAnEntryWithoutHeadersOrBodyHasNoDanglingContinuation(): void
    {
        $logger = new DebugLogger(true, true, $this->sink());

        $entry = $logger->format(
            new Request('POST', 'https://api.checkoutchamp.com/order/query/?orderId=123'),
            new Response(200, '{"result":"SUCCESS"}', ['http_code' => 200])
        );

        self::assertStringContainsString(
            "curl --location --request POST 'https://api.checkoutchamp.com/order/query/?orderId=123'\n",
            $entry
        );
        self::assertStringNotContainsString("' \\\n\n", $entry);
        self::assertStringContainsString('# Response: HTTP 200', $entry);
    }

    public function testIsRedactingReportsTheConfiguredMode(): void
    {
        self::assertTrue((new DebugLogger(true, true, $this->sink()))->isRedacting());
        self::assertFalse((new DebugLogger(true, false, $this->sink()))->isRedacting());
    }

    public function testFromOptionsReturnsADisabledLoggerWhenDebugIsOff(): void
    {
        $logger = DebugLogger::fromOptions([]);

        self::assertFalse($logger->isEnabled());
        self::assertTrue($logger->isRedacting());
    }

    /**
     * @return callable
     */
    private function sink(): callable
    {
        return function (string $entry): void {
            $this->entries[] = $entry;
        };
    }
}
