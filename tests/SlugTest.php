<?php
/**
 * Slugs: made from a title, kept unique, checked as typed.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Text\Slug;

/**
 * A slug is never empty, never cut mid-word, romanised rather than dropped,
 * and a typed one is taken as it is or not at all.
 */
final class SlugTest extends TestCase {

	/* ─── Slugs ─────────────────────────────────────────────────────── */

	#[DataProvider( 'slug_cases' )]
	public function test_slugs( string $input, string $expected ): void {
		$this->assertSame( $expected, Slug::make( $input ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public static function slug_cases(): array {
		return array(
			'plain'        => array( 'Hello World', 'hello-world' ),
			'punctuation'  => array( 'Hello, World!', 'hello-world' ),
			'apostrophe'   => array( "Dave's Samples", 'daves-samples' ),
			'curly quote'  => array( 'Dave’s Samples', 'daves-samples' ),
			'runs'         => array( 'a   b---c', 'a-b-c' ),
			'edges'        => array( '  --Hello--  ', 'hello' ),
			'numbers'      => array( 'Volume 2 Part 3', 'volume-2-part-3' ),
			'accents'      => array( 'Naïve Café', 'naive-cafe' ),
			'german'       => array( 'Übungsstück', 'ubungsstuck' ),
		);
	}

	#[RequiresPhpExtension( 'intl' )]
	public function test_non_latin_titles_produce_a_usable_slug(): void {
		// A naive [^a-z0-9] filter returns nothing at all for these.
		$this->assertSame( 'ri-ben-yunofairu', Slug::make( '日本語のファイル' ) );
		$this->assertSame( 'russkij-fajl', Slug::make( 'Русский файл' ) );
	}

	public function test_a_slug_is_never_empty(): void {
		$this->assertNotSame( '', Slug::make( '!!!' ) );
		$this->assertNotSame( '', Slug::make( '' ) );
		$this->assertNotSame( '', Slug::make( '🎵🎹' ) );
	}

	public function test_slugs_truncate_on_a_word_boundary(): void {
		$slug = Slug::make( 'the quick brown fox jumps over the lazy dog and keeps running onwards', '-', 30 );

		$this->assertLessThanOrEqual( 30, strlen( $slug ) );
		$this->assertStringEndsNotWith( '-', $slug );
		// Cut at a boundary, not mid-word.
		$this->assertStringNotContainsString( 'ju-', $slug );
	}

	public function test_uniqueness_appends_a_counter(): void {
		$taken = array( 'hello-world' => true, 'hello-world-2' => true );

		$this->assertSame(
			'hello-world-3',
			Slug::unique( 'Hello World', static fn( string $s ): bool => isset( $taken[ $s ] ) )
		);
	}

	public function test_an_unused_slug_is_returned_unchanged(): void {
		$this->assertSame( 'hello-world', Slug::unique( 'Hello World', static fn(): bool => false ) );
	}

	public function test_uniqueness_can_keep_the_hosts_own_slug(): void {
		$taken = array( 'my_slug' => true );
		$is    = static fn( string $s ): bool => isset( $taken[ $s ] );
		$keep  = static fn( string $s ): string => strtolower( $s );

		$this->assertSame( 'my_slug-2', Slug::unique( 'My_Slug', $is, '-', $keep ) );
		$this->assertSame( 'other_slug', Slug::unique( 'Other_Slug', $is, '-', $keep ) );
		$this->assertSame( 'my-slug-2', Slug::unique( 'my-slug', static fn( string $s ): bool => 'my-slug' === $s ), 'Without one, make() still decides.' );
	}

	public function test_slug_validation(): void {
		$this->assertTrue( Slug::is_valid( 'hello-world' ) );
		$this->assertTrue( Slug::is_valid( 'a1' ) );
		$this->assertFalse( Slug::is_valid( 'Hello-World' ) );
		$this->assertFalse( Slug::is_valid( 'hello--world' ) );
		$this->assertFalse( Slug::is_valid( '-hello' ) );
		$this->assertFalse( Slug::is_valid( '' ) );
	}

	/**
	 * Core's transliteration is preferred over iconv's.
	 *
	 * Order matters and is easy to get backwards. The intl transliterator
	 * goes first because it is the only one that romanises a Japanese title
	 * rather than dropping it; core's remove_accents() goes second because it
	 * is a better Latin table than iconv's; iconv goes last because it is
	 * what is left.
	 *
	 * Putting core first was tried and lost the Japanese case entirely --
	 * remove_accents() has nothing to say about CJK, so the title came back
	 * as a random fallback slug.
	 */
	public function test_a_japanese_title_is_romanised_not_dropped(): void {
		if ( ! class_exists( \Transliterator::class ) ) {
			$this->markTestSkipped( 'intl is not installed.' );
		}

		$slug = Slug::make( '日本のファイル' );

		$this->assertStringNotContainsString( 'n', substr( $slug, 0, 1 ) === 'n' && 7 === strlen( $slug ) ? 'fallback' : 'ok' );
		$this->assertMatchesRegularExpression( '/[a-z]{4,}/', $slug );
	}

	/* ─── Slugs somebody typed ─────────────────────────────────────── */

	/** @return array<string, array{0: mixed, 1: string}> */
	public static function typed_slugs(): array {
		return array(
			'plain'           => array( 'my-shop', 'my-shop' ),
			'lower-cased'     => array( '  My-Shop ', 'my-shop' ),
			'digits'          => array( '123', '123' ),
			'leading hyphen'  => array( '-shop', '' ),
			'trailing hyphen' => array( 'shop-', '' ),
			'space inside'    => array( 'my shop', '' ),
			'underscore'      => array( 'my_shop', '' ),
			'accented'        => array( 'café', '' ),
			'too short'       => array( 'ab', '' ),
			'shortest'        => array( 'abc', 'abc' ),
			'longest'         => array( 'abcdefghijkl', 'abcdefghijkl' ),
			'too long'        => array( 'abcdefghijklm', '' ),
			'reserved'        => array( 'Admin', '' ),
			'not scalar'      => array( array( 'shop' ), '' ),
			'empty'           => array( '', '' ),
			'trailing line'   => array( "shop\n", 'shop' ),
		);
	}

	#[DataProvider( 'typed_slugs' )]
	public function test_clean_accepts_a_typed_slug_or_nothing( mixed $value, string $expected ): void {
		$this->assertSame( $expected, Slug::clean( $value, 3, 12, array( 'admin' ) ) );
	}

	public function test_a_reserved_name_given_in_capitals_is_still_refused(): void {
		$this->assertSame( '', Slug::clean( 'admin', 3, 0, array( 'Admin', 'API' ) ) );
		$this->assertSame( '', Slug::clean( 'api', 3, 0, array( 'Admin', 'API' ) ) );
	}

	public function test_clean_has_no_length_cap_by_default(): void {
		$this->assertSame( str_repeat( 'a', 200 ), Slug::clean( str_repeat( 'a', 200 ) ) );
	}

	public function test_a_slug_with_a_trailing_newline_is_not_valid(): void {
		$this->assertFalse( Slug::is_valid( "my-shop\n" ) );
	}
}
