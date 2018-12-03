<?php
/**
 * Plugin Name: Cryptocurrencies for Easy Digital Downloads
 * Plugin URI: https://www.coinpayments.net
 * Description: Adds common cryptocurrencies to Easy Digital Downloads
 * Version: 1.0
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net
 * License: GPL v2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

function coinpayments_get_edd_currencies() {
	$currencies = array();
	$currencies['BTC'] = __('Bitcoin', 'coinpayments_edd_currencies');
	$currencies['LTC'] = __('Litecoin', 'coinpayments_edd_currencies');
	$currencies['NET'] = __('Netcoin', 'coinpayments_edd_currencies');
	$currencies['DOGE'] = __('Dogecoin', 'coinpayments_edd_currencies');
	return $currencies;
}

function coinpayments_extra_edd_currencies( $currencies ) {
	return array_merge($currencies, coinpayments_get_edd_currencies());
}
add_filter('edd_currencies', 'coinpayments_extra_edd_currencies');

function coinpayments_edd_currency_decimal_filter( $decimals ) {
	$currency = edd_get_currency();
	if (array_key_exists($currency, coinpayments_get_edd_currencies())) {
		//6 digits is usually sufficient. Even when Bitcoin was $1,000 USD the sixth digit was 1/10th of a penny.
		return 6;
	}

	return $decimals;
}
add_filter( 'edd_format_amount_decimals', 'coinpayments_edd_currency_decimal_filter' );
