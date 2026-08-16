<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\Campaign;
use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Campaign::class)]
final class CampaignTest extends ClientTestCase
{
    public function testCampaignQuery(): void
    {
        $this->client()->campaignQuery(['campaignId' => '7']);

        $this->assertRequest('/campaign/query/', ['campaignId' => '7']);
    }

    public function testCampaignQueryWithoutParameters(): void
    {
        $this->client()->campaignQuery();

        $this->assertRequest('/campaign/query/');
    }
}
