<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\NullSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphBackendNotConfiguredException;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use TestLogger;

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

	public function testReadOnlyClientThrowsGraphBackendNotConfiguredExceptionWithoutBackend(): void {
		$this->expectException( GraphBackendNotConfiguredException::class );

		$this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getReadOnlyNeo4jClient()
		);
	}

	public function testContentPageRenderDoesNotFailWithoutBackend(): void {
		$out = $this->newContentPageOutput( 'NoBackendViewPage' );

		$this->runWithoutGraphBackend( static function () use ( $out ): void {
			NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		// The guard short-circuits before getNeoWikiAppHtml() injects the app div.
		$this->assertStringNotContainsString( 'ext-neowiki-app', $out->getHTML() );
	}

	public function testContentPageRenderInjectsAppDivWhenConfigured(): void {
		$out = $this->newContentPageOutput( 'ConfiguredBackendViewPage' );

		NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );

		$this->assertStringContainsString( 'ext-neowiki-app', $out->getHTML() );
	}

	public function testContentPageRenderLogsWarningWithoutBackend(): void {
		$out = $this->newContentPageOutput( 'NoBackendWarningPage' );

		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );

		$this->runWithoutGraphBackend( static function () use ( $out ): void {
			NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		$buffer = $logger->getBuffer();
		$this->assertCount( 1, $buffer );
		$this->assertSame( 'warning', $buffer[0][0] );
		$this->assertStringContainsString( 'no graph database backend configured', $buffer[0][1] );
	}

	/**
	 * An edit-capable user on the latest revision triggers the subject-creator path, which builds the
	 * SubjectRepository (the Neo4j-backed reverse index) — the exact path that 500s without the guard.
	 */
	private function newContentPageOutput( string $pageName ): OutputPage {
		$page = $this->getExistingTestPage( $pageName );

		$context = new RequestContext();
		$context->setTitle( $page->getTitle() );
		$context->setUser( $this->getTestSysop()->getUser() );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $page->getLatest() );

		return $out;
	}

}
