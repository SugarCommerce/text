<?php
/**
 * Keys as labels, and numbers written for a person.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests;

use SugarCommerce\Text\Numbers;
use SugarCommerce\Text\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Text::humanize(), and the half of Numbers that is opt-in: nothing that
 * called percent() or change() before reads any differently.
 */
final class LabelsTest extends TestCase {

	/**
	 * @param string $key    A key.
	 * @param string $expect Its label.
	 */
	#[DataProvider( 'keys' )]
	public function test_humanize( string $key, string $expect ): void {
		$this->assertSame( $expect, Text::humanize( $key ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function keys(): array {
		return [
			'underscores'      => [ 'licence_activations', 'Licence activations' ],
			'hyphens'          => [ 'pay-outs', 'Pay outs' ],
			'dots'             => [ 'order.refunded', 'Order refunded' ],
			'camel case'       => [ 'bitDepth', 'Bit depth' ],
			'title case'       => [ 'Bit_Depth', 'Bit depth' ],
			'capitals kept'    => [ 'track_BPM', 'Track BPM' ],
			'capitals first'   => [ 'ISRC_code', 'ISRC code' ],
			'one word'         => [ 'paid', 'Paid' ],
			'padded'           => [ '  __email_sent__ ', 'Email sent' ],
			'a single capital' => [ 'a_B', 'A b' ],
			'accents'          => [ 'élan_vital', 'Élan vital' ],
			'nothing'          => [ '', '' ],
			'only separators'  => [ '_-.', '' ],
		];
	}

	/**
	 * The plain forms are exactly what they were.
	 */
	public function test_the_plain_forms_are_unchanged(): void {
		$this->assertSame( '42%', Numbers::percent( 42.4 ) );
		$this->assertSame( '1,234.5%', Numbers::percent( 1234.5, 1 ) );
		$this->assertSame( '+20%', Numbers::change( 120, 100 ) );
		$this->assertSame( '-20%', Numbers::change( 80, 100 ) );
		$this->assertSame( '+0%', Numbers::change( 100, 100 ) );
		$this->assertSame( 'new', Numbers::change( 50, 0 ) );
		$this->assertSame( '—', Numbers::change( 0, 0 ) );
	}

	/**
	 * Asked for, a change is written for a person: a real minus, "New".
	 */
	public function test_change_for_a_person(): void {
		$this->assertSame( '+20%', Numbers::change( 120, 100, 0, true ) );
		$this->assertSame( "\u{2212}20%", Numbers::change( 80, 100, 0, true ) );
		$this->assertSame( "\u{2212}12.5%", Numbers::change( 87.5, 100, 1, true ) );
		$this->assertSame( '0%', Numbers::change( 100, 100, 0, true ) );
		$this->assertSame( 'New', Numbers::change( 50, 0, 0, true ) );
		$this->assertSame( '—', Numbers::change( 0, 0, 0, true ) );
	}

	/**
	 * A share as a number, and none when there is nothing to share.
	 */
	public function test_rate(): void {
		$this->assertSame( 12.4, Numbers::rate( 124, 1000 ) );
		$this->assertSame( 33.3, Numbers::rate( 1, 3 ) );
		$this->assertSame( 33.33, Numbers::rate( 1, 3, 2 ) );
		$this->assertSame( 0.0, Numbers::rate( 0, 5 ) );
		$this->assertNull( Numbers::rate( 5, 0 ) );
		$this->assertNull( Numbers::rate( 5, -1 ) );
	}

	/**
	 * A percentage in the site's format; null is a dash and not 0%.
	 */
	public function test_percent_i18n(): void {
		$this->assertSame( '12.4%', Numbers::percent_i18n( 12.4 ) );
		$this->assertSame( '12%', Numbers::percent_i18n( 12.4, 0 ) );
		$this->assertSame( '0.0%', Numbers::percent_i18n( 0 ) );
		$this->assertSame( '—', Numbers::percent_i18n( null ) );
		$this->assertSame( 'n/a', Numbers::percent_i18n( null, 1, 'n/a' ) );

		// The site's separators, which the stub writes the German way.
		$GLOBALS['text_number_format'] = [ ',', '.' ];
		$this->assertSame( '1.234,5%', Numbers::percent_i18n( 1234.5 ) );
		$GLOBALS['text_number_format'] = null;
	}

	/**
	 * Whole unless it is small.
	 */
	public function test_percent_brief(): void {
		$this->assertSame( '4.5%', Numbers::percent_brief( 4.5 ) );
		$this->assertSame( '33%', Numbers::percent_brief( 33.3 ) );
		$this->assertSame( '10%', Numbers::percent_brief( 10 ) );
		$this->assertSame( '0%', Numbers::percent_brief( 0 ) );
		$this->assertSame( '—', Numbers::percent_brief( null ) );
	}

	/**
	 * The sign is the trend's, the minus is a minus, and new is a word.
	 */
	public function test_change_text(): void {
		$this->assertSame( '+12.4%', Numbers::change_text( 'up', 12.4 ) );
		$this->assertSame( "\u{2212}8.0%", Numbers::change_text( 'down', -8.0 ) );
		$this->assertSame( "\u{2212}8.0%", Numbers::change_text( Numbers::TREND_DOWN, 8.0 ) );
		$this->assertSame( '0.0%', Numbers::change_text( 'level', 0.0 ) );
		$this->assertSame( '+12%', Numbers::change_text( 'up', 12.4, 0 ) );

		$this->assertSame( 'New', Numbers::change_text( 'new', null ) );
		$this->assertSame( 'Neu', Numbers::change_text( 'new', 4.0, 1, 'Neu' ) );

		$this->assertSame( '—', Numbers::change_text( 'none', null ) );
		$this->assertSame( '—', Numbers::change_text( 'sideways', 4.0 ) );
		$this->assertSame( '—', Numbers::change_text( 'up', null ) );
		$this->assertSame( '', Numbers::change_text( 'up', null, 1, '', '' ) );
	}
}
