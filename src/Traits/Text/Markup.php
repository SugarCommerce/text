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
}
