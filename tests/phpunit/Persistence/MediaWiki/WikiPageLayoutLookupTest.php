<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageLayoutLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TestLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageLayoutLookup
 */
class WikiPageLayoutLookupTest extends MediaWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	public function testUnreadableLayoutPageIsNullAndContentIsNeverFetched(): void {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->expects( $this->never() )->method( 'getPageContent' );

		$lookup = $this->newLookup( $this->mockRegisteredAuthority( static fn () => false ), $fetcher );

		$this->assertNull( $lookup->getLayout( new LayoutName( 'Person card' ) ) );
	}

	public function testReadableLayoutPageDelegatesToTheContentFetcher(): void {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->expects( $this->once() )->method( 'getPageContent' )->willReturn( null );

		$lookup = $this->newLookup( $this->mockRegisteredAuthority( static fn () => true ), $fetcher );

		$this->assertNull( $lookup->getLayout( new LayoutName( 'Person card' ) ) );
	}

	public function testGateUsesBindingAuthorizeRead(): void {
		// probablyCan is a UI-hint check that skips the expensive ACL hook; the gate must
		// use the binding authorizeRead. Reverting fails this test.
		$authority = $this->createMock( Authority::class );
		$authority->method( 'probablyCan' )->willReturn( true );
		$authority->method( 'authorizeRead' )->willReturn( false );
		$authority->method( 'getUser' )->willReturn( new UserIdentityValue( 9999, 'Petr' ) );

		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->expects( $this->never() )->method( 'getPageContent' );

		$logger = new TestLogger( true, null, true );

		$this->assertNull(
			$this->newLookup( $authority, $fetcher, $logger )->getLayout( new LayoutName( 'Person card' ) )
		);

		// Mirrors AuthorityBasedSubjectAuthorizerTest::testDeniedReadIsLogged.
		$this->assertSame(
			[ [ 'info', 'NeoWiki: denied read of page {page} to {user}',
				[ 'page' => 'Layout:Person_card', 'user' => 'Petr' ] ] ],
			$logger->getBuffer()
		);
	}

	private function newLookup(
		Authority $authority,
		PageContentFetcher $fetcher,
		LoggerInterface $logger = new NullLogger()
	): WikiPageLayoutLookup {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getPrefixedDBkey' )->willReturn( 'Layout:Person_card' );

		$titleFactory = $this->createStub( TitleFactory::class );
		$titleFactory->method( 'newFromText' )->with( 'Person card', NeoWikiExtension::NS_LAYOUT )->willReturn( $title );

		return new WikiPageLayoutLookup(
			pageContentFetcher: $fetcher,
			authority: $authority,
			layoutDeserializer: new LayoutPersistenceDeserializer(),
			titleFactory: $titleFactory,
			logger: $logger,
		);
	}

}
