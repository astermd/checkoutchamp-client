<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests\Logging;

use AsterMD\CheckoutChampClient\Logging\Redactor;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\CheckoutChampClient\Logging\Redactor::class)]
final class RedactorTest extends TestCase
{
    /**
     * @var Redactor
     */
    private $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new Redactor();
    }

    public function testApiKeyHeadersAreMasked(): void
    {
        $redacted = $this->redactor->redactHeaders([
            'Content-Type: application/json',
            'X-Api-Key: live-key-placeholder',
        ]);

        self::assertSame(
            ['Content-Type: application/json', 'X-Api-Key: [REDACTED]'],
            $redacted
        );
    }

    public function testAuthorizationKeepsItsScheme(): void
    {
        $redacted = $this->redactor->redactHeaders(['Authorization: Bearer token-placeholder']);

        self::assertSame(['Authorization: Bearer [REDACTED]'], $redacted);
    }

    public function testAuthorizationWithoutASchemeIsFullyMasked(): void
    {
        $redacted = $this->redactor->redactHeaders(['Authorization: token-placeholder']);

        self::assertSame(['Authorization: [REDACTED]'], $redacted);
    }

    public function testHeaderNameMatchingIgnoresCaseAndSeparators(): void
    {
        $redacted = $this->redactor->redactHeaders(['x_api_key: live-key-placeholder']);

        self::assertSame(['x_api_key: [REDACTED]'], $redacted);
    }

    public function testALineWithoutAColonIsLeftAlone(): void
    {
        self::assertSame(['not-a-header'], $this->redactor->redactHeaders(['not-a-header']));
    }

    public function testCredentialsInABodyAreMasked(): void
    {
        $body = '{"apiKey":"live-key-placeholder","email":"buyer@example.test"}';

        self::assertSame(
            '{"apiKey":"[REDACTED]","email":"buyer@example.test"}',
            $this->redactor->redactBody($body)
        );
    }

    public function testNestedSensitiveKeysAreMasked(): void
    {
        $body = '{"customer":{"ssn":"000-00-0000","first_name":"Ada"}}';

        self::assertSame(
            '{"customer":{"ssn":"[REDACTED]","first_name":"Ada"}}',
            $this->redactor->redactBody($body)
        );
    }

    public function testGenericChildKeysAreMaskedUnderAPaymentParent(): void
    {
        $body = '{"card":{"number":"4111111111111111","cvv":"123","month":"12","brand":"visa"}}';

        self::assertSame(
            '{"card":{"number":"[REDACTED]","cvv":"[REDACTED]","month":"[REDACTED]","brand":"visa"}}',
            $this->redactor->redactBody($body)
        );
    }

    public function testAGenericNumberOutsideAPaymentParentIsKept(): void
    {
        $body = '{"order":{"number":"A-1234"}}';

        self::assertSame($body, $this->redactor->redactBody($body));
    }

    public function testBankAndTokenFieldsAreMasked(): void
    {
        $body = '{"routing_number":"110000000","account_number":"1234","refresh_token":"rt-placeholder"}';

        self::assertSame(
            '{"routing_number":"[REDACTED]","account_number":"[REDACTED]","refresh_token":"[REDACTED]"}',
            $this->redactor->redactBody($body)
        );
    }

    public function testACardNumberIsCaughtUnderAnUnexpectedKey(): void
    {
        $body = '{"reference":"4111111111111111"}';

        self::assertSame('{"reference":"[REDACTED]"}', $this->redactor->redactBody($body));
    }

    public function testALongNumberThatFailsLuhnIsKept(): void
    {
        $body = '{"reference":"1234567890123456"}';

        self::assertSame($body, $this->redactor->redactBody($body));
    }

    public function testValuesInsideListsAreInspected(): void
    {
        $body = '{"cards":["4111111111111111","plain"]}';

        self::assertSame('{"cards":["[REDACTED]","plain"]}', $this->redactor->redactBody($body));
    }

    public function testANonJsonBodyIsReplacedWholesale(): void
    {
        self::assertSame(Redactor::UNPARSEABLE, $this->redactor->redactBody('key=live-key-placeholder'));
    }

    public function testEmptyAndNullBodiesPassThrough(): void
    {
        self::assertNull($this->redactor->redactBody(null));
        self::assertSame('', $this->redactor->redactBody(''));
        self::assertSame('   ', $this->redactor->redactBody('   '));
    }

    public function testSlashesAreNotEscapedInTheRedactedOutput(): void
    {
        $body = '{"return_url":"https://example.test/done"}';

        self::assertSame($body, $this->redactor->redactBody($body));
    }

    public function testAccountCredentialsInABodyAreMasked(): void
    {
        $body = '{"loginId":"acct-placeholder","password":"pw-placeholder","orderId":"123"}';

        self::assertSame(
            '{"loginId":"[REDACTED]","password":"[REDACTED]","orderId":"123"}',
            $this->redactor->redactBody($body)
        );
    }

    public function testUrlCredentialsAreMasked(): void
    {
        $url = 'https://api.checkoutchamp.com/order/query/?orderId=123&loginId=acct&password=pw';

        self::assertSame(
            'https://api.checkoutchamp.com/order/query/?orderId=123&loginId=[REDACTED]&password=[REDACTED]',
            $this->redactor->redactUrl($url)
        );
    }

    public function testTheUrlPathAndOrdinaryParametersSurviveRedaction(): void
    {
        $url = 'https://api.checkoutchamp.com/order/query/?campaignId=7&orderId=123&password=pw';

        $redacted = $this->redactor->redactUrl($url);

        self::assertStringStartsWith('https://api.checkoutchamp.com/order/query/?', $redacted);
        self::assertStringContainsString('campaignId=7', $redacted);
        self::assertStringContainsString('orderId=123', $redacted);
    }

    public function testCardDataInAUrlIsMasked(): void
    {
        $url = 'https://api.checkoutchamp.com/order/import/?cardNumber=4111111111111111&cvv=123&expMonth=12';

        self::assertSame(
            'https://api.checkoutchamp.com/order/import/?cardNumber=[REDACTED]&cvv=[REDACTED]&expMonth=[REDACTED]',
            $this->redactor->redactUrl($url)
        );
    }

    public function testACardNumberInAUrlIsCaughtUnderAnUnexpectedParameterName(): void
    {
        $url = 'https://api.checkoutchamp.com/order/import/?reference=4111111111111111';

        self::assertSame(
            'https://api.checkoutchamp.com/order/import/?reference=[REDACTED]',
            $this->redactor->redactUrl($url)
        );
    }

    public function testAUrlWithoutAQueryStringIsUnchanged(): void
    {
        $url = 'https://api.checkoutchamp.com/order/query/';

        self::assertSame($url, $this->redactor->redactUrl($url));
    }

    public function testAnEmptyQueryStringIsUnchanged(): void
    {
        $url = 'https://api.checkoutchamp.com/order/query/?';

        self::assertSame($url, $this->redactor->redactUrl($url));
    }

    public function testAValuelessQueryParameterIsLeftAlone(): void
    {
        $url = 'https://api.checkoutchamp.com/order/query/?flag&orderId=123';

        self::assertSame($url, $this->redactor->redactUrl($url));
    }

    public function testUrlParameterNamesAreMatchedCaseInsensitively(): void
    {
        $url = 'https://api.checkoutchamp.com/order/query/?LoginID=acct&PassWord=pw';

        self::assertSame(
            'https://api.checkoutchamp.com/order/query/?LoginID=[REDACTED]&PassWord=[REDACTED]',
            $this->redactor->redactUrl($url)
        );
    }

    public function testRedactionDoesNotMutateItsInputs(): void
    {
        $headers = ['X-Api-Key: live-key-placeholder'];
        $body    = '{"apiKey":"live-key-placeholder"}';
        $url     = 'https://api.checkoutchamp.com/order/query/?password=pw-placeholder';

        $this->redactor->redactHeaders($headers);
        $this->redactor->redactBody($body);
        $this->redactor->redactUrl($url);

        self::assertSame(['X-Api-Key: live-key-placeholder'], $headers);
        self::assertSame('{"apiKey":"live-key-placeholder"}', $body);
        self::assertSame('https://api.checkoutchamp.com/order/query/?password=pw-placeholder', $url);
    }
}
