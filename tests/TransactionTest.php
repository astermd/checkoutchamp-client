<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\Tests\Support\ClientTestCase;
use AsterMD\CheckoutChampClient\Transaction;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Transaction::class)]
final class TransactionTest extends ClientTestCase
{
    public function testTransactionsQuery(): void
    {
        $this->client()->transactionsQuery(['orderId' => '123']);

        $this->assertRequest('/transactions/query/', ['orderId' => '123']);
    }

    public function testTransactionsQueryWithoutParameters(): void
    {
        $this->client()->transactionsQuery();

        $this->assertRequest('/transactions/query/');
    }
}
