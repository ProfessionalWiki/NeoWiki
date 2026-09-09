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
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
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
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use TestLogger;
use WikiExporter;

class NeoWikiIntegrationTestCase extends MediaWikiIntegrationTestCase {

	use HandlesNeo4jEnvOverrides;
	use TempUserTestTrait;

	/**
	 * @var array<string, GraphDatabasePlugin> Keys are store names
	 */
	private array $registeredGraphDatabasePlugins = [];

	/** @var PagePropertyProvider[] */
	private array $registeredPagePropertyProviders = [];

	/**
	 * The singleton pins a SchemaLookup whose cache is keyed by page and revision id, and those ids
	 * repeat across tests' temp tables, so an instance surviving a test would serve one test's Schema
	 * to the next. A `@before` hook because most subclasses override setUp() without calling parent;
	 * it runs before setUp(), so a subclass rebuilding the singleton in its own setUp() keeps that
	 * instance.
	 *
	 * @before
	 */
	final protected function neoWikiSetUp(): void {
		NeoWikiExtension::resetInstance();

		// These tests edit as anonymous users, and MediaWiki refuses to give an IP an actor once
		// temporary accounts are on. How NeoWiki behaves for a temporary account is its own
		// question, not one this suite answers, so the feature is off while it runs.
		$this->disableAutoCreateTempUser();
	}

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

	/**
	 * What a reader of $pageName ends up with, as the parser options' user: the parser functions run
	 * for real, and the output pipeline applies, as on a page view.
	 */
	protected function parseWikitextOn( string $pageName, string $wikitext, ?ParserOptions $parserOptions = null ): string {
		$parserOptions ??= ParserOptions::newFromAnon();

		return $this->getServiceContainer()->getParserFactory()->create()->parse(
			$wikitext,
			Title::newFromText( $pageName ),
			$parserOptions
		)->runOutputPipeline( $parserOptions, [] )->getContentHolderText();
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

	/**
	 * Pages carrying one Subject each, in the order they were created.
	 *
	 * @return int[] The page ids
	 */
	protected function createSubjectPages( string ...$pageNames ): array {
		$pageIds = [];

		foreach ( $pageNames as $pageName ) {
			$revision = $this->createPageWithSubjects( $pageName, TestSubject::build() );
			$this->assertNotNull( $revision );
			$pageIds[] = $revision->getPageId();
		}

		return $pageIds;
	}

	protected function deletePageByName( string $pageName ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$page,
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'test deletion' ) );
	}

	/**
	 * The pages a graph store was given, in the order it was given them.
	 *
	 * @return int[] The page ids
	 */
	protected static function savedPageIds( SpyGraphDatabasePlugin $store ): array {
		return array_map( static fn ( Page $page ): int => $page->getId()->id, $store->savedPages );
	}

	/**
	 * The pages the wiki has that a test did not create itself. Every page is projected, and a test's
	 * Subjects need a Schema page, which the fixture creates before the pages the test names — so a
	 * rebuild gets through one more page than the test asked for, and reaches it first.
	 */
	protected const FIXTURE_PAGES = 1;

	/**
	 * What the store was given, dropping the fixture's pages: they sort before the test's own, so a test
	 * asserting on a whole list bounds it by the first page it created.
	 *
	 * @return int[]
	 */
	protected static function savedPageIdsFrom( SpyGraphDatabasePlugin $store, int $firstTestPageId ): array {
		return array_values( array_filter(
			self::savedPageIds( $store ),
			static fn ( int $pageId ): bool => $pageId >= $firstTestPageId
		) );
	}

	/**
	 * Everything the logger was told, as one string to look for a phrase in.
	 */
	protected static function loggedText( TestLogger $logger ): string {
		return implode( "\n", array_column( $logger->getBuffer(), 1 ) );
	}

	protected function createSchema( string $name, ?string $json = null ): ?RevisionRecord {
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

	/**
	 * Bulk-inserts bare page rows — no revisions or content — straight into the page table with a
	 * single multi-row insert. The keyset name-lookup generators read only page_id and page_title and
	 * authorize by Title, so bare rows are enough to drive them past their batch size far more cheaply
	 * than creating that many real pages. Titles are $titlePrefix followed by a zero-padded counter
	 * (Foo001, Foo002, …). Returns each created title mapped to its assigned page ID, in page-ID order.
	 *
	 * @return array<string, int>
	 */
	protected function createBarePages( int $namespace, string $titlePrefix, int $count ): array {
		$titles = [];
		$rows = [];

		for ( $i = 1; $i <= $count; $i++ ) {
			$title = sprintf( '%s%03d', $titlePrefix, $i );
			$titles[] = $title;
			$rows[] = [
				'page_namespace' => $namespace,
				'page_title' => $title,
				'page_random' => 0.5,
				'page_touched' => $this->getDb()->timestamp(),
				'page_latest' => 0,
				'page_len' => 0,
				'page_is_redirect' => 0,
				'page_is_new' => 0,
			];
		}

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'page' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();

		$pageIds = [];

		$result = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_title' ] )
			->from( 'page' )
			->where( [ 'page_namespace' => $namespace, 'page_title' => $titles ] )
			->orderBy( 'page_id ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $result as $row ) {
			$pageIds[$row->page_title] = (int)$row->page_id;
		}

		return $pageIds;
	}

	/**
	 * Registers extra graph database plugins through the NeoWikiRegistration hook and rebuilds the singleton
	 * so they are composed into the write paths, letting a test drive the real hook wiring with a backend of
	 * its choosing (a spy, or one that always throws). Names them after their position, for tests that only
	 * care about what reaches the backends; use {@see self::registerNamedGraphDatabasePlugins} to address
	 * one by name.
	 */
	protected function registerGraphDatabasePlugins( GraphDatabasePlugin ...$plugins ): void {
		$named = [];

		foreach ( $plugins as $index => $plugin ) {
			$named['test-store-' . $index] = $plugin;
		}

		$this->registerNamedGraphDatabasePlugins( $named );
	}

	/**
	 * @param array<string, GraphDatabasePlugin> $plugins Keys are store names
	 */
	protected function registerNamedGraphDatabasePlugins( array $plugins ): void {
		$this->registeredGraphDatabasePlugins = $plugins + $this->registeredGraphDatabasePlugins;

		$this->registerWithNeoWiki();
	}

	/**
	 * Registers extra Page Property Providers through the NeoWikiRegistration hook and rebuilds the
	 * singleton, so their properties reach the page nodes the write paths project.
	 */
	protected function registerPagePropertyProviders( PagePropertyProvider ...$providers ): void {
		array_push( $this->registeredPagePropertyProviders, ...$providers );

		$this->registerWithNeoWiki();
	}

	/**
	 * Everything registered so far goes through one hook handler, replacing the previous one.
	 * setTemporaryHook clears the hook before adding its handler, so registering plugins and providers
	 * as two handlers would silently leave only whichever was registered last — with the test still
	 * green, since what the first helper registered simply never sees a projection.
	 */
	private function registerWithNeoWiki(): void {
		$plugins = $this->registeredGraphDatabasePlugins;
		$providers = $this->registeredPagePropertyProviders;

		$this->setTemporaryHook(
			'NeoWikiRegistration',
			static function ( NeoWikiRegistrar $registrar ) use ( $plugins, $providers ): void {
				foreach ( $plugins as $name => $plugin ) {
					$registrar->addGraphDatabasePlugin( $name, $plugin );
				}

				foreach ( $providers as $provider ) {
					$registrar->addPagePropertyProvider( $provider );
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

	/**
	 * Drops the state a real request would not inherit: MediaWiki's services, and the extension
	 * singleton that pins the Schema lookup and its caches. A test that changes a page and then
	 * reads it back otherwise depends on every cache in this process having noticed the change.
	 */
	protected function startFreshRequest(): void {
		$this->resetServices();
		NeoWikiExtension::resetInstance();
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
