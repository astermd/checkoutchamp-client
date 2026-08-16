<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\Landers;
use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Landers::class)]
final class LandersTest extends ClientTestCase
{
    public function testImportClickUsesTheNestedLanderPath(): void
    {
        $this->client()->importClick(['campaignId' => '7', 'requestUri' => '/lp/a']);

        $this->assertRequest('/landers/clicks/import/', [
            'campaignId' => '7',
            'requestUri' => '/lp/a',
        ]);
    }

    public function testConfirmPaypalSitsUnderTransactions(): void
    {
        $this->client()->confirmPaypal(['sessionId' => 'sess_1']);

        $this->assertRequest('/transactions/confirmPaypal/', ['sessionId' => 'sess_1']);
    }
}
