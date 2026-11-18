<?php
/**
 * Looking inside strings, and taking them apart.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text;

/**
 * Class Strings
 *
 * The string operations PHP stops short of: testing against several needles
 * at once, matching a list of patterns, replacing only the first or the last
 * occurrence, pulling out what sits between two markers, and splitting into
 * pieces without the empty ones explode() leaves behind.
 *
 * A note on `empty()`: it is never used on a string here. `empty( '0' )` is
 * true, so every helper that guarded with it silently did nothing for the one
 * character that is both a legitimate value and a falsy one -- a search for
 * '0' never replaced, a pattern of '0' never matched, a line of '0' vanished.
 * Emptiness is `'' === $value`.
 *
 * Text for display -- truncating, case, initials -- is {@see Text}; values
 * arriving from a request are {@see Input}.
 *
 * @since 1.0.0
 */
final class Strings {

	/**
	 * Whether a string contains any of the needles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $haystack   The string to search in.
	 * @param string ...$needles The strings to search for.
	 *
	 * @return bool False when no needle is given.
	 */
	public static function contains_any( string $haystack, string ...$needles ): bool {
		foreach ( $needles as $needle ) {
			if ( str_contains( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a string contains every one of the needles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $haystack   The string to search in.
	 * @param string ...$needles The strings to search for.
	 *
	 * @return bool True when no needle is given.
	 */
	public static function contains_all( string $haystack, string ...$needles ): bool {
		foreach ( $needles as $needle ) {
			if ( ! str_contains( $haystack, $needle ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a string starts with any of the needles.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $haystack The string to search in.
	 * @param string|array<int, string> $needles  One needle, or several.
	 *
	 * @return bool
	 */
	public static function starts_with( string $haystack, string|array $needles ): bool {
		foreach ( (array) $needles as $needle ) {
			if ( str_starts_with( $haystack, (string) $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a string ends with any of the needles.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $haystack The string to search in.
	 * @param string|array<int, string> $needles  One needle, or several.
	 *
	 * @return bool
	 */
	public static function ends_with( string $haystack, string|array $needles ): bool {
		foreach ( (array) $needles as $needle ) {
			if ( str_ends_with( $haystack, (string) $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a value matches any pattern in a list.
	 *
	 * Case-insensitive, ignoring surrounding whitespace. With wildcards on,
	 * a pattern ending in `*` matches any value that starts with the part
	 * before it. For a block list or an allow list kept in a setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $value    The value to check.
	 * @param array<mixed> $patterns Patterns to match against.
	 * @param bool         $wildcard Let a trailing `*` match any ending.
	 *
	 * @return bool
	 */
	public static function matches_any( string $value, array $patterns, bool $wildcard = false ): bool {
		$value = strtolower( trim( $value ) );

		if ( '' === $value || array() === $patterns ) {
			return false;
		}

		foreach ( $patterns as $pattern ) {
			// Patterns often come out of a settings array, where a numeric one
			// has already been cast to int. Under strict types that is a
			// TypeError in trim() rather than a non-match.
			$pattern = strtolower( trim( is_scalar( $pattern ) ? (string) $pattern : '' ) );

			if ( $wildcard && str_ends_with( $pattern, '*' ) ) {
				if ( str_starts_with( $value, rtrim( $pattern, '*' ) ) ) {
					return true;
				}
			} elseif ( $value === $pattern ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a string is letters and digits only, and not empty.
	 *
	 * ASCII letters and digits, as ctype_alnum() reads them.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The string.
	 *
	 * @return bool
	 */
	public static function is_alphanumeric( string $value ): bool {
		return ctype_alnum( $value );
	}

	/**
	 * Replace the first occurrence only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search  What to find. An empty one replaces nothing.
	 * @param string $replace What to put in its place.
	 * @param string $subject The string to search in.
	 *
	 * @return string
	 */
	public static function replace_first( string $search, string $replace, string $subject ): string {
		if ( '' === $search ) {
			return $subject;
		}

		$position = strpos( $subject, $search );

		return false === $position ? $subject : substr_replace( $subject, $replace, $position, strlen( $search ) );
	}

	/**
	 * Replace the last occurrence only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search  What to find. An empty one replaces nothing.
	 * @param string $replace What to put in its place.
	 * @param string $subject The string to search in.
	 *
	 * @return string
	 */
	public static function replace_last( string $search, string $replace, string $subject ): string {
		if ( '' === $search ) {
			return $subject;
		}

		$position = strrpos( $subject, $search );

		return false === $position ? $subject : substr_replace( $subject, $replace, $position, strlen( $search ) );
	}

	/**
	 * What sits between the first start marker and the end marker after it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $start   The start marker.
	 * @param string $end     The end marker.
	 * @param string $subject The string to search in.
	 *
	 * @return string '' when either marker is missing.
	 */
	public static function between( string $start, string $end, string $subject ): string {
		$from = strpos( $subject, $start );

		if ( false === $from ) {
			return '';
		}

		$from += strlen( $start );
		$to    = strpos( $subject, $end, $from );

		return false === $to ? '' : substr( $subject, $from, $to - $from );
	}

	/**
	 * Every run of whitespace as one space, and the ends trimmed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The string.
	 *
	 * @return string
	 */
	public static function reduce_whitespace( string $value ): string {
		return (string) preg_replace( '/\s+/', ' ', trim( $value ) );
	}

	/**
	 * Every whitespace character taken out.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The string.
	 *
	 * @return string
	 */
	public static function remove_whitespace( string $value ): string {
		return (string) preg_replace( '/\s+/', '', $value );
	}

	/**
	 * Any value as a string, without the warning.
	 *
	 * An array or an object is its JSON -- `(string)` on an array is the word
	 * "Array" and a warning, and "Array" is a value that reaches a database.
	 * WordPress's wp_json_encode() where it is loaded. Null is ''.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value.
	 *
	 * @return string '' for something JSON cannot encode.
	 */
	public static function from( mixed $value ): string {
		if ( is_array( $value ) || ( is_object( $value ) && ! $value instanceof \Stringable ) ) {
			$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value );

			return is_string( $json ) ? $json : '';
		}

		return is_scalar( $value ) || null === $value || $value instanceof \Stringable ? (string) $value : '';
	}

	/**
	 * A delimited string as its trimmed pieces.
	 *
	 * An empty string is an empty list, not a list of one empty string --
	 * which is what explode() gives, and what every caller then has to
	 * filter out again. Pieces are kept where they are, empty ones between
	 * two separators included, so a column keeps its position. An empty
	 * separator does not split at all.
	 *
	 * For a list somebody typed, where commas, spaces and new lines all
	 * separate and duplicates go, see {@see Input::list()}.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value     The delimited string.
	 * @param string $separator The separator.
	 *
	 * @return list<string>
	 */
	public static function split( string $value, string $separator = ',' ): array {
		if ( '' === trim( $value ) ) {
			return array();
		}

		if ( '' === $separator ) {
			return array( trim( $value ) );
		}

		return array_map( trim( ... ), explode( $separator, $value ) );
	}

	/**
	 * The words of a string, split on any run of whitespace.
	 *
	 * Punctuation stays with the word it is attached to. A word that is
	 * `0` is a word.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The string.
	 *
	 * @return list<string>
	 */
	public static function words( string $value ): array {
		return array_values( array_filter( preg_split( '/\s+/', $value ) ?: array(), static fn( string $word ): bool => '' !== $word ) );
	}
}
