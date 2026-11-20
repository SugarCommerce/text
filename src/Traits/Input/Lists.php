<?php
/**
 * A list typed into one field.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Input;

/**
 * Trait Lists
 *
 * Entries split on commas, spaces and new lines, or one per line.
 *
 * @since 1.0.0
 */
trait Lists {

	/**
	 * A list typed into one field, or sent as an array.
	 *
	 * A metadata value or a textarea of origins is written with
	 * commas, spaces, new lines or all three; this reads them all the same
	 * way. Each entry is trimmed, empties and duplicates go, the order is
	 * kept. It does not judge the entries -- pass each through the validator
	 * for what it should be.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value A string, or an array of scalars. Anything else is
	 *                     empty.
	 * @param int   $max   Entries read at most, before duplicates are
	 *                     dropped. 0 for no limit.
	 *
	 * @return list<string>
	 */
	public static function list( mixed $value, int $max = 0 ): array {
		$parts = is_array( $value )
			? array_values( $value )
			: ( preg_split( '/[\s,]+/', is_scalar( $value ) ? (string) $value : '', -1, PREG_SPLIT_NO_EMPTY ) ?: array() );

		if ( $max > 0 ) {
			$parts = array_slice( $parts, 0, $max );
		}

		$list = array();

		foreach ( $parts as $part ) {
			$part = is_scalar( $part ) ? trim( (string) $part ) : '';

			if ( '' !== $part ) {
				$list[ $part ] = true;
			}
		}

		return array_map( strval( ... ), array_keys( $list ) );
	}

	/**
	 * One entry per line, from a textarea.
	 *
	 * For entries that can hold spaces or commas themselves -- file paths,
	 * addresses, titles -- where list() would split them apart. Lines are
	 * split on any line ending, each is trimmed, and blank ones go.
	 * Duplicates and order are kept: a position can mean something (a
	 * download link that points at "the second file"), so removing one is
	 * the caller's decision.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value A string; anything else is empty.
	 * @param int   $max   Entries kept at most, 0 for no limit.
	 *
	 * @return list<string>
	 */
	public static function lines( mixed $value, int $max = 0 ): array {
		if ( ! is_string( $value ) ) {
			return array();
		}

		$lines = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $value ) ?: array() as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$lines[] = $line;

			if ( $max > 0 && count( $lines ) >= $max ) {
				break;
			}
		}

		return $lines;
	}
}
