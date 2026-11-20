<?php
/**
 * What the merge added: the pieces that came from three libraries and now
 * meet in one, and the rules that keep them from being the same helper twice.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Text\Input;
use SugarCommerce\Text\Models\Pagination;
use SugarCommerce\Text\Strings;
use SugarCommerce\Text\Text;

/**
 * Class MergedTest
 */
final class MergedTest extends TestCase {

	/* ─── Three ways to shorten, and they differ on purpose ─────────── */

	public function test_truncate_breaks_at_a_word_and_cut_does_not(): void {
		$text = 'The quick brown fox jumps over';

		$this->assertSame( 'The quick brown…', Text::truncate( $text, 18 ) );
		$this->assertSame( 'The quick brown...', Text::cut( $text, 18 ) );
		$this->assertSame( 'The quick...', Text::truncate_words( $text, 2 ) );
	}

	public function test_an_unbroken_truncation_is_exactly_the_length_asked_for(): void {
		$this->assertSame( 'abcdefghi…', Text::truncate( str_repeat( 'abcdefghij', 3 ), 10 ) );
		$this->assertSame( 'abcdef[…]', Text::truncate( str_repeat( 'abcdefghij', 3 ), 9, '[…]' ) );
	}

	public function test_cut_does_not_trim_what_it_leaves_alone(): void {
		$this->assertSame( '  ab  ', Text::cut( '  ab  ', 10 ) );
	}

	public function test_truncate_words_leaves_text_within_the_limit_untouched(): void {
		$this->assertSame( "one  two\n", Text::truncate_words( "one  two\n", 2 ) );
		$this->assertSame( '...', Text::truncate_words( 'one two', 0 ) );
	}

	/* ─── Markup ────────────────────────────────────────────────────── */

	public function test_strip_tags_takes_a_script_with_its_contents(): void {
		$this->assertSame( 'Hello there', Text::strip_tags( '<p>Hello <script>alert(1)</script>there</p><style>p{}</style>' ) );
	}

	/**
	 * The one pattern for this, `<(script|style)[^>]*?>.*?</\1>`, looks for
	 * the closing tag again from every opening one: a few hundred kilobytes
	 * of `<script>` with no `</script>` took seconds, and without PCRE's JIT
	 * ran out of backtracking and came back as nothing at all.
	 */
	public function test_unclosed_scripts_cost_what_reading_them_costs(): void {
		$jit   = ini_get( 'pcre.jit' );
		$limit = ini_get( 'pcre.backtrack_limit' );

		ini_set( 'pcre.jit', '0' );
		ini_set( 'pcre.backtrack_limit', '1000000' );

		try {
			$never = str_repeat( 'a<style', 50000 );
			$from  = microtime( true );

			$this->assertSame( 'kept', Text::strip_tags( str_repeat( '<script>', 50000 ) . 'kept' ) );
			$this->assertSame( strip_tags( $never ), Text::strip_tags( $never ) );
			$this->assertSame( 'kept', Text::excerpt( str_repeat( '<STYLE x>', 50000 ) . 'kept', 10, strip_tags: true ) );
			$this->assertLessThan( 1.0, microtime( true ) - $from );
		} finally {
			ini_set( 'pcre.jit', (string) $jit );
			ini_set( 'pcre.backtrack_limit', (string) $limit );
		}
	}

	/**
	 * The same answer as that pattern -- and so as wp_strip_all_tags() --
	 * wherever the pattern is quick enough to ask.
	 */
	#[DataProvider( 'markup' )]
	public function test_strip_tags_agrees_with_the_one_pattern( string $html ): void {
		$pattern = trim( strip_tags( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $html ) ) );

		$this->assertSame( $pattern, Text::strip_tags( $html ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function markup(): array {
		return array(
			'closed'               => array( 'a<script>x</script>b<style>y</style>c' ),
			'case'                 => array( 'a<SCRIPT type="x">x</Script>b' ),
			'unclosed'             => array( 'a<script>x b' ),
			'unclosed, then style' => array( '<script>a<style>b</style>c' ),
			'wrong closer'         => array( '<script>a</style>b</script>c' ),
			'never finished'       => array( 'a<script b' ),
			'tag in a tag'         => array( '<script <style>x</style>y' ),
			'longer name'          => array( '<scripts>a</script>b' ),
			'two in a row'         => array( '<style>a</style><style>b</style>c' ),
			'closer first'         => array( '</script>a<script>b</script>c' ),
			'spaced closer'        => array( '<script>a</script >b' ),
			'closer inside opener' => array( '<script a="</script>">b</script>c' ),
			'nothing'              => array( '' ),
		);
	}

	public function test_excerpt_keeps_tags_unless_asked(): void {
		$this->assertSame( '<b>Hi</b> there', Text::excerpt( "<b>Hi</b>\n\nthere" ) );
		$this->assertSame( 'Hi there', Text::excerpt( "<b>Hi</b>\n\nthere", strip_tags: true ) );
	}

	/* ─── Initials and acronyms ─────────────────────────────────────── */

	public function test_initials_pick_two_and_an_acronym_takes_every_word(): void {
		$this->assertSame( 'DS', Text::initials( 'Dave Michael Sherlock' ) );
		$this->assertSame( 'DMS', Text::acronym( 'Dave Michael Sherlock' ) );
		$this->assertSame( 'DM', Text::acronym( 'Dave Michael Sherlock', 2 ) );
		$this->assertSame( 'dm', Text::acronym( 'dave michael', uppercase: false ) );
	}

	public function test_nothing_is_a_question_mark_for_initials_and_empty_for_an_acronym(): void {
		$this->assertSame( '?', Text::initials( '  ' ) );
		$this->assertSame( '', Text::acronym( '  ' ) );
	}

	public function test_an_acronym_counts_characters(): void {
		$this->assertSame( 'ÉÜ', Text::acronym( 'élan über' ) );
	}

	/* ─── Case ──────────────────────────────────────────────────────── */

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function cases(): array {
		return array(
			'humanize keeps capitals'  => array( 'humanize', 'track_BPM', 'Track BPM' ),
			'sentence lowers them'     => array( 'sentence', 'TRACK BPM', 'Track bpm' ),
			'title takes every word'   => array( 'title', 'élan vital', 'Élan Vital' ),
			'name case is byte-first'  => array( 'name_case', 'élodie SMITH', 'élodie Smith' ),
			'camel from snake'         => array( 'camel', 'order_total-due', 'orderTotalDue' ),
			'snake from camel'         => array( 'snake', 'orderTotal Due', 'order_total_due' ),
			'snake keeps acronyms one' => array( 'snake', 'HTMLParser', 'html_parser' ),
		);
	}

	#[DataProvider( 'cases' )]
	public function test_case( string $method, string $input, string $expected ): void {
		$this->assertSame( $expected, Text::$method( $input ) );
	}

	/* ─── Two masks, for two jobs ───────────────────────────────────── */

	public function test_mask_keeps_the_length_and_redact_does_not(): void {
		$key = 'AB12CD34EF56GH78';

		$this->assertSame( 'AB12********GH78', Text::mask( $key ) );
		$this->assertSame( 'AB12…GH78', Text::redact( $key ) );
		$this->assertSame( strlen( $key ), mb_strlen( Text::mask( $key ) ) );
	}

	public function test_mask_hides_everything_too_short_to_hide_anything(): void {
		$this->assertSame( '********', Text::mask( 'abcdefgh' ) );
		$this->assertSame( '•••', Text::mask( 'abc', 0, '•' ) );
	}

	/* ─── Splitting: the plain one, and the one for a form field ────── */

	public function test_split_keeps_positions_and_list_does_not(): void {
		$this->assertSame( array( 'a', '', 'b', 'a' ), Strings::split( 'a, ,b,a' ) );
		$this->assertSame( array( 'a', 'b' ), Input::list( 'a, ,b,a' ) );
	}

	public function test_lines_are_trimmed_and_blank_ones_dropped(): void {
		$this->assertSame( array( 'one', 'two  three' ), Input::lines( "  one \r\n\n two  three\n" ) );
	}

	/* ─── Anything as a string ──────────────────────────────────────── */

	public function test_from_writes_structures_as_json(): void {
		$this->assertSame( '{"a":1}', Strings::from( array( 'a' => 1 ) ) );
		$this->assertSame( '{"b":2}', Strings::from( (object) array( 'b' => 2 ) ) );
		$this->assertSame( '', Strings::from( null ) );
		$this->assertSame( '0', Strings::from( 0 ) );
		$this->assertSame( '1', Strings::from( true ) );
	}

	public function test_from_uses_a_stringable_objects_own_text(): void {
		$value = new class() implements \Stringable {
			public function __toString(): string {
				return 'ORD-1';
			}
		};

		$this->assertSame( 'ORD-1', Strings::from( $value ) );
	}

	/* ─── Page sizes ────────────────────────────────────────────────── */

	/**
	 * A zero divides by zero; a negative LIMIT is no limit to some databases.
	 *
	 * @return array<string, array{0: array<mixed>}>
	 */
	public static function unusable_sizes(): array {
		return array(
			'none'     => array( array() ),
			'zero'     => array( array( 0, 25 ) ),
			'negative' => array( array( -1 ) ),
			'string'   => array( array( '25' ) ),
			'keyed'    => array( array( 'a' => 25 ) ),
		);
	}

	/**
	 * @param array<mixed> $sizes Page sizes.
	 */
	#[DataProvider( 'unusable_sizes' )]
	public function test_unusable_page_sizes_are_refused( array $sizes ): void {
		$this->expectException( \InvalidArgumentException::class );

		Pagination::check_sizes( $sizes );
	}

	public function test_a_slice_refuses_a_negative_size(): void {
		$this->expectException( \InvalidArgumentException::class );

		Pagination::slice( array( 1, 2, 3 ), array( 'per' => '-1' ), array( -1, 25 ) );
	}

	public function test_the_default_sizes_are_usable(): void {
		Pagination::check_sizes( Pagination::SIZES );

		$this->assertSame( 25, Pagination::slice( range( 1, 30 ), array() )->per_page );
	}
}
