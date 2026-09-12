<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Interwiki\ClassicInterwikiLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Infrastructure\TitleBasedPageIdentifiersResolver;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\TitleBasedPageIdentifiersResolver
 * @group Database
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

	/**
	 * A text can name a page of another wiki, which cannot be created here, so the test wiki needs
	 * an interwiki prefix for that case to be reachable at all.
	 */
	private function registerInterwikiPrefix(): void {
		$globalScope = 2;

		$this->overrideConfigValues( [
			MainConfigNames::InterwikiScopes => $globalScope,
			MainConfigNames::InterwikiCache => ClassicInterwikiLookup::buildCdbHash(
				[ [ 'iw_prefix' => 'elsewhere', 'iw_url' => 'https://example.com/wiki/$1', 'iw_local' => 0 ] ],
				$globalScope
			),
		] );
	}

	private function mainNamespaceTitle( string $text ): ?string {
		return ( new TitleBasedPageIdentifiersResolver(
			$this->getServiceContainer()->getTitleFactory()
		) )->getMainNamespaceTitle( $text );
	}

	public function testAnswersTheTitleTheTextNames(): void {
		$this->assertSame( 'Amsterdam', $this->mainNamespaceTitle( 'Amsterdam' ) );
	}

	public function testNormalizesTheTitle(): void {
		$this->assertSame( 'Amsterdam Museum', $this->mainNamespaceTitle( '  amsterdam_Museum ' ) );
	}

	/**
	 * @dataProvider textsThatTitleNoPageProvider
	 */
	public function testAnswersNothingForTextThatTitlesNoPage( string $text ): void {
		$this->registerInterwikiPrefix();

		$this->assertNull( $this->mainNamespaceTitle( $text ) );
	}

	public static function textsThatTitleNoPageProvider(): iterable {
		yield 'blank' => [ '   ' ];
		yield 'another namespace' => [ 'Help:Getting started' ];
		yield 'not a title' => [ 'Bad|title' ];
		yield 'another wiki' => [ 'elsewhere:Manual:Contents' ];
		// The fragment is a place on a page, which newFromText drops rather than refuses: taken as
		// a title, "Rembrandt#1642" would silently name the page "Rembrandt".
		yield 'a page and a fragment' => [ 'Rembrandt#1642' ];
		yield 'a fragment alone' => [ '#Anchor' ];
	}

}
