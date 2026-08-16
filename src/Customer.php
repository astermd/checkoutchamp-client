<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

/**
 * Customer endpoints.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Customer extends CheckoutChamp
{
    /**
     * Return information about existing customers.
     *
     * POST /customer/query/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function customerQuery(array $params = []): void
    {
        $this->section = 'customer';
        $this->method  = 'query';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Attach a note to a customer account.
     *
     * POST /customer/addnote/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function addnote(array $params = []): void
    {
        $this->section = 'customer';
        $this->method  = 'addnote';
        $this->fields  = $params;
        $this->sendPost();
    }
}
