<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PublishedPageJsonLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ObjectForgettingBagOStuff;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPageContentFetcher;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PublishedPageJsonLookup
 */
class PublishedPageJsonLookupTest extends TestCase {

	private const string JSON = '{"description":"desc","propertyDefinitions":{}}';

	public function testServesTheJsonFromASharedCacheThatCannotReconstructObjects(): void {
		// A persistent cache outlives the deploy whose classes its entries were written under, so
		// anything it holds has to survive coming back as plain data.
		$store = new ObjectForgettingBagOStuff();
		$this->newLookup( $this->newFetcher(), new WANObjectCache( [ 'cache' => $store ] ) )
			->getJson( $this->newPage( 100 ) );

		// A second lookup on a second WANObjectCache stands in for the next process.
		$nextProcessFetcher = $this->newFetcher();
		$json = $this->newLookup( $nextProcessFetcher, new WANObjectCache( [ 'cache' => $store ] ) )
			->getJson( $this->newPage( 100 ) );

		$this->assertSame( 0, $nextProcessFetcher->fetchCount );
		$this->assertSame( self::JSON, $json );
	}

	public function testReadsThePageAgainWhenItPublishesAnotherRevision(): void {
		$fetcher = $this->newFetcher();
		$lookup = $this->newLookup( $fetcher, $this->newCache() );

		$lookup->getJson( $this->newPage( 100 ) );
		$lookup->getJson( $this->newPage( 101 ) );

		$this->assertSame( 2, $fetcher->fetchCount );
	}

	public function testDoesNotCacheContentThatCouldNotBeRead(): void {
		// An unreadable blob is transient, and remembering it would pin the page as unreadable until
		// it published another revision.
		$lookup = $this->newLookup(
			new SpyPageContentFetcher( null, new SchemaContent( self::JSON ) ),
			$this->newCache()
		);

		$this->assertNull( $lookup->getJson( $this->newPage( 100 ) ) );
		$this->assertSame( self::JSON, $lookup->getJson( $this->newPage( 100 ) ) );
	}

	public function testReturnsNullWhenThePagePublishesNoRevision(): void {
		$page = $this->createMock( Title::class );
		$page->method( 'exists' )->willReturn( false );

		$this->assertNull( $this->newLookup( $this->newFetcher(), $this->newCache() )->getJson( $page ) );
	}

	private function newLookup( SpyPageContentFetcher $fetcher, WANObjectCache $cache ): PublishedPageJsonLookup {
		return new PublishedPageJsonLookup( $fetcher, $this->createMock( Authority::class ), $cache );
	}

	private function newFetcher(): SpyPageContentFetcher {
		return new SpyPageContentFetcher( new SchemaContent( self::JSON ) );
	}

	private function newCache(): WANObjectCache {
		return new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	private function newPage( int $latestRevisionId ): Title {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getLatestRevID' )->willReturn( $latestRevisionId );
		return $title;
	}

}
