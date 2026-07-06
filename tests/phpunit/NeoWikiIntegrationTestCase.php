<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use Laudis\Neo4j\Databags\SummarizedResult;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

class NeoWikiIntegrationTestCase extends MediaWikiIntegrationTestCase {

	protected function setUpNeo4j(): void {
		try {
			$client = NeoWikiExtension::getInstance()->getNeo4jClient();
			$client->run( 'MATCH (n) DETACH DELETE n' );
			$client->run( 'DROP CONSTRAINT `Page id` IF EXISTS' );
			$client->run( 'DROP CONSTRAINT `Page wiki_id id` IF EXISTS' );
			$client->run( 'DROP CONSTRAINT `Subject id` IF EXISTS' );
		}
		catch ( \Exception ) {
			$this->markTestSkipped( 'Neo4j not available' );
		}
	}

	protected function createPageWithSubjects(
		string $pageName,
		?Subject $mainSubject = null,
		SubjectMap $childSubjects = new SubjectMap()
	): ?RevisionRecord {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );

		$updater->setContent( 'main', new TextContent( '' ) );

		$updater->setContent(
			MediaWikiSubjectRepository::SLOT_NAME,
			SubjectContent::newFromData( new PageSubjects( $mainSubject, $childSubjects ) )
		);

		return $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'TODO' ) );
	}

	protected function createSchema( string $name, string $json = null ): ?RevisionRecord {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( $name, NeoWikiExtension::NS_SCHEMA )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );

		$updater->setContent(
			'main',
			new SchemaContent(
				$json ?? '{"title":"' . $name . '","propertyDefinitions":{}}',
			)
		);

		return $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'TODO' ) );
	}

	protected function markPageTableAsUsed(): void {
		if ( !in_array( 'page', $this->tablesUsed ) ) {
			$this->tablesUsed[] = 'page';
		}
	}

	protected function newProjectionStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID ),
			)
		);
	}

	protected function readGraph( string $cypher, array $parameters = [] ): SummarizedResult {
		return NeoWikiExtension::getInstance()->getNeo4jPlugin()->getReadQueryEngine()->runReadQuery( $cypher, $parameters );
	}

	protected function writeGraph( string $cypher ): SummarizedResult {
		return NeoWikiExtension::getInstance()->getNeo4jPlugin()->getWriteQueryEngine()->runWriteQuery( $cypher );
	}

	protected function readPageNodeName( int $pageId ): ?string {
		$result = $this->readGraph( 'MATCH (page:Page {id: $pageId}) RETURN page.name AS name', [ 'pageId' => $pageId ] );

		return $result->first()->toRecursiveArray()['name'] ?? null;
	}

	protected function readPageNodeNamespaceId( int $pageId ): ?int {
		$result = $this->readGraph( 'MATCH (page:Page {id: $pageId}) RETURN page.namespaceId AS namespaceId', [ 'pageId' => $pageId ] );

		return $result->first()->toRecursiveArray()['namespaceId'] ?? null;
	}

}
