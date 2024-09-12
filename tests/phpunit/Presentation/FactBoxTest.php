<?php

declare( strict_types = 1 );

namespace Presentation;

use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Presentation\FactBox
 * @group Database
 */
class FactBoxTest extends NeoWikiIntegrationTestCase {

	public function testShowsSubjectCount(): void {
		$this->createPageWithSubjects(
			pageName: 'FactBoxSmokeTest',
			mainSubject: TestSubject::build()
		);

		$this->assertStringContainsString(
			'This page defines 1 NeoWiki subjects',
			NeoWikiExtension::getInstance()->getFactBox()->htmlFor( \Title::newFromText( 'FactBoxSmokeTest' ) )
		);
	}

}
