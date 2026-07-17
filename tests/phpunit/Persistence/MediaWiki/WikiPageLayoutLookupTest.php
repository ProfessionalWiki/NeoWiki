<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageLayoutLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageLayoutLookup
 */
class WikiPageLayoutLookupTest extends MediaWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	public function testUnreadableLayoutPageIsNullAndContentIsNeverFetched(): void {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->expects( $this->never() )->method( 'getPageContent' );

		$lookup = $this->newLookup( canRead: false, fetcher: $fetcher );

		$this->assertNull( $lookup->getLayout( new LayoutName( 'Person card' ) ) );
	}

	public function testReadableLayoutPageDelegatesToTheContentFetcher(): void {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->expects( $this->once() )->method( 'getPageContent' )->willReturn( null );

		$lookup = $this->newLookup( canRead: true, fetcher: $fetcher );

		$this->assertNull( $lookup->getLayout( new LayoutName( 'Person card' ) ) );
	}

	private function newLookup( bool $canRead, PageContentFetcher $fetcher ): WikiPageLayoutLookup {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getPrefixedDBkey' )->willReturn( 'Layout:Person_card' );

		$titleFactory = $this->createStub( TitleFactory::class );
		$titleFactory->method( 'newFromText' )->with( 'Person card', NeoWikiExtension::NS_LAYOUT )->willReturn( $title );

		return new WikiPageLayoutLookup(
			pageContentFetcher: $fetcher,
			authority: $this->mockRegisteredUltimateAuthority(),
			layoutDeserializer: new LayoutPersistenceDeserializer(),
			titleFactory: $titleFactory,
			readAuthorizer: new StubPageReadAuthorizer( allowed: $canRead ),
		);
	}

}
