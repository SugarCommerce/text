<?php
/**
 * An http(s) URL, or an origin.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text\Traits\Input;

/**
 * Trait Urls
 *
 * Other schemes refused, so a value cannot become script in an href.
 *
 * @since 1.0.0
 */
trait Urls {

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
}
