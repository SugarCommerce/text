<?php
/**
 * Initials, lists, plurals and ordinals.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Text;

use SugarCommerce\Text\Strings;

/**
 * Trait Words
 *
 * Text made from words and counts the way a person writes it.
 *
 * @since 1.0.0
 */
trait Words {

	/**
	 * Up to two initials.
	 *
	 * Which two depends on what the text is, and the two cases disagree:
	 *
	 *   "Dave Michael Sherlock"   first and last -> DS   (a person)
	 *   "Trance Essentials Vol 1" first two     -> TE   (a title)
	 *
	 * First-and-last is right for a name, where the surname identifies
	 * the person. First-two is right for a title, where the trailing word
	 * is usually a volume or edition number — first-and-last would render
	 * that example as `T1`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text       Source text.
	 * @param bool   $from_start Take the first two words rather than the
	 *                           first and last. Pass it by name:
	 *                           `Text::initials( $title, from_start: true )`.
	 *
	 * @return string Uppercase; `?` when there is nothing usable.
	 */
	public static function initials( string $text, bool $from_start = false ): string {
		$parts = preg_split( '/[\s\-_]+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();

		if ( array() === $parts ) {
			return '?';
		}

		$initials = mb_strtoupper( mb_substr( $parts[0], 0, 1 ) );

		if ( count( $parts ) > 1 ) {
			$second    = $from_start ? $parts[1] : $parts[ count( $parts ) - 1 ];
			$initials .= mb_strtoupper( mb_substr( $second, 0, 1 ) );
		}

		return $initials;
	}

	/**
	 * The first letter of every word: `Acme Trading Co` → `ATC`.
	 *
	 * initials() answers "which two letters stand for this", and picks them;
	 * this takes one from each word in order, as an acronym does. Words are
	 * split on whitespace only, so `Jean-Luc` is one. Counts characters, so
	 * an accented first letter is kept whole.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text      Source text.
	 * @param int    $limit     Letters at most, from the first word. 0 for all.
	 * @param bool   $uppercase Upper-case them. False keeps them as written.
	 *
	 * @return string '' when there are no words.
	 */
	public static function acronym( string $text, int $limit = 0, bool $uppercase = true ): string {
		$words = Strings::words( $text );

		if ( $limit > 0 ) {
			$words = array_slice( $words, 0, $limit );
		}

		$letters = implode( '', array_map( static fn( string $word ): string => mb_substr( $word, 0, 1 ), $words ) );

		return $uppercase ? mb_strtoupper( $letters ) : $letters;
	}

	/**
	 * Join a list the way a person writes one.
	 *
	 * `['a']` → `a`; `['a','b']` → `a and b`; `['a','b','c']` → `a, b and c`.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $items       Items.
	 * @param string   $conjunction Word before the last item.
	 * @param int      $limit       Show at most this many, then "and N more".
	 *
	 * @return string
	 */
	public static function list( array $items, string $conjunction = 'and', int $limit = 0 ): string {
		$items = array_values( array_filter( $items, static fn( $i ): bool => is_string( $i ) && '' !== trim( $i ) ) );
		$count = count( $items );

		if ( 0 === $count ) {
			return '';
		}

		if ( $limit > 0 && $count > $limit ) {
			$shown     = array_slice( $items, 0, $limit );
			$remaining = $count - $limit;

			return implode( ', ', $shown ) . ' ' . $conjunction . ' ' . $remaining . ' more';
		}

		if ( 1 === $count ) {
			return $items[0];
		}

		$last = array_pop( $items );

		return implode( ', ', $items ) . ' ' . $conjunction . ' ' . $last;
	}

	/**
	 * Pluralise a noun against a count.
	 *
	 * Handles the common English endings; pass `$plural` for anything
	 * irregular. Not a linguistics engine — "person" will become
	 * "persons" unless you say otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $count    How many.
	 * @param string $singular Singular noun.
	 * @param string $plural   Explicit plural.
	 *
	 * @return string e.g. `3 files`, `1 entry`.
	 */
	public static function plural( int $count, string $singular, string $plural = '' ): string {
		if ( 1 === abs( $count ) ) {
			return $count . ' ' . $singular;
		}

		if ( '' !== $plural ) {
			return $count . ' ' . $plural;
		}

		$formed = match ( true ) {
			// "entry" → "entries", but "day" → "days".
			1 === preg_match( '/[^aeiou]y$/i', $singular ) => mb_substr( $singular, 0, -1 ) . 'ies',
			1 === preg_match( '/(s|x|z|ch|sh)$/i', $singular ) => $singular . 'es',
			default => $singular . 's',
		};

		return $count . ' ' . $formed;
	}

	/**
	 * An ordinal: 1st, 2nd, 3rd, 11th.
	 *
	 * @since 1.0.0
	 *
	 * @param int $number Number.
	 *
	 * @return string
	 */
	public static function ordinal( int $number ): string {
		$absolute = abs( $number );

		// 11, 12 and 13 take "th" despite ending in 1, 2 and 3.
		if ( in_array( $absolute % 100, array( 11, 12, 13 ), true ) ) {
			return $number . 'th';
		}

		return $number . match ( $absolute % 10 ) {
			1       => 'st',
			2       => 'nd',
			3       => 'rd',
			default => 'th',
		};
	}

	/**
	 * A value, or an em dash where there is nothing to show.
	 *
	 * A blank cell in a list table reads as a column that failed to load. A
	 * dash reads as "nothing here", which is usually what is meant.
	 *
	 * Zero is a value, not an absence: a count of nought and a price of nought
	 * both belong on screen as numbers.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value What to show.
	 *
	 * @return string
	 */
	public static function dash( mixed $value ): string {
		if ( null === $value || '' === $value || array() === $value || false === $value ) {
			return "\u{2014}";
		}

		return (string) $value;
	}
}
