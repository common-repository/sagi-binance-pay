<?php

declare( strict_types=1 );

namespace GSMBinancePay\WC\Helper;

class Logger {

	public static function debug($message, $force = false): void {
		if ( self::isDebugEnabled() || $force ) {
			// Convert message to string
			if ( ! is_string( $message ) ) {
				$message = wc_print_r( $message, true );
			}

			$logger = new \WC_Logger();
			$context = array( 'source' => GSMBINANCEPAY_PLUGIN_ID );
			$logger->debug( $message, $context );
		}
	}

	public static function getLogFileUrl(): string {
		$log_file = GSMBINANCEPAY_PLUGIN_ID . '-' . date('Y-m-d') . '-' . sanitize_file_name( wp_hash( GSMBINANCEPAY_PLUGIN_ID ) ) . '-log';
		return esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file=' . $log_file));
	}

	public static function isDebugEnabled(): bool {
		$settings = get_option('woocommerce_gsmbinancepay_settings');
		if ($settings) {
			if ($settings['debug'] === 'yes') {
				return true;
			}
		}
		return false;
	}
}
