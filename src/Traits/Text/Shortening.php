<?php
/**
 * Text shortened to a length or a number of words.
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
 * Trait Shortening
 *
 * At a word, at an exact character, at both ends, or as a one-line
 * summary. Counts characters, not bytes.
 *
 * @since 1.0.0
 */
trait Shortening {

	/**
	 * Shorten to a length without cutting a word in half.
	 *
	 * Counts characters, not bytes, so a limit of 20 means twenty visible
	 * characters whether the text is English or Japanese. The ellipsis is
	 * included in the budget, because a "20 character" label that renders
	 * 21 breaks a table column.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text     Source text.
	 * @param int    $length   Maximum length, including the ellipsis.
	 * @param string $ellipsis Appended when shortened.
	 *
	 * @return string
	 */
	public static function truncate( string $text, int $length = 100, string $ellipsis = '…' ): string {
		$text = trim( $text );

		if ( $length <= 0 || mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$budget = max( 1, $length - mb_strlen( $ellipsis ) );
		$cut    = mb_substr( $text, 0, $budget );
		$space  = mb_strrpos( $cut, ' ' );

		// Only break at a word boundary if one is reasonably near the end,
		// otherwise a long unbroken string would collapse to almost nothing.
		if ( false !== $space && $space > (int) ( $budget * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, " \t\n\r\0\x0B.,;:!?-" ) . $ellipsis;
	}

	/**
	 * Shorten to an exact number of characters, wherever that falls.
	 *
	 * truncate() moves the cut back to a word boundary; this does not, for
	 * a column or a field with a hard width where every character counts.
	 * The length is the whole result, suffix included, and nothing is
	 * trimmed: a value already within it comes back exactly as it was.
	 *
	 * Counts characters, not bytes. A length shorter than the suffix has no
	 * room for any of the text, and gives the suffix alone -- never a
	 * longer string than was asked for.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text   Source text.
	 * @param int    $length Maximum length, including the suffix.
	 * @param string $suffix Appended when shortened.
	 *
	 * @return string
	 */
	public static function cut( string $text, int $length, string $suffix = '...' ): string {
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		// max(0, ...) matters: a length shorter than the suffix makes this
		// negative, and mb_substr() reads a negative length as "all but the
		// last n characters" -- so asking for a shorter string returned a
		// longer one.
		$room = max( 0, $length - mb_strlen( $suffix ) );

		return mb_substr( $text, 0, $room ) . $suffix;
	}

	/**
	 * Keep at most a number of words.
	 *
	 * Words are separated by any run of whitespace. Text within the limit
	 * comes back untouched, spacing and all; shortened text has its words
	 * joined by single spaces.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text   Source text.
	 * @param int    $limit  Words to keep.
	 * @param string $suffix Appended when shortened.
	 *
	 * @return string
	 */
	public static function truncate_words( string $text, int $limit, string $suffix = '...' ): string {
		$words = Strings::words( $text );

		if ( count( $words ) <= $limit ) {
			return $text;
		}

		return implode( ' ', array_slice( $words, 0, max( 0, $limit ) ) ) . $suffix;
	}

	/**
	 * Keep both ends, drop the middle: `a1b2c3d4e5…f6a7b8c9`.
	 *
	 * For identifiers people compare by eye -- hashes, keys, long references --
	 * where the start says what it is and the end tells two apart, so
	 * truncate() keeping only the start would make different values look
	 * the same. Counts characters, not bytes. Returned unchanged when
	 * shortening would not make it shorter.
	 *
	 * For a credential, where the point is that it cannot be read back, use
	 * redact() or mask() instead: this shows as much as it is told to.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text     Source text.
	 * @param int    $head     Characters kept from the start.
	 * @param int    $tail     Characters kept from the end.
	 * @param string $ellipsis Put where the middle was.
	 *
	 * @return string
	 */
	public static function middle( string $text, int $head, int $tail, string $ellipsis = '…' ): string {
		$head = max( 0, $head );
		$tail = max( 0, $tail );

		if ( mb_strlen( $text ) <= $head + $tail + mb_strlen( $ellipsis ) ) {
			return $text;
		}

		return mb_substr( $text, 0, $head ) . $ellipsis . ( $tail > 0 ? mb_substr( $text, -$tail ) : '' );
	}

	/**
	 * Collapse to a single-line summary.
	 *
	 * Whitespace runs and line breaks become single spaces before
	 * truncating, so a multi-paragraph body becomes one clean line. With
	 * `$strip_tags` the markup goes first, so post content makes an excerpt
	 * of what it says rather than of its tags.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text       Source text.
	 * @param int    $length     Maximum length.
	 * @param bool   $strip_tags Take out HTML first, with strip_tags().
	 *
	 * @return string
	 */
	public static function excerpt( string $text, int $length = 160, bool $strip_tags = false ): string {
		if ( $strip_tags ) {
			$text = self::strip_tags( $text );
		}

		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		return self::truncate( $text, $length );
	}
}
