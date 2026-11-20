<?php
/**
 * Text made safe the way WordPress makes it safe, kept to a column's length.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Input;

/**
 * Trait Fields
 *
 * A line, a few lines or markup on its way into a column: put through the
 * WordPress function a form field's value goes through, then cut to the
 * column's length counting characters. The one part of Input that needs
 * WordPress loaded.
 *
 * @since 1.0.0
 */
trait Fields {

	/**
	 * One line of plain text, the way `sanitize_text_field()` makes it.
	 *
	 * Tags, line breaks, tabs and percent-encoded octets go and runs of
	 * space become one; then the value is trimmed and cut to `$max_length`
	 * characters -- never inside one, which a byte-counting `substr()` does
	 * and MySQL refuses. Unlike {@see self::text()}, which only takes out
	 * what cannot be seen, this is what a WordPress screen expects of a
	 * text field.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      The value. Anything not scalar is ''.
	 * @param int   $max_length Most characters to keep. 0 for no limit.
	 *
	 * @return string
	 */
	public static function text_field( mixed $value, int $max_length = 0 ): string {
		return self::truncate( is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '', $max_length );
	}

	/**
	 * Plain text of more than one line, the way `sanitize_textarea_field()`
	 * makes it.
	 *
	 * As {@see self::text_field()}, keeping the line breaks: a description,
	 * a changelog, an error.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value      The value. Anything not scalar is ''.
	 * @param int   $max_length Most characters to keep. 0 for no limit.
	 *
	 * @return string
	 */
	public static function textarea_field( mixed $value, int $max_length = 0 ): string {
		return self::truncate( is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '', $max_length );
	}

	/**
	 * Text that may carry the markup a post may, through `wp_kses_post()`.
	 *
	 * A note, a message to a buyer. Not trimmed and not cut: markup cut
	 * part-way through is broken markup.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value. Anything not scalar is ''.
	 *
	 * @return string
	 */
	public static function html( mixed $value ): string {
		return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';
	}
}
