<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

/**
 * Campaign endpoints.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Campaign extends CheckoutChamp
{
    /**
     * Return information about campaigns.
     *
     * POST /campaign/query/
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function campaignQuery(array $params = []): void
    {
        $this->section = 'campaign';
        $this->method  = 'query';
        $this->fields  = $params;
        $this->sendPost();
    }
}
