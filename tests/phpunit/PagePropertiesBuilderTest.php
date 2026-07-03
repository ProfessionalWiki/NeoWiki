<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

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

	private function getContextForNewPageWithContent( string $wikitext ): PagePropertyProviderContext {
		$revision = $this->editPage( 'PagePropertiesBuilderTestPage', $wikitext )->getNewRevision();

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
