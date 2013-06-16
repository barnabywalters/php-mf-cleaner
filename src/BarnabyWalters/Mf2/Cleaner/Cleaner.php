<?php

namespace BarnabyWalters\Mf2\Cleaner;

/**
 * Cleaner
 *
 * @author barnabywalters
 */
class Cleaner {
	public function getSummary($mf, $url = null) {
		if (!empty($mf['properties']['summary']))
			return current($mf['properties']['summary']);
		
		if (!empty($mf['properties']['content']))
			return substr(strip_tags(current($mf['properties']['content'])), 0, 19) . '…';
	}
}
