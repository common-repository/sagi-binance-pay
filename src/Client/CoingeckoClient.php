<?php

declare(strict_types=1);

namespace GSMBinancePay\WC\Client;

use GSMBinancePay\WC\Exception\BadRequestException;
use GSMBinancePay\WC\Exception\ForbiddenException;
use GSMBinancePay\WC\Exception\RequestException;
use GSMBinancePay\WC\Http\ClientInterface;
use GSMBinancePay\WC\Http\CurlClient;
use GSMBinancePay\WC\Http\Response;

class CoingeckoClient
{
	private string $baseUrl = 'https://api.coingecko.com';

	private string $apiPath = '/api/v3/';

	private ClientInterface $httpClient;

	public function __construct(ClientInterface $client = null)
	{
		// Use the $client parameter to use a custom cURL client, for example if you need to disable CURLOPT_SSL_VERIFYHOST and CURLOPT_SSL_VERIFYPEER
		if ($client === null) {
			$client = new CurlClient();
		}
		$this->httpClient = $client;
	}

	protected function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	protected function getApiUrl(): string
	{
		return $this->baseUrl . $this->apiPath;
	}

	protected function getHttpClient(): ClientInterface
	{
		return $this->httpClient;
	}

	protected function getRequestHeaders(): array
	{
		return [
			'Content-Type' => 'application/json',
		];
	}

	protected function getExceptionByStatusCode(
		string $method,
		string $url,
		Response $response
	): RequestException {
		$exceptions = [
			ForbiddenException::STATUS => ForbiddenException::class,
			BadRequestException::STATUS => BadRequestException::class,
		];

		$class = $exceptions[$response->getStatus()] ?? RequestException::class;
		$e = new $class($method, $url, $response);
		return $e;
	}

	/**
	 * Fetch Exchange rates from api.coingecko.com.
	 *
	 * @param array $sourceCoins Array of Coingecko Ids, e.g. ["theter","busd"] ...
	 * @param array $exchangeRateCoins Array of Ids to request exchange rates from incl. Fiat e.g. EUR, USD, etc ...
	 *
	 * @return array    Returns a keyed array by sourceCoins. E.g. ["theter" => ["eur" => 0.95382, "usd" => 0.9488 ]]
	 * @throws \Exception
	 */
	public function getRates(array $sourceCoins, array $exchangeRateCoins ): array {

		$url = $this->getApiUrl() . 'simple/price?ids=' . urlencode(implode(',', $sourceCoins));
		$url .= '&vs_currencies=' . urlencode(implode(',', $exchangeRateCoins));

		$headers = $this->getRequestHeaders();
		$method = 'GET';

		$response = $this->getHttpClient()->request($method, $url, $headers);

		if ($response->getStatus() === 200) {
			return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		} else {
			throw $this->getExceptionByStatusCode($method, $url, $response);
		}
	}
}
