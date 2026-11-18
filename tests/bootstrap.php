<?php
/**
 * PHPUnit bootstrap.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 *
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Core's number formatter.
	 *
	 * English unless a test sets `$GLOBALS['text_number_format']` to a
	 * decimal point and a thousands separator, which is how a test reads a
	 * figure the way another locale would write it.
	 *
	 * @param float $number   The number.
	 * @param int   $decimals Decimal places.
	 *
	 * @return string
	 */
	function number_format_i18n( $number, int $decimals = 0 ): string {
		[ $point, $thousands ] = $GLOBALS['text_number_format'] ?? array( '.', ',' );

		return number_format( (float) $number, $decimals, $point, $thousands );
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
