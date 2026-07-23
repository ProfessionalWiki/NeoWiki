<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use DumpStringOutput;
use ImportStringSource;
use Laudis\Neo4j\Databags\SummarizedResult;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use WikiExporter;

class NeoWikiIntegrationTestCase extends MediaWikiIntegrationTestCase {

	use HandlesNeo4jEnvOverrides;

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

	protected function createLayout( string $name, ?string $json = null ): ?RevisionRecord {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( $name, NeoWikiExtension::NS_LAYOUT )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );

		$updater->setContent(
			'main',
			new LayoutContent(
				$json ?? '{ "schema": "' . $name . '", "type": "infobox" }'
			)
		);

		return $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'TODO' ) );
	}

	protected function createMapping( string $name, string $json ): ?RevisionRecord {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( $name, NeoWikiExtension::NS_MAPPING )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );

		$updater->setContent( 'main', new MappingContent( $json ) );

		return $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'TODO' ) );
	}

	protected function exportPageToXml( string $pageName ): string {
		$exporter = $this->getServiceContainer()->getWikiExporterFactory()->getWikiExporter(
			$this->getDb(),
			WikiExporter::FULL
		);

		$sink = new DumpStringOutput();
		$exporter->setOutputSink( $sink );
		$exporter->openStream();
		$exporter->pageByName( $pageName );
		$exporter->closeStream();

		return (string)$sink;
	}

	/**
	 * Imports without a reporter, as importDump.php does. Special:Import and the import API wrap the
	 * importer in an ImportReporter, which creates a null revision on top of the import and so happens
	 * to fire RevisionFromEditComplete as well. Importing bare keeps tests on the import path only.
	 */
	protected function importXml( string $xml ): void {
		$importer = $this->getServiceContainer()->getWikiImporterFactory()->getWikiImporter(
			new ImportStringSource( $xml ),
			$this->getTestSysop()->getAuthority()
		);

		$importer->doImport();

		DeferredUpdates::doUpdates();
	}

	protected function markPageTableAsUsed(): void {
		if ( !in_array( 'page', $this->tablesUsed ) ) {
			$this->tablesUsed[] = 'page';
		}
	}

	/**
	 * Registers extra graph database plugins through the NeoWikiRegistration hook and rebuilds the singleton
	 * so they are composed into the write paths, letting a test drive the real hook wiring with a backend of
	 * its choosing (a spy, or one that always throws).
	 *
	 * Callers must reset the singleton again in tearDown, so later tests get an instance built without the
	 * temporary hook.
	 */
	protected function registerGraphDatabasePlugins( GraphDatabasePlugin ...$plugins ): void {
		$this->setTemporaryHook(
			'NeoWikiRegistration',
			static function ( NeoWikiRegistrar $registrar ) use ( $plugins ): void {
				foreach ( $plugins as $plugin ) {
					$registrar->addGraphDatabasePlugin( $plugin );
				}
			}
		);

		NeoWikiExtension::resetInstance();
	}

	protected function newProjectionStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID ),
			)
		);
	}

	protected function readGraph( string $cypher, array $parameters = [] ): SummarizedResult {
		return NeoWikiExtension::getInstance()->requireNeo4jPlugin()->getReadQueryEngine()->runReadQuery( $cypher, $parameters );
	}

	protected function writeGraph( string $cypher ): SummarizedResult {
		return NeoWikiExtension::getInstance()->requireNeo4jPlugin()->getWriteQueryEngine()->runWriteQuery( $cypher );
	}

	/**
	 * Runs $fn with the Neo4j backend config unset, simulating a wiki with no graph backend.
	 * Clears the CI env overrides (which otherwise win over config), nulls both URLs, and resets the
	 * NeoWikiExtension singleton so it rebuilds from the unconfigured config. Restores everything after.
	 *
	 * @param callable(): mixed $fn
	 */
	protected function runWithoutGraphBackend( callable $fn ): mixed {
		$config = $this->getServiceContainer()->getMainConfig();
		$priorWriteUrl = $config->get( 'NeoWikiNeo4jInternalWriteUrl' );
		$priorReadUrl = $config->get( 'NeoWikiNeo4jInternalReadUrl' );
		$this->snapshotAndClearNeo4jEnvOverrides();
		$this->overrideConfigValue( 'NeoWikiNeo4jInternalWriteUrl', null );
		$this->overrideConfigValue( 'NeoWikiNeo4jInternalReadUrl', null );
		NeoWikiExtension::resetInstance();

		try {
			return $fn();
		} finally {
			$this->restoreNeo4jEnvOverrides();
			$this->overrideConfigValue( 'NeoWikiNeo4jInternalWriteUrl', $priorWriteUrl );
			$this->overrideConfigValue( 'NeoWikiNeo4jInternalReadUrl', $priorReadUrl );
			NeoWikiExtension::resetInstance();
		}
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
