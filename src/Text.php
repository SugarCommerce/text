<?php
/**
 * Text for display.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text;

/**
 * Class Text
 *
 * Everything here returns plain text and escapes nothing. Escape at the
 * point of output, for the encoding of the place it is going — these
 * results are as safe to escape as their inputs were, and no safer.
 *
 * @since 1.0.0
 */
final readonly class Text {

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

	/**
	 * HTML as the text it shows.
	 *
	 * WordPress's wp_strip_all_tags() where WordPress is loaded, and the same
	 * rule without it: a `<script>` or `<style>` goes with everything inside
	 * it -- PHP's strip_tags() would leave the code behind as text -- then
	 * every other tag goes and the ends are trimmed.
	 *
	 * Not a sanitiser. What comes back is plain text, still to be escaped
	 * where it is printed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Markup.
	 *
	 * @return string
	 */
	public static function strip_tags( string $html ): string {
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return (string) wp_strip_all_tags( $html );
		}

		$html = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $html );

		return trim( strip_tags( $html ) );
	}

	/**
	 * Plain text as its paragraphs.
	 *
	 * A line break in text somebody typed into a single field -- a product
	 * description, a note -- is them starting a new paragraph, so every
	 * break is one: blank lines in between do not add empty paragraphs,
	 * and each paragraph is trimmed. Nothing is escaped or interpreted,
	 * so what comes back is still plain text, to be escaped where it is
	 * printed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Source text, with any line endings.
	 *
	 * @return list<string> The paragraphs, in order. Empty for blank text.
	 */
	public static function paragraphs( string $text ): array {
		$lines = preg_split( '/\R/u', $text ) ?: array();

		return array_values(
			array_filter(
				array_map( static fn( string $line ): string => trim( (string) preg_replace( '/[ \t]+/u', ' ', $line ) ), $lines ),
				static fn( string $line ): bool => '' !== $line
			)
		);
	}

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
	 * A machine key as a label: `licence_activations` → `Licence activations`.
	 *
	 * For keys somebody chose to name a value -- metadata, a settings
	 * array, a column, a log's type -- shown as that value's label when
	 * nobody gave it a real one. Underscores, hyphens and dots become
	 * spaces, camelCase is split, and only the first letter is capitalised:
	 * sentence case, because `Bit Depth` reads as a title and `Bit depth`
	 * reads as a label. A word already written in capitals (`BPM`, `ISRC`)
	 * is kept as written.
	 *
	 * A real label, translated, is always better. This is what stands in.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Source key.
	 *
	 * @return string '' for a key with no letters or digits in it.
	 */
	public static function humanize( string $key ): string {
		$spaced = (string) preg_replace( '/(?<=\p{Ll})(?=\p{Lu})/u', ' ', $key );
		$words  = preg_split( '/[\s_.\-]+/u', trim( $spaced ), -1, PREG_SPLIT_NO_EMPTY );
		$words  = false === $words ? array() : $words;

		$words = array_map(
			static fn( string $word ): string => mb_strlen( $word ) > 1 && mb_strtoupper( $word ) === $word ? $word : mb_strtolower( $word ),
			$words
		);

		if ( array() === $words ) {
			return '';
		}

		$words[0] = mb_strtoupper( mb_substr( $words[0], 0, 1 ) ) . mb_substr( $words[0], 1 );

		return implode( ' ', $words );
	}

	/**
	 * A name in consistent case: `GEORGE HILL` → `George Hill`.
	 *
	 * For names typed by their owners and imported from wherever they were
	 * typed -- all capitals, all lower case, a mix of both -- so a list of
	 * them reads as one list. Lower-cased first, so `McDonald` becomes
	 * `Mcdonald` and `o'brien` becomes `O'brien`: nothing here knows which
	 * capitals were deliberate, and a list where some were kept and some
	 * were not looks worse than one rule applied evenly.
	 *
	 * Words split on whitespace only, and the first letter of each is
	 * capitalised the way ucwords() does it, as a byte: a word starting
	 * with a letter outside ASCII -- `élodie` -- keeps it in lower case
	 * rather than guessing at a locale. The rest of the name is lowered as
	 * UTF-8, so `ÉLODIE` is `élodie`. For every word capitalised whatever
	 * its script, see title().
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Source name.
	 *
	 * @return string Trimmed; '' when there was nothing but whitespace.
	 */
	public static function name_case( string $name ): string {
		return ucwords( mb_strtolower( trim( $name ), 'UTF-8' ) );
	}

	/**
	 * Title Case: `élan vital` → `Élan Vital`.
	 *
	 * Every word boundary counts, hyphens included, so `mother-in-law` is
	 * `Mother-In-Law`; letters after the first in each word are lowered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Source text.
	 *
	 * @return string
	 */
	public static function title( string $text ): string {
		return mb_convert_case( $text, MB_CASE_TITLE, 'UTF-8' );
	}

	/**
	 * Sentence case: the first letter up, the rest down.
	 *
	 * For a key already split into words, see humanize(), which keeps
	 * `BPM` as written; this lowers everything after the first letter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Source text.
	 *
	 * @return string
	 */
	public static function sentence( string $text ): string {
		$text = mb_strtolower( $text );

		return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
	}

	/**
	 * camelCase: `order_total` and `order-total` → `orderTotal`.
	 *
	 * Underscores, hyphens and spaces are word breaks; each word after the
	 * first starts with a capital, and the first starts lower.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Source text.
	 *
	 * @return string
	 */
	public static function camel( string $text ): string {
		return lcfirst( str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $text ) ) ) );
	}

	/**
	 * snake_case: `fooBar Baz` and `foo-bar-baz` → `foo_bar_baz`.
	 *
	 * Splits camelCase at its capitals -- a run of them is one word, so
	 * `HTMLParser` is `html_parser` -- turns spaces and hyphens into
	 * underscores, and drops anything that is not a letter, digit or
	 * underscore.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Source text.
	 *
	 * @return string
	 */
	public static function snake( string $text ): string {
		// Two passes for the capitals: the first rule alone only sees 'lP'.
		$text = (string) preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1_$2', $text );
		$text = (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', $text );
		$text = (string) preg_replace( '/[\s\-]+/', '_', trim( $text ) );
		$text = (string) preg_replace( '/[^A-Za-z0-9_]/', '', $text );
		$text = (string) preg_replace( '/_+/', '_', $text );

		return strtolower( trim( $text, '_' ) );
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

	/**
	 * The middle hidden, a few characters showing at each end, the length
	 * kept: `AB12********3A9F`.
	 *
	 * For a licence key or a card number shown to the person it belongs to,
	 * who recognises it by its ends and knows how long it is. A value too
	 * short to hide anything once both ends are shown is masked entirely
	 * rather than shown in full. Counts characters, so a multibyte one is
	 * shown or masked whole.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value   The value.
	 * @param int    $visible Characters shown at the start, and at the end.
	 * @param string $mask    What each hidden character becomes.
	 *
	 * @return string
	 */
	public static function mask( #[\SensitiveParameter] string $value, int $visible = 4, string $mask = '*' ): string {
		$visible = max( 0, $visible );
		$length  = mb_strlen( $value );

		if ( $length <= $visible * 2 ) {
			return str_repeat( $mask, $length );
		}

		// The tail is taken from an offset counted from the start, because a
		// negative offset of nought is nought: mb_substr( $value, -0 ) is the
		// whole string, so a visible count of 0 showed everything.
		return mb_substr( $value, 0, $visible )
			. str_repeat( $mask, $length - ( $visible * 2 ) )
			. mb_substr( $value, $length - $visible );
	}

	/**
	 * A credential or name, shortened to what recognises it: `AKIA…WXYZ`.
	 *
	 * Enough of each end to tell two apart in a list, never enough to use,
	 * and -- unlike mask() -- nothing about how long it is. Four of each end
	 * from sixteen characters up (an access key), three and two from ten,
	 * and only the first character of anything shorter, where showing both
	 * ends would show half of it or more. Counted in characters, so a name
	 * is never cut through the middle of one.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	public static function redact( #[\SensitiveParameter] string $value ): string {
		$length = mb_strlen( $value );

		if ( $length < 10 ) {
			return mb_substr( $value, 0, 1 ) . '…';
		}

		return mb_substr( $value, 0, $length >= 16 ? 4 : 3 ) . '…' . mb_substr( $value, $length >= 16 ? -4 : -2 );
	}
}
