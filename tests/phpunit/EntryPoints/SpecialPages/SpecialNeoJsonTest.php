<?php

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoJson;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoJson
 */
class SpecialNeoJsonTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialNeoJson {
		return new SpecialNeoJson();
	}

	public function testPageExists(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'(neojson-summary)',
			$output
		);
	}

}
