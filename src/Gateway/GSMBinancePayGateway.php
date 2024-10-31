<?php

declare( strict_types=1 );

namespace GSMBinancePay\WC\Gateway;

use GSMBinancePay\WC\Admin\Notice;
use GSMBinancePay\WC\Client\BinanceCertificate;
use GSMBinancePay\WC\Client\BinanceOrder;
use GSMBinancePay\WC\Helper\BinanceApiHelper;
use GSMBinancePay\WC\Helper\Logger;
use GSMBinancePay\WC\Helper\PreciseNumber;

class GSMBinancePayGateway extends \WC_Payment_Gateway {

	protected BinanceApiHelper $apiHelper;

	public function __construct() {
		// General gateway setup.
		$this->id                 = 'gsmbinancepay';
		//$this->icon              = $this->getIcon();
		$this->has_fields        = false;
		$this->order_button_text = __( 'Place order', 'gsmbinancepay-for-woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user facing set variables.
		$this->title        = $this->get_option('title', 'SAGI Crypto integration for Binance Pay');
		$this->description  = $this->get_option('description', 'You will be redirected to BinancePay to complete your purchase.');

		// Admin facing title and description.
	//	$this->method_title       = 'GSMBinancePay';
		$this->method_title       = 'SAGI Crypto integration for Binance Pay';
		$this->method_description = __('BinancePay gateway supporting all available cryptocurrencies.', 'gsmbinancepay-for-woocommerce');

		// Debugging & informational settings.
		$this->debug_php_version    = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
		$this->debug_plugin_version = GSMBINANCEPAY_VERSION;

		$this->apiHelper = new BinanceApiHelper();

		// Actions.
		add_action('woocommerce_api_gsmbinancepay', [$this, 'processWebhook']);
		add_action('woocommerce_update_options_payment_gateways_' . $this->getId(), [$this, 'process_admin_options']);
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'       => __( 'Enabled/Disabled', 'gsmbinancepay-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable this payment gateway.', 'gsmbinancepay-for-woocommerce' ),
				'default'     => 'no',
				'value'       => 'yes',
				'desc_tip'    => false,
			],
			'url'       => [
				'title'       => __( 'BinancePay API URL', 'gsmbinancepay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Sandbox or production API endpoint url.', 'gsmbinancepay-for-woocommerce' ),
				'default'     => 'https://bpay.binanceapi.com',
				'desc_tip'    => true,
			],
			'apikey'       => [
				'title'       => __( 'API Key (Merchant)', 'gsmbinancepay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Provide the merchant API key from your BinancePay merchant account.', 'gsmbinancepay-for-woocommerce' ),
				'default'     => null,
				'desc_tip'    => true,
			],
			'apisecret'       => [
				'title'       => __( 'API Secret', 'gsmbinancepay-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Provide the merchant API secret from your BinancePay merchant account.', 'gsmbinancepay-for-woocommerce' ),
				'default'     => null,
				'desc_tip'    => true,
			],
			'title'       => [
				'title'       => __( 'Customer Text', 'gsmbinancepay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Controls the name of this payment method as displayed to the customer during checkout.', 'gsmbinancepay-for-woocommerce' ),
				'default'     => $this->title,
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Customer Message', 'gsmbinancepay-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Message to explain how the customer will be paying for the purchase.', 'gsmbinancepay-for-woocommerce' ),
				'default'     => $this->description,
				'desc_tip'    => true,
			],
			'debug' => [
				'title'       => __( 'Enable debug log', 'gsmbinancepay-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'Stored in WooCommerce -> Status -> Logs: <a href="%s" class="button">View Logs</a>', 'gsmbinancepay-for-woocommerce' ), Logger::getLogFileUrl()),
				'default'     => 'no',
			],
		];
	}

	public function process_admin_options() {
		parent::process_admin_options();

		// Fetch Binance certificate public key for validating webhook callbacks.
		$client = new BinanceCertificate(
			$this->get_option('url', null),
			$this->get_option('apikey', null),
			$this->get_option('apisecret', null)
		);

		try {
			$result = $client->getCertificate();

			Logger::debug('Certificate result: ' . print_r($result, true));

			$certSerial = $result['data'][0]['certSerial'] ?? null;
			$certPublic = $result['data'][0]['certPublic'] ?? null;

			if (!isset($certSerial, $certPublic)) {
				Logger::debug('No certificate returned from Binance.');
				Notice::addNotice('error', 'No certificate (for validating webhooks) returned from Binance.');
			}

			$this->update_option('certserial', $certSerial);
			$this->update_option('certpublic', $certPublic);

			Notice::addNotice('success', 'Successfully fetched certificate (for validating webhooks) from Binance.');

		} catch (\Throwable $e) {
			Logger::debug('Error fetching certificate from Binance. Error: ' . $e->getMessage());
			Notice::addNotice('error', 'Error fetching certificate from Binance.');
		}

	}

	/**
	 * @inheritDoc
	 */
	public function process_payment( $orderId ) {
		/*
		if ( ! $this->apiHelper->configured ) {
			Logger::debug( 'BinancePay Server API connection not configured, aborting. Please go to BinancePay Server settings and set it up.' );
			// todo: show error notice/make sure it fails
			throw new \Exception( __( "Can't process order. Please contact us if the problem persists.", 'gsmbinancepay-for-woocommerce' ) );
		}
		*/

		// Load the order and check it.
		$order = new \WC_Order( $orderId );
		if ( $order->get_id() === 0 ) {
			$message = 'Could not load order id ' . $orderId . ', aborting.';
			Logger::debug( $message, true );
			throw new \Exception( $message );
		}

		// Check for existing invoice and redirect instead.
		/*
		if ( $this->validInvoiceExists( $orderId ) ) {
			$existingInvoiceId = get_post_meta( $orderId, 'BinancePay_id', true );
			Logger::debug( 'Found existing BinancePay Server invoice and redirecting to it. Invoice id: ' . $existingInvoiceId );

			return [
				'result'   => 'success',
				'redirect' => $this->apiHelper->getInvoiceRedirectUrl( $existingInvoiceId ),
			];
		}
		*/

		// Create an invoice.
		Logger::debug( 'Creating Order on BinancePay.' );
		if ( $binanceOrder = $this->createBinanceOrder( $order ) ) {

			// Todo: update order status and BinancePay meta data.

			Logger::debug( 'Binance order creation successful, redirecting user.' );

			Logger::debug($binanceOrder, true);

			return [
				'result'   => 'success',
				'redirect' => $binanceOrder['data']['checkoutUrl'],
				'orderId' => $order->get_id(),
			];
		}
	}

	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get custom gateway icon, if any.
	 */
	public function getIcon(): string {
		return GSMBINANCEPAY_PLUGIN_URL . 'assets/images/binancepay-logo.svg';
	}

	/**
	 * Process webhooks from BinancePay.
	 */
	public function processWebhook() {

		Logger::debug('Webhook endpoint called.');

		if ($rawPostData = file_get_contents("php://input")) {

			Logger::debug('Webhook data received: ' . print_r($rawPostData, true));

			// Validate webhook request.
			$headers = getallheaders();
			Logger::debug('Webhook headers received: ' . print_r($headers, true));

			if (!$this->validWebhookRequest($headers, $rawPostData)) {
				Logger::debug('Failed to validate signature of webhook request.');
				wp_die('Webhook request validation failed.');
			}

			try {
				$postData = json_decode($rawPostData, false, 512, JSON_THROW_ON_ERROR);

				if (!isset($postData->bizId)) {
					Logger::debug('No BinancePay bizId (prepayId) provided, aborting.');
					wp_die('No BinancePay bizId (prepayId) provided, aborting.');
				}

				// Load the order by metadata field BinancePay_id
				$orders = wc_get_orders([
					'meta_key' => 'BinancePay_prepayId',
					'meta_value' => wc_clean(wp_unslash($postData->bizId))
				]);

				// Abort if no orders found.
				if (count($orders) === 0) {
					Logger::debug('Could not load order by BinancePay bizId: ' . $postData->bizId);
					wp_die('No order found for this bizId.', '', ['response' => 404]);
				}

				// TODO: Handle multiple matching orders.
				if (count($orders) > 1) {
					Logger::debug('Found multiple orders for bizId: ' . $postData->bizId);
					Logger::debug(print_r($orders, true));
					wp_die('Multiple orders found for this invoiceId, aborting.');
				}

				$this->processOrderStatus($orders[0], $postData);

			} catch (\Throwable $e) {
				Logger::debug('Error decoding webhook payload: ' . $e->getMessage());
				Logger::debug($rawPostData);
			}
		}
	}

	protected function processOrderStatus(\WC_Order $order, \stdClass $webhookData) {

		Logger::debug('Entering processOrderStatus()');

		if ($webhookData->bizType !== 'PAY') {
			Logger::debug('Webhook event received but ignored, wrong type: ' . $webhookData->bizType);
			return;
		}

		switch ($webhookData->bizStatus) {
			case 'PAY_CLOSED':
				$order->update_status('failed');
				$order->add_order_note(__('Payment failed/rejected.', 'gsmbinancepay-for-woocommerce'));
				Logger::debug('Payment failed. Status: PAY_CLOSED');
				break;
			case 'PAY_SUCCESS':
				// Update some Binance order meta data.
				$paymentData = json_decode($webhookData->data, true);
				$order->update_meta_data('BinancePay_trx_totalFee', $paymentData['totalFee']);
				$order->update_meta_data('BinancePay_trx_commission', $paymentData['commission']);
				$order->update_meta_data('BinancePay_trx_openUserId', $paymentData['openUserId']);
				$order->update_meta_data('BinancePay_trx_transactionId', $paymentData['transactionId']);
				$order->update_meta_data('BinancePay_trx_transactTime', $paymentData['transactTime']);
				$order->update_meta_data('BinancePay_trx_paymentInfo', json_encode($paymentData['paymentInfo']));
				$order->save();

				$order->payment_complete();
				$order->add_order_note(__('Payment successful. TransactionId: ' . $paymentData['transactionId'], 'gsmbinancepay-for-woocommerce'));

				Logger::debug('Payment successful. Status: PAY_SUCCESS');

				break;
		}
	}

	/**
	 * Checks if the order has already a BinancePay invoice set and checks if it is still
	 * valid to avoid creating multiple invoices for the same order on BinancePay Server end.
	 *
	 * @param int $orderId
	 *
	 * @return mixed Returns false if no valid invoice found or the invoice id.
	 */
	protected function validInvoiceExists( int $orderId ): bool {
		// Check order metadata for BinancePay_id.
		if ( $invoiceId = get_post_meta( $orderId, 'BinancePay_id', true ) ) {
			// Validate the order status on BinancePay server.
			$client = new Invoice( $this->apiHelper->url, $this->apiHelper->apiKey );
			try {
				Logger::debug( 'Trying to fetch existing invoice from BinancePay Server.' );
				$invoice       = $client->getInvoice( $this->apiHelper->storeId, $invoiceId );
				$invalidStates = [ 'Expired', 'Invalid' ];
				if ( in_array( $invoice->getData()['status'], $invalidStates ) ) {
					return false;
				} else {
					return true;
				}
			} catch ( \Throwable $e ) {
				Logger::debug( $e->getMessage() );
			}
		}

		return false;
	}

	/**
	 * Create an invoice on BinancePay Server.
	 */
	public function createBinanceOrder( \WC_Order $order ): ?array {
		// In case some plugins customizing the order number we need to pass that along, defaults to internal ID.
		$orderNumber = $order->get_order_number();
		Logger::debug( 'Got order number: ' . $orderNumber . ' and order ID: ' . $order->get_id() );

		$currency = $order->get_currency();
		$amount = (float) $order->get_total(); // unlike method signature suggests, it returns string.
		$stableCoin = 'USDT'; // Todo get from options.
		$stableCoinRate = BinanceApiHelper::getExchangeRate($stableCoin, $currency);
		$stableCoinAmount = $amount / $stableCoinRate;

		// Create the invoice on BinancePay Server.
		$client = new BinanceOrder(
			$this->get_option('url', null),
			$this->get_option('apikey', null),
			$this->get_option('apisecret', null)
		);

		try {
			$binancePayOrder = $client->createOrder(
				$order->get_checkout_order_received_url(),
				$order->get_cancel_order_url(),
				PreciseNumber::parseFloat($stableCoinAmount),
				$stableCoin,
				$orderNumber
			);

			Logger::debug('BincancePayOrder: ' . print_r($binancePayOrder, true));

			$order->update_meta_data('BinancePay_prepayId', $binancePayOrder['data']['prepayId'] );
			$order->update_meta_data('BinancePay_checkoutUrl', $binancePayOrder['data']['checkoutUrl'] );
			$order->update_meta_data('BinancePay_stableCoin', $stableCoin);
			$order->update_meta_data('BinancePay_stableCoinRate', $stableCoinRate);
			$order->update_meta_data('BinancePay_stableCoinCalculatedAmount', $stableCoinAmount);
			$order->save();

			return $binancePayOrder;

		} catch ( \Throwable $e ) {
			Logger::debug( $e->getMessage(), true );
			Logger::debug ($e->getTraceAsString(), true);
			// todo handle order exists as below:
			//			[status] => FAIL
			//			[code] => 400201
			//    [errorMessage] => merchantTradeNo is invalid or duplicated
		}

		return null;
	}

	/**
	 * Check webhook to be a valid request.
	 *
	 * @see https://developers.binance.com/docs/binance-pay/webhook-common
	 */
	public function validWebhookRequest(array $headers, string $requestData): bool {

		Logger::debug('Entering validWebhookRequest().');

		// Note: getallheaders() CamelCases all headers for PHP-FPM/Nginx but for others maybe not, so "BinancePay-Signature"
		// may becomes "Binancepay-Signature".
		$allowedHeaders = [
			'binancepay-certificate-sn',
			'binancepay-nonce',
			'binancepay-timestamp',
			'binancepay-signature'
		];

		$neededHeaders = [];
		foreach ($headers as $key => $value) {
			if (in_array(strtolower($key), $allowedHeaders)) {
				$neededHeaders[strtolower($key)] = $value;
			}
		}

		// Todo check if all keys present.
		if (count($neededHeaders) !== count($allowedHeaders)) {
			Logger::debug('Required headers missing, headers: ' . print_r($neededHeaders, true));
			return false;
		}

		// Compare stored certificate serial with the one sent in the header.
		$certSerial = $this->get_option('certserial');
		if ($certSerial !== $neededHeaders['binancepay-certificate-sn']) {
			Logger::debug('Error, the certificate serial in the header does not match the locally stored one:');
			Logger::debug('local serial: ' . $certSerial);
			Logger::debug('binancepay-certificate-sn: ' . $neededHeaders['binancepay-certificate-sn']);
			Logger::debug('Likely Binance issued a new certificate in the meantime. Please go to payment gateway config of BinancePay and hit save agian, it will download the certificate.');
			return false;
		}

		$payload = $neededHeaders['binancepay-timestamp'] . "\n" . $neededHeaders['binancepay-nonce'] . "\n" . $requestData . "\n";
		$decodedSignature = base64_decode($neededHeaders['binancepay-signature']);
		$publicKey = $this->get_option('certpublic');

		$result = openssl_verify($payload, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256 );

		if ($result === 1) {
			Logger::debug('This is a valid webhook request.');
			return true;
		}

		Logger::debug('Error validating webhook request.');
		return false;
	}
}
