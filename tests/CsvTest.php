<?php
/**
 * CSV tests.
 *
 * @package SugarCommerce\Text
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Tests;

use SugarCommerce\Text\Csv;
use SugarCommerce\Text\MissingColumns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Csv::class )]
#[CoversClass( MissingColumns::class )]
final class CsvTest extends TestCase {

	/**
	 * Files written by a test, removed after it.
	 *
	 * @var list<string>
	 */
	private array $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Cells a spreadsheet would run, and what they become.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function cells(): array {
		return array(
			'equals'                 => array( '=HYPERLINK("https://evil.test","Click")', '\'=HYPERLINK("https://evil.test","Click")' ),
			'plus'                   => array( '+SUM(A1)', "'+SUM(A1)" ),
			'minus not a number'     => array( '-1+2', "'-1+2" ),
			'at'                     => array( '@SUM(A1)', "'@SUM(A1)" ),
			'pipe'                   => array( '|calc', "'|calc" ),
			'tab'                    => array( "\t=1+1", "'\t=1+1" ),
			'carriage return'        => array( "\r=1+1", "'\r=1+1" ),
			'behind spaces'          => array( '  =1+1', "'  =1+1" ),
			'behind a newline'       => array( "\n=1+1", "'\n=1+1" ),
			'plain text'             => array( 'Ada Lovelace', 'Ada Lovelace' ),
			'trigger later'          => array( 'a=b', 'a=b' ),
			'email'                  => array( 'ada@example.test', 'ada@example.test' ),
			'negative number string' => array( '-12.50', '-12.50' ),
			'plus number string'     => array( '+44', '+44' ),
			'negative int'           => array( -5, '-5' ),
			'float'                  => array( -1.5, '-1.5' ),
			'empty'                  => array( '', '' ),
			'null'                   => array( null, '' ),
			'true'                   => array( true, '1' ),
			'false'                  => array( false, '' ),
			'array is JSON'          => array( array( 'a' => 1 ), '{"a":1}' ),
			'formula in JSON'        => array( array( '=1' ), '["=1"]' ),
		);
	}

	/**
	 * Every shape that runs is defused, and nothing else is touched.
	 *
	 * @param mixed  $value    The value.
	 * @param string $expected The cell.
	 */
	#[DataProvider( 'cells' )]
	public function test_cell( mixed $value, string $expected ): void {
		$this->assertSame( $expected, Csv::cell( $value ) );
	}

	/**
	 * Stringable values are text.
	 */
	public function test_stringable(): void {
		$value = new class() implements \Stringable {
			public function __toString(): string {
				return '=1';
			}
		};

		$this->assertSame( "'=1", Csv::cell( $value ) );
		$this->assertTrue( Csv::is_formula( ' @x' ) );
		$this->assertFalse( Csv::is_formula( ' -3' ) );
		$this->assertFalse( Csv::is_formula( '' ) );
	}

	/**
	 * Quoted where it must be, the semicolon included; quotes doubled.
	 */
	public function test_row(): void {
		$this->assertSame( "a,b,3\n", Csv::row( array( 'a', 'b', 3 ) ) );
		$this->assertSame( "\"a,b\",\"say \"\"hi\"\"\",\"x y\"\n", Csv::row( array( 'a,b', 'say "hi"', 'x y' ) ) );
		$this->assertSame( "\"Jo;=1\"\n", Csv::row( array( 'Jo;=1' ) ), 'Quoted, so a semicolon reader cannot split off a cell that starts with =.' );
		$this->assertSame( "\"line\nbreak\"\n", Csv::row( array( "line\nbreak" ) ) );
	}

	/**
	 * A file: a byte-order mark for Excel unless asked not to, header, rows.
	 */
	public function test_build(): void {
		$rows = ( static function () {
			yield array( 'Ada', '=1' );
			yield array( 'Bob', -2 );
		} )();

		$this->assertSame( "\xEF\xBB\xBFName,Total\nAda,'=1\nBob,-2\n", Csv::build( array( 'Name', 'Total' ), $rows ) );
		$this->assertSame( "Name\n", Csv::build( array( 'Name' ), array(), false ) );
		$this->assertSame( "a\n", Csv::build( array(), array( array( 'a' ) ), false ) );
	}

	/**
	 * Batches to an open stream; what is written is what build() writes.
	 */
	public function test_write(): void {
		$handle = fopen( 'php://memory', 'w+' );

		$this->assertTrue( Csv::write( $handle, array( array( 'Name', 'Total' ) ), true ) );
		$this->assertTrue( Csv::write( $handle, array( array( 'Ada', '=1' ) ) ) );
		$this->assertTrue( Csv::write( $handle, array( array( 'Bob', -2 ) ) ) );

		rewind( $handle );

		$this->assertSame( Csv::build( array( 'Name', 'Total' ), array( array( 'Ada', '=1' ), array( 'Bob', -2 ) ) ), stream_get_contents( $handle ) );
		$this->assertFalse( Csv::write( null, array() ) );

		fclose( $handle );
	}

	/**
	 * What is written reads back by its header, BOM and all.
	 */
	public function test_round_trip(): void {
		$path = $this->file( Csv::build( array( 'Email', 'Name' ), array( array( 'ada@example.test', 'Ada, "the first"' ), array( 'bob@example.test', 'Bob' ) ) ) );

		$this->assertSame(
			array(
				2 => array(
					'Email' => 'ada@example.test',
					'Name'  => 'Ada, "the first"',
				),
				3 => array(
					'Email' => 'bob@example.test',
					'Name'  => 'Bob',
				),
			),
			iterator_to_array( Csv::read( $path, array( 'Name' ) ) )
		);
	}

	/**
	 * Reordered columns, short rows, blank lines, spaces and another separator.
	 */
	public function test_read_is_forgiving(): void {
		$path = $this->file( " Name ; Email \n\nBob;  bob@example.test \nAda\n" );

		$this->assertSame(
			array(
				3 => array(
					'Name'  => 'Bob',
					'Email' => 'bob@example.test',
				),
				4 => array(
					'Name'  => 'Ada',
					'Email' => '',
				),
			),
			iterator_to_array( Csv::read( $path, array( 'Email' ), ';' ) )
		);
	}

	/**
	 * The wrong file is refused before the first row.
	 */
	public function test_missing_columns(): void {
		$path = $this->file( "Name\nAda\n" );

		try {
			iterator_to_array( Csv::read( $path, array( 'Email', 'Name', 'Date' ) ) );
			$this->fail( 'A file without its columns was read.' );
		} catch ( MissingColumns $e ) {
			$this->assertSame( array( 'Email', 'Date' ), $e->missing );
			$this->assertSame( array( 'Name' ), $e->found );
			$this->assertSame( 'Missing CSV columns: Email, Date.', $e->getMessage() );
		}
	}

	/**
	 * No file is an exception, not an empty list.
	 */
	public function test_no_file(): void {
		$this->expectException( \RuntimeException::class );

		iterator_to_array( Csv::read( '/nonexistent/file.csv' ) );
	}

	/**
	 * A temporary file with these contents.
	 *
	 * @param string $contents Contents.
	 *
	 * @return string Path.
	 */
	private function file( string $contents ): string {
		$path = (string) tempnam( sys_get_temp_dir(), 'csv' );

		file_put_contents( $path, $contents );

		$this->files[] = $path;

		return $path;
	}
}
