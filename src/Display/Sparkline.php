<?php
/**
 * A small line of figures over time, as an inline SVG.
 *
 * @package   SugarCommerce\Text\Display
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Display;

/**
 * Class Sparkline
 *
 * A line with a fading fill under it: a product's sales in a list column, a
 * month of revenue on a dashboard, what a discount has earned in a panel.
 *
 * Drawn as markup rather than by a charting script. A script is hundreds of
 * kilobytes and draws to a canvas from data handed to it in the page; a
 * list row or a panel loaded over REST is markup, and loading a library on
 * every screen for one small picture costs more than the picture is worth.
 * This is a polyline and a gradient, and weighs nothing.
 *
 * Each line is its own scale: the highest figure touches the top, so a
 * product's line is its own shape and not flattened by a bestseller beside
 * it. Nought is the bottom, unless a figure is below it.
 *
 * Every drawing gets a gradient id of its own. Twenty sparklines on a page
 * that share one id fight over one fill -- and a counter is not enough when
 * markup arrives in pieces over REST, each piece counting from one again --
 * so the id ends in random characters unless the caller names it.
 *
 * Plain PHP; everything printed is escaped here.
 *
 * @since 1.0.0
 */
final readonly class Sparkline {

	/**
	 * What a drawing is unless told otherwise.
	 *
	 * @since 1.0.0
	 */
	private const array DEFAULTS = array(
		'width'          => 64,
		'height'         => 18,
		'padding'        => 0,
		'floor'          => 1,
		'colour'         => 'currentColor',
		'stroke_width'   => 2,
		'stroke_opacity' => 1,
		'emphasise_last' => false,
		'last_width'     => 2,
		'fade'           => array( array( 0, 0.28 ), array( 1, 0 ) ),
		'stretch'        => false,
		'skip_flat'      => false,
		'label'          => '',
		'title'          => false,
		'class'          => '',
		'area_class'     => '',
		'line_class'     => '',
		'id'             => '',
		'id_prefix'      => 'sparkline-',
		'inside'         => '',
	);

	/**
	 * A sparkline.
	 *
	 * ```php
	 * // In a list column, sixty-four by eighteen, the last step in bold.
	 * Sparkline::svg( $weekly, array(
	 *     'label'          => 'Up 12% on the four weeks before',
	 *     'title'          => true,
	 *     'colour'         => '#2271b1',
	 *     'padding'        => 1.5,
	 *     'stroke_width'   => 1.25,
	 *     'stroke_opacity' => 0.75,
	 *     'emphasise_last' => true,
	 * ) );
	 *
	 * // Across a card, stretched to its width by CSS.
	 * Sparkline::svg( $daily, array( 'width' => 300, 'height' => 56, 'stretch' => true ) );
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param array<array-key, int|float> $values Figures, oldest first. Keys are ignored.
	 * @param array<string, mixed>        $args   {
	 *     @type int          $width          Drawing width, in its own units. Default 64.
	 *     @type int          $height         Drawing height. Default 18.
	 *     @type float|array  $padding        Room inside the edges: one number, or
	 *                                        `top`, `right`, `bottom`, `left`. Default 0.
	 *     @type float        $floor          The least the top of the drawing stands for,
	 *                                        so a run of noughts is a flat line and not a
	 *                                        division by nought. Default 1; 0 for figures
	 *                                        that are all fractions.
	 *     @type string       $colour         Any CSS colour. Default `currentColor`, the
	 *                                        text colour around it.
	 *     @type float        $stroke_width   The line's width. Default 2.
	 *     @type float        $stroke_opacity The line's opacity. Default 1.
	 *     @type bool         $emphasise_last Draw the last step again, at `last_width` and
	 *                                        full opacity: where it is heading now.
	 *     @type float        $last_width     Default 2.
	 *     @type array        $fade           Stops of the fill under the line, top to
	 *                                        bottom, as `[ offset, opacity ]` pairs.
	 *                                        Default `[ [0, .28], [1, 0] ]`; empty for no fill.
	 *     @type bool         $stretch        Fill whatever box CSS gives it: no width or
	 *                                        height of its own, no kept aspect ratio, and a
	 *                                        line that keeps its width while stretched.
	 *     @type bool         $skip_flat      Nothing at all when no figure is above nought.
	 *     @type string       $label          What it shows, in words, for a screen reader.
	 *                                        Without one it is hidden from them.
	 *     @type bool         $title          Also a `<title>`, the tooltip of the label.
	 *     @type string       $class          Classes on the `<svg>`.
	 *     @type string       $area_class     Classes on the fill.
	 *     @type string       $line_class     Classes on the line.
	 *     @type string       $id             The gradient's id. Generated when empty.
	 *     @type string       $id_prefix      What a generated id starts with.
	 *     @type string       $inside         Markup added inside the `<svg>`, after the
	 *                                        line: hit areas, a rule. NOT escaped.
	 * }
	 *
	 * @return string Markup; empty with fewer than two figures (one is not a line).
	 */
	public static function svg( array $values, array $args = array() ): string {
		$args   = array_merge( self::DEFAULTS, $args );
		$values = self::numbers( $values );

		if ( count( $values ) < 2 ) {
			return '';
		}

		if ( $args['skip_flat'] && max( $values ) <= 0 ) {
			return '';
		}

		$width  = max( 1, (int) $args['width'] );
		$height = max( 1, (int) $args['height'] );
		$points = self::points( $values, $args );
		$line   = implode( ' ', array_map( static fn( array $point ): string => $point[0] . ',' . $point[1], $points ) );
		$last   = end( $points );
		$id     = '' !== (string) $args['id'] ? (string) $args['id'] : $args['id_prefix'] . bin2hex( random_bytes( 4 ) );
		$colour = self::attr( (string) $args['colour'] );
		$label  = trim( (string) $args['label'] );

		$stretch = (bool) $args['stretch'];
		$scaling = $stretch ? ' vector-effect="non-scaling-stroke"' : '';

		$svg = '<svg' . self::class_attr( (string) $args['class'] );

		if ( ! $stretch ) {
			$svg .= ' width="' . $width . '" height="' . $height . '"';
		}

		$svg .= ' viewBox="0 0 ' . $width . ' ' . $height . '"';
		$svg .= $stretch ? ' preserveAspectRatio="none"' : '';
		$svg .= '' !== $label
			? ' role="img" aria-label="' . self::attr( $label ) . '"'
			: ' aria-hidden="true"';
		$svg .= ' focusable="false">';

		if ( '' !== $label && $args['title'] ) {
			$svg .= '<title>' . self::attr( $label ) . '</title>';
		}

		$stops = '';

		foreach ( is_array( $args['fade'] ) ? $args['fade'] : array() as $stop ) {
			if ( ! is_array( $stop ) || ! isset( $stop[0], $stop[1] ) ) {
				continue;
			}

			$stops .= '<stop offset="' . self::number( (float) $stop[0] ) . '" stop-color="' . $colour . '" stop-opacity="' . self::number( (float) $stop[1] ) . '"/>';
		}

		if ( '' !== $stops ) {
			// The fill closes along the bottom edge, under the first point and
			// the last, so it starts and ends where the line does.
			$area = 'M' . $points[0][0] . ',' . $height . ' L' . implode( ' L', explode( ' ', $line ) ) . ' L' . $last[0] . ',' . $height . ' Z';

			$svg .= '<defs><linearGradient id="' . self::attr( $id ) . '" x1="0" y1="0" x2="0" y2="1">' . $stops . '</linearGradient></defs>';
			$svg .= '<path' . self::class_attr( (string) $args['area_class'] ) . ' d="' . $area . '" fill="url(#' . self::attr( $id ) . ')"/>';
		}

		$opacity = (float) $args['stroke_opacity'];

		$svg .= '<polyline' . self::class_attr( (string) $args['line_class'] ) . ' points="' . $line . '" fill="none" stroke="' . $colour . '"'
			. ( $opacity < 1 ? ' stroke-opacity="' . self::number( $opacity ) . '"' : '' )
			. ' stroke-width="' . self::number( (float) $args['stroke_width'] ) . '" stroke-linejoin="round" stroke-linecap="round"' . $scaling . '/>';

		if ( $args['emphasise_last'] ) {
			$before = $points[ count( $points ) - 2 ];

			$svg .= '<polyline points="' . $before[0] . ',' . $before[1] . ' ' . $last[0] . ',' . $last[1] . '" fill="none" stroke="' . $colour . '"'
				. ' stroke-width="' . self::number( (float) $args['last_width'] ) . '" stroke-linecap="round"' . $scaling . '/>';
		}

		return $svg . (string) $args['inside'] . '</svg>';
	}

	/**
	 * Where each figure is drawn, in the drawing's own units.
	 *
	 * The same sums svg() draws with, for a caller that puts something at
	 * each point -- a hit area, a dot -- in the same picture.
	 *
	 * @since 1.0.0
	 *
	 * @param array<array-key, int|float> $values Figures, oldest first.
	 * @param array<string, mixed>        $args   `width`, `height`, `padding` and
	 *                                            `floor`, as svg() takes them.
	 *
	 * @return list<array{0: float, 1: float}> `[ x, y ]` a figure, rounded to
	 *                                         two places; y grows downwards.
	 */
	public static function points( array $values, array $args = array() ): array {
		$args   = array_merge( self::DEFAULTS, $args );
		$values = self::numbers( $values );
		$count  = count( $values );

		if ( 0 === $count ) {
			return array();
		}

		$width  = max( 1, (int) $args['width'] );
		$height = max( 1, (int) $args['height'] );
		$pad    = self::padding( $args['padding'] );

		// Nought at the bottom unless something is below it; the highest
		// figure, or the floor, at the top.
		$low  = min( 0.0, min( $values ) );
		$high = max( max( $values ), $low + max( 0.0, (float) $args['floor'] ) );
		$span = $high - $low;
		$room = $height - $pad['top'] - $pad['bottom'];
		$step = $count > 1 ? ( $width - $pad['left'] - $pad['right'] ) / ( $count - 1 ) : 0.0;

		$points = array();

		foreach ( $values as $index => $value ) {
			$share    = $span > 0 ? ( $value - $low ) / $span : 0.0;
			$points[] = array(
				round( $pad['left'] + $index * $step, 2 ),
				round( $height - $pad['bottom'] - $share * $room, 2 ),
			);
		}

		return $points;
	}

	/**
	 * The figures as a list of floats; anything not a number is nought.
	 *
	 * @param array<array-key, mixed> $values Figures.
	 *
	 * @return list<float>
	 */
	private static function numbers( array $values ): array {
		return array_map( static fn( mixed $value ): float => is_numeric( $value ) ? (float) $value : 0.0, array_values( $values ) );
	}

	/**
	 * Padding as four sides.
	 *
	 * @param mixed $padding One number, or an array of sides.
	 *
	 * @return array{top: float, right: float, bottom: float, left: float}
	 */
	private static function padding( mixed $padding ): array {
		if ( is_array( $padding ) ) {
			return array(
				'top'    => (float) ( $padding['top'] ?? 0 ),
				'right'  => (float) ( $padding['right'] ?? 0 ),
				'bottom' => (float) ( $padding['bottom'] ?? 0 ),
				'left'   => (float) ( $padding['left'] ?? 0 ),
			);
		}

		$all = is_numeric( $padding ) ? (float) $padding : 0.0;

		return array(
			'top'    => $all,
			'right'  => $all,
			'bottom' => $all,
			'left'   => $all,
		);
	}

	/**
	 * A number as an attribute writes it: `0.28`, `2`, never `2.0`.
	 *
	 * @param float $number The number.
	 *
	 * @return string
	 */
	private static function number( float $number ): string {
		return (string) round( $number, 4 );
	}

	/**
	 * ` class="…"`, or nothing.
	 *
	 * @param string $classes Classes.
	 *
	 * @return string
	 */
	private static function class_attr( string $classes ): string {
		$classes = trim( $classes );

		return '' === $classes ? '' : ' class="' . self::attr( $classes ) . '"';
	}

	/**
	 * Escaped for an attribute or text, as WordPress's esc_attr() does it.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	private static function attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
	}
}
