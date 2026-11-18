<?php
/**
 * Sparkline tests.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests\Display;

use SugarCommerce\Text\Display\Sparkline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Sparkline::class )]
final class SparklineTest extends TestCase {

	/**
	 * The highest figure touches the top and nought the bottom, inside the
	 * padding, evenly spaced.
	 */
	public function test_points(): void {
		$this->assertSame(
			array( array( 0.0, 18.0 ), array( 32.0, 9.0 ), array( 64.0, 0.0 ) ),
			Sparkline::points( array( 0, 5, 10 ) )
		);

		// Each side of its own.
		$this->assertSame(
			array( array( 0.0, 52.0 ), array( 300.0, 6.0 ) ),
			Sparkline::points(
				array( 0, 10 ),
				array(
					'width'   => 300,
					'height'  => 56,
					'padding' => array(
						'top'    => 6,
						'bottom' => 4,
					),
				)
			)
		);

		// One number for all four, as a list column draws it.
		$this->assertSame(
			array( array( 1.5, 16.5 ), array( 62.5, 1.5 ) ),
			Sparkline::points( array( 0, 3 ), array( 'padding' => 1.5 ) )
		);

		// Keys are ignored; what is not a number is nought.
		$this->assertSame( Sparkline::points( array( 0, 4 ) ), Sparkline::points( array( 'a' => 'x', 'b' => 4 ) ) );
		$this->assertSame( array(), Sparkline::points( array() ) );
	}

	/**
	 * Noughts are a flat line along the bottom, not a division by nought;
	 * the floor is the least the top stands for.
	 */
	public function test_floor(): void {
		$this->assertSame( array( array( 0.0, 18.0 ), array( 64.0, 18.0 ) ), Sparkline::points( array( 0, 0 ) ) );

		// Fractions under the default floor of 1 stay low...
		$this->assertSame( 9.0, Sparkline::points( array( 0, 0.5 ) )[1][1] );

		// ...and fill the height with no floor.
		$this->assertSame( 0.0, Sparkline::points( array( 0, 0.5 ), array( 'floor' => 0 ) )[1][1] );
		$this->assertSame( 18.0, Sparkline::points( array( 0, 0 ), array( 'floor' => 0 ) )[1][1] );
	}

	/**
	 * A figure below nought moves the bottom down to it.
	 */
	public function test_negative_figures(): void {
		$this->assertSame(
			array( array( 0.0, 18.0 ), array( 32.0, 9.0 ), array( 64.0, 0.0 ) ),
			Sparkline::points( array( -10, 0, 10 ) )
		);
	}

	/**
	 * The whole drawing, as a list column wants it.
	 */
	public function test_svg(): void {
		$svg = Sparkline::svg(
			array( 0, 5, 10 ),
			array(
				'id'     => 'g1',
				'class'  => 'trend',
				'colour' => '#2271b1',
				'label'  => 'Up & away',
				'title'  => true,
			)
		);

		$this->assertSame(
			'<svg class="trend" width="64" height="18" viewBox="0 0 64 18" role="img" aria-label="Up &amp; away" focusable="false">'
			. '<title>Up &amp; away</title>'
			. '<defs><linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">'
			. '<stop offset="0" stop-color="#2271b1" stop-opacity="0.28"/>'
			. '<stop offset="1" stop-color="#2271b1" stop-opacity="0"/>'
			. '</linearGradient></defs>'
			. '<path d="M0,18 L0,18 L32,9 L64,0 L64,18 Z" fill="url(#g1)"/>'
			. '<polyline points="0,18 32,9 64,0" fill="none" stroke="#2271b1" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>'
			. '</svg>',
			$svg
		);
	}

	/**
	 * Stretched to its box, a quieter line with the last step bold, three
	 * stops, classes on its parts and markup of the caller's inside.
	 */
	public function test_options(): void {
		$svg = Sparkline::svg(
			array( 1, 2, 3 ),
			array(
				'id'             => 'g2',
				'stretch'        => true,
				'stroke_width'   => 1.25,
				'stroke_opacity' => 0.75,
				'emphasise_last' => true,
				'fade'           => array( array( 0, 0.28 ), array( 0.6, 0.08 ), array( 1, 0 ) ),
				'area_class'     => 'area',
				'line_class'     => 'line',
				'inside'         => '<rect class="hit"/>',
			)
		);

		$this->assertStringStartsWith( '<svg viewBox="0 0 64 18" preserveAspectRatio="none" aria-hidden="true" focusable="false">', $svg );
		$this->assertStringNotContainsString( 'width="64"', $svg );
		$this->assertStringNotContainsString( '<title>', $svg );
		$this->assertStringContainsString( '<stop offset="0.6" stop-color="currentColor" stop-opacity="0.08"/>', $svg );
		$this->assertStringContainsString( '<path class="area" d=', $svg );
		$this->assertStringContainsString( '<polyline class="line" points="0,12 32,6 64,0" fill="none" stroke="currentColor" stroke-opacity="0.75" stroke-width="1.25" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>', $svg );
		$this->assertStringContainsString( '<polyline points="32,6 64,0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/>', $svg );
		$this->assertStringEndsWith( '<rect class="hit"/></svg>', $svg );

		// No fade, no fill.
		$plain = Sparkline::svg( array( 1, 2 ), array( 'fade' => array() ) );

		$this->assertStringNotContainsString( '<defs>', $plain );
		$this->assertStringNotContainsString( '<path', $plain );
		$this->assertStringContainsString( '<polyline', $plain );
	}

	/**
	 * One figure is not a line; a flat run can be skipped.
	 */
	public function test_nothing_to_draw(): void {
		$this->assertSame( '', Sparkline::svg( array() ) );
		$this->assertSame( '', Sparkline::svg( array( 5 ) ) );
		$this->assertNotSame( '', Sparkline::svg( array( 0, 0 ) ) );
		$this->assertSame( '', Sparkline::svg( array( 0, 0 ), array( 'skip_flat' => true ) ) );
		$this->assertSame( '', Sparkline::svg( array( -1, 0 ), array( 'skip_flat' => true ) ) );
		$this->assertNotSame( '', Sparkline::svg( array( 0, 1 ), array( 'skip_flat' => true ) ) );
	}

	/**
	 * Twenty on a page do not share a fill.
	 */
	public function test_each_has_its_own_gradient(): void {
		$ids = array();

		for ( $i = 0; $i < 20; $i++ ) {
			preg_match( '/linearGradient id="([^"]+)"/', Sparkline::svg( array( 1, 2 ), array( 'id_prefix' => 'shop-' ) ), $found );
			$ids[] = $found[1];
		}

		$this->assertCount( 20, array_unique( $ids ) );
		$this->assertStringStartsWith( 'shop-', $ids[0] );
		$this->assertStringContainsString( 'fill="url(#' . $ids[19] . ')"', Sparkline::svg( array( 1, 2 ), array( 'id' => $ids[19] ) ) );
	}

	/**
	 * What the caller passes is escaped, except `inside`.
	 */
	public function test_escaping(): void {
		$svg = Sparkline::svg(
			array( 1, 2 ),
			array(
				'id'     => 'a"b',
				'class'  => '"><script>',
				'colour' => 'red" onload="x',
			)
		);

		$this->assertStringNotContainsString( '<script>', $svg );
		$this->assertStringNotContainsString( '" onload="', $svg );
		$this->assertStringContainsString( 'id="a&quot;b"', $svg );
	}
}
