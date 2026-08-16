<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\Customer;
use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Customer::class)]
final class CustomerTest extends ClientTestCase
{
    public function testCustomerQuery(): void
    {
        $this->client()->customerQuery(['customerId' => '55']);

        $this->assertRequest('/customer/query/', ['customerId' => '55']);
    }

    public function testAddNote(): void
    {
        $this->client()->addnote(['customerId' => '55', 'note' => 'Called the customer']);

        $this->assertRequest('/customer/addnote/', [
            'customerId' => '55',
            'note'       => 'Called the customer',
        ]);
    }
}
