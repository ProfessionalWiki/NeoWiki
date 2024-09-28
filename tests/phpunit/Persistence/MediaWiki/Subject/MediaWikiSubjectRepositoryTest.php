<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\Persistence\MediaWiki\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j\Neo4JPageIdentifiersLookup
 * @group Database
 */
class MediaWikiSubjectRepositoryTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	private function newRepository(): MediaWikiSubjectRepository {
		return NeoWikiExtension::getInstance()->newSubjectRepository();
	}

	public function testGetSubjectReturnsNullForUnknownSubject(): void {
		$this->assertNull(
			$this->newRepository()->getSubject(
				new SubjectId( 'sTestMSR1111111' )
			)
		);
	}

	private function createPages(): void {
		$this->markPageTableAsUsed();
		$this->truncateTables( $this->tablesUsed, $this->db );

		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->createPageWithSubjects(
			'SubjectRepoTestOne',
			mainSubject: TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestMSR1111113',
					label: new SubjectLabel( 'Test subject 3' ),
				),
				TestSubject::build(
					id: 'sTestMSR1111114',
					label: new SubjectLabel( 'Test subject 4' ),
				)
			)
		);

		$this->createPageWithSubjects(
			'SubjectRepoTestTwo',
			mainSubject: TestSubject::build(
				id: 'sTestMSR1111115',
				label: new SubjectLabel( 'Test subject 5' ),
			)
		);

		$this->createPageWithSubjects(
			'SubjectRepoTestThree'
		);
	}

	public function testDeleteSubject(): void {
		$this->createPages();

		$this->newRepository()->deleteSubject(
			new SubjectId( 'sTestMSR1111113' )
		);

		$this->assertNull(
			$this->newRepository()->getSubject(
				new SubjectId( 'sTestMSR1111113' )
			)
		);
	}

	public function testDeleteSubjectForUnknownSubject(): void {
		$this->createPages();

		$this->newRepository()->deleteSubject(
			new SubjectId( 'sTestMSR1111113' )
		);

		$this->assertNull(
			$this->newRepository()->getSubject(
				new SubjectId( 'sTestMSR1111113' )
			)
		);
	}

	public function testGetMainSubjectReturnsNullForUnknownPage(): void {
		$this->assertNull(
			$this->newRepository()->getMainSubject( new PageId( 404404404 ) )
		);
	}

	public function testGetMainSubjectReturnsNullForPageWithoutSubject(): void {
		$pageId = $this->createPageWithSubjects( 'SubjectRepoTestPageWithSubject' )->getPage()->getId();

		$this->assertNull(
			$this->newRepository()->getMainSubject( new PageId( $pageId ) )
		);
	}

	public function testGetMainSubjectReturnsSubject(): void {
		$pageId = $this->createPageWithSubjects(
			'SubjectRepoTestPageWithSubject',
			mainSubject: TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			)
		)->getPage()->getId();

		$this->assertEquals(
			TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			),
			$this->newRepository()->getMainSubject( new PageId( $pageId ) )
		);
	}

	public function testGetAndSetPageSubjects(): void {
		$pageId = new PageId(
			$this->createPageWithSubjects( 'SubjectRepoTestPageWithSubject' )->getPage()->getId()
		);

		$repo = $this->newRepository();
		$subjects = $repo->getSubjectsByPageId( $pageId );

		$subjects->setMainSubject(
			TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			)
		);

		$repo->savePageSubjects( $subjects, $pageId );

		$this->assertEquals(
			TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			),
			$repo->getMainSubject( $pageId )
		);
	}

	public function testGetPageSubjectsReturnsEmptySubjectMapForUnknownPage(): void {
		$this->assertEquals(
			PageSubjects::newEmpty(),
			$this->newRepository()->getSubjectsByPageId( new PageId( 404404404 ) )
		);
	}

	public function testGetSubjectReturnsSubject(): void {
		$this->createPages();

		$this->assertEquals(
			TestSubject::build(
				id: 'sTestMSR1111113',
				label: new SubjectLabel( 'Test subject 3' ),
			),
			$this->newRepository()->getSubject(
				new SubjectId( 'sTestMSR1111113' )
			)
		);
	}

}
