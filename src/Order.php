<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

/**
 * Order, lead and upsale endpoints.
 *
 * Several of these carry personal and cardholder data. Because this API sends
 * every parameter in the query string, see the README for how debug logging
 * treats them.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Order extends CheckoutChamp
{
    /**
     * Return information about existing orders.
     *
     * POST /order/query/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function orderQuery(array $params = []): void
    {
        $this->section = 'order';
        $this->method  = 'query';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Add a new lead to the CRM.
     *
     * POST /leads/import/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function importLeads(array $params = []): void
    {
        $this->section = 'leads';
        $this->method  = 'import';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Update an existing order.
     *
     * POST /order/update/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function updateOrder(array $params = []): void
    {
        $this->section = 'order';
        $this->method  = 'update';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Pre-authorise a card on a new order before billing the final charge.
     *
     * Usually called after importLeads().
     *
     * POST /order/preauth/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function preauth(array $params = []): void
    {
        $this->section = 'order';
        $this->method  = 'preauth';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Create a new order and bill the customer.
     *
     * POST /order/import/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function importOrder(array $params = []): void
    {
        $this->section = 'order';
        $this->method  = 'import';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Bill and attach an upsale to an existing order.
     *
     * POST /upsale/import/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function importUpsale(array $params = []): void
    {
        $this->section = 'upsale';
        $this->method  = 'import';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Send confirmation auto-responder emails to the customer immediately.
     *
     * POST /order/confirm/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function confirm(array $params = []): void
    {
        $this->section = 'order';
        $this->method  = 'confirm';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Approve or decline orders awaiting quality assurance.
     *
     * POST /order/qa/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function qa(array $params = []): void
    {
        $this->section = 'order';
        $this->method  = 'qa';
        $this->fields  = $params;
        $this->sendPost();
    }
}
