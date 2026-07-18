<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
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
		$this->markPageTableAsUsed();
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The test rebuilds the singleton with a spy plugin registered; reset it so later tests get a
		// clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testRebuildRemovesADeletedSubjectPageFromTheGraph(): void {
		$this->createPageWithSubjects( 'Surviving page before', TestSubject::build() );
		$deleted = $this->createPageWithSubjects( 'Deleted during outage', TestSubject::build() );
		$this->createPageWithSubjects( 'Surviving page after', TestSubject::build() );

		$this->deletePageByName( 'Deleted during outage' );

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->runRebuild();

		$this->assertSame(
			[ $deleted->getPageId() ],
			array_map( static fn ( PageId $pageId ) => $pageId->id, $spy->deletedPageIds ),
			'the rebuild should remove exactly the page MediaWiki no longer has'
		);
	}

	/**
	 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::createGraphDatabaseConstraints
	 */
	public function testRebuildCreatesGraphUniquenessConstraints(): void {
		// setUpNeo4j() dropped any pre-existing constraints, so the graph starts without them.
		// A subject page is present so the rebuild re-projects real data under the freshly created
		// constraints. Only the wiring is asserted here (both constraints exist by name); their shape
		// and enforcement are covered by Neo4jConstraintUpdaterTest.
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
	 * The rebuild runs on wikis with no Neo4j backend (e.g. a SPARQL-only install), so ensuring
	 * constraints must be a no-op there rather than throwing.
	 *
	 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::createGraphDatabaseConstraints
	 */
	public function testEnsuringConstraintsIsSkippedWhenNeo4jIsNotConfigured(): void {
		$this->runWithoutGraphBackend( function (): void {
			$extension = NeoWikiExtension::getInstance();
			$this->assertNull( $extension->getNeo4jPlugin(), 'precondition: no Neo4j backend is configured' );

			// Must not throw despite there being no Neo4j write engine to target.
			$extension->createGraphDatabaseConstraints();
		} );
	}

	private function runRebuild(): void {
		ob_start();
		try {
			( new RebuildGraphDatabases() )->execute();
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
