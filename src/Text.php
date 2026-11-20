<?php
/**
 * Text for display.
 *
 * @package   SugarCommerce\Text
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Text;

use SugarCommerce\Text\Traits\Text\Cases;
use SugarCommerce\Text\Traits\Text\Markup;
use SugarCommerce\Text\Traits\Text\Masking;
use SugarCommerce\Text\Traits\Text\Shortening;
use SugarCommerce\Text\Traits\Text\Words;

/**
 * Class Text
 *
 * Everything here returns plain text and escapes nothing. Escape at the
 * point of output, for the encoding of the place it is going — these
 * results are as safe to escape as their inputs were, and no safer.
 *
 * @since 1.0.0
 */
final readonly class Text {

	use Shortening;
	use Markup;
	use Words;
	use Cases;
	use Masking;
}
