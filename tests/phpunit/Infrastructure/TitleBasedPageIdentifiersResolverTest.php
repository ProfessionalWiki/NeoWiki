<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Infrastructure\TitleBasedPageIdentifiersResolver;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\TitleBasedPageIdentifiersResolver
 */
class TitleBasedPageIdentifiersResolverTest extends MediaWikiIntegrationTestCase {

	private const int PAGE_ID = 42;

	private function newResolver( ?Title $title ): TitleBasedPageIdentifiersResolver {
		$titleFactory = $this->createStub( TitleFactory::class );
		$titleFactory->method( 'newFromID' )->willReturn( $title );

		return new TitleBasedPageIdentifiersResolver( $titleFactory );
	}

	public function testResolvesThePrefixedTitleAndNamespace(): void {
		$resolver = $this->newResolver( Title::makeTitle( NS_HELP, 'Bunnies' ) );

		$this->assertEquals(
			new PageIdentifiers( new PageId( self::PAGE_ID ), 'Help:Bunnies', NS_HELP ),
			$resolver->getIdentifiersOfPage( new PageId( self::PAGE_ID ) )
		);
	}

	public function testUnresolvablePageIdYieldsNull(): void {
		$resolver = $this->newResolver( null );

		$this->assertNull( $resolver->getIdentifiersOfPage( new PageId( self::PAGE_ID ) ) );
	}

}
