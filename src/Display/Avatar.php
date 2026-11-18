<?php
/**
 * Deterministic initials avatars.
 *
 * @package   SugarCommerce\Text\Display
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Display;

use SugarCommerce\Text\Text;

/**
 * Class Avatar
 *
 * Two kinds of picture drawn from a name, with no request to anybody.
 *
 * `svg()` is a placeholder for an `<img src>`: white letters on a gradient
 * whose hue is worked out from the name, as a `data:` URI.
 *
 * `monogram()` is a person, inline: one or two letters on a pale tile, the
 * letters in a deep shade of the same hue, the pair taken from a
 * {@see Palette}. A Gravatar is a request to somebody else's server for
 * every face on the screen, and for most people what comes back is the same
 * grey silhouette; a monogram costs nothing to fetch and tells two people
 * apart at a glance. Three questions, answered separately so each can be
 * asked on its own: which letters (`initials()`), which colour
 * (`swatch()`), and what that looks like as markup (`monogram_svg()`).
 *
 * Deterministic: the same name always yields the same colour, so a list
 * does not reshuffle its palette as you scroll or paginate. The hue comes
 * from a hash of the name; saturation and lightness are fixed, which
 * keeps every generated colour in the same family instead of producing
 * the occasional neon.
 *
 * No network request, no upload, no file to store.
 *
 * @since 1.0.0
 */
final readonly class Avatar {

	/**
	 * A monogram, from a person's name and address.
	 *
	 * ```php
	 * echo Avatar::monogram( 'Ada Lovelace', 'ada@example.com', 32, array(
	 *     'palette' => Palette::sweet(),
	 *     'class'   => 'myplugin-avatar',
	 * ) );
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param string               $name  Their name.
	 * @param string               $email Their address: picks the colour, and the
	 *                                    letter when the name has none.
	 * @param int                  $size  Width and height, in pixels.
	 * @param array<string, mixed> $args  `palette` (default {@see Palette::sweet()}),
	 *                                    plus what {@see self::monogram_svg()} takes:
	 *                                    `label`, `class`, `style`.
	 *
	 * @return string Escaped markup.
	 */
	public static function monogram( string $name, string $email = '', int $size = 32, array $args = array() ): string {
		$palette = isset( $args['palette'] ) && is_array( $args['palette'] ) ? $args['palette'] : null;

		return self::monogram_svg( self::initials( $name, $email ), self::swatch( $email, $name, $palette ), $size, $args );
	}

	/**
	 * One or two letters for a person.
	 *
	 * The first letter of the first word of their name and of the last,
	 * because a middle name is not what anybody is known by; one letter
	 * for a name of one word. A word counts from its first letter or
	 * digit, so a name in quotes or brackets still has its own initial,
	 * and a word with neither -- an emoji, a dash -- is not a word. A name
	 * that comes to nothing gives way to the address, which gives the one
	 * letter that starts it.
	 *
	 * Capitals where the script has them. One that does not (Chinese,
	 * Arabic) is drawn as it is written.
	 *
	 * Unlike {@see Text::initials()}, which answers `?`, nothing to go on
	 * is '': the monogram then draws the figure of a person.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  Their name.
	 * @param string $email Their address, for when the name has no letters.
	 *
	 * @return string One or two characters, or '' when there is nothing to go on.
	 */
	public static function initials( string $name, string $email = '' ): string {
		$words = array();

		foreach ( (array) preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			// From its first letter or digit on. Whatever is in front of
			// that is punctuation or a picture.
			$word = (string) preg_replace( '/^[^\p{L}\p{N}]+/u', '', (string) $word );

			if ( '' !== $word ) {
				$words[] = $word;
			}
		}

		if ( array() !== $words ) {
			return Text::initials( implode( ' ', $words ) );
		}

		// Before the @, and only its first letter: "ada.lovelace" is one
		// address, not two names.
		$local = (string) preg_replace( '/@.*$/su', '', trim( $email ) );

		if ( 1 === preg_match( '/[\p{L}\p{N}]/u', $local, $found ) ) {
			return mb_strtoupper( $found[0] );
		}

		return '';
	}

	/**
	 * The colour that is this person's, on every screen and every visit.
	 *
	 * Picked from the palette ({@see Palette::pick()}) by their address, in
	 * small letters and trimmed, so Ada@Example.com and ada@example.com are
	 * one person; by their name when there is no address. Nobody at all is
	 * {@see Palette::NEUTRAL}.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                   $email   Their address.
	 * @param string                                   $name    Their name, used when there is no address.
	 * @param array<string, array<string, string>>|null $palette The colours, by key. Default {@see Palette::sweet()}.
	 *
	 * @return array{name: string, tint: string, shade: string, key: string} `key` is '' for nobody.
	 */
	public static function swatch( string $email, string $name = '', ?array $palette = null ): array {
		$who = mb_strtolower( trim( $email ) );
		$who = '' !== $who ? $who : mb_strtolower( trim( $name ) );

		$palette ??= Palette::sweet();
		$key       = Palette::pick( $palette, $who );

		if ( '' === $key ) {
			return Palette::NEUTRAL + array( 'key' => '' );
		}

		return $palette[ $key ] + array( 'key' => $key );
	}

	/**
	 * A monogram as an inline SVG.
	 *
	 * Inline, so there is no request for it and it is sharp at any size
	 * and on any screen. The letters are text in the page's own font, not
	 * outlines, so they match what is written beside them.
	 *
	 * It carries the `avatar avatar-{size}` classes WordPress gives a
	 * Gravatar, so whatever a stylesheet says about an avatar in a list --
	 * the corner above all -- it says about this as well. The corner is
	 * repeated inline for the places no such stylesheet reaches.
	 *
	 * With no letters it is a figure of a person: a head and shoulders,
	 * the shape every "nobody yet" has.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $initials From initials(); may be empty.
	 * @param array{tint: string, shade: string} $colour   From swatch().
	 * @param int                                $size     Width and height, in pixels, 12 to 512.
	 * @param array<string, mixed>               $args     {
	 *     @type string $label Who it is, when nothing printed beside it says so. Without
	 *                         one it is hidden from screen readers: the name is nearly
	 *                         always beside it, and told twice they have learned nothing.
	 *     @type string $class Classes, before `avatar avatar-{size}`.
	 *     @type string $style CSS, after the corner.
	 * }
	 *
	 * @return string Escaped markup.
	 */
	public static function monogram_svg( string $initials, array $colour, int $size = 32, array $args = array() ): string {
		$size  = max( 12, min( 512, $size ) );
		$label = trim( (string) ( $args['label'] ?? '' ) );
		$half  = $size / 2;
		$shade = self::esc( (string) ( $colour['shade'] ?? Palette::NEUTRAL['shade'] ) );
		$tint  = self::esc( (string) ( $colour['tint'] ?? Palette::NEUTRAL['tint'] ) );

		$spoken = '' !== $label
			? 'role="img" aria-label="' . self::esc( $label ) . '"'
			: 'role="img" aria-hidden="true"';

		if ( '' !== $initials ) {
			// Two letters at two fifths of the tile, one a little larger:
			// both fill about the same share of it.
			$letters = sprintf(
				'<text x="50%%" y="50%%" dy=".35em" text-anchor="middle" font-size="%s" font-weight="600" fill="%s">%s</text>',
				(string) round( $size * ( mb_strlen( $initials ) > 1 ? 0.4 : 0.46 ), 1 ),
				$shade,
				self::esc( $initials )
			);
		} else {
			// A head, and shoulders cut off by the bottom of the tile.
			$letters = sprintf(
				'<g fill="%1$s"><circle cx="%2$s" cy="%3$s" r="%4$s"/><path d="M%5$s %6$s a%7$s %7$s 0 0 1 %8$s 0 z"/></g>',
				$shade,
				(string) $half,
				(string) round( $size * 0.38, 2 ),
				(string) round( $size * 0.17, 2 ),
				(string) round( $size * 0.2, 2 ),
				(string) $size,
				(string) round( $size * 0.3, 2 ),
				(string) round( $size * 0.6, 2 )
			);
		}

		return sprintf(
			'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 %2$d %2$d" %3$s focusable="false" style="%4$s"><rect width="%2$d" height="%2$d" fill="%5$s"/>%6$s</svg>',
			self::esc( trim( (string) ( $args['class'] ?? '' ) . ' avatar avatar-' . $size ) ),
			$size,
			$spoken,
			self::esc( trim( 'border-radius:20%;overflow:hidden;vertical-align:middle;' . (string) ( $args['style'] ?? '' ), ';' ) ),
			$tint,
			$letters
		);
	}

	/**
	 * Build an avatar.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name       Source text.
	 * @param int    $size       Width and height in pixels.
	 * @param int    $saturation Colour saturation, 0-100.
	 * @param int    $lightness  Colour lightness, 0-100.
	 * @param bool   $from_start Derive initials from the first two words
	 *                           rather than the first and last — right
	 *                           for a product title, wrong for a person.
	 *                           See {@see Text::initials()}.
	 *
	 * @return string A `data:image/svg+xml` URI.
	 */
	public static function svg( string $name, int $size = 200, int $saturation = 55, int $lightness = 42, bool $from_start = false ): string {
		$size       = max( 16, min( 1024, $size ) );
		$saturation = max( 0, min( 100, $saturation ) );
		$lightness  = max( 0, min( 100, $lightness ) );

		$initials = Text::initials( $name, $from_start );
		$hue      = self::hue( $name );

		// The gradient's second stop is offset around the wheel and
		// darkened, which reads as depth without needing a second input.
		$from = sprintf( 'hsl(%d,%d%%,%d%%)', $hue, $saturation, $lightness );
		$to   = sprintf( 'hsl(%d,%d%%,%d%%)', ( $hue + 40 ) % 360, $saturation, max( 0, $lightness - 10 ) );

		// The initials come from user data, so they are XML-escaped. The
		// whole document is then percent-encoded into the data URI, but
		// escaping here means the SVG is well-formed on its own too.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '"'
			. ' width="' . $size . '" height="' . $size . '">'
			. '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
			. '<stop offset="0" stop-color="' . $from . '"/>'
			. '<stop offset="1" stop-color="' . $to . '"/>'
			. '</linearGradient></defs>'
			. '<rect width="' . $size . '" height="' . $size . '" fill="url(#g)"/>'
			. '<text x="50%" y="50%" font-family="ui-sans-serif,system-ui,sans-serif"'
			. ' font-size="' . (int) ( $size * 0.4 ) . '" font-weight="600" fill="#ffffff"'
			. ' text-anchor="middle" dominant-baseline="central">'
			. htmlspecialchars( $initials, ENT_QUOTES | ENT_XML1, 'UTF-8' )
			. '</text></svg>';

		return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}

	/**
	 * The hue a name maps to.
	 *
	 * Exposed so an application can tint something else — a border, a
	 * chart series — to match the avatar beside it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Source text.
	 *
	 * @return int Degrees, 0-359.
	 */
	public static function hue( string $name ): int {
		return (int) ( hexdec( substr( hash( 'sha256', trim( $name ) ), 0, 4 ) ) % 360 );
	}

	/**
	 * The colour a name maps to, as CSS.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name       Source text.
	 * @param int    $saturation Saturation, 0-100.
	 * @param int    $lightness  Lightness, 0-100.
	 *
	 * @return string e.g. `hsl(214,55%,42%)`.
	 */
	public static function colour( string $name, int $saturation = 55, int $lightness = 42 ): string {
		return sprintf( 'hsl(%d,%d%%,%d%%)', self::hue( $name ), $saturation, $lightness );
	}

	/**
	 * Escaped for an attribute or text, as WordPress's esc_attr() does it.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	private static function esc( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
	}
}
