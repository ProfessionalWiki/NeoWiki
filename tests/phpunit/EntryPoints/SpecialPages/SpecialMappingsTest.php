<?php

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialMappings;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialMappings
 */
class SpecialMappingsTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialMappings {
		return new SpecialMappings();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'id="ext-neowiki-mappings"',
			$output
		);
	}

}
