<?php
/**
 * A secret shown only as much as recognises it.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Text;

/**
 * Trait Masking
 *
 * mask() keeps the length and both ends; redact() keeps neither the
 * length nor more than a few characters.
 *
 * @since 1.0.0
 */
trait Masking {

	/**
	 * The middle hidden, a few characters showing at each end, the length
	 * kept: `AB12********3A9F`.
	 *
	 * For a licence key or a card number shown to the person it belongs to,
	 * who recognises it by its ends and knows how long it is. A value too
	 * short to hide anything once both ends are shown is masked entirely
	 * rather than shown in full. Counts characters, so a multibyte one is
	 * shown or masked whole.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value   The value.
	 * @param int    $visible Characters shown at the start, and at the end.
	 * @param string $mask    What each hidden character becomes.
	 *
	 * @return string
	 */
	public static function mask( #[\SensitiveParameter] string $value, int $visible = 4, string $mask = '*' ): string {
		$visible = max( 0, $visible );
		$length  = mb_strlen( $value );

		if ( $length <= $visible * 2 ) {
			return str_repeat( $mask, $length );
		}

		// The tail is taken from an offset counted from the start, because a
		// negative offset of nought is nought: mb_substr( $value, -0 ) is the
		// whole string, so a visible count of 0 showed everything.
		return mb_substr( $value, 0, $visible )
			. str_repeat( $mask, $length - ( $visible * 2 ) )
			. mb_substr( $value, $length - $visible );
	}

	/**
	 * A credential or name, shortened to what recognises it: `AKIA…WXYZ`.
	 *
	 * Enough of each end to tell two apart in a list, never enough to use,
	 * and -- unlike mask() -- nothing about how long it is. Four of each end
	 * from sixteen characters up (an access key), three and two from ten,
	 * and only the first character of anything shorter, where showing both
	 * ends would show half of it or more. Counted in characters, so a name
	 * is never cut through the middle of one.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	public static function redact( #[\SensitiveParameter] string $value ): string {
		$length = mb_strlen( $value );

		if ( $length < 10 ) {
			return mb_substr( $value, 0, 1 ) . '…';
		}

		return mb_substr( $value, 0, $length >= 16 ? 4 : 3 ) . '…' . mb_substr( $value, $length >= 16 ? -4 : -2 );
	}
}
