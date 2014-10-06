<?php

namespace BarnabyWalters\Mf2;

use PHPUnit_Framework_TestCase;

/**
 * CleanerTest
 *
 * @author barnabywalters
 */
class CleanerTest extends PHPUnit_Framework_TestCase {
	protected function mf($name, array $properties, $value='') {
		if (is_array($name))
			$type = $name;
		else
			$type = [$name];
		
		foreach ($properties as $name => $arg) {
			if (is_array($arg) and !isMicroformat($arg) and !isEmbeddedHtml($arg))
				$properties[$name] = $arg;
			else
				$properties[$name] = [$arg];
		}
		
		return [
			'type' => $type,
			'properties' => $properties,
			'value' => $value
		];
	}
	
	public function testIsMicroformatReturnsFalseIfNotArray() {
		$this->assertFalse(isMicroformat(''));
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
	
	public function testIsMicroformatReturnsTrueIfValueIsSet() {
		$this->assertTrue(isMicroformat([
			'type' => ['h-card'],
			'properties' => [],
			'value' => 'a string'
		]));
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
	
	public function testGetPublishedReturnsFallbackIfProvided() {
		$mf = $this->mf('h-entry', []);
		$this->assertEquals('fallback', getPublished($mf, true, 'fallback'));
	}
	
	public function testGetAuthorPassesIfAuthorPresent() {
		$mf = $this->mf('h-entry', ['author' => [$this->mf('h-card', ['name' => 'Me'])]]);
		$result = getAuthor($mf);
		$this->assertEquals('Me', getProp($result, 'name'));
	}
	
	public function testGetAuthorFindsSeparateHCardWithSameName() {
		$nonAuthorCard = $this->mf('h-card', ['name' => 'Someone Else', 'url' => 'http://example.org']);
		$card = $this->mf('h-card', ['name' => 'Me', 'url' => 'http://waterpigs.co.uk']);
		$entry = $this->mf('h-entry', ['name' => 'Entry', 'author' => 'Me']);
		$mfs = [
			'items' => [$nonAuthorCard, $card, $entry]
		];
		
		$result = getAuthor($entry, $mfs);
		
		$this->assertEquals('http://waterpigs.co.uk', getProp($result, 'url'));
	}
	
	public function testGetAuthorFindsSeparateHCardWithSameDomain() {
		$card = $this->mf('h-card', ['name' => 'Me', 'url' => 'http://waterpigs.co.uk']);
		$entry = $this->mf('h-entry', ['name' => 'The Entry']);
		$mfs = ['items' => [$entry, $card]];
		
		$result = getAuthor($entry, $mfs, 'http://waterpigs.co.uk/notes/1234');
		
		$this->assertEquals('Me', getProp($result, 'name'));
	}
	
	public function testGetAuthorDoesntFallBackToFirstHCard() {
		$cards = [$this->mf('h-card', ['name' => 'Bill']), $this->mf('h-card', ['name' => 'James'])];
		$entry = $this->mf('h-entry', ['name' => 'Entry']);
		$mfs = ['items' => $cards];
		
		$result = getAuthor($entry, $mfs);
		
		$this->assertEquals(null, $result);
	}
	
	public function testGetAuthorFindsAuthorWithUrlOfPageRelAuthor() {
		$cards = [$this->mf('h-card', ['name' => 'N. T. Author']), $this->mf('h-card', ['name' => 'The Author', 'url' => 'http://example.com'])];
		$entry = $this->mf('h-entry', ['name' => 'Entry']);
		$mfs = [
			'items' => $cards,
			'rels' => [
				'author' => ['http://example.com']
			]
		];
		
		$result = getAuthor($entry, $mfs);
		
		$this->assertEquals($cards[1], $result);
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
	
	public function testFindMicroformatsByCallable() {
		$mfs = [
			'items' => [$this->mf('h-card', ['url' => 'http://waterpigs.co.uk/'])]
		];
		
		$results = findMicroformatsByCallable($mfs, function ($mf) {
			if (!hasProp($mf, 'url'))
				return false;
			
			$urls = $mf['properties']['url'];
			
			foreach ($urls as $url) {
				if (parse_url($url, PHP_URL_HOST) === parse_url('http://waterpigs.co.uk', PHP_URL_HOST))
					return true;
			}
			
			return false;
		});
		
		$this->assertEquals(1, count($results));
	}
	
	public function testFindMicroformatsSearchesSingleMicroformatStructure() {
		$card = $this->mf('h-card', ['name' => 'Me']);
		$entry = $this->mf('h-entry', ['author' => [$card], 'name' => 'entry']);
		
		$results = findMicroformatsByType($entry, 'h-card');
		$this->assertEquals(1, count($results));
	}	
	
	public function testIsEmbeddedHtml() {
		$e = array('value' => '', 'html' => '');
		$this->assertTrue(isEmbeddedHtml($e));
		$this->assertFalse(isEmbeddedHtml(array()));
	}
	
	public function testGetPlaintextProperty() {
		$e = $this->mf('h-entry', [
			'name' => 'text',
			'content' => ['text' => 'content', 'html' => '<b>content</b>'],
			'author' => [$this->mf('h-card', [], 'name')]
		]);
		$this->assertEquals('text', getPlaintext($e, 'name'));
		$this->assertEquals('content', getPlaintext($e, 'content'));
		$this->assertEquals('name', getPlaintext($e, 'author'));
	}
	
	public function testGetPlaintextArray() {
		$e = $this->mf('h-entry', [
			'category' => ['text', 'more']
		]);
		$this->assertEquals(['text', 'more'], getPlaintextArray($e, 'category'));
	}
	
	public function testGetHtmlProperty() {
		$e = $this->mf('h-entry', [
			'name' => ['"text"<>'],
			'content' => ['value' => 'content', 'html' => '<b>content</b>'],
			'author' => [$this->mf('h-card', [], '"name"<>')]
		]);
		$this->assertEquals('&quot;text&quot;&lt;&gt;', getHtml($e, 'name'));
		$this->assertEquals('<b>content</b>', getHtml($e, 'content'));
		$this->assertEquals('&quot;name&quot;&lt;&gt;', getHtml($e, 'author'));
	}
	
	public function testExpandAuthorExpandsFromLargerHCardsInContext() {
		$this->markTestSkipped();
	}
	
	public function testMergeMicroformatsRecursivelyMerges() {
		$this->markTestSkipped();
	}

	public function testGetAuthorDoesntReturnNonHCards() {
		$mf = [
			'items' => [[
				'type' => ['h-entry'],
				'properties' => [
					'url' => ['http://example.com/post/100'],
					'name' => ['Some Entry']
				]
			],
			[
				'type' => ['h-card'],
				'properties' => [
					'url' => ['http://example.com/'],
					'name' => ['Mrs. Example']
				]
			]]
		];

		$author = getAuthor($mf['items'][0], $mf, 'http://example.com/post/100');

		$this->assertContains('h-card', $author['type']);
	}

	public function testGetRepresentativeHCardFindsUidUrlPageUrlAllMatching() {
		$url = 'https://example.com';
		$mf = [
			'items' => [[
				'type' => ['h-entry'],
				'properties' => [
					'url' => ['https://example.com'],
					'uid' => ['https://example.com'],
					'name' => ['Correct h-card']
				]
			]]
		];

		$repHCard = getRepresentativeHCard($mf, $url);

		$this->assertEquals('Correct h-card', getPlaintext($repHCard, 'name'));
	}

	public function testGetRepresentativeHCardFindsUrlRelMeMatching() {
		$url = 'https://example.com';
		$mf = [
			'items' => [[
					'type' => ['h-entry'],
					'properties' => [
							'url' => ['https://example.org'],
							'name' => ['Correct h-card']
					]
			]],
			'rels' => [
				'me' => ['https://example.org']
			]
		];

		$repHCard = getRepresentativeHCard($mf, $url);

		$this->assertEquals('Correct h-card', getPlaintext($repHCard, 'name'));
	}

	public function testGetRepresentativeHCardFindsSingleUrlPageUrlMatching() {
		$url = 'https://example.com';
		$mf = [
				'items' => [[
						'type' => ['h-entry'],
						'properties' => [
								'url' => ['https://example.com'],
								'name' => ['Correct h-card']
						]
				]],
				'rels' => [
						'me' => ['https://example.org']
				]
		];

		$repHCard = getRepresentativeHCard($mf, $url);

		$this->assertEquals('Correct h-card', getPlaintext($repHCard, 'name'));
	}

	public function testGetRepresentativeHCardIgnoresMultipleUrlPageUrlMatching() {
		$url = 'https://example.com';
		$mf = [
				'items' => [[
						'type' => ['h-entry'],
						'properties' => [
								'url' => ['https://example.com'],
								'name' => ['Incorrect h-card']
						]
				], [
						'type' => ['h-entry'],
						'properties' => [
								'url' => ['https://example.com'],
								'name' => ['Another Incorrect h-card']
						]
				]]
		];

		$repHCard = getRepresentativeHCard($mf, $url);

		$this->assertEquals(null, $repHCard);
	}
}
