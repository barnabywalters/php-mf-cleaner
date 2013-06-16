<?php

namespace BarnabyWalters\Mf2\Cleaner;

use PHPUnit_Framework_TestCase;

/**
 * CleanerTest
 *
 * @author barnabywalters
 */
class CleanerTest extends PHPUnit_Framework_TestCase {
	/** @var Cleaner **/
	protected $c;
	
	public function setUp() {
		parent::setUp();
	}
	
	public function mf($name, array $properties) {
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
}
