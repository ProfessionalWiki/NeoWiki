<?php

declare( strict_types = 1 );

namespace Presentation;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\FactBox
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
