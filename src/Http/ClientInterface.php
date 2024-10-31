<?php

declare(strict_types=1);

namespace GSMBinancePay\WC\Http;

use GSMBinancePay\WC\Exception\ConnectException;
use GSMBinancePay\WC\Exception\RequestException;

interface ClientInterface
{
    /**
     * Sends the HTTP request to API server.
     *
     * @param string $method
     * @param string $url
     * @param array  $headers
     * @param string $body
     *
     * @throws ConnectException
     * @throws RequestException
     *
     * @return ResponseInterface
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): ResponseInterface;
}
