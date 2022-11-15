<?php
/**
 * @file functions.php provides utility functions for processing and validating microformats
 * $mf is generally expected to be an array although some functions can verify this, as well.
 * @link microformats.org/wiki/microformats2
 */
namespace BarnabyWalters\Mf2;

use DateTime;
use Exception;

/**
 * Iterates over array keys, returns true if has numeric keys.
 * @param array $arr
 * @return bool
 */
function hasNumericKeys(array $arr): bool {
	foreach ($arr as $key => $val) if (is_numeric($key)) return true;
	return false;
}

/**
 * Verifies if $mf is an array without numeric keys, and has a 'properties' key.
 * @param $mf
 * @return bool
 */
function isMicroformat($mf): bool {
	return (is_array($mf) and !hasNumericKeys($mf) and !empty($mf['type']) and isset($mf['properties']));
}

/**
 * Verifies if $mf has an 'items' key which is also an array, returns true.
 * @param $mf
 * @return bool
 */
function isMicroformatCollection($mf): bool {
	return (is_array($mf) and isset($mf['items']) and is_array($mf['items']));
}

/**
 * Verifies if $p is an array without numeric keys and has key 'value' and 'html' set.
 * @param $p
 * @return bool
 */
function isEmbeddedHtml($p): bool {
	return is_array($p) and !hasNumericKeys($p) and isset($p['value']) and isset($p['html']);
}

/**
 * Verifies if property named $propName is in array $mf.
 * @param array $mf
 * @param $propName
 * @return bool
 */
function hasProp(array $mf, $propName): bool {
	return !empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]);
}

/**
 * shortcut for getPlaintext.
 * @deprecated use getPlaintext from now on
 * @param array $mf
 * @param $propName
 * @param null|string $fallback
 * @return mixed|null
 */
function getProp(array $mf, $propName, $fallback = null) {
	return getPlaintext($mf, $propName, $fallback);
}

/**
 * If $v is a microformat or embedded html, return $v['value']. Else return v.
 * @param $v
 * @return mixed
 */
function toPlaintext($v) {
	if (isMicroformat($v) or isEmbeddedHtml($v))
		return $v['value'];
	return $v;
}

/**
 * Returns plaintext of $propName with optional $fallback
 * @param array $mf
 * @param $propName
 * @param null|string $fallback
 * @return mixed|null
 * @link http://php.net/manual/en/function.current.php
 */
function getPlaintext(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName])) {
		return toPlaintext(current($mf['properties'][$propName]));
	}

	return $fallback;
}

/**
 * Converts $propName in $mf into array_map plaintext, or $fallback if not valid.
 * @param array $mf
 * @param $propName
 * @param mixed $fallback default null
 * @return mixed
 */
function getPlaintextArray(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return array_map(__NAMESPACE__ . '\toPlaintext', $mf['properties'][$propName]);

	return $fallback;
}

/**
 * Returns ['html'] element of $v, or ['value'] or just $v, in order of availablility.
 * @param $v
 * @return mixed
 */
function toHtml($v) {
	if (isEmbeddedHtml($v))
		return $v['html'];
	elseif (isMicroformat($v))
		return htmlspecialchars($v['value']);
	return htmlspecialchars($v);
}

/**
 * Gets HTML of $propName or if not, $fallback
 * @param array $mf
 * @param $propName
 * @param null|string $fallback
 * @return mixed|null
 */
function getHtml(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return toHtml(current($mf['properties'][$propName]));

	return $fallback;
}

/**
 * Returns 'summary' element of $mf or a truncated Plaintext of $mf['properties']['content'] with 19 chars and ellipsis.
 * @deprecated as not often used
 * @param array $mf
 * @return mixed|null|string
 */
function getSummary(array $mf) {
	if (hasProp($mf, 'summary'))
		return getPlaintext($mf, 'summary');

	if (!empty($mf['properties']['content']))
		return substr(strip_tags(getPlaintext($mf, 'content') ?? ''), 0, 19) . '…';
}

/**
 * Gets the date published of $mf array.
 * @param array $mf
 * @param bool $ensureValid
 * @param null|string $fallback optional result if date not available
 * @return mixed|null
 */
function getPublished(array $mf, $ensureValid = false, $fallback = null) {
	return getDateTimeProperty('published', $mf, $ensureValid, $fallback);
}

/**
 * Gets the date updated of $mf array.
 * @param array $mf
 * @param bool $ensureValid
 * @param null $fallback
 * @return mixed|null
 */
function getUpdated(array $mf, $ensureValid = false, $fallback = null) {
	return getDateTimeProperty('updated', $mf, $ensureValid, $fallback);
}

/**
 * Gets the DateTime properties including published or updated, depending on params.
 * @param $name string updated or published
 * @param array $mf
 * @param bool $ensureValid
 * @param null|string $fallback
 * @return mixed|null
 */
function getDateTimeProperty($name, array $mf, $ensureValid = false, $fallback = null) {
	$compliment = 'published' === $name ? 'updated' : 'published';

	if (hasProp($mf, $name))
		$return = getPlaintext($mf, $name);
	elseif (hasProp($mf, $compliment))
		$return = getPlaintext($mf, $compliment);
	else
		return $fallback;

	if (!$ensureValid)
		return $return;
	else {
		try {
			new DateTime($return ?? '');
			return $return;
		} catch (Exception $e) {
			return $fallback;
		}
	}
}

/**
 * True if same hostname is parsed on both
 * @param $u1 string url
 * @param $u2 string url
 * @return bool
 * @link http://php.net/manual/en/function.parse-url.php
 */
function sameHostname($u1, $u2) {
	return parse_url($u1, PHP_URL_HOST) === parse_url($u2, PHP_URL_HOST);
}

/**
 * Large function for fishing out author of $mf from various possible array elements.
 * @param array $mf
 * @param array|null $context
 * @param null $url
 * @param bool $matchName
 * @param bool $matchHostname
 * @return mixed|null
 * @todo: this needs to be just part of an indiewebcamp.com/authorship algorithm, at the moment it tries to do too much
 * @todo: maybe split some bits of this out into separate functions
 *
 */
function getAuthor(array $mf, array $context = null, $url = null, $matchName = true, $matchHostname = true) {
	$entryAuthor = null;
	
	if (null === $url and hasProp($mf, 'url'))
		$url = getPlaintext($mf, 'url');
	
	if (hasProp($mf, 'author') and isMicroformat(current($mf['properties']['author'])))
		$entryAuthor = current($mf['properties']['author']);
	elseif (hasProp($mf, 'reviewer') and isMicroformat(current($mf['properties']['author'])))
		$entryAuthor = current($mf['properties']['reviewer']);
	elseif (hasProp($mf, 'author'))
		$entryAuthor = getPlaintext($mf, 'author');
	
	// If we have no context that’s the best we can do
	if (null === $context)
		return $entryAuthor;
	
	// Whatever happens after this we’ll need these
	$flattenedMf = flattenMicroformats($context);
	$hCards = findMicroformatsByType($flattenedMf, 'h-card', false);

	if (is_string($entryAuthor)) {
		// look through all page h-cards for one with this URL
		$authorHCards = findMicroformatsByProperty($hCards, 'url', $entryAuthor, false);

		if (!empty($authorHCards))
			$entryAuthor = current($authorHCards);
	}

	if (is_string($entryAuthor) and $matchName) {
		// look through all page h-cards for one with this name
		$authorHCards = findMicroformatsByProperty($hCards, 'name', $entryAuthor, false);
		
		if (!empty($authorHCards))
			$entryAuthor = current($authorHCards);
	}

	if (null !== $entryAuthor)
		return $entryAuthor;
	
	// Look for an "author" property on the top-level "h-feed" if present
	$feed = findMicroformatsByType($flattenedMf, 'h-feed', false);
	if ($feed) {
		$feed = current($feed);
		if($feed && isMicroformat($feed) && !empty($feed['properties']) && !empty($feed['properties']['author'])) {
			return current($feed['properties']['author']);
		}
	}

	// look for page-wide rel-author, h-card with that
	if (!empty($context['rels']) and !empty($context['rels']['author'])) {
		// Grab first href with rel=author
		$relAuthorHref = current($context['rels']['author']);
		
		$relAuthorHCards = findMicroformatsByProperty($hCards, 'url', $relAuthorHref);
		
		if (!empty($relAuthorHCards))
			return current($relAuthorHCards);
	}

	// look for h-card with same hostname as $url if given
	if (null !== $url and $matchHostname) {
		$sameHostnameHCards = findMicroformatsByCallable($hCards, function ($mf) use ($url) {
			if (!hasProp($mf, 'url'))
				return false;

			foreach ($mf['properties']['url'] as $u) {
				if (sameHostname($url, $u))
					return true;
			}
		}, false);

		if (!empty($sameHostnameHCards))
			return current($sameHostnameHCards);
	}

	// Without fetching, this is the best we can do. Return the found string value, or null.
	return empty($relAuthorHref)
		? null
		: $relAuthorHref;
}

/**
 * Returns array per parse_url standard with pathname key added.
 * @param $url
 * @return mixed
 * @link http://php.net/manual/en/function.parse-url.php
 */
function parseUrl($url) {
	$r = parse_url($url);
	if (empty($r['path'])) {
		$r['path'] = '/';
	}
	return $r;
}

/**
 * See if urls match for each component of parsed urls. Return true if so.
 * @param $url1
 * @param $url2
 * @return bool
 * @see parseUrl()
 */
function urlsMatch($url1, $url2) {
	$u1 = parseUrl($url1);
	$u2 = parseUrl($url2);

	foreach (array_unique(array_merge(array_keys($u1), array_keys($u2))) as $component) {
		if (!array_key_exists($component, $u1) or !array_key_exists($component, $u2)) {
			return false;
		}

		if ($u1[$component] != $u2[$component]) {
			return false;
		}
	}

	return true;
}

/**
 * Given two arrays of URLs, determine if any of them match
 * @return bool
 */
function anyUrlsMatch($array1, $array2) {
	if (!(is_array($array1) && is_array($array2))) {
		throw new \InvalidArgumentException('anyUrlsMatch must be called with two arrays');
	}

	foreach ($array1 as $url1) {
		foreach ($array2 as $url2) {
			if (urlsMatch($url1, $url2)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Representative h-card
 *
 * Given the microformats on a page representing a person or organisation (h-card), find the single h-card which is
 * representative of the page, or null if none is found.
 *
 * @see http://microformats.org/wiki/representative-h-card-parsing
 *
 * @param array $mfs The parsed microformats of a page to search for a representative h-card
 * @param string $url The URL the microformats were fetched from
 * @return array|null Either a single h-card array structure, or null if none was found
 */
function getRepresentativeHCard(array $mfs, $url) {
	$hCards = findMicroformatsByType($mfs, 'h-card');

	/**
	 * If the page contains an h-card with uid and url properties
	 * both matching the page URL, the first such h-card is the
	 * representative h-card
	 */
	$hCardMatches = findMicroformatsByCallable($hCards, function ($hCard) use ($url) {
		$hCardUid = getPlaintext($hCard, 'uid');
		$hCardUrls = getPlaintextArray($hCard, 'url');

		# h-card must have uid and url properties
		if (!($hCardUid && $hCardUrls)) {
			return false;
		}

		# uid must match the page URL
		if (!urlsMatch($hCardUid, $url)) {
			return false;
		}

		# at least one h-card.url property must match the page URL
		if (anyUrlsMatch($hCardUrls, [$url])) {
			return true;
		}

		return false;
	});

	if (count($hCardMatches) > 0) {
		return $hCardMatches[0];
	}

	/**
	 * If no representative h-card was found, if the page contains an h-card
	 * with a url property value which also has a rel=me relation
	 * (i.e. matches a URL in parse_results.rels.me), the first such h-card
	 * is the representative h-card
	 */
	if (!empty($mfs['rels']['me'])) {
		$hCardMatches = findMicroformatsByCallable($hCards, function ($hCard) use ($mfs) {
			$hCardUrls = getPlaintextArray($hCard, 'url');

			# h-card must have url property
			if (!$hCardUrls) {
				return false;
			}

			# at least one h-card.url property must match a rel-me URL
			if (anyUrlsMatch($hCardUrls, $mfs['rels']['me'])) {
				return true;
			}

			return false;
		});

		if (count($hCardMatches) > 0) {
			return $hCardMatches[0];
		}
	}

	/**
	 * If no representative h-card was found, if the page contains
	 * one single h-card, and the h-card has a url property matching
	 * the page URL, that h-card is the representative h-card
	 */
	$hCardMatches = [];
	if (count($hCards) == 1) {
		$hCardMatches = findMicroformatsByCallable($hCards, function ($hCard) use ($url) {
			$hCardUrls = getPlaintextArray($hCard, 'url');

			# h-card must have url property
			if (!$hCardUrls) {
				return false;
			}

			# at least one h-card.url property must match the page URL
			if (anyUrlsMatch($hCardUrls, [$url])) {
				return true;
			}

			return false;
		});

		if (count($hCardMatches) === 1) {
			return $hCardMatches[0];
		}
	}

	// Otherwise, no representative h-card could be found.
	return null;
}

/**
 * Makes microformat properties into a flattened array, returned.
 * @param array $mf
 * @return array
 */
function flattenMicroformatProperties(array $mf) {
	$items = array();
	
	if (!isMicroformat($mf))
		return $items;
	
	foreach ($mf['properties'] as $propArray) {
		foreach ($propArray as $prop) {
			if (isMicroformat($prop)) {
				$items[] = $prop;
				$items = array_merge($items, flattenMicroformatProperties($prop));
			}
		}
	}
	
	return $items;
}

/**
 * Flattens microformats. Can intake multiple Microformats including possible MicroformatCollection.
 * @param array $mfs
 * @return array
 */
function flattenMicroformats(array $mfs) {
	if (isMicroformatCollection($mfs))
		$mfs = $mfs['items'];
	elseif (isMicroformat($mfs))
		$mfs = array($mfs);
	
	$items = array();
	
	foreach ($mfs as $mf) {
		$items[] = $mf;
		
		$items = array_merge($items, flattenMicroformatProperties($mf));
		
		if (empty($mf['children']))
			continue;
		
		foreach ($mf['children'] as $child) {
			$items[] = $child;
			$items = array_merge($items, flattenMicroformatProperties($child));
		}
	}
	
	return $items;
}

/**
 * Find Microformats By Type
 * 
 * Traverses a mf2 tree and returns all microformats objects whose type matches the one
 * given. 
 * 
 * @param array $mfs
 * @param $name
 * @param bool $flatten
 * @return mixed
 */
function findMicroformatsByType(array $mfs, $name, $flatten = true) {
	return findMicroformatsByCallable($mfs, function ($mf) use ($name) {
		return in_array($name, $mf['type']);
	}, $flatten);
}

/**
 *
 * @param array $mfs
 * @param $propName
 * @param $propValue
 * @param bool $flatten
 * @return mixed
 * @see findMicroformatsByCallable()
 */
function findMicroformatsByProperty(array $mfs, $propName, $propValue, $flatten = true) {
	return findMicroformatsByCallable($mfs, function ($mf) use ($propName, $propValue) {
		if (!hasProp($mf, $propName))
			return false;
		
		if (in_array($propValue, $mf['properties'][$propName]))
			return true;
		
		return false;
	}, $flatten);
}

/**
 * $callable should be a function or an exception will be thrown. $mfs can accept microformat collections.
 * If $flatten is true then the result will be flattened.
 * @param array $mfs
 * @param $callable
 * @param bool $flatten
 * @return mixed
 * @link http://php.net/manual/en/function.is-callable.php
 * @see flattenMicroformats()
 */
function findMicroformatsByCallable(array $mfs, $callable, $flatten = true) {
	if (!is_callable($callable))
		throw new \InvalidArgumentException('$callable must be callable');
	
	if ($flatten and (isMicroformat($mfs) or isMicroformatCollection($mfs)))
		$mfs = flattenMicroformats($mfs);
	
	return array_values(array_filter($mfs, $callable));
}

/**
 * Remove False Positive Root Microformats
 * 
 * Unfortunately, a well-known CSS framework uses some non-semantic classnames which look like root
 * classnames to the microformats2 parsing algorithm. This function takes either a single microformat
 * or a mf2 tree and restructures it as if the false positive classnames had never been there.
 * 
 * Always returns a microformat collection (`{"items": []}`) even when passed a single microformat, as
 * if the single microformat was a false positive, it may be replaced with more than one child.
 * 
 * The default list of known false positives is stored in `FALSE_POSITIVE_ROOT_CLASSNAME_REGEXES` and
 * is used by default. You can provide your own list if you want. Some of the known false positives are
 * prefixes, so the values of `$classnamesToRemove` must all be regexes (e.g. `'/h-wrong/'`).
 */
function removeFalsePositiveRootMicroformats(array $mfs, ?array $classnamesToRemove=null) {
	if (is_null($classnamesToRemove)) {
		$classnamesToRemove = FALSE_POSITIVE_ROOT_CLASSNAME_REGEXES;
	}

	if (!isMicroformatCollection($mfs)) {
		if (isMicroformat($mfs)) {
			$mfs = ['items' => [$mfs]];
		}
		// Nothing we can do with this, return it as-is.
		return $mfs;
	}

	$correctedTree = ['items' => []];

	$recurse = function ($mf) use (&$recurse, $classnamesToRemove) {
		foreach ($mf['properties'] as $prop => $values) {
			$newPropVals = [];
			foreach ($values as $value) {
				if (isMicroformat($value)) {
					$newPropVals = array_merge($newPropVals, $recurse($value));
				} else {
					$newPropVals[] = $value;
				}
			}
			$mf['properties'][$prop] = $newPropVals;
		}

		if (!empty($mf['children'])) {
			$correctedChildren = [];
			foreach ($mf['children'] as $child) {
				$correctedChildren = array_merge($correctedChildren, $recurse($child));
			}
			$mf['children'] = $correctedChildren;
		}

		// If this mf structure’s types are all false-positive classnames, replace it with its children.
		$hasOnlyFalsePositiveRootClassnames = true;
		foreach ($mf['type'] as $mft) {
			$currentTypeIsFalsePositive = false;
			foreach ($classnamesToRemove as $ctr) {
				if (1 === preg_match($ctr, $mft)) {
					$currentTypeIsFalsePositive = true;
					break;
				}
			}
			if (false === $currentTypeIsFalsePositive) {
				$hasOnlyFalsePositiveRootClassnames = false;
				break;
			}
		}

		if ($hasOnlyFalsePositiveRootClassnames) {
			return array_key_exists('children', $mf) ? $mf['children'] : [];
		} else {
			return [$mf];
		}
	};

	foreach ($mfs['items'] as $mf) {
		$correctedTree['items'] = array_merge($correctedTree['items'], $recurse($mf));
	}

	return $correctedTree;
}

const FALSE_POSITIVE_ROOT_CLASSNAME_REGEXES = [
	// https://tailwindcss.com/docs/height
	'/h-px/',
	'/h-auto/',
	'/h-full/',
	'/h-screen/',
	'/h-min/',
	'/h-max/',
	'/h-fit/',
	// https://chat.indieweb.org/dev/2022-11-14/1668463558928800
	'/h-screen-[a-zA-Z0-9\-\_]+/',
	'/h-full-[a-zA-Z0-9\-\_]+/'
];
