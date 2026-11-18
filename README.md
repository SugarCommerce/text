# Text

Turn raw values into something a person can read, and turn what a person typed into something safe to keep.

## What it does

Every admin screen ends up with the same formatters written inline -- a title cut to fit a column, `1.2k`, `2.4 MB`,
"red, green and blue", `Licence activations` from `licence_activations` -- and every form handler with the same
clean-up: a name with a zero-width space in it, `?per=100000`, `(bool) "false"`. They are each small, which is exactly
why they get rewritten per screen and drift apart. This is one copy of each.

## Features

- Shorten text to fit -- at a word, at an exact width, to a number of words, or keeping both ends of a hash
- Turn a machine key into a label, and convert between camelCase, snake_case, Title Case and sentence case
- Join a list into "red, green and blue", pluralise against a count, write 1st, 2nd, 3rd
- Write counts, percentages and changes the way the site -- or the customer's language -- writes numbers
- Format bytes and durations, and parse `512M` and `2h 5m` back
- Give an incoming value its shape: a capped line of text with nothing invisible in it, a clamped number, a strict switch, one of an allowed set, a URL, a pasted order number
- Find "licence" in "License Agreement.pdf": every word typed, any order, one typo forgiven, accents ignored
- Page a list and draw its controls, and write CSV a spreadsheet will not run as formulas

## Installation

```bash
composer require sugarcommerce/text
```

## Quick start

```php
use SugarCommerce\Text\Input;
use SugarCommerce\Text\Numbers;
use SugarCommerce\Text\Text;

// An order list row.
Text::truncate( $product_title, 40 );          // "Midnight Drive - Extended Master…"
Text::plural( $count, 'item' );                 // "1 item" / "4 items"
Numbers::percent_i18n( Numbers::rate( 3, 40 ) );// "7.5%" -- "7,5%" on a German site
Text::humanize( 'licence_activations' );        // "Licence activations"

// The form that edits it.
$note  = Input::multiline( $_POST['note'] ?? '', 2000, 50 );
$limit = Input::limit( $_GET['per_page'] ?? null, 20, 100 );
```

Everything returns plain text and escapes nothing. Escape where it is printed.

## Text for display

```php
use SugarCommerce\Text\Text;

Text::truncate( $title, 40 );                 // at a word, the ellipsis inside the 40
Text::cut( $sku_list, 20 );                    // exactly 20, wherever that falls: "SKU-1, SKU-2, SKU-..."
Text::truncate_words( $description, 20 );      // twenty words and "..."
Text::middle( $hash, 10, 8 );                  // "a1b2c3d4e5…c3d4e5f6" -- both ends kept
Text::excerpt( $post_content, 160, strip_tags: true );
Text::paragraphs( $note );                     // every line break a paragraph, blanks dropped

Text::list( array( 'red', 'green', 'blue' ) ); // "red, green and blue"
Text::list( $names, 'and', 3 );                // "Ada, Bob, Cy and 4 more"
Text::plural( 3, 'entry' );                    // "3 entries"
Text::ordinal( 22 );                           // "22nd"
Text::dash( $maybe_empty );                    // the value, or an em dash -- 0 is a value

Text::initials( 'Dave Michael Sherlock' );     // "DS" -- first and last, for a person
Text::initials( 'Trance Vol 1', from_start: true ); // "TV" -- first two, for a title
Text::acronym( 'Acme Trading Co' );            // "ATC" -- one from every word
```

## Case

```php
Text::humanize( 'track_BPM' );     // "Track BPM" -- sentence case, capitals kept
Text::name_case( 'GEORGE HILL' );  // "George Hill"
Text::title( 'élan vital' );       // "Élan Vital"
Text::sentence( 'HELLO THERE' );   // "Hello there"
Text::camel( 'order_total' );      // "orderTotal"
Text::snake( 'HTMLParser' );       // "html_parser"
```

## Masking

```php
Text::mask( $licence_key );        // "AB12********3A9F" -- the length kept, for its owner
Text::redact( $access_key );       // "AKIA…WXYZ" -- nothing about the length, for a list
```

## Inside strings

```php
use SugarCommerce\Text\Strings;

Strings::contains_any( $filename, 'invoice', 'receipt' );
Strings::starts_with( $path, array( '/wp-admin', '/wp-login' ) );
Strings::matches_any( $email_domain, $blocked, wildcard: true );   // "mail.*" matches "mail.ru"
Strings::replace_last( '-', ' – ', $title );
Strings::between( '[', ']', 'Order [ABC-12] paid' );               // "ABC-12"
Strings::split( 'a, b,,c' );                                        // ['a', 'b', '', 'c']
Strings::from( $anything );                                         // JSON for an array, never "Array"
```

## Numbers

```php
use SugarCommerce\Text\Numbers;

Numbers::compact( 12400 );                 // "12.4k"
Numbers::share( 3, 40, 1 );                // "7.5%", or "—" of nothing
Numbers::change( 120, 100 );               // "+20%", "new" when there was nothing before
Numbers::trend( 80, 100 );                 // Numbers::TREND_DOWN

// On a WordPress screen: the site's separators, a real minus sign, "New" translated.
Numbers::percent_i18n( 1234.5 );           // "1,234.5%" -- "1.234,5%" on a German site
Numbers::percent_brief( 33.3 );            // "33%" (4.5% keeps its decimal)
Numbers::change_text( Numbers::TREND_DOWN, 8.0 ); // "−8.0%"

// In the customer's language, through intl -- a receipt, an email.
Numbers::format_localized( 1234.5, 'de', 1 );     // "1.234,5"
Numbers::percent_localized( 3.9, 'fr', 1 );       // "3,9 %"
```

Money is not here: it is integer minor units with its own rules, in `sugarcommerce/money`.

## Bytes, durations and files

```php
use SugarCommerce\Text\Bytes;
use SugarCommerce\Text\Duration;
use SugarCommerce\Text\FileType;

Bytes::format( 104857600 );                // "100 MB", not "100.0 MB"
Bytes::format( 1000, decimal: true );      // "1 kB"
Bytes::format_localized( 1572864, 'de' );  // "1,5 MB"
Bytes::parse( '512M' );                    // 536870912, null for nonsense or too big

Duration::clock( 4530 );                   // "1:15:30"
Duration::compact( 7500 );                 // "2h 5m"
Duration::words( 7500 );                   // "about 2 hours"
Duration::parse( '2h 5m' );                // 7500

FileType::label( 'Mix.WAV' );              // "WAV audio"
FileType::kind_of_mime( 'application/pdf' );   // "document"
FileType::mime( 'cover.png' );             // "image/png"
FileType::inline( 'stem.aiff' );           // "" -- not every browser plays it
FileType::pretty( 'https://example.com/' );// "example.com"
```

## Input

A value arrives from a form, a query string or a webhook, and what it is going to be is known before it gets there.
These make it that, and answer rubbish with the empty value for its kind -- `''`, `0`, `false` -- never an exception.
They take `mixed`, because `(string) $array` is the word "Array" and a warning.

```php
use SugarCommerce\Text\Input;

Input::visible( "invoice\u{202E}txt.exe" );    // "invoicetxt.exe" -- bidi, zero-width, tag block out
Input::text( $name, 60 );                       // one line, nothing invisible, 60 characters at most
Input::multiline( $note, 2000, 50 );            // lines kept, blank runs one blank line
Input::truncate( $html, 20000 );                // trimmed and capped, inside untouched
Input::key( $status );                          // sanitize_key() without WordPress
Input::digits( '+44 (0)20 7946' );              // "440207946"
Input::bool( $setting );                        // strict: "false" is false, "maybe" the default
Input::int( $page, 1, 500, 1 );                 // clamped; 1e30 is 500, not a wrapped number
Input::int_or_null( $expiry_days, 1, 3650 );    // null for unset, 0 or ''
Input::limit( $_GET['per_page'] ?? null );      // 1 to 100, 30 by default
Input::one_of( $_GET['orderby'] ?? '', array( 'date', 'total' ), 'date' );
Input::one_of_int( $_GET['days'] ?? null, array( 7, 30, 90 ), 30 );
Input::url( $link, https_only: true );          // http(s) only; javascript: and data: are ''
Input::origin( 'https://Shop.example.com/x' );  // "https://shop.example.com"
Input::pasted_code( '“Order ID: 9AB 12”', 64, array( 'Order ID' ) ); // "9AB12"
Input::prefixed_id( $stripe_price, 'price' );   // "price_1A2b", or ''
Input::hex( $token, 64 );                       // a sha256, lower-cased, or ''
Input::hex_colour( $brand );                    // "#6d28d9", or ''
Input::json( $payload );                        // JSON kept byte for byte, or ''
Input::list( "a, b\nc" );                       // ['a', 'b', 'c'], duplicates gone
Input::lines( $textarea );                      // one entry per line, commas kept
Input::number_list( $peaks, 0.0, 1.0, 4000, 2 );// clamped floats, or null
```

What is safe to print is WordPress's business (`esc_html()`, `wp_kses_post()`); this is about shape. An email address
is `sugarcommerce/network`'s.

## Search

```php
use SugarCommerce\Text\Search;

Search::matches( 'Licence Agreement.pdf', 'license' );   // true -- one letter out
Search::matches( 'Midnight Drive - Master.wav', 'drive midnigth' ); // true -- any order, a swap is one typo
Search::filter( $files, $_GET['s'] ?? '', 'name' );        // the rows that match, in order
```

Every word typed has to be found; a word of four letters or more may be one edit out; accents are ignored where intl is
installed. In PHP rather than SQL because `LIKE` can do the first and not the second.

## Pagination

```php
use SugarCommerce\Text\Pagination;

$page = Pagination::of( $total, $_GET, 50 );   // reads ?page=, clamps it to the pages there are
$rows = $wpdb->get_results( $wpdb->prepare( "SELECT … LIMIT 50 OFFSET %d", $page->offset() ) );

$page->window();          // [1, 2, null, 8, 9, 10, 11, 12, null, 19, 20] -- null is a gap
$page->page_link( 3 );    // "?page=3&s=alf" -- every other parameter kept
$page->next_link();       // or null on the last page
$page->sort_link( 'total' );  // flips direction on the current column, drops the page
$page->from(); $page->to();   // "showing 51–100 of 940"
$page->meta();            // ['total' => 940, 'page' => 2, 'pages' => 19, 'per_page' => 50]

$page = Pagination::slice( $rows_in_memory, $_GET );   // rows narrowed in PHP, ?per= allow-listed
```

It runs no query: count and fetch with whatever the list already uses, and take the arithmetic and links from here.

## CSV a spreadsheet will not run

Excel, Numbers and Sheets run a cell that starts with `=` as a formula, and an export is full of what customers typed.
`Csv` puts an apostrophe in front of a cell that starts with `=`, `+`, `-`, `@`, `|`, a tab or a carriage return (also
after leading whitespace), and leaves numbers alone -- `-12.50` is a refund and must still sum.

```php
use SugarCommerce\Text\Csv;
use SugarCommerce\Text\MissingColumns;

$csv = Csv::build( array( 'Name', 'Email', 'Total' ), $rows );   // with a BOM, so Excel reads UTF-8

Csv::write( $handle, $batch );                                     // an export too big for memory
Csv::cell( '=HYPERLINK("https://evil.test")' );                    // "'=HYPERLINK(…)"

try {
    foreach ( Csv::read( $path, array( 'Email', 'Date' ) ) as $line => $row ) {
        $row['Email'];                                             // by header, so column order does not matter
    }
} catch ( MissingColumns $e ) {
    echo 'Missing: ' . implode( ', ', $e->missing );
}
```

## Yes and no

```php
use SugarCommerce\Text\Booleans;

Booleans::yes_no( get_post_meta( $id, '_featured', true ) );   // "Yes" / "No", translated
Booleans::enabled( $setting );                                  // "Enabled" / "Disabled"
Booleans::truthy( 'false' );                                    // false, unlike (bool)
```

## Pictures: avatars, palettes and sparklines

These draw rather than write, and live under their own namespace, `SugarCommerce\Text\Display`, for now -- they may
move to a package of their own later.

```php
use SugarCommerce\Text\Display\Avatar;
use SugarCommerce\Text\Display\Palette;
use SugarCommerce\Text\Display\Sparkline;

// A person as an inline monogram: the same colour on every screen, no request to anybody.
echo Avatar::monogram( 'Ada Lovelace', 'ada@example.com', 32, array( 'class' => 'myplugin-avatar' ) );
Avatar::svg( 'Trance Essentials', 200, from_start: true );   // a data: URI placeholder for an <img>

$palette = Palette::clean( apply_filters( 'myplugin_palette', Palette::sweet() ), Palette::sweet() );
Palette::contrast( '#d0ecfb', '#0d5e8c' );                   // 5.7 -- 4.5 is WCAG AA

// A line with a fading fill, as inline SVG: no script, no canvas.
echo Sparkline::svg( $weekly_sales, array( 'label' => 'Up 12% on the four weeks before', 'emphasise_last' => true ) );
```

## Moving from arraypress/wp-*

`ArrayPress\FormatUtils\X` is now `SugarCommerce\Text\X` unless the table says otherwise; every method of `Text`,
`Numbers`, `Booleans`, `Bytes`, `Duration`, `FileType`, `Csv`, `MissingColumns` and `Input` keeps its name and
arguments. Text domain `arraypress` is now `sugarcommerce`.

| Old | New |
|---|---|
| `ArrayPress\FormatUtils\Avatar` | `SugarCommerce\Text\Display\Avatar` |
| `ArrayPress\FormatUtils\Palette` | `SugarCommerce\Text\Display\Palette` |
| `ArrayPress\FormatUtils\Sparkline` | `SugarCommerce\Text\Display\Sparkline` |
| `ArrayPress\FormatUtils\{Text,Numbers,Booleans,Bytes,Duration,FileType,Csv,MissingColumns,Input}` | `SugarCommerce\Text\…`, same name |
| `Str::contains_any()`, `contains_all()`, `starts_with()`, `ends_with()`, `matches_any()`, `is_alphanumeric()` | `Strings::` the same |
| `Str::replace_first()`, `replace_last()`, `between()`, `reduce_whitespace()`, `remove_whitespace()`, `from()` | `Strings::` the same |
| `Str::to_array( $value, $sep )` | `Strings::split( $value, $sep )` |
| `Str::to_words()` | `Strings::words()` |
| `Str::to_lines()` | `Input::lines()` -- each line is now trimmed as well |
| `Str::truncate( $value, $length, $suffix )` | `Text::cut()` -- same arguments; `Text::truncate()` is the word-safe one |
| `Str::words( $value, $limit, $suffix )` | `Text::truncate_words()` |
| `Str::excerpt( $html, $length )` | `Text::excerpt( $html, $length, strip_tags: true )` -- now cut at a word, with `…`; `Text::cut( Text::strip_tags( $html ), $length )` for the old exact cut |
| `Str::initials( $name, $limit, $uppercase )` | `Text::acronym()` -- same arguments; `Text::initials()` is the two-letter one |
| `Str::mask()` | `Text::mask()` |
| `Str::camel()`, `snake()`, `title()`, `sentence()` | `Text::` the same |

From the retired SaaS packages: `Format\Search` is `Search`; `Format\Text::middle()`, `name_case()`, `paragraphs()`,
`Numbers::format_localized()`, `percent_localized()`, `Bytes::format_localized()`, `FileType::mime()` and `inline()` are
here under the same names; `Sanitize\Input::clean_text()` is `Input::text()`, `Input::mask()` is `Text::redact()`, and
its other methods are `Input::` the same; `Paginate\Page` is `Pagination`, with `Paginator::SIZES` and `check_sizes()`
on it. Not carried over: `Paginator` (it ran SQL against `sugarcommerce/sqlite`; a WordPress list counts and fetches
with `$wpdb` or a table library and takes `Pagination::of()` from here), and `Input::email()` / `mask_email()`, which
are `sugarcommerce/network`'s.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
