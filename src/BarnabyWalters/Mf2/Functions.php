<?php

namespace BarnabyWalters\Mf2;

use Carbon\Carbon;
use Exception;

function hasNumericKeys(array $arr) {
	$numericKeys = array_filter(array_keys($arr), function ($i) { return is_numeric($i); });
	return count($numericKeys) !== 0;
}

function isMicroformat($mf) {
	if (!is_array($mf))
		return false;
	
	// Children must be arrays
	if (count(array_filter($mf, function ($item) { return !is_array($item); })) !== 0)
		return false;
	
	// No numeric keys
	if (hasNumericKeys($mf))
		return false;
	
	if (empty($mf['type']))
		return false;
	
	if (!isset($mf['properties']));
		return false;
	
	return true;
}

function hasProp(array $mf, $propName) {
	return !empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]);
}

function getProp(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return current($mf['properties'][$propName]);

	return $fallback;
}

function getSummary(array $mf, $url = null) {
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
function getPublished(array $mf, $ensureValid = false) {
	return getDateTimeProperty('published', $mf, $ensureValid);
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
function getUpdated(array $mf, $ensureValid = false) {
	return getDateTimeProperty('updated', $mf, $ensureValid);
}

function getDateTimeProperty($name, array $mf, $ensureValid = false) {
	$compliment = 'published' === $name ? 'updated' : 'published';

	if (hasProp($mf, $name))
		$return = getProp($mf, $name);
	elseif (hasProp($mf, $compliment))
		$return = getProp($mf, $compliment);
	else
		return null;

	if (!$ensureValid)
		return $return;
	else {
		try {
			new Carbon($return);
			return $return;
		} catch (Exception $e) {
			return null;
		}
	}
}

function getAuthor(array $mf) {
	if (hasProp($mf, 'author'))
		return getProp($mf, 'author');
	
	if (hasProp($mf, 'reviewer'))
		return getProp($mf, 'reviewer');
}

function flattenMicroformatProperties(array $mf) {
	$items = [];
	
	foreach ($mf['properties'] as $propArray) {
		foreach ($propArray as $prop) {
			if (isMicroformat($prop)) {
				$items[] = $prop;
				array_merge($items, flattenMicroformat($prop));
			}
		}
	}
	
	return $items;
}

function flattenMicroformats(array $mfs) {
	if (isset($mfs['items']))
		$mfs = $mfs['items'];
	elseif (isMicroformat($mfs))
		$mfs = [$mfs];
	
	foreach ($mfs as $mf) {
		$items[] = $mf;
		
		array_merge($items, flattenMicroformatProperties($mf));
		
		if (empty($mf['children']))
			continue;
		
		foreach ($mf['children'] as $child) {
			$items[] = $child;
			array_merge($items, flattenMicroformatProperties($child));
		}
	}
	
	return $items;
}

function findMicroformatsByType(array $mfs, $name) {
	if (isset($mfs['items']) and is_array($mfs['items']))
		$items = flattenMicroformats($mfs);
	else
		$items = $mfs;
	
	return array_values(array_filter($items, function ($mf) use ($name) {
		return in_array($name, $mf['type']);
	}));
}