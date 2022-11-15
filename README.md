# php-mf-cleaner

[![Latest Stable Version](http://poser.pugx.org/barnabywalters/mf-cleaner/v)](https://packagist.org/packages/barnabywalters/mf-cleaner) <a href="https://github.com/barnabywalters/mf-cleaner/actions/workflows/php.yml"><img src="https://github.com/barnabywalters/php-mf-cleaner/actions/workflows/php.yml/badge.svg?branch=main" alt="" /></a> [![License](http://poser.pugx.org/barnabywalters/mf-cleaner/license)](https://packagist.org/packages/barnabywalters/mf-cleaner) [![Total Downloads](http://poser.pugx.org/barnabywalters/mf-cleaner/downloads)](https://packagist.org/packages/barnabywalters/mf-cleaner) 

Lots of little helpers for processing canonical [microformats2](http://microformats.org/wiki/microformats2) array structures. Counterpart to [indieweb/php-mf2](https://github.com/indieweb/php-mf2).

## Installation

barnabywalters/mf-cleaner is currently tested against and compatible with PHP 7.3, 7.4, 8.0 and 8.1.

Install barnabywalters/mf-cleaner using [composer](https://getcomposer.org/):

    composer.phar require barnabywalters/mf-cleaner
    composer.phar install (or composer.phar update)

Versioned releases are GPG signed so you can verify that the code hasn’t been tampered with.

    gpg --recv-keys 1C00430B19C6B426922FE534BEF8CE58118AD524
    cd vendor/barnabywalters/mf-cleaner
    git tag -v v0.2.0 # Replace with the version you have installed

## Usage

Most of the functions are self explanatory, and all come with summaries in docblocks if something is unclear. This example shows the most common usage:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

// Alias the namespace to ”Mf2” for convenience
use BarnabyWalters\Mf2;

// Check if an array structure is a microformat

$hCard = [
	'type' => ['h-card'],
	'properties' => [
		'name' => ['Example McExampleface'],
		'photo' => [['value' => 'https://example.org/photo.png', 'alt' => 'a photo of an example']],
		'logo' => ['https://example.org/logo.png']
	]
];

Mf2\isMicroformat($hCard); // true
Mf2\isMicroformat([1, 2, 3, 4, 'key' => 'value']); // false

Mf2\hasProp($hCard, 'name'); // true

Mf2\getPlaintext($hCard, 'name'); // 'Example McExampleface'

Mf2\getPlaintext($hCard, 'photo'); // 'https://example.org/photo.png'
Mf2\getImgAlt($hCard, 'photo'); // ['value' => 'https://example.org/photo.png', 'alt' => 'a photo of an example']

Mf2\getImgAlt($hCard, 'logo'); // ['value' => 'https://example.org/logo.png', 'alt' => '']

$hEntry = [
	'type' => ['h-entry'],
	'properties' => [
		'published' => ['2013-06-12 12:00:00'],
		'author' => [$hCard],
		'summary' => ['A plaintext summary with <>&" HTML special characters :o'],
		'content' => [['value' => 'Hi!', 'html' => '<p><em>Hi!</em></p>']]
	]
];

Mf2\flattenMicroformats($hEntry); // returns array with $hEntry followed by $hCard
Mf2\getAuthor($hEntry); // returns $hCard. Is an incomplete but still useful implementation of https://indieweb.org/authorship-spec which doesn’t follow links.

// Get the published datetime, fall back to updated if that’s present check that
// it can be parsed by \DateTime, return null if it can’t be found or is invalid
Mf2\getPublished($hEntry, true, null); // '2013-06-12 12:00:00'

Mf2\getHtml($hEntry, 'content'); // '<p><em>Hi!</em></p>'
Mf2\getHtml($hEntry, 'summary'); // "A plaintext summary with &lt;&gt;&amp;&quot; HTML special characters :o"

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

If you have any questions about using this library, join the [indieweb dev chatroom](https://chat.indieweb.org/dev/), and ping `barnaby` or ask one of the other friendly people there.

If you find a bug or problem with the library, or want to suggest a feature, please [create an issue](https://github.com/barnabywalters/php-mf-cleaner/issues/new).

If discussions lead to you wanting to submit a pull request, following this process, while not required, will increase the chances of it quickly being accepted:

* Fork this repo to your own github account, and clone it to your development computer.
* Run `./run_coverage.sh` and ensure that all tests pass — you’ll need XDebug for code coverage data.
* If applicable, write failing regression tests e.g. for a bug you’re fixing.
* Make your changes.
* Run `./run_coverage.sh` and `open docs/coverage/index.html`. Make sure that the changes you made are covered by tests. mf-cleaner had nearly 100% test coverage from early in its development, and that number should never go down!
* Run `./vendor/bin/psalm` and and fix any warnings it brings up.
* Install and run `./phpDocumentor.phar` to regenerate the documentation if applicable.
* Push your changes and submit the PR.

## Changelog

### v0.2.0

2022-11-15

> Awoken from their eight year long slumber, the maintainer lurched into activity to release a long-overdue update…

**Breaking Changes:**

* Raised minimum PHP version to 7.3
* Renamed main branch from `master` to `main`. If you were requiring `dev-master` you will need to rename it to `dev-main`

Other changes:

* Added support for img-alt structures. `getPlaintext()` and `toPlaintext()` correctly return the `value` value. Added `isImgAlt()`, `toImgAlt()` and `getImgAlt()`, all of which do exactly what you’d expect them to.
* Initial implementation of `removeFalsePositiveRootMicroformats()`, to restructure mf2 data into something usable when known non-mf2 h-* classnames are used
* Added some more tests to improve coverage
* Set up GH Action CI to test against PHP 7.3, 7.4, 8.0 and 8.1
* Set up /docs with generated documentation (thanks HongPong!) and public code coverage info
* getAuthor additionally looks for an h-feed author property (thanks aaronpk!)
* Moved deeply nested Functions file to a shallower location for convenience
* Started signing release tags to enable verification
* Updated readme usage

### v0.1.4
2014-10-06

* Improved getAuthor() algorithm, made non-standard portions optional
* Added getRepresentativeHCard() function implementing http://microformats.org/wiki/representative-h-card-parsing

### v0.1.3
2014-05-16

* Fixed issue causing getAuthor to return non-h-card microformats

### v0.1.2

### v0.1.1

### v0.1.0
* Initial version