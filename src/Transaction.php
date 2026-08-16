<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

/**
 * Transaction endpoints.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Transaction extends CheckoutChamp
{
    /**
     * Return information about transactions recorded in the CRM.
     *
     * POST /transactions/query/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function transactionsQuery(array $params = []): void
    {
        $this->section = 'transactions';
        $this->method  = 'query';
        $this->fields  = $params;
        $this->sendPost();
    }
}
