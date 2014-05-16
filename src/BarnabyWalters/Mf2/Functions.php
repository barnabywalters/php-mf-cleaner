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
function getAuthor(array $mf, array $context = null, $url = null) {
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
	if (null !== $url) {
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

	// *sigh* return the first h-card or null
	return empty($hCards)
		? null
		: $hCards[0];
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
