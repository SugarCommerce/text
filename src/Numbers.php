<?php
/**
 * Numbers for display.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text;

/**
 * Class Numbers
 *
 * For money, use `sugarcommerce/money` -- money is integer minor units and
 * needs its own rules. This is for counts and rates.
 *
 * Three registers. compact(), percent(), share() and change() are plain
 * English, for logs, exports and anything parsed later. The `_i18n` methods
 * write the site's own separators through number_format_i18n(), for a
 * WordPress screen. The `_localized` methods take a language tag and go
 * through intl, for an email or a receipt in the customer's language rather
 * than the site's.
 *
 * @since 1.0.0
 */
final readonly class Numbers {

	/**
	 * Abbreviate a large count: `1.2k`, `3.4M`, `1.1B`.
	 *
	 * Below the threshold the number is left alone, because `847` is
	 * already readable and `0.8k` is worse.
	 *
	 * @since 1.0.0
	 *
	 * @param int|float $value     Value.
	 * @param int       $precision Decimal places on an abbreviated value.
	 *
	 * @return string
	 */
	public static function compact( int|float $value, int $precision = 1 ): string {
		$sign     = $value < 0 ? '-' : '';
		$absolute = abs( $value );

		if ( $absolute < 1000 ) {
			return $sign . (string) ( is_float( $value ) ? round( $absolute, $precision ) : (int) $absolute );
		}

		foreach ( array(
			'T' => 1e12,
			'B' => 1e9,
			'M' => 1e6,
			'k' => 1e3,
		) as $suffix => $threshold ) {
			if ( $absolute >= $threshold ) {
				$scaled = $absolute / $threshold;

				// A whole value drops its decimal: "2k", not "2.0k".
				$rendered = abs( $scaled - round( $scaled ) ) < 0.05
					? self::whole( $scaled )
					: number_format( $scaled, $precision );

				return $sign . $rendered . $suffix;
			}
		}

		return $sign . (string) $absolute;
	}

	/**
	 * A percentage.
	 *
	 * @since 1.0.0
	 *
	 * @param float $value     Ratio, or a percentage when `$of` is given.
	 * @param int   $precision Decimal places.
	 *
	 * @return string
	 */
	public static function percent( float $value, int $precision = 0 ): string {
		$rendered = 0 === $precision
			? self::whole( $value )
			: number_format( $value, $precision );

		return $rendered . '%';
	}

	/**
	 * A number with a language's own separators: `1.234,5` in German,
	 * `1 234,5` in French, `1,234.5` in English.
	 *
	 * For counts printed on their own, like an allowance of `5.000` files,
	 * where `5,000` in the middle of a German sentence reads as the English
	 * page it came from — and to a German reader as five.
	 *
	 * The same rule as Bytes::format_localized(): English, or no intl
	 * extension, is exactly number_format()'s output, and so is a locale
	 * intl refuses, so one read from a request cannot throw. Halves round
	 * away from zero either way — ICU's default is to the even neighbour,
	 * which would make the two paths disagree about `2.5`.
	 *
	 * @since 1.0.0
	 *
	 * @param int|float $value    The number.
	 * @param string    $locale   A language tag: `de`, `pt-BR`, `fr_FR`.
	 * @param int       $decimals Decimal places, always shown.
	 *
	 * @return string
	 */
	public static function format_localized( int|float $value, string $locale, int $decimals = 0 ): string {
		$decimals = max( 0, $decimals );
		$value    = self::unsigned_zero( $value, $decimals );
		$plain    = number_format( $value, $decimals );

		if ( is_float( $value ) && ! is_finite( $value ) ) {
			return $plain;
		}

		$formatter = self::formatter( $locale, \NumberFormatter::DECIMAL, $decimals, $decimals );
		$written   = $formatter?->format( $value );

		return is_string( $written ) ? $written : $plain;
	}

	/**
	 * A percentage with a language's own separators and sign: `3,9 %` in
	 * German, `3.9%` in English.
	 *
	 * Like percent(), $value is already a percentage — 3.9 for 3.9% — and
	 * English, no intl, or a locale intl refuses is percent()'s output.
	 * The language decides more than the decimal mark: French and German
	 * put a no-break space before the sign, and Arabic marks the direction.
	 *
	 * $trim drops trailing zeros, the way a price list writes a rate:
	 * `10%` and `3.9%` from the same column, not `10.00%` and `3.90%`.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $value     A percentage: 42.4 for 42.4%.
	 * @param string $locale    A language tag: `de`, `pt-BR`, `fr_FR`.
	 * @param int    $precision Decimal places; with $trim, at most this many.
	 * @param bool   $trim      Drop trailing zeros after the decimal mark.
	 *
	 * @return string
	 */
	public static function percent_localized( float $value, string $locale, int $precision = 0, bool $trim = false ): string {
		$precision = max( 0, $precision );
		$value     = (float) self::unsigned_zero( $value, $precision );
		$plain     = self::percent( $value, $precision );

		if ( $trim && $precision > 0 ) {
			$plain = rtrim( rtrim( substr( $plain, 0, -1 ), '0' ), '.' ) . '%';
		}

		if ( ! is_finite( $value ) ) {
			return $plain;
		}

		$formatter = self::formatter( $locale, \NumberFormatter::PERCENT, $trim ? 0 : $precision, $precision );
		$written   = $formatter?->format( $value / 100 );

		return is_string( $written ) ? $written : $plain;
	}

	/**
	 * An intl formatter for a language, or null where the plain English
	 * output stands: English itself, no intl, or a tag intl refuses.
	 *
	 * @param string $locale Language tag.
	 * @param int    $style  NumberFormatter style.
	 * @param int    $min    Fewest decimal places.
	 * @param int    $max    Most decimal places.
	 *
	 * @return \NumberFormatter|null
	 */
	private static function formatter( string $locale, int $style, int $min, int $max ): ?\NumberFormatter {
		if ( '' === $locale || str_starts_with( strtolower( $locale ), 'en' ) || ! class_exists( \NumberFormatter::class ) ) {
			return null;
		}

		try {
			$formatter = new \NumberFormatter( str_replace( '-', '_', $locale ), $style );
		} catch ( \ValueError | \IntlException ) {
			return null;
		}

		$formatter->setAttribute( \NumberFormatter::MIN_FRACTION_DIGITS, $min );
		$formatter->setAttribute( \NumberFormatter::MAX_FRACTION_DIGITS, $max );
		$formatter->setAttribute( \NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP );

		return $formatter;
	}

	/**
	 * Zero for a value that rounds to it, so nothing prints `-0`.
	 *
	 * number_format() already drops the sign; ICU keeps it, and `-0 %` beside
	 * a rate that did not change reads as a fall.
	 *
	 * @param int|float $value    Value.
	 * @param int       $decimals Decimal places it will be shown to.
	 *
	 * @return int|float
	 */
	private static function unsigned_zero( int|float $value, int $decimals ): int|float {
		return is_float( $value ) && 0.0 === round( $value, $decimals ) ? 0.0 : $value;
	}

	/**
	 * A float rounded and written with no decimal point or separators.
	 *
	 * Through an int where one holds it — so `-0.4` is `0`, not `-0` — and
	 * written out as a float where none does, rather than cast and wrapped
	 * into some other number.
	 *
	 * @param float $value Value.
	 *
	 * @return string
	 */
	private static function whole( float $value ): string {
		$rounded = round( $value );

		if ( $rounded >= (float) PHP_INT_MIN && $rounded < (float) PHP_INT_MAX ) {
			return (string) (int) $rounded;
		}

		return is_finite( $rounded ) ? number_format( $rounded, 0, '.', '' ) : (string) $rounded;
	}

	/**
	 * One number as a percentage of another.
	 *
	 * @since 1.0.0
	 *
	 * @param float $part      The part.
	 * @param float $whole     The whole.
	 * @param int   $precision Decimal places.
	 *
	 * @return string `—` when the whole is zero, since a share of nothing
	 *                is undefined rather than 0%.
	 */
	public static function share( float $part, float $whole, int $precision = 0 ): string {
		if ( 0.0 === $whole ) {
			return '—';
		}

		return self::percent( $part / $whole * 100, $precision );
	}

	/**
	 * A signed change, for a metric next to its previous period.
	 *
	 * Plain unless asked: `+12%`, `-4%`, `new`, in English and with a
	 * hyphen for the minus, which is what this has always returned and what
	 * anything already calling it shows. With `$i18n` it is written for a
	 * person on a WordPress screen -- the site's own separators, a real
	 * minus sign and a translated "New"; see {@see self::change_text()}.
	 *
	 * @since 1.0.0
	 * @param float $previous  Previous value.
	 * @param int   $precision Decimal places.
	 * @param bool  $i18n      Write it in the site's number format, translated.
	 *
	 * @return string e.g. `+12%`, `-4%`, `new` when there was nothing before.
	 */
	public static function change( float $current, float $previous, int $precision = 0, bool $i18n = false ): string {
		if ( 0.0 === $previous ) {
			if ( 0.0 === $current ) {
				return '—';
			}

			return $i18n ? self::change_text( self::TREND_NEW, null, $precision ) : 'new';
		}

		$delta = ( $current - $previous ) / abs( $previous ) * 100;

		if ( $i18n ) {
			return self::change_text( $delta > 0 ? self::TREND_UP : ( $delta < 0 ? self::TREND_DOWN : self::TREND_LEVEL ), $delta, $precision );
		}

		return ( $delta >= 0 ? '+' : '' ) . self::percent( $delta, $precision );
	}

	/* ─── For a person, on a WordPress screen ───────────────────────── */

	/**
	 * What a figure did since the period before: up, down, neither, or it
	 * was not there before.
	 *
	 * @since 1.0.0
	 */
	public const TREND_UP    = 'up';
	public const TREND_DOWN  = 'down';
	public const TREND_LEVEL = 'level';
	public const TREND_NEW   = 'new';

	/**
	 * Which way a figure went between two periods.
	 *
	 * Only the direction: `TREND_UP`, `TREND_DOWN` or `TREND_LEVEL`, from
	 * `$now <=> $before`. An int and a float that are the same number are
	 * level. For "it was not there before" -- a nought last time -- see
	 * {@see self::change()}, which answers `new`; a direction is still a
	 * direction from nought, so this says up.
	 *
	 * @since 1.0.0
	 *
	 * @param int|float $now    The figure now.
	 * @param int|float $before The figure it is compared with.
	 *
	 * @return string One of the TREND_ constants, never TREND_NEW.
	 */
	public static function trend( int|float $now, int|float $before ): string {
		return match ( $now <=> $before ) {
			1       => self::TREND_UP,
			-1      => self::TREND_DOWN,
			default => self::TREND_LEVEL,
		};
	}

	/**
	 * One number as a percentage of another, as a number.
	 *
	 * The figure itself -- 12.4 -- for sums, for an API, or to hand to
	 * {@see self::percent_i18n()}. share() is the same question answered as
	 * text.
	 *
	 * @since 1.0.0
	 *
	 * @param int|float $part      The part.
	 * @param int|float $whole     The whole.
	 * @param int       $precision Decimal places to round to.
	 *
	 * @return float|null Null when there is no whole to be a share of. No
	 *                    orders is not a refund rate of 0%: that would be a
	 *                    claim about orders there were none of.
	 */
	public static function rate( int|float $part, int|float $whole, int $precision = 1 ): ?float {
		return $whole > 0 ? round( ( $part / $whole ) * 100, $precision ) : null;
	}

	/**
	 * A percentage in the site's number format, or a dash for none.
	 *
	 * percent() is English whatever the site is set to: `1,234.5%`. This
	 * goes through number_format_i18n(), so a German site reads `1.234,5%`.
	 * Null is "there is no rate" and is a dash, not `0%`.
	 *
	 * @since 1.0.0
	 *
	 * @param int|float|null $value     A percentage -- 12.4 for 12.4% -- or
	 *                                  null for none.
	 * @param int            $precision Decimal places, always shown.
	 * @param string         $none      What to give for null.
	 *
	 * @return string `12.4%`, or `—`.
	 */
	public static function percent_i18n( int|float|null $value, int $precision = 1, string $none = "\u{2014}" ): string {
		if ( null === $value ) {
			return $none;
		}

		$precision = max( 0, $precision );

		// Outside WordPress there is no site to ask, and English it is.
		$rendered = function_exists( 'number_format_i18n' )
			? number_format_i18n( (float) $value, $precision )
			: number_format( (float) $value, $precision );

		return $rendered . '%';
	}

	/**
	 * A percentage for a sentence: `33%`, and `4.5%` when it is small.
	 *
	 * A third is 33%, and the decimal place after it is noise in something
	 * read aloud. Under ten the same decimal place is most of the figure,
	 * so it is kept.
	 *
	 * @since 1.0.0
	 *
	 * @param int|float|null $value A percentage, or null for none.
	 * @param string         $none  What to give for null.
	 *
	 * @return string `33%`, `4.5%`, `0%` or `—`.
	 */
	public static function percent_brief( int|float|null $value, string $none = "\u{2014}" ): string {
		return self::percent_i18n( $value, null !== $value && $value > 0 && $value < 10 ? 1 : 0, $none );
	}

	/**
	 * How a figure has changed, in a few characters, with its sign.
	 *
	 * For where there is no arrow beside the figure to carry the
	 * direction. The sign comes from the trend and not from the number, so
	 * a caller that has already decided a change too small to matter is
	 * "level" does not get `+0.0%`. The minus is a minus sign (U+2212), as
	 * wide as the plus it sits in a column with; a hyphen is not.
	 * Something that was not there before is new, which is not a
	 * percentage, and anything else is a dash.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $trend     One of the TREND_ constants.
	 * @param float|null $change    The change as a percentage. Its sign is
	 *                              ignored.
	 * @param int        $precision Decimal places.
	 * @param string     $new_label What to call something new. Translated
	 *                              "New" unless given.
	 * @param string     $none      What to give when there is nothing to
	 *                              compare.
	 *
	 * @return string `+12.4%`, `−8.0%`, `0.0%`, `New` or `—`. Not escaped.
	 */
	public static function change_text( string $trend, ?float $change, int $precision = 1, string $new_label = '', string $none = "\u{2014}" ): string {
		if ( self::TREND_NEW === $trend ) {
			return '' !== $new_label ? $new_label : __( 'New', 'sugarcommerce' );
		}

		$signs = array(
			self::TREND_UP    => '+',
			self::TREND_DOWN  => "\u{2212}",
			self::TREND_LEVEL => '',
		);

		if ( null === $change || ! isset( $signs[ $trend ] ) ) {
			return $none;
		}

		return $signs[ $trend ] . self::percent_i18n( abs( $change ), $precision );
	}
}
