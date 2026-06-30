<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\RedHerb\RedHerbGraphDatabasePlugin;

/**
 * Proves that the graph-database plugin registry actually dispatches page events to
 * extension-registered plugins, not just that they get added to the registry. RedHerb
 * registers a real GraphDatabasePlugin via the NeoWikiRegistration hook; this test reads
 * the live registered instance back out of the registry and confirms it recorded the
 * save/delete events that NeoWikiExtension::getGraphDatabasePlugin() fanned out to it.
 *
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry
 * @group Database
 */
class ExtensionGraphDatabasePluginDispatchTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'RedHerb' ) ) {
			$this->markTestSkipped( 'RedHerb extension is not loaded' );
		}

		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testRegisteredExtensionPluginReceivesSaveAndDeleteEvents(): void {
		$plugin = $this->getRegisteredRedHerbPlugin();
		$plugin->savedPages = [];
		$plugin->deletedPageIds = [];

		$revision = $this->createPageWithSubjects( 'Extension dispatch page', TestSubject::build() );
		$pageId = $revision->getPageId();

		$this->assertSame(
			[ $pageId ],
			array_map( static fn ( Page $page ) => $page->getId()->id, $plugin->savedPages ),
			'extension-registered plugin should receive savePage for the edited page'
		);

		$this->deletePageByName( 'Extension dispatch page' );

		$this->assertSame(
			[ $pageId ],
			array_map( static fn ( PageId $id ) => $id->id, $plugin->deletedPageIds ),
			'extension-registered plugin should receive deletePage for the deleted page'
		);
	}

	private function getRegisteredRedHerbPlugin(): RedHerbGraphDatabasePlugin {
		$plugins = NeoWikiExtension::getInstance()->getGraphDatabasePluginRegistry()->getPlugins();

		foreach ( $plugins as $plugin ) {
			if ( $plugin instanceof RedHerbGraphDatabasePlugin ) {
				return $plugin;
			}
		}

		$this->fail( 'RedHerbGraphDatabasePlugin was not found in the graph-database plugin registry' );
	}

	private function deletePageByName( string $pageName ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage( $page, $this->getTestSysop()->getUser() );

		$status = $deletePage->deleteUnsafe( 'test cleanup' );
		$this->assertStatusGood( $status );

		DeferredUpdates::doUpdates();
	}

}
