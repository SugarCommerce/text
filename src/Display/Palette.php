<?php
/**
 * Colours in pairs: a pale tint and a deep shade of the same hue.
 *
 * @package   SugarCommerce\Text\Display
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Display;

/**
 * Class Palette
 *
 * A palette here is a list of colours by key, each a `name` for people and
 * two hex colours of one hue: a `tint` pale enough to sit behind text and a
 * `shade` deep enough to be that text. White letters on a pastel cannot be
 * read, so the letters are the shade, and a pair is only worth keeping when
 * the shade can be read on its tint -- {@see Color::ratio()} measures it.
 *
 * Something that needs "one of several colours" -- a person's monogram, a
 * chart series, a tag -- takes one from here, so the accents of a plugin
 * come from one list. {@see self::pick()} gives the same thing the same
 * colour every time.
 *
 * Plain PHP. Where the list comes from (a constant, an option, a filter) is
 * the application's business; {@see self::clean()} makes whatever arrives
 * of it safe to draw with.
 *
 * @since 1.0.0
 */
final class Palette {

	/**
	 * The colour of nobody: a grey pair, no hue.
	 *
	 * A colour says "this one", and there is no one.
	 *
	 * @since 1.0.0
	 */
	public const NEUTRAL = array(
		'name'  => '',
		'tint'  => '#e9eaee',
		'shade' => '#5d6470',
	);

	/**
	 * Twelve sweet-shop colours.
	 *
	 * In the order of a colour wheel, starting at red, so neighbours in the
	 * list are neighbours in hue and anything that walks it in order (a
	 * chart with several series) gets a rainbow and not a jumble. Every pair
	 * is better than 4.5 to 1 against itself: WCAG AA for body text.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{name: string, tint: string, shade: string}> By key.
	 */
	public static function sweet(): array {
		return array(
			'strawberry' => array(
				'name'  => __( 'Strawberry', 'sugarcommerce' ),
				'tint'  => '#ffdde1',
				'shade' => '#a6283d',
			),
			'cherry'     => array(
				'name'  => __( 'Cherry', 'sugarcommerce' ),
				'tint'  => '#f9cfd6',
				'shade' => '#8c1730',
			),
			'peach'      => array(
				'name'  => __( 'Peach', 'sugarcommerce' ),
				'tint'  => '#ffe0cf',
				'shade' => '#9a4520',
			),
			'apricot'    => array(
				'name'  => __( 'Apricot', 'sugarcommerce' ),
				'tint'  => '#ffe7c2',
				'shade' => '#875000',
			),
			'lemon'      => array(
				'name'  => __( 'Lemon', 'sugarcommerce' ),
				'tint'  => '#fff3b5',
				'shade' => '#6b5900',
			),
			'lime'       => array(
				'name'  => __( 'Lime', 'sugarcommerce' ),
				'tint'  => '#e2f4b8',
				'shade' => '#446600',
			),
			'mint'       => array(
				'name'  => __( 'Mint', 'sugarcommerce' ),
				'tint'  => '#cff3e2',
				'shade' => '#126b4c',
			),
			'sky'        => array(
				'name'  => __( 'Sky', 'sugarcommerce' ),
				'tint'  => '#d0ecfb',
				'shade' => '#0d5e8c',
			),
			'blueberry'  => array(
				'name'  => __( 'Blueberry', 'sugarcommerce' ),
				'tint'  => '#d7dffb',
				'shade' => '#2f43a3',
			),
			'lavender'   => array(
				'name'  => __( 'Lavender', 'sugarcommerce' ),
				'tint'  => '#e6defa',
				'shade' => '#5b3ea8',
			),
			'grape'      => array(
				'name'  => __( 'Grape', 'sugarcommerce' ),
				'tint'  => '#ecd5f5',
				'shade' => '#782b92',
			),
			'bubblegum'  => array(
				'name'  => __( 'Bubblegum', 'sugarcommerce' ),
				'tint'  => '#ffd8ec',
				'shade' => '#a3246c',
			),
		);
	}

	/**
	 * A palette as it arrived, made safe to draw with.
	 *
	 * For a list that came through a filter or an option. A colour is kept
	 * when it has a `tint` and a `shade` that are hex colours, written the
	 * one way {@see Color::hex()} writes them (`#aabbcc`, lowercase, from
	 * `#ABC`, `aabbcc` or `#aabbcc`); its `name` is the key when it has none. Anything else is left out. An answer with
	 * nothing left in it is answered with the fallback: something has to be
	 * drawn in a colour.
	 *
	 * Contrast is not checked here -- a darker brand pair that a site chose
	 * on purpose is theirs to choose. Check it in a test with
	 * {@see Color::readable()}.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed                                                          $palette  What arrived.
	 * @param array<string, array{name: string, tint: string, shade: string}> $fallback What to use when none of it is usable.
	 *
	 * @return array<string, array{name: string, tint: string, shade: string}>
	 */
	public static function clean( mixed $palette, array $fallback = array() ): array {
		$kept = array();

		foreach ( is_array( $palette ) ? $palette : array() as $key => $colour ) {
			if ( ! is_array( $colour ) ) {
				continue;
			}

			$tint  = Color::hex( $colour['tint'] ?? '' );
			$shade = Color::hex( $colour['shade'] ?? '' );

			if ( '' === $tint || '' === $shade ) {
				continue;
			}

			$name = $colour['name'] ?? $key;

			$kept[ (string) $key ] = array(
				'name'  => is_scalar( $name ) ? (string) $name : (string) $key,
				'tint'  => $tint,
				'shade' => $shade,
			);
		}

		return array() === $kept ? $fallback : $kept;
	}

	/**
	 * The key of the colour that is something's own, on every screen and
	 * every visit.
	 *
	 * Picked by counting along the list with crc32 of the seed, which is
	 * the same number on every machine and every PHP -- all that is asked of
	 * it, since nothing here is a secret. The seed is used as given: lower-
	 * case and trim it first when `Ada@Example.com` and `ada@example.com`
	 * are one person ({@see Avatar::swatch()} does).
	 *
	 * A thing keeps its colour only while the palette keeps its length and
	 * its order.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $palette The colours, by key.
	 * @param string               $seed    What the colour belongs to.
	 *
	 * @return string The key, or '' for an empty palette or an empty seed.
	 */
	public static function pick( array $palette, string $seed ): string {
		if ( array() === $palette || '' === $seed ) {
			return '';
		}

		$keys = array_keys( $palette );

		return (string) $keys[ crc32( $seed ) % count( $keys ) ];
	}
}
