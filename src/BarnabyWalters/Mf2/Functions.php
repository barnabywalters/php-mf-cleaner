<?php

namespace BarnabyWalters\Mf2;

use DateTime;
use Exception;

function hasNumericKeys(array $arr) {
	foreach ($arr as $key=>$val) if (is_numeric($key)) return true;
	return false;
}

function isMicroformat($mf) {
	return (is_array($mf) and !hasNumericKeys($mf) and !empty($mf['type']) and isset($mf['properties']));
}

function isMicroformatCollection($mf) {
	return (is_array($mf) and isset($mf['items']) and is_array($mf['items']));
}

function isEmbeddedHtml($p) {
	return is_array($p) and !hasNumericKeys($p) and isset($p['value']) and isset($p['html']);
}

function hasProp(array $mf, $propName) {
	return !empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]);
}

/** shortcut for getPlaintext, use getPlaintext from now on */
function getProp(array $mf, $propName, $fallback = null) {
	return getPlaintext($mf, $propName, $fallback);
}

function toPlaintext($v) {
	if (isMicroformat($v) or isEmbeddedHtml($v))
		return $v['value'];
	return $v;
}

function getPlaintext(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName])) {
		return toPlaintext(current($mf['properties'][$propName]));
	}

	return $fallback;
}

function getPlaintextArray(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return array_map(__NAMESPACE__ . '\toPlaintext', $mf['properties'][$propName]);

	return $fallback;
}

function toHtml($v) {
	if (isEmbeddedHtml($v))
		return $v['html'];
	elseif (isMicroformat($v))
		return htmlspecialchars($v['value']);
	return htmlspecialchars($v);
}

function getHtml(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return toHtml(current($mf['properties'][$propName]));

	return $fallback;
}

/** @deprecated as not often used **/
function getSummary(array $mf) {
	if (hasProp($mf, 'summary'))
		return getProp($mf, 'summary');

	if (!empty($mf['properties']['content']))
		return substr(strip_tags(getPlaintext($mf, 'content')), 0, 19) . '…';
}

function getPublished(array $mf, $ensureValid = false, $fallback = null) {
	return getDateTimeProperty('published', $mf, $ensureValid, $fallback);
}

function getUpdated(array $mf, $ensureValid = false, $fallback = null) {
	return getDateTimeProperty('updated', $mf, $ensureValid, $fallback);
}

function getDateTimeProperty($name, array $mf, $ensureValid = false, $fallback = null) {
	$compliment = 'published' === $name ? 'updated' : 'published';

	if (hasProp($mf, $name))
		$return = getProp($mf, $name);
	elseif (hasProp($mf, $compliment))
		$return = getProp($mf, $compliment);
	else
		return $fallback;

	if (!$ensureValid)
		return $return;
	else {
		try {
			new DateTime($return);
			return $return;
		} catch (Exception $e) {
			return $fallback;
		}
	}
}

function sameHostname($u1, $u2) {
	return parse_url($u1, PHP_URL_HOST) === parse_url($u2, PHP_URL_HOST);
}

// TODO: maybe split some bits of this out into separate functions
// TODO: this needs to be just part of an indiewebcamp.com/authorship algorithm, at the moment it tries to do too much
function getAuthor(array $mf, array $context = null, $url = null, $matchName = true, $matchHostname = true) {
	$entryAuthor = null;
	
	if (null === $url and hasProp($mf, 'url'))
		$url = getProp($mf, 'url');
	
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

function parseUrl($url) {
	$r = parse_url($url);
	$r['pathname'] = empty($r['path']) ? '/' : $r['path'];
	return $r;
}

function urlsMatch($url1, $url2) {
	$u1 = parseUrl($url1);
	$u2 = parseUrl($url2);

	foreach (array_merge(array_keys($u1), array_keys($u2)) as $component) {
		if (!array_key_exists($component, $u1) or !array_key_exists($component, $u1)) {
			return false;
		}

		if ($u1[$component] != $u2[$component]) {
			return false;
		}
	}

	return true;
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
	$hCardsMatchingUidUrlPageUrl = findMicroformatsByCallable($mfs, function ($hCard) use ($url) {
		return hasProp($hCard, 'uid') and hasProp($hCard, 'url')
			and urlsMatch(getPlaintext($hCard, 'uid'), $url)
			and count(array_filter($hCard['properties']['url'], function ($u) use ($url) {
				return urlsMatch($u, $url);
			})) > 0;
	});
	if (!empty($hCardsMatchingUidUrlPageUrl)) return $hCardsMatchingUidUrlPageUrl[0];

	if (!empty($mfs['rels']['me'])) {
		$hCardsMatchingUrlRelMe = findMicroformatsByCallable($mfs, function ($hCard) use ($mfs) {
			if (hasProp($hCard, 'url')) {
				foreach ($mfs['rels']['me'] as $relUrl) {
					foreach ($hCard['properties']['url'] as $url) {
						if (urlsMatch($url, $relUrl)) {
							return true;
						}
					}
				}
			}
			return false;
		});
		if (!empty($hCardsMatchingUrlRelMe)) return $hCardsMatchingUrlRelMe[0];
	}

	$hCardsMatchingUrlPageUrl = findMicroformatsByCallable($mfs, function ($hCard) use ($url) {
		return hasProp($hCard, 'url')
			and count(array_filter($hCard['properties']['url'], function ($u) use ($url) {
				return urlsMatch($u, $url);
			})) > 0;
	});
	if (count($hCardsMatchingUrlPageUrl) === 1) return $hCardsMatchingUrlPageUrl[0];

	// Otherwise, no representative h-card could be found.
	return null;
}

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

function findMicroformatsByType(array $mfs, $name, $flatten = true) {
	return findMicroformatsByCallable($mfs, function ($mf) use ($name) {
		return in_array($name, $mf['type']);
	}, $flatten);
}

function findMicroformatsByProperty(array $mfs, $propName, $propValue, $flatten = true) {
	return findMicroformatsByCallable($mfs, function ($mf) use ($propName, $propValue) {
		if (!hasProp($mf, $propName))
			return false;
		
		if (in_array($propValue, $mf['properties'][$propName]))
			return true;
		
		return false;
	}, $flatten);
}

function findMicroformatsByCallable(array $mfs, $callable, $flatten = true) {
	if (!is_callable($callable))
		throw new \InvalidArgumentException('$callable must be callable');
	
	if ($flatten and (isMicroformat($mfs) or isMicroformatCollection($mfs)))
		$mfs = flattenMicroformats($mfs);
	
	return array_values(array_filter($mfs, $callable));
}
