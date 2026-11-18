<?php
/**
 * Monogram avatars and the palette they are drawn from.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests\Display;

use SugarCommerce\Text\Display\Avatar;
use SugarCommerce\Text\Numbers;
use SugarCommerce\Text\Display\Palette;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Avatar::class )]
#[CoversClass( Palette::class )]
#[CoversClass( Numbers::class )]
final class MonogramTest extends TestCase {

	/**
	 * Names, addresses and the letters they make.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function names(): array {
		return array(
			'first and last'       => array( 'Ada Lovelace', '', 'AL' ),
			'middle name skipped'  => array( 'Ada King Lovelace', '', 'AL' ),
			'one word'             => array( 'Ada', '', 'A' ),
			'small letters'        => array( 'ada lovelace', '', 'AL' ),
			'quoted'               => array( '"Ada" (Lovelace)', '', 'AL' ),
			'emoji word skipped'   => array( 'Ada 🎉 Lovelace 🎉', '', 'AL' ),
			'hyphen splits words'  => array( 'Mary Smith-Jones', '', 'MJ' ),
			'accented'             => array( 'élodie ørsted', '', 'ÉØ' ),
			'no capitals'          => array( '李 小龍', '', '李小' ),
			'digits count'         => array( '3M Company', '', '3C' ),
			'falls to the address' => array( '', 'ada.lovelace@example.test', 'A' ),
			'emoji only, address'  => array( '🎉', '_bob@example.test', 'B' ),
			'nothing'              => array( '', '', '' ),
			'nothing but an emoji' => array( '🎉', '', '' ),
			'address of symbols'   => array( '', '__@example.test', '' ),
		);
	}

	/**
	 * The first letter of the first and last word, or of the address.
	 *
	 * @param string $name     Name.
	 * @param string $email    Address.
	 * @param string $expected Letters.
	 */
	#[DataProvider( 'names' )]
	public function test_the_letters( string $name, string $email, string $expected ): void {
		$this->assertSame( $expected, Avatar::initials( $name, $email ) );
	}

	/**
	 * Twelve colours, in wheel order, and the letters of each can be read
	 * on its tile: WCAG AA for body text, 4.5 to 1.
	 */
	public function test_every_sweet_pair_can_be_read(): void {
		$palette = Palette::sweet();

		$this->assertSame(
			array( 'strawberry', 'cherry', 'peach', 'apricot', 'lemon', 'lime', 'mint', 'sky', 'blueberry', 'lavender', 'grape', 'bubblegum' ),
			array_keys( $palette )
		);

		foreach ( $palette as $key => $colour ) {
			$this->assertNotSame( '', $colour['name'], $key );
			$this->assertGreaterThanOrEqual( 4.5, Palette::contrast( $colour['tint'], $colour['shade'] ), $key );
		}

		$this->assertGreaterThanOrEqual( 4.5, Palette::contrast( Palette::NEUTRAL['tint'], Palette::NEUTRAL['shade'] ) );
		$this->assertSame( $palette, Palette::clean( $palette ), 'The sweet palette is clean as it is.' );
	}

	/**
	 * The contrast ratio is WCAG's.
	 */
	public function test_contrast(): void {
		$this->assertEqualsWithDelta( 21.0, Palette::contrast( '#000000', '#ffffff' ), 0.001 );
		$this->assertEqualsWithDelta( 21.0, Palette::contrast( '#FFF', '#000' ), 0.001, 'Either order, short form.' );
		$this->assertEqualsWithDelta( 1.0, Palette::contrast( '#777777', '#777777' ), 0.001 );
		$this->assertEqualsWithDelta( 4.48, Palette::contrast( '#777777', '#ffffff' ), 0.01, 'The grey that just fails AA.' );
		$this->assertEqualsWithDelta( 4.0, Palette::contrast( '#ff0000', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, Palette::contrast( 'red', '#ffffff' ), 0.001, 'Not a hex colour is no contrast.' );
	}

	/**
	 * What is not a colour is left out; nothing usable is the fallback.
	 */
	public function test_clean(): void {
		$cleaned = Palette::clean(
			array(
				'liquorice' => array(
					'name'  => 'Liquorice',
					'tint'  => '#EEEEEE',
					'shade' => '#111',
				),
				'unnamed'   => array(
					'tint'  => '#eeeeee',
					'shade' => '#111111',
				),
				'broken'    => array(
					'tint'  => 'red',
					'shade' => '#111111',
				),
				'no shade'  => array( 'tint' => '#eeeeee' ),
				'too long'  => array(
					'tint'  => '#eeeeeee',
					'shade' => '#111111',
				),
				'nonsense'  => 'pink',
			),
			array( 'x' => Palette::NEUTRAL )
		);

		$this->assertSame( array( 'liquorice', 'unnamed' ), array_keys( $cleaned ) );
		$this->assertSame( '#EEEEEE', $cleaned['liquorice']['tint'], 'Kept as given.' );
		$this->assertSame( 'unnamed', $cleaned['unnamed']['name'], 'The key names a colour with no name.' );

		$fallback = array( 'x' => Palette::NEUTRAL );

		$this->assertSame( $fallback, Palette::clean( array(), $fallback ) );
		$this->assertSame( $fallback, Palette::clean( 'nonsense', $fallback ) );
		$this->assertSame( array(), Palette::clean( null ) );

		$this->assertSame( '#abc', Palette::hex( '#abc' ) );
		$this->assertSame( '#AABBCC', Palette::hex( '#AABBCC' ) );
		$this->assertSame( '', Palette::hex( '#aabbccdde' ), 'Nine digits is not a colour.' );
		$this->assertSame( '', Palette::hex( '#abcd' ) );
		$this->assertSame( '', Palette::hex( 'abc' ) );
		$this->assertSame( '', Palette::hex( ' #abc' ) );
		$this->assertSame( '', Palette::hex( 123 ) );
	}

	/**
	 * A thing keeps its colour, and every colour is somebody's.
	 */
	public function test_pick(): void {
		$palette = Palette::sweet();
		$keys    = array_keys( $palette );

		$this->assertSame( $keys[ crc32( 'ada@example.test' ) % 12 ], Palette::pick( $palette, 'ada@example.test' ) );
		$this->assertSame( '', Palette::pick( $palette, '' ) );
		$this->assertSame( '', Palette::pick( array(), 'ada' ) );

		$used = array();

		for ( $i = 0; $i < 200; $i++ ) {
			$used[ Palette::pick( $palette, "customer{$i}@example.test" ) ] = true;
		}

		$this->assertCount( 12, $used );
	}

	/**
	 * By address, whatever its capitals; by name without one; nobody is grey.
	 */
	public function test_a_person_keeps_their_colour(): void {
		$one = Avatar::swatch( 'Ada@Example.test', 'Ada Lovelace' );

		$this->assertSame( $one, Avatar::swatch( '  ada@example.TEST ', 'Somebody Else' ) );
		$this->assertArrayHasKey( $one['key'], Palette::sweet() );
		$this->assertSame( Palette::sweet()[ $one['key'] ]['tint'], $one['tint'] );

		$this->assertSame( Avatar::swatch( '', 'Ada Lovelace' ), Avatar::swatch( '', 'ada lovelace ' ) );
		$this->assertSame( Palette::pick( Palette::sweet(), 'ada lovelace' ), Avatar::swatch( '', 'Ada Lovelace' )['key'] );

		$nobody = Avatar::swatch( '', '' );

		$this->assertSame( '', $nobody['key'] );
		$this->assertSame( Palette::NEUTRAL['tint'], $nobody['tint'] );
		$this->assertSame( Palette::NEUTRAL['shade'], $nobody['shade'] );

		$own = array(
			'only' => array(
				'name'  => 'Only',
				'tint'  => '#eeeeee',
				'shade' => '#111111',
			),
		);

		$this->assertSame( 'only', Avatar::swatch( 'ada@example.test', '', $own )['key'] );
	}

	/**
	 * A monogram is an inline SVG of the size asked for, in the person's pair.
	 */
	public function test_a_monogram_is_drawn_inline(): void {
		$avatar = Avatar::monogram( 'Ada Lovelace', 'ada@example.test', 40, array( 'class' => 'shop-avatar' ) );
		$colour = Avatar::swatch( 'ada@example.test' );

		$this->assertStringStartsWith( '<svg class="shop-avatar avatar avatar-40" width="40" height="40" viewBox="0 0 40 40" role="img" aria-hidden="true" focusable="false"', $avatar );
		$this->assertStringContainsString( 'font-size="16"', $avatar, 'Two letters at two fifths.' );
		$this->assertStringContainsString( '>AL</text>', $avatar );
		$this->assertStringContainsString( '<rect width="40" height="40" fill="' . $colour['tint'] . '"/>', $avatar );
		$this->assertStringContainsString( 'fill="' . $colour['shade'] . '"', $avatar );
		$this->assertStringContainsString( 'style="border-radius:20%;overflow:hidden;vertical-align:middle"', $avatar );
		$this->assertStringEndsWith( '</svg>', $avatar );

		$one = Avatar::monogram( 'Ada', '', 40 );

		$this->assertStringContainsString( 'font-size="18.4"', $one, 'One letter a little larger.' );
		$this->assertStringStartsWith( '<svg class="avatar avatar-40"', $one );
	}

	/**
	 * Labelled when asked, styled, clamped, escaped; a figure for nobody.
	 */
	public function test_monogram_options(): void {
		$labelled = Avatar::monogram_svg( 'A"', Palette::NEUTRAL, 32, array( 'label' => 'Ada & "Co"', 'style' => 'margin:0' ) );

		$this->assertStringContainsString( 'role="img" aria-label="Ada &amp; &quot;Co&quot;"', $labelled );
		$this->assertStringNotContainsString( 'aria-hidden', $labelled );
		$this->assertStringContainsString( '>A&quot;</text>', $labelled );
		$this->assertStringContainsString( 'style="border-radius:20%;overflow:hidden;vertical-align:middle;margin:0"', $labelled );

		$this->assertStringContainsString( 'width="12"', Avatar::monogram_svg( 'A', Palette::NEUTRAL, 1 ) );
		$this->assertStringContainsString( 'width="512"', Avatar::monogram_svg( 'A', Palette::NEUTRAL, 9999 ) );

		$figure = Avatar::monogram( '', '', 50 );

		$this->assertStringNotContainsString( '<text', $figure );
		$this->assertStringContainsString( '<g fill="' . Palette::NEUTRAL['shade'] . '"><circle cx="25" cy="19" r="8.5"/><path d="M10 50 a15 15 0 0 1 30 0 z"/></g>', $figure );
		$this->assertStringContainsString( 'fill="' . Palette::NEUTRAL['tint'] . '"', $figure );

		$own = Avatar::monogram(
			'Ada',
			'ada@example.test',
			32,
			array(
				'palette' => array(
					'only' => array(
						'name'  => 'Only',
						'tint'  => '#abcdef',
						'shade' => '#123456',
					),
				),
			)
		);

		$this->assertStringContainsString( 'fill="#abcdef"', $own );
		$this->assertStringContainsString( 'fill="#123456"', $own );
	}

	/**
	 * Which way a figure went.
	 */
	public function test_trend(): void {
		$this->assertSame( Numbers::TREND_UP, Numbers::trend( 2, 1 ) );
		$this->assertSame( Numbers::TREND_DOWN, Numbers::trend( 1, 2 ) );
		$this->assertSame( Numbers::TREND_LEVEL, Numbers::trend( 2, 2 ) );
		$this->assertSame( Numbers::TREND_UP, Numbers::trend( 0.2, 0.1 ) );
		$this->assertSame( Numbers::TREND_DOWN, Numbers::trend( -0.5, 0 ) );
		$this->assertSame( Numbers::TREND_LEVEL, Numbers::trend( 3, 3.0 ) );
		$this->assertSame( Numbers::TREND_LEVEL, Numbers::trend( 0, 0 ) );
		$this->assertSame( Numbers::TREND_UP, Numbers::trend( 5, 0 ), 'From nought is still up.' );
	}
}
