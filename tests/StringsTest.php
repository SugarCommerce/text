<?php
/**
 * String helpers.
 *
 * Only the ones that do more than wrap a core or PHP function survive here,
 * so every test below is about behaviour core does not already give you.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests;

use SugarCommerce\Text\Input;
use SugarCommerce\Text\Strings;
use SugarCommerce\Text\Text;
use PHPUnit\Framework\TestCase;

/**
 * Class StringsTest
 */
final class StringsTest extends TestCase {

	/**
	 * starts_with takes a list, which str_starts_with does not.
	 *
	 * That is the whole reason it exists rather than deferring to PHP.
	 */
	public function test_starts_with_accepts_a_list(): void {
		$this->assertTrue( Strings::starts_with( 'foobar', 'foo' ) );
		$this->assertTrue( Strings::starts_with( 'foobar', [ 'baz', 'foo' ] ) );
		$this->assertFalse( Strings::starts_with( 'foobar', [ 'baz', 'qux' ] ) );
		$this->assertFalse( Strings::starts_with( 'foobar', [] ) );
	}

	/**
	 * ends_with likewise.
	 */
	public function test_ends_with_accepts_a_list(): void {
		$this->assertTrue( Strings::ends_with( 'foobar', 'bar' ) );
		$this->assertTrue( Strings::ends_with( 'foobar', [ 'baz', 'bar' ] ) );
		$this->assertFalse( Strings::ends_with( 'foobar', [ 'baz' ] ) );
	}

	/**
	 * between pulls out a delimited section.
	 */
	public function test_between_extracts_a_section(): void {
		$this->assertSame( 'middle', Strings::between( '[', ']', 'start[middle]end' ) );
		$this->assertSame( '', Strings::between( '[', ']', 'no markers here' ) );
		$this->assertSame( '', Strings::between( '[', ']', 'start[unclosed' ) );
	}

	/**
	 * truncate cuts by characters and appends a suffix.
	 */
	public function test_truncate(): void {
		$this->assertSame( 'abcdefghij', Text::cut( 'abcdefghij', 10 ) );

		// The length is the maximum for the whole result, suffix included.
		$this->assertSame( 'ab...', Text::cut( 'abcdefghij', 5 ) );
	}

	/**
	 * A length below the suffix width never returns something longer.
	 *
	 * mb_substr() reads a negative length as "all but the last n characters",
	 * so asking for two characters used to return eighteen.
	 */
	public function test_truncate_below_the_suffix_width(): void {
		foreach ( [ 3, 2, 1, 0 ] as $length ) {
			$out = Text::cut( 'abcdefghijklmnop', $length );

			$this->assertSame( '...', $out, "A length of {$length} produced content." );
		}
	}

	/**
	 * truncate counts characters, not bytes.
	 *
	 * A multibyte string cut by bytes produces broken characters.
	 */
	public function test_truncate_is_multibyte_safe(): void {
		$out = Text::cut( 'ééééééééé', 6 );

		$this->assertSame( 'ééé...', $out );
		$this->assertSame( $out, mb_convert_encoding( $out, 'UTF-8', 'UTF-8' ), 'The cut broke a character.' );
	}

	/**
	 * words cuts by word count.
	 */
	public function test_words_cuts_by_word(): void {
		$this->assertSame( 'one two...', Text::truncate_words( 'one two three four', 2 ) );
		$this->assertSame( 'one two', Text::truncate_words( 'one two', 5 ) );
	}

	/**
	 * mask hides the middle.
	 */
	public function test_mask_hides_the_middle(): void {
		$out = Text::mask( '4111111111111111', 4 );

		$this->assertStringStartsWith( '4111', $out );
		$this->assertStringEndsWith( '1111', $out );
		$this->assertStringContainsString( '*', $out );
	}

	/**
	 * A short string is masked entirely rather than exposed.
	 *
	 * Showing four either side of a six-character secret shows all of it.
	 */
	public function test_a_short_string_is_fully_masked(): void {
		$this->assertSame( '******', Text::mask( 'secret', 4 ) );
	}

	/**
	 * initials takes the first letter of each word.
	 */
	public function test_initials(): void {
		$this->assertSame( 'DS', Text::acronym( 'David Sherlock' ) );
		$this->assertSame( 'D', Text::acronym( 'David Sherlock', 1 ) );
		$this->assertSame( '', Text::acronym( '   ' ) );
	}

	/**
	 * Case conversion.
	 */
	public function test_case_conversion(): void {
		$this->assertSame( 'helloWorld', Text::camel( 'hello world' ) );
		$this->assertSame( 'hello_world', Text::snake( 'hello world' ) );
	}

	/**
	 * matches_any compares against a list, optionally with wildcards.
	 */
	public function test_matches_any(): void {
		$this->assertTrue( Strings::matches_any( 'apple', [ 'apple', 'pear' ] ) );
		$this->assertFalse( Strings::matches_any( 'plum', [ 'apple', 'pear' ] ) );

		$this->assertTrue( Strings::matches_any( 'apple pie', [ 'apple*' ], true ) );
		$this->assertFalse( Strings::matches_any( 'apple pie', [ 'pear*' ], true ) );
	}

	/**
	 * An empty subject or list matches nothing.
	 */
	public function test_matches_any_empty_cases(): void {
		$this->assertFalse( Strings::matches_any( '', [ 'a' ] ) );
		$this->assertFalse( Strings::matches_any( 'a', [] ) );
	}

	/**
	 * excerpt strips markup before counting.
	 *
	 * Otherwise the tags eat the budget and the visible text is far shorter
	 * than asked for.
	 */
	public function test_excerpt_strips_markup_first(): void {
		$out = Text::excerpt( '<p>Hello <strong>there</strong> friend</p>', 100, strip_tags: true );

		$this->assertStringNotContainsString( '<', $out );
		$this->assertStringContainsString( 'Hello there friend', $out );
	}

	/**
	 * Whitespace helpers.
	 */
	public function test_whitespace_helpers(): void {
		$this->assertSame( 'a b c', Strings::reduce_whitespace( "a   b \n c" ) );
		$this->assertSame( 'abc', Strings::remove_whitespace( " a b\tc " ) );
	}

	/**
	 * to_words and to_lines split sensibly.
	 */
	public function test_splitting(): void {
		$this->assertSame( [ 'one', 'two' ], Strings::words( '  one   two  ' ) );
		$this->assertSame( [ 'a', 'b' ], Input::lines( "a\n\nb\n" ) );
	}

	/**
	 * The split results are lists, not gappy arrays.
	 *
	 * array_filter() keeps the original keys, so dropping the empty pieces
	 * left holes: $words[0] was missing, and json_encode() produced an object
	 * rather than an array -- which changes the shape of any REST response
	 * carrying one.
	 */
	public function test_splitting_returns_a_list(): void {
		$this->assertSame( '["one","two"]', wp_json_encode_shim( Strings::words( '  one   two  ' ) ) );
		$this->assertSame( '["a","b"]', wp_json_encode_shim( Input::lines( "a\n\nb\n" ) ) );
		$this->assertSame( 'one', Strings::words( '  one   two  ' )[0] );
	}

	/**
	 * replace_first and replace_last touch one occurrence each.
	 */
	public function test_replace_first_and_last(): void {
		$this->assertSame( 'x-b-b', Strings::replace_first( 'b', 'x', 'b-b-b' ) );
		$this->assertSame( 'b-b-x', Strings::replace_last( 'b', 'x', 'b-b-b' ) );
	}

	/**
	 * A zero is a value, not an absence.
	 *
	 * empty( '0' ) is true, so every helper that guarded with empty() did
	 * nothing for the one character that is both a legitimate value and a
	 * falsy one: a search for '0' never replaced, a pattern of '0' never
	 * matched, a line of '0' vanished from the split.
	 */
	public function test_a_zero_is_not_empty(): void {
		$this->assertTrue( Strings::matches_any( '0', [ '0' ] ) );
		$this->assertSame( '1x0', Strings::replace_first( '0', 'x', '100' ) );
		$this->assertSame( 'x', Strings::replace_first( '0', 'x', '0' ) );
		$this->assertSame( '10x', Strings::replace_last( '0', 'x', '100' ) );
		$this->assertSame( [ 'a', '0', 'b' ], Input::lines( "a\n0\nb" ) );
		$this->assertSame( [ 'a', '0', 'b' ], Strings::words( 'a 0 b' ) );
		$this->assertSame( '0Z', Text::acronym( '0 Zero' ) );
	}

	/**
	 * Needles and patterns that arrive as numbers are compared, not fatal.
	 *
	 * A settings array holding [ 1, 2 ] has already had its strings cast to
	 * int, and under strict types that was a TypeError inside trim() or
	 * str_starts_with() rather than a non-match.
	 */
	public function test_needles_and_patterns_may_be_numbers(): void {
		$this->assertTrue( Strings::starts_with( '1abc', [ 1 ] ) );
		$this->assertTrue( Strings::ends_with( 'abc1', [ 1 ] ) );
		$this->assertTrue( Strings::matches_any( '1', [ 1 ] ) );
		$this->assertFalse( Strings::matches_any( 'a', [ 1 ] ) );
	}

	/**
	 * snake_case is what the name says.
	 *
	 * It used to lowercase and swap spaces for underscores, which left
	 * 'fooBar' as 'foobar' and 'foo-bar' as 'foo-bar' -- neither of them
	 * snake_case, and the second one still a hyphen that a database column
	 * or a meta key cannot have.
	 */
	public function test_snake_splits_capitals_and_hyphens(): void {
		$this->assertSame( 'foo_bar_baz', Text::snake( 'fooBar Baz' ) );
		$this->assertSame( 'foo_bar', Text::snake( 'foo-bar' ) );
		$this->assertSame( 'foo_bar', Text::snake( 'FooBar' ) );
		$this->assertSame( 'html_parser', Text::snake( 'HTMLParser' ) );
		$this->assertSame( 'already_snake', Text::snake( 'already_snake' ) );
		$this->assertSame( 'a_b', Text::snake( '  a  -  b  ' ) );
		$this->assertSame( 'price_usd', Text::snake( 'Price (USD)' ) );
	}

	/**
	 * mask counts characters, not bytes.
	 *
	 * Masking by strlen() cut a multibyte character in half and returned
	 * broken UTF-8 in place of the one thing this function exists to keep
	 * readable.
	 */
	public function test_mask_is_multibyte_safe(): void {
		$out = Text::mask( 'héllo wörld', 2 );

		$this->assertSame( 'hé*******ld', $out );
		$this->assertSame( $out, mb_convert_encoding( $out, 'UTF-8', 'UTF-8' ), 'The mask broke a character.' );
	}

	/**
	 * A negative visible count shows nothing rather than something strange.
	 */
	public function test_mask_with_a_negative_count_masks_everything(): void {
		$this->assertSame( '***', Text::mask( 'abc', -1 ) );
	}

	/**
	 * words treats a run of whitespace as one gap.
	 *
	 * Splitting on a single space made every extra space an empty word, so
	 * "a  b c" limited to two words came back as "a ..." -- one word and a
	 * gap, counted as two.
	 */
	public function test_words_ignores_runs_of_whitespace(): void {
		$this->assertSame( 'a b...', Text::truncate_words( "a  b\n c", 2 ) );
		$this->assertSame( "a  b\n c", Text::truncate_words( "a  b\n c", 3 ), 'A string within the limit was rewritten.' );
	}

	/**
	 * to_array gives a list, and nothing gives an empty one.
	 *
	 * explode() turns '' into [ '' ], which every caller then filters out
	 * again. An empty separator used to be a ValueError.
	 */
	public function test_to_array(): void {
		$this->assertSame( [ 'a', 'b', 'c' ], Strings::split( 'a, b ,c' ) );
		$this->assertSame( [ 'a', '', 'b' ], Strings::split( 'a,,b' ), 'An explicit empty piece is kept.' );
		$this->assertSame( [], Strings::split( '' ) );
		$this->assertSame( [], Strings::split( '   ' ) );
		$this->assertSame( [ 'a,b' ], Strings::split( 'a,b', '' ) );
	}

	/**
	 * Title and sentence case handle characters outside ASCII.
	 */
	public function test_title_and_sentence_are_multibyte_safe(): void {
		$this->assertSame( 'Élan Vital', Text::title( 'élan VITAL' ) );
		$this->assertSame( 'Élan vital', Text::sentence( 'éLAN VITAL' ) );
		$this->assertSame( '', Text::sentence( '' ) );
	}
}

/**
 * json_encode without WordPress loaded.
 *
 * @param mixed $value The value.
 *
 * @return string
 */
function wp_json_encode_shim( $value ): string {
	return (string) json_encode( $value );
}
