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

use SugarCommerce\Text\Traits\Input\Bounded;
use SugarCommerce\Text\Traits\Input\Choices;
use SugarCommerce\Text\Traits\Input\Cleaned;
use SugarCommerce\Text\Traits\Input\Codes;
use SugarCommerce\Text\Traits\Input\Lists;
use SugarCommerce\Text\Traits\Input\Urls;

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
final readonly class Input {

	use Cleaned;
	use Bounded;
	use Choices;
	use Codes;
	use Urls;
	use Lists;
}
