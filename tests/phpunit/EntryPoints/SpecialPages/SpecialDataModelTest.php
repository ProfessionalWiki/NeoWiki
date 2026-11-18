<?php

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialDataModel;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialDataModel
 */
class SpecialDataModelTest extends SpecialPageTestBase {

	use HelpLinkAssertions;

	protected function newSpecialPage(): SpecialDataModel {
		return new SpecialDataModel();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'id="ext-neowiki-data-model"',
			$output
		);
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$this->assertHelpLinkLeadsToTheDocs( $this->outputOf( $this->newSpecialPage() ) );
	}

}
