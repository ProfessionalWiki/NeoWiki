<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaContentUnavailableException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\ReplicaCacheOptions;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaJsonLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ObjectForgettingBagOStuff;
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

	private const SCHEMA_JSON = '{"description":"desc","propertyDefinitions":{}}';

	public function testServesTheSchemaFromASharedCacheThatCannotReconstructObjects(): void {
		// A persistent cache outlives the deploy whose classes its entries were written under, so
		// anything the shared tier holds has to survive coming back as plain data.
		$cache = new WANObjectCache( [ 'cache' => new ObjectForgettingBagOStuff() ] );

		$this->newLookup( $this->newSpyLookup(), cache: $cache )->getSchema( new SchemaName( 'Person' ) );

		// A second lookup stands in for the next process: its own process-local tier is empty, so
		// only the shared tier can spare it the page read.
		$freshProcessInner = $this->newSpyLookup();
		$schema = $this->newLookup( $freshProcessInner, cache: $cache )->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 0, $freshProcessInner->calls );
		$this->assertEquals( $this->expectedSchema(), $schema );
	}

	public function testReloadsWhenTheSchemaRevisionChanges(): void {
		$inner = $this->newSpyLookup();

		$lookup = $this->newLookup( $inner, titleFactory: $this->newTitleFactory( 'Person', 1, 100, 101 ) );
		$lookup->getSchema( new SchemaName( 'Person' ) );
		$lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 2, $inner->calls );
	}

	public function testReturnsNullForMissingPageWithoutHittingTheInnerLookup(): void {
		$inner = $this->newSpyLookup();

		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( false );
		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'makeTitleSafe' )->willReturn( $title );

		$lookup = $this->newLookup( $inner, titleFactory: $factory );

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Missing' ) ) );
		$this->assertSame( 0, $inner->calls );
	}

	public function testReturnsNullWithoutHittingTheInnerLookupWhenTheUserCannotRead(): void {
		$inner = $this->newSpyLookup();

		$lookup = $this->newLookup( $inner, readAuthorizer: new StubPageReadAuthorizer( allowed: false ) );

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
		$this->assertSame( 0, $inner->calls );
	}

	public function testResolvesTheSameRevisionOnlyOnceWhenTheSharedCacheStoresNothing(): void {
		$inner = $this->newSpyLookup();

		$lookup = $this->newLookup( $inner, cache: $this->newDiscardingCache() );
		$first = $lookup->getSchema( new SchemaName( 'Person' ) );
		$second = $lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 1, $inner->calls );
		$this->assertEquals( $this->expectedSchema(), $first );
		$this->assertEquals( $this->expectedSchema(), $second );
	}

	public function testResolvesUndeserializableSchemaOnlyOnceWhenTheSharedCacheStoresNothing(): void {
		$inner = $this->newSpyLookup( json: 'not json' );

		$lookup = $this->newLookup( $inner, cache: $this->newDiscardingCache() );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );

		$this->assertSame( 1, $inner->calls );
	}

	public function testResolvesEachSchemaSeparately(): void {
		$inner = $this->newSpyLookup();

		$lookup = $this->newLookup(
			$inner,
			cache: $this->newDiscardingCache(),
			titleFactory: $this->newTitleFactoryPerPage( [ 'Person' => 1, 'City' => 2 ] )
		);
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

		$lookup = $this->newLookup(
			$this->newSpyLookup(),
			cache: $this->newDiscardingCache(),
			readAuthorizer: $authorizer
		);
		$this->assertNotNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );

		$authorizer->allowed = false;

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testDoesNotRememberARevisionWhoseContentCouldNotBeRead(): void {
		// Unlike content that does not deserialize, an unreadable blob is transient. Remembering it
		// would pin the Schema as missing until someone edited it, since the key is the revision id.
		$inner = $this->newUnreadableContentSpyLookup();

		$lookup = $this->newLookup( $inner );

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );

		$this->assertSame( 2, $inner->calls );
	}

	public function testServesTheSchemaOnceTheRevisionBecomesReadableAgain(): void {
		$inner = $this->newRecoveringSpyLookup();

		$lookup = $this->newLookup( $inner, titleFactory: $this->newTitleFactory( 'Person', 1, 100, 100, 100 ) );

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
		$this->assertEquals(
			$this->expectedSchema(),
			$lookup->getSchema( new SchemaName( 'Person' ) )
		);
		$this->assertEquals(
			$this->expectedSchema(),
			$lookup->getSchema( new SchemaName( 'Person' ) )
		);

		$this->assertSame( 2, $inner->calls );
	}

	private function newLookup(
		SchemaJsonLookup $inner,
		?WANObjectCache $cache = null,
		?TitleFactory $titleFactory = null,
		?PageReadAuthorizer $readAuthorizer = null
	): CachingSchemaLookup {
		return new CachingSchemaLookup(
			schemaJsonLookup: $inner,
			schemaDeserializer: $this->newDeserializer(),
			cache: $cache ?? new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			titleFactory: $titleFactory ?? $this->newTitleFactory( 'Person', 1, 100, 100 ),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			cacheOptions: new ReplicaCacheOptions( $this->newConnectionProvider(), MW_VERSION )
		);
	}

	private function expectedSchema(): Schema {
		return new Schema( new SchemaName( 'Person' ), 'desc', new PropertyDefinitions( [] ) );
	}

	private function newDeserializer(): SchemaPersistenceDeserializer {
		return new SchemaPersistenceDeserializer(
			TestSources::newPropertyTypeRegistry()
		);
	}

	/**
	 * @return SchemaJsonLookup&object{calls: int}
	 */
	private function newSpyLookup( string $json = self::SCHEMA_JSON ): SchemaJsonLookup {
		return new class( $json ) implements SchemaJsonLookup {
			public int $calls = 0;

			public function __construct( private readonly string $json ) {
			}

			public function getSchemaJson( Title $schemaPage ): string {
				$this->calls++;
				return $this->json;
			}
		};
	}

	/**
	 * @return SchemaJsonLookup&object{calls: int}
	 */
	private function newUnreadableContentSpyLookup(): SchemaJsonLookup {
		return new class() implements SchemaJsonLookup {
			public int $calls = 0;

			public function getSchemaJson( Title $schemaPage ): string {
				$this->calls++;
				throw SchemaContentUnavailableException::forName( $schemaPage->getText() );
			}
		};
	}

	/**
	 * Unreadable on the first call, then readable, as a transient blob failure behaves.
	 *
	 * @return SchemaJsonLookup&object{calls: int}
	 */
	private function newRecoveringSpyLookup(): SchemaJsonLookup {
		return new class( self::SCHEMA_JSON ) implements SchemaJsonLookup {
			public int $calls = 0;

			public function __construct( private readonly string $json ) {
			}

			public function getSchemaJson( Title $schemaPage ): string {
				$this->calls++;

				if ( $this->calls === 1 ) {
					throw SchemaContentUnavailableException::forName( $schemaPage->getText() );
				}

				return $this->json;
			}
		};
	}

	public function testSchemaIsNamedAfterItsPageRatherThanAfterTheAsking(): void {
		$schema = $this->newLookup(
			$this->newSpyLookup(),
			titleFactory: $this->newTitleFactory( 'Person', 1, 100, 100 )
		)->getSchema( new SchemaName( 'person' ) );

		$this->assertSame( 'Person', $schema->getName()->getText() );
	}

	/**
	 * A page may be titled as no Schema may be named, and every read of a Subject naming it reaches
	 * here. Reporting it as missing is what keeps such a page from taking a request down with it.
	 */
	public function testPageTitledAsNoSchemaMayBeNamedResolvesToNull(): void {
		$lookup = $this->newLookup(
			$this->newSpyLookup(),
			titleFactory: $this->newTitleFactory( 'Page', 1, 100 )
		);

		$this->assertNull( $lookup->getSchema( new SchemaName( 'page_' ) ) );
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
			$title->method( 'getText' )->willReturn( (string)$pageName );
			$titles[$pageName] = $title;
		}

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'makeTitleSafe' )->willReturnCallback(
			static fn ( int $namespace, string $pageName ): ?Title => $titles[$pageName] ?? null
		);
		return $factory;
	}

	private function newTitleFactory( string $pageText, int $articleId, int ...$revIds ): TitleFactory {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( $articleId );
		$title->method( 'getLatestRevID' )->willReturnOnConsecutiveCalls( ...$revIds );
		$title->method( 'getText' )->willReturn( $pageText );

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'makeTitleSafe' )->willReturn( $title );
		return $factory;
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
