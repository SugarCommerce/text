<?php
/**
 * Input shaping tests.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests;

use SugarCommerce\Text\Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each of these is asked three things: something good, something messy and
 * something that is not the kind of value at all. The third must come back
 * as the clean empty value and never as an error.
 */
final class InputTest extends TestCase {

	/**
	 * The cut counts characters, so it cannot land inside one.
	 */
	public function test_truncate_counts_characters_not_bytes(): void {
		$name = str_repeat( 'あ', 300 );
		$cut  = Input::truncate( $name, 255 );

		$this->assertSame( 255, mb_strlen( $cut ) );
		$this->assertTrue( mb_check_encoding( $cut, 'UTF-8' ) );
		$this->assertSame( 'abc', Input::truncate( '  abc  ', 10 ) );
		$this->assertSame( 'a  b', Input::truncate( 'a  b' ), 'The inside is left alone.' );
		$this->assertSame( 'abcdef', Input::truncate( 'abcdef', 0 ) );
		$this->assertSame( '42', Input::truncate( 42, 5 ) );
		$this->assertSame( '', Input::truncate( [ 'a' ], 5 ) );
		$this->assertSame( '', Input::truncate( null, 5 ) );
	}

	/**
	 * A key is lower-case letters, digits, dashes and underscores.
	 */
	public function test_key(): void {
		$this->assertSame( 'one_time', Input::key( 'one_time' ) );
		$this->assertSame( 'partially_refunded', Input::key( ' Partially_Refunded! ' ) );
		$this->assertSame( 'eu-vat', Input::key( 'EU-VAT' ) );
		$this->assertSame( 'chargesucceeded', Input::key( 'charge.succeeded' ) );
		$this->assertSame( 'abc', Input::key( 'abcdef', 3 ) );
		$this->assertSame( '', Input::key( [ 'status' ] ) );
		$this->assertSame( '', Input::key( null ) );
	}

	/**
	 * Digits and nothing else, leading noughts kept.
	 */
	public function test_digits(): void {
		$this->assertSame( '4242', Input::digits( '4242' ) );
		$this->assertSame( '0042', Input::digits( '**** 0042' ) );
		$this->assertSame( '1234', Input::digits( '1234 5678', 4 ) );
		$this->assertSame( '', Input::digits( 'visa' ) );
		$this->assertSame( '', Input::digits( true ) );
		$this->assertSame( '', Input::digits( [ 1 ] ) );
	}

	/**
	 * What a switch reads as.
	 *
	 * @param mixed $value  The value.
	 * @param bool  $expect What it is.
	 */
	#[DataProvider( 'switches' )]
	public function test_bool( mixed $value, bool $expect ): void {
		$this->assertSame( $expect, Input::bool( $value ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function switches(): array {
		return [
			'true'          => [ true, true ],
			'one'           => [ 1, true ],
			'string one'    => [ '1', true ],
			'yes'           => [ 'yes', true ],
			'Yes, padded'   => [ ' Yes ', true ],
			'on'            => [ 'on', true ],
			'string true'   => [ 'true', true ],
			'false'         => [ false, false ],
			'nought'        => [ 0, false ],
			'string nought' => [ '0', false ],
			'no'            => [ 'no', false ],
			'off'           => [ 'off', false ],
			'string false'  => [ 'false', false ],
			'empty'         => [ '', false ],
			'null'          => [ null, false ],
			'a word'        => [ 'banana', false ],
			'an array'      => [ [ 1 ], false ],
		];
	}

	/**
	 * Something that is not a switch is the default, either way.
	 */
	public function test_bool_falls_back_to_the_default(): void {
		$this->assertTrue( Input::bool( 'banana', true ) );
		$this->assertFalse( Input::bool( 'no', true ) );
	}

	/**
	 * A whole number, held between its bounds.
	 */
	public function test_int(): void {
		$this->assertSame( 5, Input::int( 5 ) );
		$this->assertSame( 5, Input::int( '5' ) );
		$this->assertSame( 0, Input::int( -5 ) );
		$this->assertSame( 0, Input::int( '-5' ) );
		$this->assertSame( 3, Input::int( '3.9' ) );
		$this->assertSame( 0, Input::int( 'many' ) );
		$this->assertSame( 0, Input::int( null ) );
		$this->assertSame( 0, Input::int( [ 5 ] ) );
		$this->assertSame( 7, Input::int( 'many', 0, 10, 7 ) );
		$this->assertSame( 10, Input::int( 99, 0, 10 ) );
		$this->assertSame( -3, Input::int( -3, -10, 10 ) );
		$this->assertSame( PHP_INT_MAX, Input::int( '1e30' ) );
		$this->assertSame( 9007199254740993, Input::int( '9007199254740993' ), 'A long run of digits is not sent through a float.' );
	}

	/**
	 * A limit is between one and its ceiling; an offset is nought or more.
	 */
	public function test_limit_and_offset(): void {
		$this->assertSame( 10, Input::limit( 10 ) );
		$this->assertSame( 10, Input::limit( '10' ) );
		$this->assertSame( 100, Input::limit( 5000 ) );
		$this->assertSame( 30, Input::limit( null ) );
		$this->assertSame( 30, Input::limit( 0 ) );
		$this->assertSame( 30, Input::limit( -4 ) );
		$this->assertSame( 30, Input::limit( 'all' ) );
		$this->assertSame( 20, Input::limit( '', 20, 50 ) );
		$this->assertSame( 50, Input::limit( 80, 20, 50 ) );
		$this->assertSame( 5, Input::limit( null, 20, 5 ), 'The default cannot be past the ceiling.' );

		$this->assertSame( 40, Input::offset( '40' ) );
		$this->assertSame( 0, Input::offset( -1 ) );
		$this->assertSame( 0, Input::offset( null ) );
		$this->assertSame( 0, Input::offset( 'next' ) );
	}

	/**
	 * Arrays are encoded, JSON is left as it was, the rest is nothing.
	 */
	public function test_json(): void {
		$this->assertSame( '[1,2,3]', Input::json( [ 1, 2, 3 ] ) );
		$this->assertSame( '{"a":"b"}', Input::json( [ 'a' => 'b' ] ) );
		$this->assertSame( '{"a":"b"}', Input::json( (object) [ 'a' => 'b' ] ) );

		// Byte for byte: the spacing and the escaping are whoever sent it's.
		$sent = '{ "url": "https:\/\/example.com\/?a=1&b=2", "n": 1.0 }';
		$this->assertSame( $sent, Input::json( $sent ) );

		$this->assertSame( '[]', Input::json( [] ) );
		$this->assertSame( '', Input::json( '' ) );
		$this->assertSame( '', Input::json( '   ' ) );
		$this->assertSame( '', Input::json( '{not json' ) );
		$this->assertSame( '', Input::json( null ) );
		$this->assertSame( '', Input::json( 12 ) );
		$this->assertSame( '', Input::json( [ "\xB1\x31" ] ), 'What cannot be encoded is nothing, not false.' );
	}

	/**
	 * A hash of the right length, or nothing.
	 */
	public function test_hex(): void {
		$hash = hash( 'sha256', 'x' );

		$this->assertSame( $hash, Input::hex( $hash, 64 ) );
		$this->assertSame( $hash, Input::hex( ' ' . strtoupper( $hash ) . ' ', 64 ) );
		$this->assertSame( '', Input::hex( substr( $hash, 1 ), 64 ) );
		$this->assertSame( '', Input::hex( str_repeat( 'z', 64 ), 64 ) );
		$this->assertSame( '', Input::hex( 12, 2 ) );
	}

	/**
	 * Every kind of thing that changes how text shows without being seen.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function invisibles(): array {
		return array(
			'right-to-left override' => array( "\u{202E}" ),
			'left-to-right embed'    => array( "\u{202A}" ),
			'first isolate'          => array( "\u{2068}" ),
			'pop isolate'            => array( "\u{2069}" ),
			'zero-width space'       => array( "\u{200B}" ),
			'zero-width joiner'      => array( "\u{200D}" ),
			'right-to-left mark'     => array( "\u{200F}" ),
			'arabic letter mark'     => array( "\u{061C}" ),
			'word joiner'            => array( "\u{2060}" ),
			'soft hyphen'            => array( "\u{00AD}" ),
			'byte-order mark'        => array( "\u{FEFF}" ),
			'hangul filler'          => array( "\u{3164}" ),
			'tag letter'             => array( "\u{E0041}" ),
			'tag cancel'             => array( "\u{E007F}" ),
			'null byte'              => array( "\0" ),
			'escape'                 => array( "\x1B" ),
			'delete'                 => array( "\x7F" ),
			'C1 control'             => array( "\u{0085}" ),
		);
	}

	/**
	 * `exe.txt` cannot be made to look like `txt.exe`.
	 *
	 * @param string $character The character.
	 */
	#[DataProvider( 'invisibles' )]
	public function test_visible_takes_out_what_cannot_be_seen( string $character ): void {
		$this->assertSame( 'invoice.exe.txt', Input::visible( "invoice{$character}.exe.txt" ) );
		$this->assertSame( 'Ada', Input::text( "{$character}Ada{$character}" ) );
		$this->assertSame( "a\nb", Input::multiline( "a{$character}\nb" ) );
	}

	/**
	 * What can be seen stays, and so do the ends of the ranges' neighbours.
	 */
	public function test_visible_keeps_what_can_be_seen(): void {
		$kept = "Zoë Ölçer 李小龍 ❤ a\tb\nc\rd \u{2010} \u{2070} \u{2027} \u{200A}";

		$this->assertSame( $kept, Input::visible( $kept ) );
		$this->assertSame( '', Input::visible( "bad \xB1\x31" ), 'Not UTF-8 is nothing.' );
		$this->assertSame( '', Input::visible( array( 'a' ) ) );
		$this->assertSame( '12', Input::visible( 12 ) );
	}

	/**
	 * One line: whitespace collapsed, ends trimmed, capped by character.
	 */
	public function test_text(): void {
		$this->assertSame( 'Ada Lovelace', Input::text( "  Ada \t\n Lovelace  " ) );
		$this->assertSame( 'Ada', Input::text( 'Ada Lovelace', 4 ), 'A cut that ends on a space is trimmed.' );
		$this->assertSame( 'あい', Input::text( 'あいう', 2 ) );
		$this->assertSame( '', Input::text( null ) );
		$this->assertSame( '', Input::text( "\u{200B}\u{202E}" ) );
	}

	/**
	 * Lines kept, blank runs one blank line, CRs gone, capped.
	 */
	public function test_multiline(): void {
		$this->assertSame( "one\ntwo", Input::multiline( "one\r\ntwo" ) );
		$this->assertSame( "one\ntwo", Input::multiline( "one\rtwo" ) );
		$this->assertSame( "one\n\ntwo", Input::multiline( "one\n\n\n\n\ntwo" ) );
		$this->assertSame( "one\n\ntwo", Input::multiline( "one \n \n \n two" ), 'Spaces cannot hide a run of blank lines.' );
		$this->assertSame( "a b\nc", Input::multiline( "  a \t  b  \n  c  " ) );
		$this->assertSame( "one\ntwo", Input::multiline( "one\ntwo\nthree", 0, 2 ) );
		$this->assertSame( 'one', Input::multiline( "one\ntwo", 4 ) );
		$this->assertSame( '', Input::multiline( array() ) );
	}
}
