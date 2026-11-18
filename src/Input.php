<?php
/**
 * Giving a value the shape it is meant to have.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text;

/**
 * Class Input
 *
 * A value arrives from a form, a query string, a webhook or a column, and
 * what it is going to be -- a key, a switch, a count, a string no longer
 * than the column that keeps it -- is known before it gets there. These
 * make it that, and answer rubbish with the clean empty value for its kind
 * rather than an exception: '' for text, 0 for a number, false for a switch.
 *
 * Most take `mixed`, because the caller casting first is the bug:
 * `(string) $array` is the word "Array" and a warning, and "Array" is a
 * value that reaches a database.
 *
 * Text, keys and numbers; a URL or an origin; a code somebody pasted, an id
 * with a prefix; one of an allowed set; a list typed into a field. An email
 * address is `sugarcommerce/network`'s, a colour {@see Display\Color::hex()}.
 *
 * Plain PHP. What is safe to print is WordPress's business
 * (`sanitize_text_field()`, `wp_kses_post()`); this is about shape.
 *
 * @since 1.0.0
 */
final class Input {

	/**
	 * Control characters: C0 except tab, newline and carriage return, DEL,
	 * and C1.
	 *
	 * @since 1.0.0
	 */
	private const CONTROL = '/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}-\x{9F}]/u';

	/**
	 * Characters that change how text displays without being seen.
	 *
	 * Zero-width spaces and joiners, the soft hyphen and the invisible
	 * fillers; every bidirectional control -- the embeddings and overrides
	 * (U+202A-U+202E) and the isolates (U+2066-U+2069) that "Trojan Source"
	 * used, plus the Arabic letter mark; invisible operators; the
	 * interlinear annotation marks; the byte-order mark; and the Unicode tag
	 * block, which renders as nothing and can carry a whole hidden sentence.
	 *
	 * @since 1.0.0
	 */
	private const INVISIBLE = '/[\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{3164}\x{FEFF}\x{FFA0}\x{FFF9}-\x{FFFB}\x{E0000}-\x{E007F}]/u';

	/**
	 * Text with what cannot be seen taken out.
	 *
	 * Removes control characters (C0 except tab, newline and carriage
	 * return; DEL; C1) and the characters used for display spoofing:
	 * zero-width joiners, bidirectional overrides and isolates, the
	 * byte-order mark, the tag block. A filename containing U+202E renders
	 * right-to-left and can make `exe.txt` look like `txt.exe`; a name with
	 * a zero-width space in it is two different names that look like one,
	 * which is how a "unique" display name is duplicated.
	 *
	 * Nothing else is changed: spaces, lines and length are as they were.
	 * Text that is not valid UTF-8 comes back as '': there is no safe way to
	 * guess what it was meant to say.
	 *
	 * Note that U+200D is the joiner inside many emoji (a family, a flag
	 * with a symbol); those come out as the separate pictures they are
	 * made of. That is the price of the rule, and the right one for a name
	 * or a label.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value. Anything not scalar is ''.
	 *
	 * @return string
	 */
	public static function visible( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) $value;

		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			return '';
		}

		$value = preg_replace( self::CONTROL, '', $value ) ?? '';

		return preg_replace( self::INVISIBLE, '', $value ) ?? '';
	}

	/**
	 * One line of text: nothing invisible, whitespace collapsed, capped.
	 *
	 * For a name, a label, a subject: every run of whitespace -- a tab, a
	 * newline a paste brought with it -- is one space, the ends are
	 * trimmed, and what {@see self::visible()} takes out is out.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      The value. Anything not scalar is ''.
	 * @param int   $max_length Most characters to keep. 0 for no limit.
	 *
	 * @return string
	 */
	public static function text( mixed $value, int $max_length = 0 ): string {
		$value = trim( preg_replace( '/\s+/u', ' ', self::visible( $value ) ) ?? '' );

		return $max_length > 0 ? rtrim( mb_substr( $value, 0, $max_length ) ) : $value;
	}

	/**
	 * Text that is allowed to have lines in it.
	 *
	 * text() collapses every run of whitespace to one space, which is right
	 * for a name and wrong for a note somebody typed into a textarea: their
	 * paragraphs come back as one long line.
	 *
	 * This keeps single newlines, collapses runs of blank lines to one, and
	 * still takes out what text() takes out. Carriage returns are normalised
	 * away first, so a note typed on Windows and the same note typed on a
	 * Mac store identically.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      The value. Anything not scalar is ''.
	 * @param int   $max_length Most characters to keep. 0 for no limit.
	 * @param int   $max_lines  Lines to keep. 0 for no limit.
	 *
	 * @return string
	 */
	public static function multiline( mixed $value, int $max_length = 0, int $max_lines = 0 ): string {
		// Both \r\n and a lone \r become \n before anything counts lines --
		// and before visible() could see a lone \r as anything else.
		$value = str_replace( array( "\r\n", "\r" ), "\n", self::visible( $value ) );

		// Horizontal whitespace only: a run of spaces and tabs collapses, a
		// newline survives.
		$value = preg_replace( '/[^\S\n]+/u', ' ', $value ) ?? '';

		// A space beside a newline is invisible, would otherwise count
		// towards the length, and would hide a blank line from the rule
		// below -- so it goes first.
		$value = preg_replace( '/ *\n */', "\n", $value ) ?? '';

		// Three blank lines and one say the same thing to a reader.
		$value = preg_replace( '/\n{3,}/', "\n\n", $value ) ?? '';
		$value = trim( $value );

		if ( $max_lines > 0 ) {
			$value = implode( "\n", array_slice( explode( "\n", $value ), 0, $max_lines ) );
		}

		return $max_length > 0 ? rtrim( mb_substr( $value, 0, $max_length ) ) : $value;
	}

	/**
	 * Trimmed and capped, with the inside left alone.
	 *
	 * Counts characters, not bytes, so a limit of 255 cannot cut a multibyte
	 * character in half and leave invalid UTF-8 for a database to refuse --
	 * which is what `substr()` does to the 255th byte of a Japanese name.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      The value. Anything not scalar is ''.
	 * @param int   $max_length Most characters to keep. 0 for no limit.
	 *
	 * @return string
	 */
	public static function truncate( mixed $value, int $max_length = 0 ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		return $max_length > 0 ? mb_substr( $value, 0, $max_length ) : $value;
	}

	/**
	 * A key: lower-case letters, digits, dashes and underscores.
	 *
	 * A status, a type, a slug-like identifier. The same answer as
	 * WordPress's `sanitize_key()`, without needing WordPress, and with an
	 * optional cap.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      The value. Anything not scalar is ''.
	 * @param int   $max_length Most characters to keep. 0 for no limit.
	 *
	 * @return string
	 */
	public static function key( mixed $value, int $max_length = 0 ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$key = (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );

		return $max_length > 0 ? substr( $key, 0, $max_length ) : $key;
	}

	/**
	 * Only the digits of something.
	 *
	 * The last four of a card, a phone number with its punctuation taken
	 * out. A string, because a leading nought is part of it.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      The value. Anything not scalar is ''.
	 * @param int   $max_length Most digits to keep. 0 for no limit.
	 *
	 * @return string
	 */
	public static function digits( mixed $value, int $max_length = 0 ): string {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return '';
		}

		$digits = (string) preg_replace( '/\D/', '', (string) $value );

		return $max_length > 0 ? substr( $digits, 0, $max_length ) : $digits;
	}

	/**
	 * A switch, read the way a person meant it.
	 *
	 * On is true, 1, "1", "true", "on" or "yes"; off is their opposites and
	 * the empty string. Anything else -- "maybe", an array, a typo -- is the
	 * default rather than whatever a truthiness cast makes of it:
	 * `(bool) "false"` is true, which is how a setting ends up doing the
	 * opposite of what somebody chose.
	 *
	 * Stricter than Booleans::truthy(), which is for showing a value and
	 * calls any non-empty string a yes.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   Stored or submitted value.
	 * @param bool  $default Returned when the value is not a switch at all.
	 *
	 * @return bool
	 */
	public static function bool( mixed $value, bool $default = false ): bool {
		if ( null === $value ) {
			return false;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? $default;
	}

	/**
	 * A whole number within bounds.
	 *
	 * Clamped rather than rejected: a page number of ten million is
	 * somebody fuzzing, and the last page is a better answer than an error.
	 * Compared before it is converted, so a number too big for an int --
	 * `1e30` from a JSON body -- clamps to the bound it is past instead of
	 * wrapping. A fraction is cut, not rounded.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value.
	 * @param int   $min     Lower bound.
	 * @param int   $max     Upper bound.
	 * @param int   $default Returned when the value is not a number at all.
	 *
	 * @return int
	 */
	public static function int( mixed $value, int $min = 0, int $max = PHP_INT_MAX, int $default = 0 ): int {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		if ( is_int( $value ) ) {
			return max( $min, min( $max, $value ) );
		}

		// Digits only is what a form and a column send, and the one shape a
		// float cannot be trusted with once the number is long.
		if ( is_string( $value ) && ctype_digit( $value ) && strlen( $value ) < 19 ) {
			return max( $min, min( $max, (int) $value ) );
		}

		$number = (float) $value;

		if ( is_nan( $number ) ) {
			return $default;
		}

		if ( $number >= $max ) {
			return $max;
		}

		if ( $number <= $min ) {
			return $min;
		}

		return max( $min, min( $max, (int) $number ) );
	}

	/**
	 * How many rows to return: at least one, at most a ceiling.
	 *
	 * For `?limit=` on a list. Nothing given, nought, a negative and
	 * anything that is not a number are the default -- none of them is
	 * somebody asking for one row -- and more than the ceiling is the
	 * ceiling.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value asked for.
	 * @param int   $default How many when nothing usable was asked for.
	 * @param int   $max     The most that may be asked for.
	 *
	 * @return int Between 1 and $max.
	 */
	public static function limit( mixed $value, int $default = 30, int $max = 100 ): int {
		$max   = max( 1, $max );
		$limit = self::int( $value, 0, $max, 0 );

		return $limit > 0 ? $limit : max( 1, min( $max, $default ) );
	}

	/**
	 * How many rows to skip: nought or more.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value asked for.
	 *
	 * @return int
	 */
	public static function offset( mixed $value ): int {
		return self::int( $value, 0, PHP_INT_MAX, 0 );
	}

	/**
	 * A structured value as the JSON to keep.
	 *
	 * An array or an object is encoded. A string that is already JSON is
	 * returned exactly as it is -- not decoded and encoded again, which
	 * would re-escape it, and a payload kept to be read back as it was sent
	 * should be the bytes that were sent. Anything else is ''.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value.
	 * @param int   $flags `json_encode()` flags for an array or object.
	 *
	 * @return string JSON, or '' when there is nothing to keep.
	 */
	public static function json( mixed $value, int $flags = 0 ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = json_encode( $value, $flags );

			return false === $encoded ? '' : $encoded;
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		return json_validate( $value ) ? $value : '';
	}

	/**
	 * A hex string of an exact length, lower-cased, or ''.
	 *
	 * A hash, a token: `hex( $value, 64 )` is a sha256 or nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value  The value.
	 * @param int   $length How many hex characters it must be.
	 *
	 * @return string
	 */
	public static function hex( mixed $value, int $length ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtolower( trim( $value ) );

		return strlen( $value ) === $length && ctype_xdigit( $value ) ? $value : '';
	}

	/**
	 * An http(s) URL, or ''.
	 *
	 * Other schemes are rejected outright. `javascript:` and `data:` are the
	 * ones that matter -- both become script execution the moment the value
	 * reaches an href, and `filter_var` alone accepts them happily.
	 *
	 * Valid is not the same as safe to print: `filter_var` passes
	 * `https://a.example/"><script>`, so the result still has to be escaped
	 * for the attribute it goes into. Longer than 2048 characters is
	 * refused rather than cut, because a cut URL is a different URL.
	 *
	 * With $https_only, `http://` is refused too -- for a link the app will
	 * later follow or hand out, where a plain-HTTP one can be rewritten on
	 * the way.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      Untrusted URL.
	 * @param bool  $https_only Refuse http:// as well.
	 *
	 * @return string The URL, or '' when it is not a usable one.
	 */
	public static function url( mixed $value, bool $https_only = false ): string {
		$value  = self::text( $value );
		$scheme = parse_url( $value, PHP_URL_SCHEME );
		$scheme = is_string( $scheme ) ? strtolower( $scheme ) : '';

		if ( mb_strlen( $value ) > 2048 || ! in_array( $scheme, $https_only ? array( 'https' ) : array( 'http', 'https' ), true ) ) {
			return '';
		}

		return false === filter_var( $value, FILTER_VALIDATE_URL ) ? '' : $value;
	}

	/**
	 * A code somebody pasted -- an order number, a transaction or tracking
	 * id, a licence key -- or ''.
	 *
	 * People copy more than the code: the label in front of it
	 * ("Transaction ID: …"), the quotes around it, straight or curly, and
	 * spaces from wherever it was laid out, including non-breaking and other
	 * Unicode spaces that trim() cannot see. All of that is removed; what is
	 * left has to be 1 to $max characters of A–Z, a–z, 0–9, `_` and `-`.
	 * Case is kept: some systems' codes are case-sensitive.
	 *
	 * The quotes are removed by pattern, not trim(): trim() works on bytes,
	 * and a curly quote shares its first two bytes with other characters --
	 * an em space among them -- so trimming one can cut another in half.
	 *
	 *   Input::pasted_code( '“Transaction ID: 9AB 12345”', 64, array( 'Transaction ID' ) ); // '9AB12345'
	 *
	 * @since 1.0.0
	 *
	 * @param mixed         $value  What was pasted.
	 * @param int           $max    Longest code accepted.
	 * @param array<string> $labels Labels that may come before it, followed by
	 *                              a colon. Matched case-insensitively, with
	 *                              any spacing where the label has a space
	 *                              ("Txn ID" matches "TxnID:" and "txn  id :").
	 *
	 * @return string The code, or '' when what is left is not one.
	 *
	 * @throws \InvalidArgumentException When $max is under 1.
	 */
	public static function pasted_code( mixed $value, int $max = 64, array $labels = array() ): string {
		if ( $max < 1 ) {
			throw new \InvalidArgumentException( 'A code has to be allowed at least one character.' );
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$quotes = '/^["\'\x{201C}\x{201D}\x{2018}\x{2019}]+|["\'\x{201C}\x{201D}\x{2018}\x{2019}]+$/u';
		$code   = trim( $value );

		if ( array() !== $labels ) {
			// Longest first, so a label is never cut short by one it begins with.
			usort( $labels, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

			// The quotes can be around the label and the code together, as
			// copied from a sentence, or around the code alone; a label is
			// looked for both ways.
			$names    = implode( '|', array_map( static fn( string $label ): string => (string) preg_replace( '/\s+/', '\\s*', preg_quote( trim( $label ), '/' ) ), $labels ) );
			$label    = '/^\s*(?:' . $names . ')\s*:\s*/iu';
			$unquoted = trim( preg_replace( $quotes, '', $code ) ?? $code );
			$code     = 1 === preg_match( $label, $code ) || 1 !== preg_match( $label, $unquoted ) ? $code : $unquoted;
			$code     = preg_replace( $label, '', $code ) ?? $code;
		}

		$code = preg_replace( $quotes, '', $code ) ?? $code;
		$code = preg_replace( '/[\s\p{Z}]+/u', '', $code ) ?? $code;

		return strlen( $code ) <= $max && 1 === preg_match( '/^[A-Za-z0-9_-]+$/D', $code ) ? $code : '';
	}

	/**
	 * An identifier of the form `prefix_alphanumeric`, or ''.
	 *
	 * The shape most object-store APIs use -- Stripe's `acct_`, `price_`,
	 * `cs_`; Slack's, Shopify's, plenty of others. Checking the prefix as
	 * well as the alphabet is what stops a caller passing a customer id where
	 * an account id was meant and having it silently look up nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value  Untrusted identifier.
	 * @param string $prefix Expected prefix, without the underscore.
	 *
	 * @return string The id, or '' when it does not match.
	 */
	public static function prefixed_id( string $value, string $prefix ): string {
		$value = trim( $value );

		return 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '_[A-Za-z0-9_]+$/D', $value ) ? $value : '';
	}

	/**
	 * A list of prefixed ids, each checked as {@see prefixed_id()} checks one.
	 *
	 * For the form field or JSON array that names several objects -- the
	 * prices in a cart, the products on a shelf. What does not match is
	 * dropped rather than failing the list, duplicates go, and the order
	 * the caller gave is kept, because it is usually a display order.
	 *
	 * Bounded on the way in: a list padded with ten thousand entries costs
	 * no more than one at the cap, so the cap is how many are read, not how
	 * many survive.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $values A list, or one scalar. Anything else is empty.
	 * @param string $prefix Expected prefix, without the underscore.
	 * @param int    $max    Entries read at most. 0 for no limit.
	 *
	 * @return list<string>
	 */
	public static function prefixed_ids( mixed $values, string $prefix, int $max = 0 ): array {
		$values = is_array( $values ) ? array_values( $values ) : ( is_scalar( $values ) ? array( $values ) : array() );

		if ( $max > 0 ) {
			$values = array_slice( $values, 0, $max );
		}

		$ids = array();

		foreach ( $values as $value ) {
			$id = self::prefixed_id( is_scalar( $value ) ? (string) $value : '', $prefix );

			if ( '' !== $id ) {
				$ids[ $id ] = true;
			}
		}

		return array_map( strval( ... ), array_keys( $ids ) );
	}

	/**
	 * An integer in a range, or null when nothing was chosen.
	 *
	 * For the settings that mean "no limit" when they are unset: a link that
	 * never expires, a download that is not capped. Empty string, null and
	 * zero all mean the same thing, because a select whose "unlimited" option
	 * has an empty value and a form that posts 0 are both saying it.
	 *
	 * Distinct from int(), which always returns a number. The difference is
	 * whether "not set" is a value the caller needs to keep.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Untrusted value.
	 * @param int   $min   Lower bound.
	 * @param int   $max   Upper bound.
	 *
	 * @return int|null The clamped integer, or null when unset.
	 */
	public static function int_or_null( mixed $value, int $min, int $max ): ?int {
		if ( ! is_numeric( $value ) || 0 === self::int( $value, PHP_INT_MIN, PHP_INT_MAX ) ) {
			return null;
		}

		return self::int( $value, $min, $max, $min );
	}

	/**
	 * One of a fixed set, or a default.
	 *
	 * The allow-list form of validation, which is the only one that works for
	 * values that reach SQL as identifiers -- a sort column, a table alias:
	 * an `ORDER BY` cannot take a bound parameter, so the column has to be one
	 * of the names written in code.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $value   Untrusted value.
	 * @param array<int, string> $allowed Permitted values.
	 * @param string|null        $default Returned when it is not one. Falls
	 *                                    back to the first permitted value,
	 *                                    or '' when none is permitted.
	 *
	 * @return string
	 */
	public static function one_of( string $value, array $allowed, ?string $default = null ): string {
		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return $default ?? ( array() === $allowed ? '' : $allowed[ array_key_first( $allowed ) ] );
	}

	/**
	 * One of a fixed set of whole numbers, or a default.
	 *
	 * one_of() for the sets that are numbers -- a reporting window of 7, 30
	 * or 90 days, a link lifetime of an hour or a day. Reads an int, or a
	 * string of digits as a query string or form sends one (`'30'`, `' 30 '`,
	 * `'030'`, `'-1'`).
	 *
	 * Stricter than int() on purpose. int() clamps, so it reads `'7.9'` and
	 * `'1e1'` as near enough; an allow-list asks whether the value is one of
	 * these exactly, and `'7.9'` is not 7. A float, a bool, a number too big
	 * for an int and anything with more than digits in it are the default.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed           $value   Untrusted value.
	 * @param array<int, int> $allowed Permitted values.
	 * @param int|null        $default Returned when it is not one. Falls back
	 *                                 to the first permitted value, or 0 when
	 *                                 none is permitted.
	 *
	 * @return int
	 */
	public static function one_of_int( mixed $value, array $allowed, ?int $default = null ): int {
		if ( is_string( $value ) && 1 === preg_match( '/^\s*(-?)0*(\d+)\s*$/D', $value, $parts ) ) {
			$value = filter_var( $parts[1] . $parts[2], FILTER_VALIDATE_INT );
		}

		if ( is_int( $value ) && in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return $default ?? ( array() === $allowed ? 0 : $allowed[ array_key_first( $allowed ) ] );
	}

	/**
	 * A URL's origin -- scheme, host, and a port if one was given -- or ''.
	 *
	 * An origin and nothing more, because it is compared with where a
	 * browser says a request came from, and a path would be one more thing
	 * to get wrong. https only by default: an allow-listed http origin is a
	 * page anybody on the network in between can rewrite.
	 *
	 * Refused: anything carrying credentials (`https://user@host` reads as
	 * one host to a person and another to a parser), a host outside plain
	 * letters, digits, dots and hyphens, and any other scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      A URL or an origin.
	 * @param bool  $https_only Refuse http:// as well. Default true.
	 *
	 * @return string Lower-cased `https://host[:port]`, or ''.
	 */
	public static function origin( mixed $value, bool $https_only = true ): string {
		$parts = parse_url( is_scalar( $value ) ? trim( (string) $value ) : '' );

		if ( ! is_array( $parts ) || isset( $parts['user'] ) || ! isset( $parts['host'] ) || '' === $parts['host'] ) {
			return '';
		}

		$scheme = strtolower( $parts['scheme'] ?? '' );

		if ( 'https' !== $scheme && ( $https_only || 'http' !== $scheme ) ) {
			return '';
		}

		$host = strtolower( $parts['host'] );

		return 1 === preg_match( '/^[a-z0-9.-]+$/D', $host )
			? $scheme . '://' . $host . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
			: '';
	}

	/**
	 * A list typed into one field, or sent as an array.
	 *
	 * A metadata value or a textarea of origins is written with
	 * commas, spaces, new lines or all three; this reads them all the same
	 * way. Each entry is trimmed, empties and duplicates go, the order is
	 * kept. It does not judge the entries -- pass each through the validator
	 * for what it should be.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value A string, or an array of scalars. Anything else is
	 *                     empty.
	 * @param int   $max   Entries read at most, before duplicates are
	 *                     dropped. 0 for no limit.
	 *
	 * @return list<string>
	 */
	public static function list( mixed $value, int $max = 0 ): array {
		$parts = is_array( $value )
			? array_values( $value )
			: ( preg_split( '/[\s,]+/', is_scalar( $value ) ? (string) $value : '', -1, PREG_SPLIT_NO_EMPTY ) ?: array() );

		if ( $max > 0 ) {
			$parts = array_slice( $parts, 0, $max );
		}

		$list = array();

		foreach ( $parts as $part ) {
			$part = is_scalar( $part ) ? trim( (string) $part ) : '';

			if ( '' !== $part ) {
				$list[ $part ] = true;
			}
		}

		return array_map( strval( ... ), array_keys( $list ) );
	}

	/**
	 * One entry per line, from a textarea.
	 *
	 * For entries that can hold spaces or commas themselves -- file paths,
	 * addresses, titles -- where list() would split them apart. Lines are
	 * split on any line ending, each is trimmed, and blank ones go.
	 * Duplicates and order are kept: a position can mean something (a
	 * download link that points at "the second file"), so removing one is
	 * the caller's decision.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value A string; anything else is empty.
	 * @param int   $max   Entries kept at most, 0 for no limit.
	 *
	 * @return list<string>
	 */
	public static function lines( mixed $value, int $max = 0 ): array {
		if ( ! is_string( $value ) ) {
			return array();
		}

		$lines = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $value ) ?: array() as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$lines[] = $line;

			if ( $max > 0 && count( $lines ) >= $max ) {
				break;
			}
		}

		return $lines;
	}

	/**
	 * A list of numbers, each clamped to a range, or null when it is not one.
	 *
	 * For data a browser computed and sent back -- a waveform's peaks, a
	 * chart's points. All or nothing: one entry that is not a number means
	 * the list was not what the page drew, and storing the rest would store
	 * something else. Numeric strings are refused too, because JSON numbers
	 * are what a script sends.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $value     A list of ints and floats.
	 * @param float    $min       Lower bound for each.
	 * @param float    $max       Upper bound for each.
	 * @param int      $max_count Most entries allowed; more is not a list
	 *                            of this kind. 0 for no limit.
	 * @param int|null $decimals  Round each to this many places, or null to
	 *                            leave them.
	 *
	 * @return list<float>|null Null for an empty list too.
	 */
	public static function number_list( mixed $value, float $min, float $max, int $max_count = 0, ?int $decimals = null ): ?array {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || array() === $value || ( $max_count > 0 && count( $value ) > $max_count ) ) {
			return null;
		}

		$numbers = array();

		foreach ( $value as $number ) {
			if ( ! is_int( $number ) && ! is_float( $number ) ) {
				return null;
			}

			if ( is_float( $number ) && is_nan( $number ) ) {
				return null;
			}

			$clamped   = max( $min, min( $max, (float) $number ) );
			$numbers[] = null === $decimals ? $clamped : round( $clamped, $decimals );
		}

		return $numbers;
	}
}
