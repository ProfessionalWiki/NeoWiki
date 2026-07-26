<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup
 */
class CachingSchemaLookupTest extends TestCase {

	public function testCachesSchemaSoTheInnerLookupRunsOnce(): void {
		$inner = $this->newSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $this->newTitleFactory( 1, 100, 100 ), new StubPageReadAuthorizer( allowed: true ), $this->newConnectionProvider() );
		$first = $lookup->getSchema( new SchemaName( 'Person' ) );
		$second = $lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 1, $inner->calls );
		$this->assertEquals( $inner->schema, $first );
		$this->assertEquals( $inner->schema, $second );
	}

	public function testReloadsWhenTheSchemaRevisionChanges(): void {
		$inner = $this->newSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $this->newTitleFactory( 1, 100, 101 ), new StubPageReadAuthorizer( allowed: true ), $this->newConnectionProvider() );
		$lookup->getSchema( new SchemaName( 'Person' ) );
		$lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 2, $inner->calls );
	}

	public function testReturnsNullForMissingPageWithoutHittingTheInnerLookup(): void {
		$inner = $this->newSpyLookup();

		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( false );
		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturn( $title );

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $factory, new StubPageReadAuthorizer( allowed: true ), $this->newConnectionProvider() );

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Missing' ) ) );
		$this->assertSame( 0, $inner->calls );
	}

	public function testReturnsNullWithoutHittingTheInnerLookupWhenTheUserCannotRead(): void {
		$inner = $this->newSpyLookup();

		$lookup = new CachingSchemaLookup(
			$inner,
			$this->newCache(),
			$this->newTitleFactory( 1, 100, 100 ),
			new StubPageReadAuthorizer( allowed: false ),
			$this->newConnectionProvider()
		);

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
		$this->assertSame( 0, $inner->calls );
	}

	public function testResolvesTheSameRevisionOnlyOnceWhenTheSharedCacheStoresNothing(): void {
		$inner = $this->newSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newDiscardingCache(), $this->newTitleFactory( 1, 100, 100 ), new StubPageReadAuthorizer( allowed: true ), $this->newConnectionProvider() );
		$first = $lookup->getSchema( new SchemaName( 'Person' ) );
		$second = $lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 1, $inner->calls );
		$this->assertEquals( $inner->schema, $first );
		$this->assertEquals( $inner->schema, $second );
	}

	public function testResolvesAbsentSchemaOnlyOnceWhenTheSharedCacheStoresNothing(): void {
		$inner = $this->newNullReturningSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newDiscardingCache(), $this->newTitleFactory( 1, 100, 100 ), new StubPageReadAuthorizer( allowed: true ), $this->newConnectionProvider() );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );

		$this->assertSame( 1, $inner->calls );
	}

	public function testResolvesEachSchemaSeparately(): void {
		$inner = $this->newSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newDiscardingCache(), $this->newTitleFactoryPerPage( [ 'Person' => 1, 'City' => 2 ] ), new StubPageReadAuthorizer( allowed: true ), $this->newConnectionProvider() );
		$lookup->getSchema( new SchemaName( 'Person' ) );
		$lookup->getSchema( new SchemaName( 'City' ) );
		$lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 2, $inner->calls );
	}

	public function testChecksReadPermissionOnEveryCall(): void {
		$authorizer = new class() implements PageReadAuthorizer {
			public bool $allowed = true;

			public function authorizeReadByPageId( PageId $pageId ): bool {
				return $this->allowed;
			}

			public function authorizeReadByPageTitle( Title $title ): bool {
				return $this->allowed;
			}
		};

		$lookup = new CachingSchemaLookup( $this->newSpyLookup(), $this->newDiscardingCache(), $this->newTitleFactory( 1, 100, 100 ), $authorizer, $this->newConnectionProvider() );
		$lookup->getSchema( new SchemaName( 'Person' ) );

		$authorizer->allowed = false;

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testCachesANullResultForTheSameRevision(): void {
		// An existing page whose content is not a valid schema yields null; that
		// result is cached too, so it is not re-loaded on every call for the rev.
		$inner = $this->newNullReturningSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $this->newTitleFactory( 1, 100, 100 ), new StubPageReadAuthorizer( allowed: true ), $this->newConnectionProvider() );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );

		$this->assertSame( 1, $inner->calls );
	}

	/**
	 * @return SchemaLookup&object{calls: int, schema: Schema}
	 */
	private function newSpyLookup(): SchemaLookup {
		return new class() implements SchemaLookup {
			public int $calls = 0;
			public Schema $schema;

			public function __construct() {
				$this->schema = new Schema( new SchemaName( 'Test' ), 'desc', new PropertyDefinitions( [] ) );
			}

			public function getSchema( SchemaName $schemaName ): ?Schema {
				$this->calls++;
				return $this->schema;
			}
		};
	}

	/**
	 * @return SchemaLookup&object{calls: int}
	 */
	private function newNullReturningSpyLookup(): SchemaLookup {
		return new class() implements SchemaLookup {
			public int $calls = 0;

			public function getSchema( SchemaName $schemaName ): ?Schema {
				$this->calls++;
				return null;
			}
		};
	}

	/**
	 * @param array<string, int> $articleIdsByPageName
	 */
	private function newTitleFactoryPerPage( array $articleIdsByPageName ): TitleFactory {
		$titles = [];

		foreach ( $articleIdsByPageName as $pageName => $articleId ) {
			$title = $this->createMock( Title::class );
			$title->method( 'exists' )->willReturn( true );
			$title->method( 'getArticleID' )->willReturn( $articleId );
			$title->method( 'getLatestRevID' )->willReturn( 100 );
			$titles[$pageName] = $title;
		}

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturnCallback(
			static fn ( string $pageName ): ?Title => $titles[$pageName] ?? null
		);
		return $factory;
	}

	private function newTitleFactory( int $articleId, int ...$revIds ): TitleFactory {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( $articleId );
		$title->method( 'getLatestRevID' )->willReturnOnConsecutiveCalls( ...$revIds );

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturn( $title );
		return $factory;
	}

	private function newCache(): WANObjectCache {
		return new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	/**
	 * Stands in for a wiki with no shared object cache configured ($wgMainCacheType = CACHE_NONE),
	 * where every read past the process-local tier reaches the inner lookup.
	 */
	private function newDiscardingCache(): WANObjectCache {
		return new WANObjectCache( [ 'cache' => new EmptyBagOStuff() ] );
	}

	private function newConnectionProvider(): IConnectionProvider {
		$replica = $this->createMock( IReadableDatabase::class );
		$replica->method( 'getSessionLagStatus' )->willReturn( [ 'lag' => 0, 'since' => INF ] );

		$provider = $this->createMock( IConnectionProvider::class );
		$provider->method( 'getReplicaDatabase' )->willReturn( $replica );
		return $provider;
	}

}
