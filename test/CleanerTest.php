<?php

namespace BarnabyWalters\Mf2;

use PHPUnit_Framework_TestCase;

/**
 * CleanerTest
 *
 * @author barnabywalters
 */
class CleanerTest extends PHPUnit_Framework_TestCase {
	protected function mf($name, array $properties) {
		if (is_array($name))
			$type = $name;
		else
			$type = [$name];
		
		foreach ($properties as $name => $arg) {
			if (is_array($arg))
				$properties[$name] = $arg;
			else
				$properties[$name] = [$arg];
		}
		
		return [
			'type' => $type,
			'properties' => $properties
		];
	}
	
	public function testIsMicroformatReturnsFalseIfNotArray() {
		$this->assertFalse(isMicroformat(''));
	}
	
	public function testIsMicroformatReturnsFalseIfContainsNonArrayValues() {
		$this->assertFalse(isMicroformat([[], '']));
	}
	
	public function testIsMicroformatReturnsFalseIfTypeMissing() {
		$this->assertFalse(isMicroformat(['properties' => []]));
	}
	
	public function testIsMicroformatReturnsFalseIfPropertiesMissing() {
		$this->assertFalse(isMicroformat(['type' => ['h-thing']]));
	}
	
	public function testIsMicroformatReturnsFalseIfHasNumericKeys() {
		$this->assertFalse(isMicroformat([[], 'thing' => []]));
	}
	
	public function testHasNumericKeysWorks() {
		$withNumericKeys = ['a', 'b', 'c'];
		$noNumericKeys = ['key' => 'value'];
		
		$this->assertTrue(hasNumericKeys($withNumericKeys));
		$this->assertFalse(hasNumericKeys($noNumericKeys));
	}
	
	public function testIsMicroformatCollectionChecksForItemsKey() {
		$this->assertTrue(isMicroformatCollection(['items' => []]));
		$this->assertFalse(isMicroformatCollection(['notItems' => []]));
	}
	
	public function testGetSummaryPassesIfSummaryPresent() {
		$mf = $this->mf('h-entry', ['summary' => 'Hello Summary']);
		$result = getSummary($mf);
		$this->assertEquals($mf['properties']['summary'][0], $result);
	}
	
	public function testGetSummaryUsesStrippedFirstCharactersOfContent() {
		$result = getSummary([
			'type' => ['h-entry'],
			'properties' => [
				'content' => ['<p>Hello hello hello there indeed</p>']
			]
		]);
		
		$this->assertEquals('Hello hello hello t…', $result);
	}
	
	public function testGetPublishedPassesIfPublishedPresent() {
		$mf = $this->mf('h-entry', ['published' => '2013-12-06']);
		$result = getPublished($mf);
		$this->assertEquals(getProp($mf, 'published'), $result);
	}
	
	public function testGetPublishedFallsBackToUpdated() {
		$mf = $this->mf('h-entry', ['updated' => '2013-12-06']);
		$result = getPublished($mf);
		$this->assertEquals(getProp($mf, 'updated'), $result);
	}
	
	public function testGetPublishedReturnsNullIfValidDatetimeRequested() {
		$mf = $this->mf('h-entry', ['published' => 'werty']);
		$result = getPublished($mf, true);
		$this->assertNull($result);
	}
	
	public function testGetPublishedReturnsNullIfNoPotentialValueFound() {
		$mf = $this->mf('h-entry', []);
		$result = getPublished($mf);
		$this->assertNull($result);
	}
	
	public function testGetAuthorPassesIfAuthorPresent() {
		$mf = $this->mf('h-entry', ['author' => [$this->mf('h-card', ['name' => 'Me'])]]);
		$result = getAuthor($mf);
		$this->assertEquals('Me', getProp($result, 'name'));
	}
	
	public function testFindMicroformatsByTypeFindsRootMicroformats() {
		$mfs = [
			'items' => [[
				'type' => ['h-card'],
				'properties' => [
					'name' => ['me']
				]
			]]
		];
		
		$result = findMicroformatsByType($mfs, 'h-card');
		$this->assertEquals('me', getProp($result[0], 'name'));
	}
	
	public function testFlattenMicroformatsReturnsFlatArrayOfMicroformats() {
		$org = $this->mf('h-card', ['name' => 'organisation']);
		$card = $this->mf('h-card', ['name' => 'me', 'org' => [$org]]);
		$entry = $this->mf('h-entry', ['name' => 'blog posting']);
		$card['children'] = [$entry];
		
		$mfs = [
			'items' => [$card]
		];
		
		$result = flattenMicroformats($mfs);
		
		$this->assertTrue(in_array($org, $result));
		$this->assertTrue(in_array($card, $result));
		$this->assertTrue(in_array($entry, $result));
	}
	
	public function testFindMicroformatsByProperty() {
		$mfs = [
			'items' => [$this->mf('h-card', ['name' => 'Me'])]
		];
		
		$results = findMicroformatsByProperty($mfs, 'name', 'Me');
		$this->assertEquals(1, count($results));
	}
	
	public function testExpandAuthorExpandsFromLargerHCardsInContext() {
		$this->markTestSkipped();
	}
	
	public function testMergeMicroformatsRecursivelyMerges() {
		$this->markTestSkipped();
	}
}
