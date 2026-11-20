<?php
/**
 * Text in another case: a label, a name, a title, code.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Text;

/**
 * Trait Cases
 *
 * humanize() for a machine key shown as a label; name_case(), title()
 * and sentence() for people's text; camel() and snake() for code.
 *
 * @since 1.0.0
 */
trait Cases {

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
}
