<?php
/**
 * Rendering a boolean for a person to read.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text;

/**
 * Class Booleans
 *
 * A settings screen shows "Yes", a status column shows "Enabled", a debug
 * panel shows "true". All three are the same value and none of them is
 * `var_export()`.
 *
 * These go through the translation functions, which is the reason they are
 * here rather than inline: "Yes" is a word, and a site in French wants "Oui".
 *
 * @since 1.0.0
 */
final class Booleans {

	/**
	 * Yes or no.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value What to read as a boolean.
	 *
	 * @return string
	 */
	public static function yes_no( mixed $value ): string {
		return self::truthy( $value )
			? __( 'Yes', 'sugarcommerce' )
			: __( 'No', 'sugarcommerce' );
	}

	/**
	 * Enabled or disabled.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value What to read as a boolean.
	 *
	 * @return string
	 */
	public static function enabled( mixed $value ): string {
		return self::truthy( $value )
			? __( 'Enabled', 'sugarcommerce' )
			: __( 'Disabled', 'sugarcommerce' );
	}

	/**
	 * On or off.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value What to read as a boolean.
	 *
	 * @return string
	 */
	public static function on_off( mixed $value ): string {
		return self::truthy( $value )
			? __( 'On', 'sugarcommerce' )
			: __( 'Off', 'sugarcommerce' );
	}

	/**
	 * Active or inactive.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value What to read as a boolean.
	 *
	 * @return string
	 */
	public static function active( mixed $value ): string {
		return self::truthy( $value )
			? __( 'Active', 'sugarcommerce' )
			: __( 'Inactive', 'sugarcommerce' );
	}

	/**
	 * What counts as true.
	 *
	 * Wider than PHP's own cast, because the values arriving here have been
	 * through a database and a form. Meta comes back as the string "0", a
	 * checkbox posts "on", and a settings array that has been JSON encoded and
	 * decoded carries "false" -- which PHP reads as true, being a non-empty
	 * string, and which is the bug this exists to avoid.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	public static function truthy( mixed $value ): bool {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );

			if ( in_array( $value, array( 'false', 'no', 'off', 'null', '' ), true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
