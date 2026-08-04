<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildGraphDatabases;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/RebuildGraphDatabases.php';

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\RebuildGraphDatabases
 * @group Database
 */
class RebuildGraphDatabasesTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The test rebuilds the singleton with a spy plugin registered; reset it so later tests get a
		// clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testRebuildProjectsPagesWithAndWithoutSubjects(): void {
		$subjectPageId = $this->createPageWithSubjects( 'Page with subjects', TestSubject::build() )->getPageId();
		$plainPageId = $this->insertPage( 'Plain page', 'No subjects here.' )['id'];

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->runRebuild();

		$this->assertSame(
			[ $subjectPageId, $plainPageId ],
			$this->savedPageIdsAfter( $spy, $subjectPageId - 1 ),
			'the rebuild should project every page, whether or not it holds Subjects'
		);
	}

	public function testRebuildRemovesDeletedPagesFromTheGraph(): void {
		$this->createPageWithSubjects( 'Surviving page before', TestSubject::build() );
		$deletedSubjectPage = $this->createPageWithSubjects( 'Deleted during outage', TestSubject::build() );
		$deletedPlainPage = $this->editPage( 'Deleted plain page', 'No subjects here.' )->getNewRevision();
		$this->createPageWithSubjects( 'Surviving page after', TestSubject::build() );

		$this->deletePageByName( 'Deleted during outage' );
		$this->deletePageByName( 'Deleted plain page' );

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->runRebuild();

		$this->assertSame(
			[ $deletedSubjectPage->getPageId(), $deletedPlainPage->getPageId() ],
			array_map( static fn ( PageId $pageId ) => $pageId->id, $spy->deletedPageIds ),
			'the rebuild should remove exactly the pages MediaWiki no longer has'
		);
	}

	/**
	 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore::initialize
	 */
	public function testRebuildCreatesGraphUniquenessConstraints(): void {
		// setUpNeo4j() dropped any pre-existing constraints, so the graph starts without them. A subject
		// page is present so the rebuild re-projects real data under the freshly created constraints. This
		// is the end-to-end proof that the rebuild's initialization step (routed through the graph plugin
		// system) reaches Neo4j: both constraints exist by name. Their shape and enforcement are covered by
		// Neo4jConstraintUpdaterTest.
		$this->createPageWithSubjects( 'Page with subject', TestSubject::build() );

		$this->runRebuild();

		$this->assertSame(
			[
				[ 'name' => 'Page wiki_id id' ],
				[ 'name' => 'Subject id' ],
			],
			$this->readGraph( 'SHOW CONSTRAINTS YIELD name ORDER BY name' )->toRecursiveArray()
		);
	}

	/**
	 * The rebuild runs on wikis with no graph backend configured (e.g. a SPARQL-only install with no
	 * store), so its initialization step must be a no-op there rather than throwing.
	 *
	 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin::initialize
	 */
	public function testInitializingGraphDatabasesDoesNotThrowWithoutABackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$extension = NeoWikiExtension::getInstance();
			$this->assertNull( $extension->getNeo4jPlugin(), 'precondition: no Neo4j backend is configured' );

			// The rebuild's initialization step, resolved through the facade exactly as the script does.
			// Must not throw despite there being no store to initialize.
			$extension->getGraphDatabasePlugin()->initialize();
		} );
	}

	/**
	 * The rebuild projects every page on the wiki, so a test asserting a full list has to bound it: this
	 * drops the pages that exist before the ones the test creates, such as its Schema page.
	 *
	 * @return int[]
	 */
	private function savedPageIdsAfter( SpyGraphDatabasePlugin $spy, int $firstPageId ): array {
		$pageIds = array_map( static fn ( Page $page ): int => $page->getId()->id, $spy->savedPages );

		return array_values( array_filter( $pageIds, static fn ( int $pageId ): bool => $pageId > $firstPageId ) );
	}

	/**
	 * A whole-wiki rebuild parses and re-projects every page, so an interrupted run must be able to pick
	 * up where it stopped instead of redoing the pages it already reconciled.
	 */
	public function testResumesAfterTheGivenPageId(): void {
		$firstPageId = $this->insertPage( 'Resume first page', 'One.' )['id'];
		$secondPageId = $this->insertPage( 'Resume second page', 'Two.' )['id'];

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->runRebuild( fromPageId: $firstPageId );

		$this->assertSame(
			[ $secondPageId ],
			$this->savedPageIdsAfter( $spy, $firstPageId - 1 ),
			'the page resumed past should not be projected again'
		);
	}

	private function runRebuild( ?int $fromPageId = null ): void {
		$script = new RebuildGraphDatabases();

		if ( $fromPageId !== null ) {
			$script->setOption( 'from-page-id', (string)$fromPageId );
		}

		ob_start();
		try {
			$script->execute();
		} finally {
			ob_end_clean();
		}
	}

	private function deletePageByName( string $pageName ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$page,
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'test deletion' ) );
	}

}
