<?php

namespace BarnabyWalters\Mf2\Cleaner;

function mfHasProp(array $mf, $propName) {
	return !empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]);
}

function mfProp(array $mf, $propName) {
	return current($mf['properties'][$propName]);
}

/**
 * Cleaner
 *
 * @author barnabywalters
 */
class Cleaner {
	public function getSummary(array $mf, $url = null) {
		if (mfHasProp($mf, 'summary'))
			return mfProp($mf, 'summary');
		
		if (!empty($mf['properties']['content']))
			return substr(strip_tags(mfProp($mf, 'content')), 0, 19) . '…';
	}
	
	public function getPublished(array $mf) {
		if (mfHasProp($mf, 'published'))
			return mfProp($mf, 'published');
		
		if (mfHasProp($mf, 'updated'))
			return mfProp($mf, 'updated');
	}
}
