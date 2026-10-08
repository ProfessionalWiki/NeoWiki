<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectCountLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * Needs the integration base class for the Neo4j test database the projection writes to.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectCountLookup
 * @group Database
 */
class Neo4jSubjectCountLookupTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	protected function newProjectionStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: 'Computer' ),
				TestSchema::build( name: 'Person' ),
				TestSchema::build( name: 'Zebra' ),
			)
		);
	}

	public function testCountsTheSubjectsOfEachSchemaThatHasSome(): void {
		$this->savePage( 1, self::subject( 'sTestSCL1111111', 'Computer' ), self::subject( 'sTestSCL1111112', 'Person' ) );
		$this->savePage( 2, self::subject( 'sTestSCL1111113', 'Computer' ) );

		$this->assertSame( [ 'Computer' => 2, 'Person' => 1 ], $this->counts() );
	}

	public function testCountsASubjectThatTwoPagesHoldOnce(): void {
		$this->savePage( 1, self::subject( 'sTestSCL1111111', 'Computer' ) );
		$this->savePage( 2, self::subject( 'sTestSCL1111111', 'Computer' ) );

		$this->assertSame( [ 'Computer' => 1 ], $this->counts() );
	}

	public function testLeavesOutASubjectNoPageHolds(): void {
		$this->savePage( 1, self::subject( 'sTestSCL1111111', 'Computer' ) );
		// What a Subject leaves behind when its page is saved after its Schema was deleted.
		$this->getClient()->run(
			'CREATE (:Subject:Computer {id: $id, wiki_id: $wikiId, name: "Orphan"})',
			[ 'id' => 'sTestSCL1111112', 'wikiId' => self::wikiId() ]
		);

		$this->assertSame( [ 'Computer' => 1 ], $this->counts() );
	}

	public function testLeavesOutTheSubjectsOfOtherWikis(): void {
		$this->savePage( 1, self::subject( 'sTestSCL1111111', 'Computer' ) );
		// Page ids repeat across a farm's wikis, so the other wiki's page has our page's id.
		$this->getClient()->run(
			'CREATE (:Page {id: 1, wiki_id: "otherwiki"})-[:HasSubject {isMain: false}]->'
				. '(:Subject:Computer {id: $id, name: "Theirs", wiki_id: "otherwiki"})',
			[ 'id' => 'sTestSCL1111112' ]
		);

		$this->assertSame( [ 'Computer' => 1 ], $this->counts() );
	}

	private function savePage( int $id, Subject ...$subjects ): void {
		$this->newProjectionStore()->savePage( TestPage::build(
			id: $id,
			otherSubjects: new SubjectMap( ...$subjects ),
		) );
	}

	private static function subject( string $id, string $schema ): Subject {
		return TestSubject::build( id: $id, schemaName: new SchemaName( $schema ) );
	}

	/**
	 * @return array<string, int> Sorted by Schema name, which the lookup does not promise.
	 */
	private function counts(): array {
		$counts = ( new Neo4jSubjectCountLookup( $this->getClient(), self::wikiId() ) )->getSubjectCountsBySchema();
		ksort( $counts );

		return $counts;
	}

	private static function wikiId(): string {
		return NeoWikiExtension::getInstance()->config->wikiId;
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}

}
