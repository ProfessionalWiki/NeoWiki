<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPagePropertyProvider;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \ProfessionalWiki\NeoWiki\PagePropertiesBuilder
 * @group Database
 */
class PagePropertiesBuilderTest extends NeoWikiIntegrationTestCase {

	private const string PAGE_NAME = 'PagePropertiesBuilderTestPage';

	public function testProviderReceivesContent(): void {
		$context = $this->getContextForNewPageWithContent( 'Some text [[Category:Cats]]' );

		$this->assertSame( 'Some text [[Category:Cats]]', $context->content );
	}

	public function testProviderReceivesContentModel(): void {
		$context = $this->getContextForNewPageWithContent( 'Whatever wikitext' );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $context->contentModel );
	}

	public function testProviderReceivesParserRecordedProperties(): void {
		$context = $this->getContextForNewPageWithContent( 'Sorted {{DEFAULTSORT:Zebra}}' );

		$this->assertSame( 'Zebra', $context->parserProperties['defaultsort'] );
	}

	public function testProviderReceivesCategoriesFromParsedContent(): void {
		$context = $this->getContextForNewPageWithContent( 'Some text [[Category:Cats]]' );

		$this->assertSame( [ 'Cats' ], $context->categories );
	}

	public function testProviderReceivesTheAuthorOfTheRevision(): void {
		$author = $this->getTestUser()->getUser();

		$revision = $this->editPage( self::PAGE_NAME, 'Some text', '', NS_MAIN, $author )->getNewRevision();

		$this->assertSame( $author->getName(), $this->getContextFor( $revision )->lastEditor );
	}

	/**
	 * RevisionDelete hides an author without creating a revision of its own, so a name the wiki no
	 * longer shows must not reach the providers whose properties get projected (#1246).
	 */
	public function testProviderReceivesNoAuthorOnceRevisionDeleteHidTheirName(): void {
		$revision = $this->editPage(
			self::PAGE_NAME, 'Some text', '', NS_MAIN, $this->getTestUser()->getUser()
		)->getNewRevision();

		$this->hideRevisionAuthor( Title::newFromText( self::PAGE_NAME ), $revision->getId() );

		$this->assertSame( '', $this->getContextFor( $this->reloadRevision( $revision ) )->lastEditor );
	}

	private function getContextForNewPageWithContent( string $wikitext ): PagePropertyProviderContext {
		return $this->getContextFor( $this->editPage( self::PAGE_NAME, $wikitext )->getNewRevision() );
	}

	private function getContextFor( RevisionRecord $revision ): PagePropertyProviderContext {
		$spy = new SpyPagePropertyProvider();

		$this->newPagePropertiesBuilder( $spy )->getPagePropertiesFor( $revision );

		return $spy->getReceivedContext();
	}

	/**
	 * The record an edit returns carries the visibility the revision had when it was made, which a
	 * later RevisionDelete does not change.
	 */
	private function reloadRevision( RevisionRecord $revision ): RevisionRecord {
		$reloaded = $this->getServiceContainer()->getRevisionStore()
			->getRevisionById( $revision->getId(), IDBAccessObject::READ_LATEST );

		$this->assertNotNull( $reloaded );

		return $reloaded;
	}

	private function newPagePropertiesBuilder( SpyPagePropertyProvider $provider ): PagePropertiesBuilder {
		$registry = new PagePropertyProviderRegistry();
		$registry->addProvider( $provider );

		$services = $this->getServiceContainer();

		return new PagePropertiesBuilder(
			revisionStore: $services->getRevisionStore(),
			contentHandlerFactory: $services->getContentHandlerFactory(),
			titleFormatter: $services->getTitleFormatter(),
			providerRegistry: $registry,
		);
	}

}
