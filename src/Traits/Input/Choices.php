<?php
/**
 * One of a fixed set, or a default.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Input;

/**
 * Trait Choices
 *
 * The allow-list form of validation, for strings and for whole numbers.
 *
 * @since 1.0.0
 */
trait Choices {

	/**
	 * One of a fixed set, or a default.
	 *
	 * The allow-list form of validation, which is the only one that works for
	 * values that reach SQL as identifiers -- a sort column, a table alias:
	 * an `ORDER BY` cannot take a bound parameter, so the column has to be one
	 * of the names written in code.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $value   Untrusted value.
	 * @param array<int, string> $allowed Permitted values.
	 * @param string|null        $default Returned when it is not one. Falls
	 *                                    back to the first permitted value,
	 *                                    or '' when none is permitted.
	 *
	 * @return string
	 */
	public static function one_of( string $value, array $allowed, ?string $default = null ): string {
		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return $default ?? ( array() === $allowed ? '' : $allowed[ array_key_first( $allowed ) ] );
	}

	/**
	 * One of a fixed set of whole numbers, or a default.
	 *
	 * one_of() for the sets that are numbers -- a reporting window of 7, 30
	 * or 90 days, a link lifetime of an hour or a day. Reads an int, or a
	 * string of digits as a query string or form sends one (`'30'`, `' 30 '`,
	 * `'030'`, `'-1'`).
	 *
	 * Stricter than int() on purpose. int() clamps, so it reads `'7.9'` and
	 * `'1e1'` as near enough; an allow-list asks whether the value is one of
	 * these exactly, and `'7.9'` is not 7. A float, a bool, a number too big
	 * for an int and anything with more than digits in it are the default.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed           $value   Untrusted value.
	 * @param array<int, int> $allowed Permitted values.
	 * @param int|null        $default Returned when it is not one. Falls back
	 *                                 to the first permitted value, or 0 when
	 *                                 none is permitted.
	 *
	 * @return int
	 */
	public static function one_of_int( mixed $value, array $allowed, ?int $default = null ): int {
		if ( is_string( $value ) && 1 === preg_match( '/^\s*(-?)0*(\d+)\s*$/D', $value, $parts ) ) {
			$value = filter_var( $parts[1] . $parts[2], FILTER_VALIDATE_INT );
		}

		if ( is_int( $value ) && in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return $default ?? ( array() === $allowed ? 0 : $allowed[ array_key_first( $allowed ) ] );
	}
}
