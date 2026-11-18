<?php
/**
 * The one colour helper: hex, rgb, rgba, contrast, ratio, readable.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests\Display;

use SugarCommerce\Text\Display\Color;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Color::class )]
final class ColorTest extends TestCase {

	/**
	 * Every spelling of one colour, and the one way it is written back.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function spellings(): array {
		return array(
			'upper case'      => array( '#E8A5C4', '#e8a5c4' ),
			'no hash'         => array( 'e8a5c4', '#e8a5c4' ),
			'short'           => array( '#abc', '#aabbcc' ),
			'short, no hash'  => array( 'ABC', '#aabbcc' ),
			'padded'          => array( '  #FFFFFF  ', '#ffffff' ),
			'already written' => array( '#6d28d9', '#6d28d9' ),
		);
	}

	#[DataProvider( 'spellings' )]
	public function test_a_colour_is_written_one_way( mixed $given, string $written ): void {
		$this->assertSame( $written, Color::hex( $given ) );
	}

	/**
	 * Not colours: names, CSS that survives escaping, wrong lengths, two
	 * hashes, and what is not a string at all.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function not_colours(): array {
		return array(
			'a name'          => array( 'red' ),
			'injected css'    => array( 'red;background:url(//evil)' ),
			'trailing junk'   => array( '#6d28d9;x' ),
			'empty'           => array( '' ),
			'a hash alone'    => array( '#' ),
			'two hashes'      => array( '##abc' ),
			'four digits'     => array( '#abcd' ),
			'seven digits'    => array( '#eeeeeee' ),
			'nine digits'     => array( '#aabbccdde' ),
			'trailing line'   => array( "#abcdef\n" . 'x' ),
			'rgb()'           => array( 'rgb(0,0,0)' ),
			'an array'        => array( array( '#6d28d9' ) ),
			'null'            => array( null ),
			'a number'        => array( 123 ),
		);
	}

	#[DataProvider( 'not_colours' )]
	public function test_what_is_not_a_colour_is_the_fallback( mixed $given ): void {
		$this->assertSame( '', Color::hex( $given ) );
		$this->assertSame( '#2271b1', Color::hex( $given, '#2271b1' ) );
	}

	public function test_rgb(): void {
		$this->assertSame( array( 232, 165, 196 ), Color::rgb( '#e8a5c4' ) );
		$this->assertSame( array( 170, 187, 204 ), Color::rgb( 'abc' ) );
		$this->assertSame( array( 0, 0, 0 ), Color::rgb( '#000' ) );
		$this->assertNull( Color::rgb( 'red' ) );
	}

	public function test_rgba(): void {
		$this->assertSame( 'rgba(255, 0, 0, 0.1)', Color::rgba( '#ff0000', 0.1 ) );
		$this->assertSame( 'rgba(170, 187, 204, 1)', Color::rgba( '#abc' ) );
		$this->assertSame( 'rgba(0, 0, 0, 0.5)', Color::rgba( '000000', 0.5 ) );
		$this->assertSame( 'rgba(0, 0, 0, 0)', Color::rgba( '#000', -2 ), 'Clamped to 0.' );
		$this->assertSame( 'rgba(0, 0, 0, 1)', Color::rgba( '#000', 7 ), 'Clamped to 1.' );
		$this->assertSame( '', Color::rgba( 'red;x', 0.5 ) );
	}

	/**
	 * WCAG, not YIQ: the grey where the two disagree gets black.
	 */
	public function test_contrast_is_black_or_white_by_wcag(): void {
		$this->assertSame( '#ffffff', Color::contrast( '#000000' ) );
		$this->assertSame( '#000000', Color::contrast( '#ffffff' ) );
		$this->assertSame( '#000000', Color::contrast( '#767676' ), 'YIQ says light too; WCAG ratios 4.54 vs 4.62.' );
		$this->assertSame( '#ffffff', Color::contrast( '#757575' ), 'Just darker: white wins.' );
		$this->assertSame( '#ffffff', Color::contrast( '#2271b1' ) );
		$this->assertSame( '#000000', Color::contrast( '#ffd700' ) );
		$this->assertSame( '#000000', Color::contrast( 'not a colour' ) );

		$this->assertTrue( Color::is_light( '#ffd700' ) );
		$this->assertFalse( Color::is_light( '#2271b1' ) );
	}

	public function test_ratio(): void {
		$this->assertEqualsWithDelta( 21.0, Color::ratio( '#000000', '#ffffff' ), 0.001 );
		$this->assertEqualsWithDelta( 21.0, Color::ratio( '#FFF', '#000' ), 0.001, 'Either order, short form.' );
		$this->assertEqualsWithDelta( 1.0, Color::ratio( '#777777', '#777777' ), 0.001 );
		$this->assertEqualsWithDelta( 4.48, Color::ratio( '#777777', '#ffffff' ), 0.01, 'The grey that just fails AA.' );
		$this->assertEqualsWithDelta( 4.0, Color::ratio( '#ff0000', '#ffffff' ), 0.01 );
		$this->assertNull( Color::ratio( 'red', '#ffffff' ) );
		$this->assertNull( Color::ratio( '#ffffff', 'red' ) );
	}

	public function test_readable_is_aa(): void {
		$this->assertFalse( Color::readable( '#777777', '#ffffff' ), '4.48 fails body text.' );
		$this->assertTrue( Color::readable( '#777777', '#ffffff', large: true ), 'and passes large text.' );
		$this->assertTrue( Color::readable( '#767676', '#ffffff' ), '4.54 passes.' );
		$this->assertFalse( Color::readable( '#ff0000', '#ffffff', large: false ) );
		$this->assertTrue( Color::readable( '#ff0000', '#ffffff', large: true ), '4.0 is over 3.' );
		$this->assertFalse( Color::readable( 'red', '#ffffff', large: true ) );
	}
}
