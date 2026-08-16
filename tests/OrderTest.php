<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\Order;
use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Order::class)]
final class OrderTest extends ClientTestCase
{
    public function testOrderQuery(): void
    {
        $this->client()->orderQuery(['orderId' => '123']);

        $this->assertRequest('/order/query/', ['orderId' => '123']);
    }

    public function testOrderQueryWithoutParameters(): void
    {
        $this->client()->orderQuery();

        $this->assertRequest('/order/query/');
    }

    public function testImportLeads(): void
    {
        $this->client()->importLeads([
            'campaignId'  => '7',
            'emailAddress' => 'buyer@example.test',
        ]);

        $this->assertRequest('/leads/import/', [
            'campaignId'   => '7',
            'emailAddress' => 'buyer@example.test',
        ]);
    }

    public function testUpdateOrder(): void
    {
        $this->client()->updateOrder(['orderId' => '123', 'firstName' => 'Ada']);

        $this->assertRequest('/order/update/', ['orderId' => '123', 'firstName' => 'Ada']);
    }

    public function testPreauth(): void
    {
        $this->client()->preauth(['sessionId' => 'sess_1']);

        $this->assertRequest('/order/preauth/', ['sessionId' => 'sess_1']);
    }

    public function testImportOrder(): void
    {
        $this->client()->importOrder(['sessionId' => 'sess_1', 'product1_id' => '9']);

        $this->assertRequest('/order/import/', ['sessionId' => 'sess_1', 'product1_id' => '9']);
    }

    public function testImportUpsale(): void
    {
        $this->client()->importUpsale(['orderId' => '123', 'product1_id' => '9']);

        $this->assertRequest('/upsale/import/', ['orderId' => '123', 'product1_id' => '9']);
    }

    public function testConfirm(): void
    {
        $this->client()->confirm(['orderId' => '123']);

        $this->assertRequest('/order/confirm/', ['orderId' => '123']);
    }

    public function testQa(): void
    {
        $this->client()->qa(['orderId' => '123', 'qaStatus' => 'APPROVED']);

        $this->assertRequest('/order/qa/', ['orderId' => '123', 'qaStatus' => 'APPROVED']);
    }

    public function testConsecutiveCallsDoNotLeakTheEarlierParameters(): void
    {
        $client = $this->client();

        $client->importOrder(['sessionId' => 'sess_1', 'product1_id' => '9']);
        $client->orderQuery(['orderId' => '123']);

        $this->assertRequest('/order/query/', ['orderId' => '123']);
    }

    public function testCallerParametersCannotShadowTheCredentials(): void
    {
        $this->client()->orderQuery([
            'orderId'  => '123',
            'loginId'  => 'attacker-supplied',
            'password' => 'attacker-supplied',
        ]);

        // Credentials are appended last, so the configured values win.
        $this->assertRequest('/order/query/', ['orderId' => '123']);
    }
}
