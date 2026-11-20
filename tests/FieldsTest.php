<?php
/**
 * Input's form-field sanitisers and flag.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Text\Input;

/**
 * The shape a value is given on its way into a column: good, messy and not
 * that kind of value at all, the last always the clean empty value.
 */
final class FieldsTest extends TestCase {

	/**
	 * A flag is the switch bool() reads, as 1 or 0.
	 *
	 * @param mixed $value  The value.
	 * @param int   $expect 1 or 0.
	 */
	#[DataProvider( 'flags' )]
	public function test_flag( mixed $value, int $expect ): void {
		$this->assertSame( $expect, Input::flag( $value ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: int}>
	 */
	public static function flags(): array {
		return [
			'one'           => [ 1, 1 ],
			'true'          => [ true, 1 ],
			'yes'           => [ 'yes', 1 ],
			'on'            => [ 'on', 1 ],
			'string one'    => [ '1', 1 ],
			'no'            => [ 'no', 0 ],
			'string false'  => [ 'false', 0 ],
			'string nought' => [ '0', 0 ],
			'empty'         => [ '', 0 ],
			'null'          => [ null, 0 ],
			// Booleans::truthy() calls any other word a yes; a column must not.
			'a word'        => [ 'banana', 0 ],
			'an array'      => [ [ 1 ], 0 ],
		];
	}

	/**
	 * One line: tags and breaks out, spaces folded, cut by character.
	 */
	public function test_text_field(): void {
		$this->assertSame( 'Deep House Kit', Input::text_field( "  <b>Deep</b>\n House   Kit " ) );
		$this->assertSame( 'Drums & Bass', Input::text_field( 'Drums & Bass' ) );
		$this->assertSame( 'Deep', Input::text_field( 'Deep House', 4 ) );
		$this->assertSame( '42', Input::text_field( 42 ) );
		$this->assertSame( '', Input::text_field( [ 'x' ], 10 ) );
		$this->assertSame( '', Input::text_field( null ) );

		$cut = Input::text_field( str_repeat( 'あ', 400 ), 255 );

		$this->assertSame( str_repeat( 'あ', 255 ), $cut );
		$this->assertTrue( mb_check_encoding( $cut, 'UTF-8' ) );
	}

	/**
	 * Several lines: the breaks stay.
	 */
	public function test_textarea_field(): void {
		$this->assertSame( "One\nTwo three", Input::textarea_field( "One\nTwo <i>three</i>" ) );
		$this->assertSame( 'One', Input::textarea_field( "One\nTwo", 3 ) );
		$this->assertSame( '', Input::textarea_field( null ) );
		$this->assertSame( '', Input::textarea_field( [ 'x' ] ) );
	}

	/**
	 * Markup a post may carry; never cut, never trimmed.
	 */
	public function test_html(): void {
		$this->assertSame( '<p>Thanks <strong>Pat</strong></p>x()', Input::html( '<p>Thanks <strong>Pat</strong></p><script>x()</script>' ) );
		$this->assertSame( ' <p>a</p> ', Input::html( ' <p>a</p> ' ) );
		$this->assertSame( '', Input::html( [ '<p>' ] ) );
		$this->assertSame( '', Input::html( null ) );
	}
}
