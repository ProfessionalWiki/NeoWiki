<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiSubjectRepository
 * @group database
 */
class MediaWikiSubjectRepositoryTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		try {
			$client = NeoWikiExtension::getInstance()->getNeo4jClient();
			$client->run( 'MATCH (n) DETACH DELETE n' );
		}
		catch ( \Exception ) {
			$this->markTestSkipped( 'Neo4j not available' );
		}
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

	public function testGetSubjectReturnsSubject(): void {
		$this->createPages();

		$this->assertEquals(
			TestSubject::build(
				id: '93e58a18-dc3e-41aa-8d67-79a18e9846f9',
				label: new SubjectLabel( 'Test subject 46f9' ),
			),
			$this->newRepository()->getSubject(
				new SubjectId( '93e58a18-dc3e-41aa-8d67-79a18e9846f9' )
			)
		);
	}

	private function createPages(): void {
		$this->editPage(
			'SubjectRepoTestOne',
			SubjectContent::newFromSubjects( new SubjectMap(
				TestSubject::build(
					id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48d086',
					label: new SubjectLabel( 'Test subject d086' ),
				),
				TestSubject::build(
					id: '93e58a18-dc3e-41aa-8d67-79a18e9846f9',
					label: new SubjectLabel( 'Test subject 46f9' ),
				),
				TestSubject::build(
					id: '9d6b4927-0c04-41b3-8daa-3b1d83f42cf3',
					label: new SubjectLabel( 'Test subject 2cf3' ),
				)
			) )
		);

		$this->editPage(
			'SubjectRepoTestTwo',
			SubjectContent::newFromSubjects( new SubjectMap(
				TestSubject::build(
					id: 'e594cecf-bb6f-4857-a59b-eb26152e135d',
					label: new SubjectLabel( 'Test subject 135d' ),
				)
			) )
		);

		$this->editPage(
			'SubjectRepoTestThree',
			SubjectContent::newFromSubjects( new SubjectMap() )
		);
	}

}
