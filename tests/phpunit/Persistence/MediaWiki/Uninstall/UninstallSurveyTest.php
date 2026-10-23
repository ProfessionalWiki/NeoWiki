<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Uninstall;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\UninstallSurvey;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Counts rows in the test wiki's page, slot and archive tables.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\UninstallSurvey
 * @group Database
 */
class UninstallSurveyTest extends NeoWikiIntegrationTestCase {

	public function testCountsThePagesHoldingSubjectsNow(): void {
		$this->createSubjectPages( 'Holds Subjects', 'Also holds Subjects' );
		$this->createDeletedPageWithSubjects();
		$this->editPage( 'Holds no Subjects', 'Plain' );

		$this->assertSame( 2, $this->newSurvey()->countPagesHoldingSubjects() );
	}

	public function testCountsEveryRevisionThatHoldsSubjectsIncludingDeletedOnes(): void {
		$this->createPageWithSubjects( 'Holds Subjects', TestSubject::build() );
		$this->changeSubjectsOfPage( 'Holds Subjects', otherSubjects: new SubjectMap( TestSubject::build() ) );
		$this->createDeletedPageWithSubjects();

		$this->assertSame( 3, $this->newSurvey()->countRevisionsHoldingSubjects() );
	}

	public function testCountsThePagesInNeoWikiNamespaces(): void {
		$this->createSchema( 'Company' );
		$this->createLayout( 'Company' );
		$this->editPage( 'Not in a NeoWiki namespace', 'Plain' );

		$this->assertSame( 2, $this->newSurvey()->countPagesInNeoWikiNamespaces() );
	}

	public function testCountsTheDeletedRevisionsInNeoWikiNamespaces(): void {
		$this->createSchema( 'Company' );
		$this->deletePageByName( 'Schema:Company' );
		$this->editPage( 'Not in a NeoWiki namespace', 'Plain' );
		$this->deletePageByName( 'Not in a NeoWiki namespace' );

		$this->assertSame( 1, $this->newSurvey()->countDeletedRevisionsInNeoWikiNamespaces() );
	}

	private function createDeletedPageWithSubjects(): void {
		$this->createPageWithSubjects( 'Deleted with Subjects', TestSubject::build() );
		$this->deletePageByName( 'Deleted with Subjects' );
	}

	private function newSurvey(): UninstallSurvey {
		return new UninstallSurvey( $this->getDb() );
	}

}
