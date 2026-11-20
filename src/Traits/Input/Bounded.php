<?php
/**
 * Switches and numbers, clamped to the range they belong in.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Input;

/**
 * Trait Bounded
 *
 * A switch read the way a person meant it; whole numbers, limits and
 * offsets that cannot run past their bounds; a list of numbers a script
 * sent back.
 *
 * @since 1.0.0
 */
trait Bounded {

	/**
	 * A switch, read the way a person meant it.
	 *
	 * On is true, 1, "1", "true", "on" or "yes"; off is their opposites and
	 * the empty string. Anything else -- "maybe", an array, a typo -- is the
	 * default rather than whatever a truthiness cast makes of it:
	 * `(bool) "false"` is true, which is how a setting ends up doing the
	 * opposite of what somebody chose.
	 *
	 * Stricter than Booleans::truthy(), which is for showing a value and
	 * calls any non-empty string a yes.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   Stored or submitted value.
	 * @param bool  $default Returned when the value is not a switch at all.
	 *
	 * @return bool
	 */
	public static function bool( mixed $value, bool $default = false ): bool {
		if ( null === $value ) {
			return false;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? $default;
	}

	/**
	 * A switch as the one or nought a flag column keeps.
	 *
	 * Read as {@see self::bool()} reads it, so anything that is not a yes
	 * -- "no", "false", "banana", an array -- is 0. A tinyint(1) column
	 * written with a bare `(int) (bool)` stores 1 for "false".
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Stored or submitted value.
	 *
	 * @return int 1 or 0.
	 */
	public static function flag( mixed $value ): int {
		return self::bool( $value ) ? 1 : 0;
	}

	/**
	 * A whole number within bounds.
	 *
	 * Clamped rather than rejected: a page number of ten million is
	 * somebody fuzzing, and the last page is a better answer than an error.
	 * Compared before it is converted, so a number too big for an int --
	 * `1e30` from a JSON body -- clamps to the bound it is past instead of
	 * wrapping. A fraction is cut, not rounded.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value.
	 * @param int   $min     Lower bound.
	 * @param int   $max     Upper bound.
	 * @param int   $default Returned when the value is not a number at all.
	 *
	 * @return int
	 */
	public static function int( mixed $value, int $min = 0, int $max = PHP_INT_MAX, int $default = 0 ): int {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		if ( is_int( $value ) ) {
			return max( $min, min( $max, $value ) );
		}

		// Digits only is what a form and a column send, and the one shape a
		// float cannot be trusted with once the number is long.
		if ( is_string( $value ) && ctype_digit( $value ) && strlen( $value ) < 19 ) {
			return max( $min, min( $max, (int) $value ) );
		}

		$number = (float) $value;

		if ( is_nan( $number ) ) {
			return $default;
		}

		if ( $number >= $max ) {
			return $max;
		}

		if ( $number <= $min ) {
			return $min;
		}

		return max( $min, min( $max, (int) $number ) );
	}

	/**
	 * How many rows to return: at least one, at most a ceiling.
	 *
	 * For `?limit=` on a list. Nothing given, nought, a negative and
	 * anything that is not a number are the default -- none of them is
	 * somebody asking for one row -- and more than the ceiling is the
	 * ceiling.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value asked for.
	 * @param int   $default How many when nothing usable was asked for.
	 * @param int   $max     The most that may be asked for.
	 *
	 * @return int Between 1 and $max.
	 */
	public static function limit( mixed $value, int $default = 30, int $max = 100 ): int {
		$max   = max( 1, $max );
		$limit = self::int( $value, 0, $max, 0 );

		return $limit > 0 ? $limit : max( 1, min( $max, $default ) );
	}

	/**
	 * How many rows to skip: nought or more.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value asked for.
	 *
	 * @return int
	 */
	public static function offset( mixed $value ): int {
		return self::int( $value, 0, PHP_INT_MAX, 0 );
	}

	/**
	 * An integer in a range, or null when nothing was chosen.
	 *
	 * For the settings that mean "no limit" when they are unset: a link that
	 * never expires, a download that is not capped. Empty string, null and
	 * zero all mean the same thing, because a select whose "unlimited" option
	 * has an empty value and a form that posts 0 are both saying it.
	 *
	 * Distinct from int(), which always returns a number. The difference is
	 * whether "not set" is a value the caller needs to keep.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Untrusted value.
	 * @param int   $min   Lower bound.
	 * @param int   $max   Upper bound.
	 *
	 * @return int|null The clamped integer, or null when unset.
	 */
	public static function int_or_null( mixed $value, int $min, int $max ): ?int {
		if ( ! is_numeric( $value ) || 0 === self::int( $value, PHP_INT_MIN, PHP_INT_MAX ) ) {
			return null;
		}

		return self::int( $value, $min, $max, $min );
	}

	/**
	 * A list of numbers, each clamped to a range, or null when it is not one.
	 *
	 * For data a browser computed and sent back -- a waveform's peaks, a
	 * chart's points. All or nothing: one entry that is not a number means
	 * the list was not what the page drew, and storing the rest would store
	 * something else. Bounded strings are refused too, because JSON numbers
	 * are what a script sends.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $value     A list of ints and floats.
	 * @param float    $min       Lower bound for each.
	 * @param float    $max       Upper bound for each.
	 * @param int      $max_count Most entries allowed; more is not a list
	 *                            of this kind. 0 for no limit.
	 * @param int|null $decimals  Round each to this many places, or null to
	 *                            leave them.
	 *
	 * @return list<float>|null Null for an empty list too.
	 */
	public static function number_list( mixed $value, float $min, float $max, int $max_count = 0, ?int $decimals = null ): ?array {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || array() === $value || ( $max_count > 0 && count( $value ) > $max_count ) ) {
			return null;
		}

		$numbers = array();

		foreach ( $value as $number ) {
			if ( ! is_int( $number ) && ! is_float( $number ) ) {
				return null;
			}

			if ( is_float( $number ) && is_nan( $number ) ) {
				return null;
			}

			$clamped   = max( $min, min( $max, (float) $number ) );
			$numbers[] = null === $decimals ? $clamped : round( $clamped, $decimals );
		}

		return $numbers;
	}
}
