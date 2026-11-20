<?php
/**
 * A hex colour: read leniently, written one way, and black or white on top.
 *
 * @package   SugarCommerce\Text\Display
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Display;

/**
 * Class Color
 *
 * The one colour helper of the SugarCommerce packages. Fields, emails,
 * flyouts, pdf and the palette here each had their own copy -- six, with
 * two different rules for "is this colour light" -- and now ask this.
 *
 * Core sanitizes a hex value (`sanitize_hex_color()`) and stops there:
 * it keeps whichever spelling it was given, so `#ABC` and `#aabbcc` are two
 * different strings to a database, and it has no notion of contrast. Both
 * are what a stored colour needs, which is why this is here and why it is
 * only this. Lighten, darken and mix are theming, and nothing used them.
 *
 * Contrast is WCAG's relative luminance throughout, not the YIQ brightness
 * most pickers use. They disagree near the middle: YIQ calls #767676 light
 * and picks black, where the real ratios are 4.54 against white and 4.62
 * against black. WCAG is the one an accessibility audit uses.
 *
 * @since 1.0.0
 */
final readonly class Color {

	/**
	 * A colour as the one form to store and print: `#rrggbb`, lowercase.
	 *
	 * Lenient in what it reads and strict in what it returns. A colour is
	 * pasted as often as it is picked, and what gets pasted is `#E8A5C4`,
	 * `e8a5c4` or the short `#abc` a stylesheet uses -- all one colour, all
	 * returned as `#e8a5c4`, so two rows holding the same colour hold the
	 * same string.
	 *
	 * Anything else -- a name, `rgb()`, a hash on its own, a stray
	 * semicolon -- is the fallback rather than a guess. That matters because
	 * a colour is printed into a `style` attribute, where escaping does
	 * nothing useful: the dangerous input is `red;background:url(...)`.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value    A colour, or whatever was submitted for one.
	 * @param string $fallback What to return for anything that is not a colour.
	 *
	 * @return string `#rrggbb`, or the fallback.
	 */
	public static function hex( mixed $value, string $fallback = '' ): string {
		$rgb = is_string( $value ) ? self::rgb( $value ) : null;

		if ( null === $rgb ) {
			return $fallback;
		}

		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * A hex colour as red, green and blue, 0 to 255 each.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex A hex colour, with or without the hash, three or six digits.
	 *
	 * @return array{0: int, 1: int, 2: int}|null Null when it is not a colour.
	 */
	public static function rgb( string $hex ): ?array {
		$hex = trim( $hex );

		if ( str_starts_with( $hex, '#' ) ) {
			$hex = substr( $hex, 1 );
		}

		if ( 3 === strlen( $hex ) ) {
			// #abc is #aabbcc.
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 1 !== preg_match( '/^[0-9a-fA-F]{6}$/D', $hex ) ) {
			return null;
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * A hex colour as CSS `rgba()`, for a see-through version of it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex   A hex colour.
	 * @param float  $alpha Opacity, 0 to 1; clamped.
	 *
	 * @return string `rgba(r, g, b, a)`, or '' when it is not a colour.
	 */
	public static function rgba( string $hex, float $alpha = 1.0 ): string {
		$rgb = self::rgb( $hex );

		if ( null === $rgb ) {
			return '';
		}

		$alpha = max( 0.0, min( 1.0, $alpha ) );

		return sprintf( 'rgba(%d, %d, %d, %s)', $rgb[0], $rgb[1], $rgb[2], rtrim( rtrim( number_format( $alpha, 3, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * Black or white, whichever reads better on this background.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex A hex colour.
	 *
	 * @return string `#000000` or `#ffffff`; black when it is not a colour.
	 */
	public static function contrast( string $hex ): string {
		$rgb = self::rgb( $hex );

		if ( null === $rgb ) {
			return '#000000';
		}

		$luminance = self::luminance( $rgb );

		// The ratio against white, and against black.
		$against_white = 1.05 / ( $luminance + 0.05 );
		$against_black = ( $luminance + 0.05 ) / 0.05;

		return $against_black >= $against_white ? '#000000' : '#ffffff';
	}

	/**
	 * Whether dark text belongs on this colour: {@see self::contrast()} picks black.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex A hex colour.
	 *
	 * @return bool
	 */
	public static function is_light( string $hex ): bool {
		return '#000000' === self::contrast( $hex );
	}

	/**
	 * The contrast ratio between two colours: 1 for the same colour, 21 for
	 * black on white.
	 *
	 * @since 1.0.0
	 *
	 * @param string $one A hex colour.
	 * @param string $two Another.
	 *
	 * @return float|null Null when either is not a colour.
	 */
	public static function ratio( string $one, string $two ): ?float {
		$first  = self::rgb( $one );
		$second = self::rgb( $two );

		if ( null === $first || null === $second ) {
			return null;
		}

		$first  = self::luminance( $first );
		$second = self::luminance( $second );

		return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
	}

	/**
	 * Whether two colours meet WCAG AA: 4.5:1 for body text, 3:1 for large
	 * text (18pt, or 14pt bold) and for the parts of a control.
	 *
	 * @since 1.0.0
	 *
	 * @param string $one   A hex colour.
	 * @param string $two   Another.
	 * @param bool   $large Whether the text is large.
	 *
	 * @return bool False when either is not a colour.
	 */
	public static function readable( string $one, string $two, bool $large = false ): bool {
		$ratio = self::ratio( $one, $two );

		return null !== $ratio && $ratio >= ( $large ? 3.0 : 4.5 );
	}

	/**
	 * Relative luminance, as WCAG defines it.
	 *
	 * Gamma-corrected, which is the part the YIQ approximation leaves out
	 * and the reason the two disagree near the middle of the range.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb The colour.
	 *
	 * @return float 0 for black, 1 for white.
	 */
	private static function luminance( array $rgb ): float {
		$channels = array();

		foreach ( $rgb as $value ) {
			$channel = $value / 255;

			$channels[] = $channel <= 0.04045
				? $channel / 12.92
				: ( ( $channel + 0.055 ) / 1.055 ) ** 2.4;
		}

		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}
}
