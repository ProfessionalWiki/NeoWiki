<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Cypher;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jReferencingSubjectLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jReferencingSubjectLookup
 */
class Neo4jReferencingSubjectLookupTest extends NeoWikiIntegrationTestCase {

	private const string TARGET_ID = 'sTestRSL1111111';
	private const string SOURCE_ID = 'sTestRSL1111112';
	private const string OTHER_SOURCE_ID = 'sTestRSL1111113';
	private const string UNNAMED_SOURCE_ID = 'sTestRSL1111114';

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReturnsNothingOnEmptyGraph(): void {
		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testFindsTheSubjectPointingAtTheTarget(): void {
		$this->saveOnOnePage(
			$this->target(),
			$this->referrer( self::SOURCE_ID, 'Referring subject' )
		);

		$this->assertSame( [ self::SOURCE_ID ], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testIgnoresASubjectTheTargetPointsAtWithoutPointingBack(): void {
		$this->saveOnOnePage(
			$this->referrer( self::TARGET_ID, 'Target', targetId: self::SOURCE_ID ),
			TestSubject::build( id: self::SOURCE_ID, label: new SubjectLabel( 'Neighbour' ) )
		);

		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testIgnoresASubjectReferencingItself(): void {
		$this->saveOnOnePage(
			$this->referrer( self::TARGET_ID, 'Target', targetId: self::TARGET_ID )
		);

		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testNamesASubjectOnceHoweverManyRelationsItHasIntoTheTarget(): void {
		$this->saveOnOnePage(
			$this->target(),
			TestSubject::build(
				id: self::SOURCE_ID,
				label: new SubjectLabel( 'Referring subject' ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'Made in', [
						TestRelation::build( targetId: self::TARGET_ID ),
					] ),
					TestStatement::buildRelation( 'Sold in', [
						TestRelation::build( targetId: self::TARGET_ID ),
					] ),
				] )
			)
		);

		$this->assertSame( [ self::SOURCE_ID ], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testOrdersByName(): void {
		$this->saveOnOnePage(
			$this->target(),
			$this->referrer( self::SOURCE_ID, 'Zeppelin' ),
			$this->referrer( self::OTHER_SOURCE_ID, 'Anvil' )
		);

		$this->assertSame(
			[ self::OTHER_SOURCE_ID, self::SOURCE_ID ],
			$this->idsOfSubjectsReferencingTarget()
		);
	}

	public function testOrdersAnUnlabelledSubjectByItsSchemaName(): void {
		$this->saveOnOnePage(
			$this->target(),
			$this->referrer( self::SOURCE_ID, 'Zeppelin' ),
			$this->referrer( self::UNNAMED_SOURCE_ID, null ),
			$this->referrer( self::OTHER_SOURCE_ID, 'Anvil' )
		);

		$this->assertSame(
			[ self::OTHER_SOURCE_ID, self::UNNAMED_SOURCE_ID, self::SOURCE_ID ],
			$this->idsOfSubjectsReferencingTarget()
		);
	}

	public function testStopsAtTheLimit(): void {
		$this->saveOnOnePage(
			$this->target(),
			$this->referrer( self::SOURCE_ID, 'Anvil' ),
			$this->referrer( self::OTHER_SOURCE_ID, 'Zeppelin' )
		);

		$this->assertSame( [ self::SOURCE_ID ], $this->idsOfSubjectsReferencingTarget( limit: 1 ) );
	}

	public function testExcludesAForeignSubjectReachableThroughALocalPage(): void {
		$this->saveOnOnePage( $this->target() );

		// A cross-wiki-shared Subject id (ADR 22) can leave a local Page holding a Subject stamped
		// for another wiki. Its relations are that wiki's business, and the page id its rows would
		// be gated by resolves against the wrong wiki, so the Subject filter must withhold it.
		$this->createReferringNodes(
			sourceId: 'sTestRSL2222221',
			pageId: 500,
			pageWikiId: $this->currentWikiId(),
			subjectWikiId: $this->currentWikiId() . '-other'
		);

		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testExcludesASubjectHeldOnlyByAForeignPage(): void {
		$this->saveOnOnePage( $this->target() );

		$this->createReferringNodes(
			sourceId: 'sTestRSL2222222',
			pageId: 501,
			pageWikiId: $this->currentWikiId() . '-other',
			subjectWikiId: $this->currentWikiId()
		);

		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testExcludesAStubReferrerNoPageHolds(): void {
		$this->saveOnOnePage( $this->target() );

		// The shape a relation into a Subject that does not exist yet leaves behind: a node with the
		// Subject label and an id, held by no Page. Nothing readable stands behind it.
		$this->getClient()->run(
			'MATCH (target:Subject { id: $targetId })
			 CREATE (:Subject { id: "sTestRSL2222223", wiki_id: $wikiId })-[:Made_in { id: "rTestRSL111111" }]->(target)',
			[ 'targetId' => self::TARGET_ID, 'wikiId' => $this->currentWikiId() ]
		);

		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	private function target(): Subject {
		return TestSubject::build( id: self::TARGET_ID, label: new SubjectLabel( 'Target' ) );
	}

	private function referrer( string $id, ?string $label, string $targetId = self::TARGET_ID ): Subject {
		return TestSubject::build(
			id: $id,
			label: $label,
			statements: new StatementList( [
				TestStatement::buildRelation( 'Made in', [ TestRelation::build( targetId: $targetId ) ] ),
			] )
		);
	}

	private function saveOnOnePage( Subject ...$subjects ): void {
		$this->newProjectionStore()->savePage( TestPage::build(
			id: 1,
			properties: TestPageProperties::build( title: 'Referrers' ),
			otherSubjects: new SubjectMap( ...$subjects )
		) );
	}

	/**
	 * Creates a Page, a Subject on it and a relation into the target directly in the graph, so a test
	 * can plant a source whose wiki_id, or whose Page's, is not this wiki's — which the projection
	 * store never writes.
	 */
	private function createReferringNodes(
		string $sourceId,
		int $pageId,
		string $pageWikiId,
		string $subjectWikiId
	): void {
		$this->getClient()->run(
			'MATCH (target:Subject { id: $targetId })
			 CREATE (:Page { id: $pageId, wiki_id: $pageWikiId })-[:HasSubject { isMain: false }]->'
				. '(:Subject:' . Cypher::escape( TestSubject::DEFAULT_SCHEMA_ID )
				. ' { id: $sourceId, name: "Foreign", wiki_id: $subjectWikiId })'
				. '-[:Made_in { id: "rTestRSL222222" }]->(target)',
			[
				'targetId' => self::TARGET_ID,
				'sourceId' => $sourceId,
				'pageId' => $pageId,
				'pageWikiId' => $pageWikiId,
				'subjectWikiId' => $subjectWikiId,
			]
		);
	}

	protected function newProjectionStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build(
					name: TestSubject::DEFAULT_SCHEMA_ID,
					properties: new PropertyDefinitions( [
						'Made in' => TestProperty::buildRelation( relationType: 'Made in' ),
						'Sold in' => TestProperty::buildRelation( relationType: 'Sold in' ),
					] )
				)
			)
		);
	}

	/**
	 * @return string[]
	 */
	private function idsOfSubjectsReferencingTarget( int $limit = 10 ): array {
		return array_map(
			static fn ( $id ): string => $id->text,
			$this->newLookup()->getIdsOfSubjectsReferencing( new SubjectId( self::TARGET_ID ), $limit )
		);
	}

	private function newLookup(): Neo4jReferencingSubjectLookup {
		return new Neo4jReferencingSubjectLookup(
			client: $this->getClient(),
			wikiId: $this->currentWikiId(),
		);
	}

	private function currentWikiId(): string {
		return NeoWikiExtension::getInstance()->config->wikiId;
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}

}
