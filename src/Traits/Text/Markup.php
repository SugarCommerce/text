<?php
/**
 * HTML and typed text as the plain text they show.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Text;

/**
 * Trait Markup
 *
 * Tags out, script and style with what is inside them; a typed field as
 * its paragraphs.
 *
 * @since 1.0.0
 */
trait Markup {

	/**
	 * HTML as the text it shows.
	 *
	 * WordPress's wp_strip_all_tags() rule: a `<script>` or `<style>` goes
	 * with everything inside it -- PHP's strip_tags() would leave the code
	 * behind as text -- then every other tag goes and the ends are trimmed.
	 *
	 * The same answer as wp_strip_all_tags(), but not by calling it: its
	 * one pattern for the first step looks for the closing tag again from
	 * every opening one, so a few hundred kilobytes of `<script>` with no
	 * `</script>` -- a review, a product description -- cost seconds, and
	 * without PCRE's JIT came back as nothing at all.
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
		return trim( strip_tags( self::without_code( $html ) ) );
	}

	/**
	 * Markup with every `<script>` and `<style>` element taken out, what
	 * is inside it too.
	 *
	 * `<(script|style)[^>]*?>.*?</\1>`, case-insensitive, walked by hand: an
	 * opening tag is closed by the first closing tag of its name after it,
	 * and one that never is stays for strip_tags(). A closing tag that is
	 * not there is looked for once, not once for every opening tag before
	 * it, so the cost is what reading the markup costs.
	 *
	 * @param string $html Markup.
	 *
	 * @return string
	 */
	private static function without_code( string $html ): string {
		$text = '';
		$kept = 0;
		$from = 0;

		// Whether a closing tag of the name can still be ahead.
		$closes = array(
			'script' => true,
			'style'  => true,
		);

		while ( 1 === preg_match( '/<(script|style)/i', $html, $open, PREG_OFFSET_CAPTURE, $from ) ) {
			$start = $open[0][1];
			$name  = strtolower( $open[1][0] );
			$end   = strpos( $html, '>', $start );

			// No tag from here on is ever finished, so none is removed.
			if ( false === $end ) {
				break;
			}

			$close = $closes[ $name ] ? stripos( $html, '</' . $name . '>', $end + 1 ) : false;

			if ( false === $close ) {
				$closes[ $name ] = false;
				$from            = $start + 1;
				continue;
			}

			$text .= substr( $html, $kept, $start - $kept );
			$kept  = $close + strlen( $name ) + 3;
			$from  = $kept;
		}

		return $text . substr( $html, $kept );
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
}
