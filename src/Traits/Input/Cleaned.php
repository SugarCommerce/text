<?php
/**
 * Text with what cannot be seen taken out, on one line or several.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Input;

/**
 * Trait Cleaned
 *
 * Control characters and display spoofing out, whitespace tidied, a
 * length kept to; and keys and digits, which are text with only some
 * characters allowed.
 *
 * @since 1.0.0
 */
trait Cleaned {

	/**
	 * Control characters: C0 except tab, newline and carriage return, DEL,
	 * and C1.
	 *
	 * @since 1.0.0
	 */
	private const string CONTROL = '/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}-\x{9F}]/u';

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
	private const string INVISIBLE = '/[\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{3164}\x{FEFF}\x{FFA0}\x{FFF9}-\x{FFFB}\x{E0000}-\x{E007F}]/u';

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
}
