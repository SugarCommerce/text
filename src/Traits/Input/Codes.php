<?php
/**
 * Codes, ids and JSON, kept exactly or refused.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Input;

/**
 * Trait Codes
 *
 * A value that is only any use exactly as it should be -- a hash, an id
 * with a prefix, a code somebody pasted, JSON to keep -- is that or ''.
 *
 * @since 1.0.0
 */
trait Codes {

	/**
	 * A structured value as the JSON to keep.
	 *
	 * An array or an object is encoded. A string that is already JSON is
	 * returned exactly as it is -- not decoded and encoded again, which
	 * would re-escape it, and a payload kept to be read back as it was sent
	 * should be the bytes that were sent. Anything else is ''.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value.
	 * @param int   $flags `json_encode()` flags for an array or object.
	 *
	 * @return string JSON, or '' when there is nothing to keep.
	 */
	public static function json( mixed $value, int $flags = 0 ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = json_encode( $value, $flags );

			return false === $encoded ? '' : $encoded;
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		return json_validate( $value ) ? $value : '';
	}

	/**
	 * A hex string of an exact length, lower-cased, or ''.
	 *
	 * A hash, a token: `hex( $value, 64 )` is a sha256 or nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value  The value.
	 * @param int   $length How many hex characters it must be.
	 *
	 * @return string
	 */
	public static function hex( mixed $value, int $length ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtolower( trim( $value ) );

		return strlen( $value ) === $length && ctype_xdigit( $value ) ? $value : '';
	}

	/**
	 * A code somebody pasted -- an order number, a transaction or tracking
	 * id, a licence key -- or ''.
	 *
	 * People copy more than the code: the label in front of it
	 * ("Transaction ID: …"), the quotes around it, straight or curly, and
	 * spaces from wherever it was laid out, including non-breaking and other
	 * Unicode spaces that trim() cannot see. All of that is removed; what is
	 * left has to be 1 to $max characters of A–Z, a–z, 0–9, `_` and `-`.
	 * Case is kept: some systems' codes are case-sensitive.
	 *
	 * The quotes are removed by pattern, not trim(): trim() works on bytes,
	 * and a curly quote shares its first two bytes with other characters --
	 * an em space among them -- so trimming one can cut another in half.
	 *
	 *   Input::pasted_code( '“Transaction ID: 9AB 12345”', 64, array( 'Transaction ID' ) ); // '9AB12345'
	 *
	 * @since 1.0.0
	 *
	 * @param mixed         $value  What was pasted.
	 * @param int           $max    Longest code accepted.
	 * @param array<string> $labels Labels that may come before it, followed by
	 *                              a colon. Matched case-insensitively, with
	 *                              any spacing where the label has a space
	 *                              ("Txn ID" matches "TxnID:" and "txn  id :").
	 *
	 * @return string The code, or '' when what is left is not one.
	 *
	 * @throws \InvalidArgumentException When $max is under 1.
	 */
	public static function pasted_code( mixed $value, int $max = 64, array $labels = array() ): string {
		if ( $max < 1 ) {
			throw new \InvalidArgumentException( 'A code has to be allowed at least one character.' );
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$quotes = '/^["\'\x{201C}\x{201D}\x{2018}\x{2019}]+|["\'\x{201C}\x{201D}\x{2018}\x{2019}]+$/u';
		$code   = trim( $value );

		if ( array() !== $labels ) {
			// Longest first, so a label is never cut short by one it begins with.
			usort( $labels, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

			// The quotes can be around the label and the code together, as
			// copied from a sentence, or around the code alone; a label is
			// looked for both ways.
			$names    = implode( '|', array_map( static fn( string $label ): string => (string) preg_replace( '/\s+/', '\\s*', preg_quote( trim( $label ), '/' ) ), $labels ) );
			$label    = '/^\s*(?:' . $names . ')\s*:\s*/iu';
			$unquoted = trim( preg_replace( $quotes, '', $code ) ?? $code );
			$code     = 1 === preg_match( $label, $code ) || 1 !== preg_match( $label, $unquoted ) ? $code : $unquoted;
			$code     = preg_replace( $label, '', $code ) ?? $code;
		}

		$code = preg_replace( $quotes, '', $code ) ?? $code;
		$code = preg_replace( '/[\s\p{Z}]+/u', '', $code ) ?? $code;

		return strlen( $code ) <= $max && 1 === preg_match( '/^[A-Za-z0-9_-]+$/D', $code ) ? $code : '';
	}

	/**
	 * An identifier of the form `prefix_alphanumeric`, or ''.
	 *
	 * The shape most object-store APIs use -- Stripe's `acct_`, `price_`,
	 * `cs_`; Slack's, Shopify's, plenty of others. Checking the prefix as
	 * well as the alphabet is what stops a caller passing a customer id where
	 * an account id was meant and having it silently look up nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value  Untrusted identifier.
	 * @param string $prefix Expected prefix, without the underscore.
	 *
	 * @return string The id, or '' when it does not match.
	 */
	public static function prefixed_id( string $value, string $prefix ): string {
		$value = trim( $value );

		return 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '_[A-Za-z0-9_]+$/D', $value ) ? $value : '';
	}

	/**
	 * A list of prefixed ids, each checked as {@see prefixed_id()} checks one.
	 *
	 * For the form field or JSON array that names several objects -- the
	 * prices in a cart, the products on a shelf. What does not match is
	 * dropped rather than failing the list, duplicates go, and the order
	 * the caller gave is kept, because it is usually a display order.
	 *
	 * Bounded on the way in: a list padded with ten thousand entries costs
	 * no more than one at the cap, so the cap is how many are read, not how
	 * many survive.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $values A list, or one scalar. Anything else is empty.
	 * @param string $prefix Expected prefix, without the underscore.
	 * @param int    $max    Entries read at most. 0 for no limit.
	 *
	 * @return list<string>
	 */
	public static function prefixed_ids( mixed $values, string $prefix, int $max = 0 ): array {
		$values = is_array( $values ) ? array_values( $values ) : ( is_scalar( $values ) ? array( $values ) : array() );

		if ( $max > 0 ) {
			$values = array_slice( $values, 0, $max );
		}

		$ids = array();

		foreach ( $values as $value ) {
			$id = self::prefixed_id( is_scalar( $value ) ? (string) $value : '', $prefix );

			if ( '' !== $id ) {
				$ids[ $id ] = true;
			}
		}

		return array_map( strval( ... ), array_keys( $ids ) );
	}
}
