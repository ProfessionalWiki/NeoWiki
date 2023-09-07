<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4JPageIdentifiersLookup
 * @group database
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
				new SubjectId( '00000000-0000-0000-0000-000000000000' )
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
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48d086',
				label: new SubjectLabel( 'Test subject d086' ),
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: '93e58a18-dc3e-41aa-8d67-79a18e9846f8',
					label: new SubjectLabel( 'Test subject 46f8' ),
				),
				TestSubject::build(
					id: '9d6b4927-0c04-41b3-8daa-3b1d83f42cf3',
					label: new SubjectLabel( 'Test subject 2cf3' ),
				)
			)
		);

		$this->createPageWithSubjects(
			'SubjectRepoTestTwo',
			mainSubject: TestSubject::build(
				id: 'e594cecf-bb6f-4857-a59b-eb26152e135d',
				label: new SubjectLabel( 'Test subject 135d' ),
			)
		);

		$this->createPageWithSubjects(
			'SubjectRepoTestThree'
		);
	}

	public function testDeleteSubject(): void {
		$this->createPages();

		$this->newRepository()->deleteSubject(
			new SubjectId( '93e58a18-dc3e-41aa-8d67-79a18e9846f8' )
		);

		$this->assertNull(
			$this->newRepository()->getSubject(
				new SubjectId( '93e58a18-dc3e-41aa-8d67-79a18e9846f8' )
			)
		);
	}

	public function testDeleteSubjectForUnknownSubject(): void {
		$this->createPages();

		$this->newRepository()->deleteSubject(
			new SubjectId( '93e58a18-dc3e-41aa-8d67-79a18e9846f8' )
		);

		$this->assertNull(
			$this->newRepository()->getSubject(
				new SubjectId( '93e58a18-dc3e-41aa-8d67-79a18e9846f8' )
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
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48d086',
				label: new SubjectLabel( 'Test subject d086' ),
			)
		)->getPage()->getId();

		$this->assertEquals(
			TestSubject::build(
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48d086',
				label: new SubjectLabel( 'Test subject d086' ),
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
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48d086',
				label: new SubjectLabel( 'Test subject d086' ),
			)
		);

		$repo->savePageSubjects( $subjects, $pageId );

		$this->assertEquals(
			TestSubject::build(
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48d086',
				label: new SubjectLabel( 'Test subject d086' ),
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
				id: '93e58a18-dc3e-41aa-8d67-79a18e9846f8',
				label: new SubjectLabel( 'Test subject 46f8' ),
			),
			$this->newRepository()->getSubject(
				new SubjectId( '93e58a18-dc3e-41aa-8d67-79a18e9846f8' )
			)
		);
	}

}
