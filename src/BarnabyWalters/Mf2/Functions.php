<?php

namespace BarnabyWalters\Mf2\Cleaner;

use DateTime;
use Exception;

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
	$compliment = 'published' === $name
		? 'updated'
		: 'published';

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
			new DateTime($return);
			return $return;
		} catch (Exception $e) {
			return null;
		}
	}
}

function getAuthor(array $mf, array $context = null) {
	if (hasProp($mf, 'author'))
		return getProp($mf, 'author');
}