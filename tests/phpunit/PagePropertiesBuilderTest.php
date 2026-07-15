<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPagePropertyProvider;

/**
 * @covers \ProfessionalWiki\NeoWiki\PagePropertiesBuilder
 * @group Database
 */
class PagePropertiesBuilderTest extends NeoWikiIntegrationTestCase {

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

	public function testParseIsBoundToTheProvidedRevision(): void {
		$revision = $this->editPage( 'PagePropertiesBuilderTestPage', '{{DEFAULTSORT:Rev{{REVISIONID}}}}' )->getNewRevision();
		$this->editPage( 'PagePropertiesBuilderTestPage', 'Newer revision without a defaultsort' );

		$this->assertSame(
			'Rev' . $revision->getId(),
			$this->getContextForRevision( $revision )->parserProperties['defaultsort']
		);
	}

	public function testProviderReceivesContentModelOfNonWikitextContent(): void {
		$revision = $this->editPage( 'MediaWiki:PagePropertiesBuilderTest.json', '{ "answer": 42 }' )->getNewRevision();

		$this->assertSame( CONTENT_MODEL_JSON, $this->getContextForRevision( $revision )->contentModel );
	}

	public function testContextHasEmptySentinelsWhenContentIsUnavailable(): void {
		$revision = $this->editPage( 'PagePropertiesBuilderTestPage', 'Hidden [[Category:Cats]] {{DEFAULTSORT:Zebra}}' )->getNewRevision();
		$this->editPage( 'PagePropertiesBuilderTestPage', 'Newer public revision' );
		$this->revisionDelete( $revision );

		$context = $this->getContextForRevision( $this->getSuppressedRevision( $revision->getId() ) );

		$this->assertSame( '', $context->content );
		$this->assertSame( '', $context->contentModel );
		$this->assertSame( [], $context->parserProperties );
		$this->assertSame( [], $context->categories );
	}

	private function getSuppressedRevision( int $revisionId ): RevisionRecord {
		return $this->getServiceContainer()->getRevisionStore()->getRevisionById( $revisionId );
	}

	private function getContextForNewPageWithContent( string $wikitext ): PagePropertyProviderContext {
		return $this->getContextForRevision(
			$this->editPage( 'PagePropertiesBuilderTestPage', $wikitext )->getNewRevision()
		);
	}

	private function getContextForRevision( RevisionRecord $revision ): PagePropertyProviderContext {
		$spy = new SpyPagePropertyProvider();

		$this->newPagePropertiesBuilder( $spy )->getPagePropertiesFor( $revision, null );

		return $spy->getReceivedContext();
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
