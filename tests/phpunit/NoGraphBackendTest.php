<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\NullSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphBackendNotConfigured;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @group Database
 */
class NoGraphBackendTest extends NeoWikiIntegrationTestCase {

	public function testGetNeo4jPluginIsNullWithoutBackend(): void {
		$plugin = $this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getNeo4jPlugin()
		);

		$this->assertNull( $plugin );
	}

	public function testGetNeo4jPluginIsPresentWhenConfigured(): void {
		$this->assertNotNull( NeoWikiExtension::getInstance()->getNeo4jPlugin() );
	}

	public function testEditSucceedsWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()
				->newFromTitle( Title::newFromText( 'NoBackendEditPage' ) );

			$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
			$updater->setContent( 'main', new WikitextContent( 'hello' ) );
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'no-backend edit' ) );

			$this->assertTrue( $updater->wasRevisionCreated() );
		} );
	}

	public function testSubjectLabelLookupIsNullObjectWithoutBackend(): void {
		$lookup = $this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getSubjectLabelLookup()
		);

		$this->assertInstanceOf( NullSubjectLabelLookup::class, $lookup );
	}

	public function testSubjectLabelLookupIsNeo4jWhenConfigured(): void {
		$this->assertInstanceOf(
			Neo4jSubjectLabelLookup::class,
			NeoWikiExtension::getInstance()->getSubjectLabelLookup()
		);
	}

	public function testLuaQueryOfferedWhenConfigured(): void {
		$names = NeoWikiExtension::getInstance()->getNeo4jPlugin()?->getLuaLibraryFunctionNames() ?? [];

		$this->assertContains( 'query', $names );
	}

	public function testLuaQueryNotOfferedWithoutBackend(): void {
		$names = $this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getNeo4jPlugin()?->getLuaLibraryFunctionNames() ?? []
		);

		$this->assertSame( [], $names );
	}

	public function testReadOnlyClientThrowsGraphBackendNotConfiguredWithoutBackend(): void {
		$this->expectException( GraphBackendNotConfigured::class );

		$this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getReadOnlyNeo4jClient()
		);
	}

	public function testContentPageRenderDoesNotFailWithoutBackend(): void {
		$page = $this->getExistingTestPage( 'NoBackendViewPage' );

		// An edit-capable user on the latest revision triggers the subject-creator path, which builds
		// the SubjectRepository (the Neo4j-backed reverse index) — the exact path that 500s without the guard.
		$context = new RequestContext();
		$context->setTitle( $page->getTitle() );
		$context->setUser( $this->getTestSysop()->getUser() );
		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $page->getLatest() );

		$this->runWithoutGraphBackend( static function () use ( $out, $context ): void {
			NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );
		} );

		// The render path short-circuits on a missing backend instead of throwing.
		$this->addToAssertionCount( 1 );
	}

}
