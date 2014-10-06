# php-mf-cleaner

Lots of little helpers for processing canonical [microformats2](http://microformats.org/wiki/microformats2) array structures. Counterpart to indieweb/php-mf2

## Installation

Install using [Composer](https://getcomposer.org) by adding `barnabywalters/mf-cleaner` to your composer.json:

```json
{
	"require: {
		"barnabywalters/mf-cleaner": "0.*"
	}
}
```

Then require `vendor/autoload.php` as usual, and you’re ready to go.

## Usage

Should be pretty much self-documenting, you can always look in the tests to see 
exactly what a function is supposed to do. Here are some common examples:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

// Alias the namespace to ”Mf2” for convenience
use BarnabyWalters\Mf2;

// Check if an array structure is a microformat

$hCard = [
	'type' => ['h-card'],
	'properties' => [
		'name' => ['Mr. Bran']
	]
];

Mf2\isMicroformat($hCard); // true
Mf2\isMicroformat([1, 2, 3, 4, 'key' => 'value']); // false

Mf2\hasProp($hCard, 'name'); // true

Mf2\getPlaintext($hCard, 'name'); // 'Mr. Bran'

$hEntry = [
	'type' => ['h-entry'],
	'properties' => [
		'published' => ['2013-06-12 12:00:00'],
		'author' => $hCard
	]
];

Mf2\flattenMicroformats($hEntry); // returns array with $hEntry followed by $hCard
Mf2\getAuthor($hEntry); // returns $hCard, can do all sorts of handy searching

// Get the published datetime, fall back to updated if that’s present check that
// it can be parsed by \DateTime, return null if it can’t be found or is invalid
Mf2\getPublished($hEntry, true, null); // '2013-06-12 12:00:00'

$microformats = [
	'items' => [$hEntry, $hCard]
];

Mf2\isMicroformatCollection($microformats); // true

Mf2\findMicroformatsByType($microformats, 'h-card'); // [$hCard]

Mf2\findMicroformatsByProperty($microformats, 'published'); // [$hEntry]

Mf2\findMicroformatsByCallable($microformats, function ($mf) {
	return Mf2\hasProp($mf, 'published') and Mf2\hasProp($mf, 'author');
}); // [$hEntry]

```

## Contributing

Pull requests very welcome, please try to maintain stylistic, structural
and naming consistency with the existing codebase, and don’t be too upset if I 
make naming changes :)

Please add tests which cover changes you plan to make or have made. I use PHPUnit,
which is the de-facto standard for modern PHP development.

At the very least, run the test suite before and after making your changes to 
make sure you haven’t broken anything.

Issues/bug reports welcome. If you know how to write tests then please do so as
code always expresses problems and intent much better than English, and gives me
a way of measuring whether or not fixes have actually solved your problem. If you
don’t know how to write tests, don’t worry :) Just include as much useful information
in the issue as you can.

## Changelog

### v0.1.3 2014-10-06
* Improved getAuthor() algorithm, made non-standard portions optional
* Added getRepresentativeHCard() function implementing http://microformats.org/wiki/representative-h-card-parsing

### v0.1.3 2014-05-16
* Fixed issue causing getAuthor to return non-h-card microformats

### v0.1.2

### v0.1.1

### v0.1.0
* Initial version