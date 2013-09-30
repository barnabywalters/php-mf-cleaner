<?php

namespace BarnabyWalters\Mf2;

use BarnabyWalters\Helpers\Helpers as H;
use Carbon\Carbon;
use Exception;

function hasNumericKeys(array $arr) {
	foreach ($arr as $key=>$val) if (is_numeric($key)) return true;
}

function isMicroformat($mf) {
	return (is_array($mf) and !hasNumericKeys($mf) and !empty($mf['type']) and isset($mf['properties']));
}

function isMicroformatCollection($mf) {
	return (is_array($mf) and isset($mf['items']) and is_array($mf['items']));
}

function hasProp(array $mf, $propName) {
	return !empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]);
}

function getProp(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return current($mf['properties'][$propName]);

	return $fallback;
}

function getSummary(array $mf) {
	if (hasProp($mf, 'summary'))
		return getProp($mf, 'summary');

	if (!empty($mf['properties']['content']))
		return substr(strip_tags(getProp($mf, 'content')), 0, 19) . '…';
}

/**
 * Get Published Datetime
 * 
 * Given a mf2 structure, tries to approximate the datetime it was published. 
 * If $ensureValid is true, will return null if the found value can’t be parsed
 * by DateTime,
 * 
 * @param array $mf individual mf2 array structure
 * @param bool $ensureValid whether or not to check whether or not the potential return value can be parsed as a DateTime
 * @return string|null
 */
function getPublished(array $mf, $ensureValid = false, $fallback = null) {
	return getDateTimeProperty('published', $mf, $ensureValid, $fallback);
}

/**
 * Get Updated Datetime
 * 
 * Given a mf2 structure, tries to approximate the datetime it was 
 * last updated. 
 * If $ensureValid is true, will return null if the found value can’t be parsed
 * by DateTime.
 * 
 * @param array $mf individual mf2 array structure
 * @param bool $ensureValid whether or not to check whether or not the potential return value can be parsed as a DateTime
 * @return string|null
 */
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
			new Carbon($return);
			return $return;
		} catch (Exception $e) {
			return $fallback;
		}
	}
}

// TODO: maybe split some bits of this out into separate functions
function getAuthor(array $mf, array $context = null, $url = null) {
	$entryAuthor = null;
	
	if (null === $url and hasProp($mf, 'url'))
		$url = getProp($mf, 'url');
	
	if (hasProp($mf, 'author'))
		$entryAuthor = getProp($mf, 'author');
	elseif (hasProp($mf, 'reviewer'))
		$entryAuthor = getProp($mf, 'reviewer');
	
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
		$sameHostnameHCards = findMicroformatsByCallable($flattenedMf, function ($mf) use ($url) {
			if (!hasProp($mf, 'url'))
				return false;

			foreach ($mf['properties']['url'] as $u) {
				if (H::sameHostname($url, $u))
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
