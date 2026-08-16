<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

/**
 * Lander page and PayPal confirmation endpoints.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Landers extends CheckoutChamp
{
    /**
     * Record a lander page click.
     *
     * POST /landers/clicks/import/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function importClick(array $params = []): void
    {
        $this->section = 'landers/clicks';
        $this->method  = 'import';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Confirm a PayPal transaction.
     *
     * POST /transactions/confirmPaypal/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function confirmPaypal(array $params = []): void
    {
        $this->section = 'transactions';
        $this->method  = 'confirmPaypal';
        $this->fields  = $params;
        $this->sendPost();
    }
}
