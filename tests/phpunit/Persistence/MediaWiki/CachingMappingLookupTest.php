<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingJsonLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\ReplicaCacheOptions;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ObjectForgettingBagOStuff;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup
 */
class CachingMappingLookupTest extends TestCase {

	private const MAPPING_JSON = <<<'JSON'
		{
			"version": 1,
			"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
			"schemas": {
				"Person": { "subject": { "class": "edm:Agent" }, "properties": {} }
			}
		}
		JSON;

	public function testDeniedMappingIsNullAndDoesNotReachTheInnerLookup(): void {
		// The gate must run before the cache and the inner lookup, so a denied Mapping is never
		// loaded and a cache hit cannot serve it either.
		$inner = $this->newSpyLookup();

		$lookup = $this->newLookup( $inner, readAuthorizer: new StubPageReadAuthorizer( allowed: false ) );

		$this->assertNull( $lookup->getMapping( new MappingName( 'EDM' ) ) );
		$this->assertSame( 0, $inner->calls );
	}

	public function testReadableMappingIsDeserialized(): void {
		$inner = $this->newSpyLookup();

		$mapping = $this->newLookup( $inner )->getMapping( new MappingName( 'EDM' ) );

		$this->assertSame( 1, $inner->calls );
		$this->assertSame( 'edm:Agent', $mapping?->forSchema( new SchemaName( 'Person' ) )?->subject?->class );
	}

	public function testCachesMappingSoTheInnerLookupRunsOnce(): void {
		$inner = $this->newSpyLookup();

		$lookup = $this->newLookup( $inner );
		$lookup->getMapping( new MappingName( 'EDM' ) );
		$lookup->getMapping( new MappingName( 'EDM' ) );

		$this->assertSame( 1, $inner->calls );
	}

	public function testServesTheMappingFromASharedCacheThatCannotReconstructObjects(): void {
		// A persistent cache outlives the deploy whose classes its entries were written under, so
		// anything the shared tier holds has to survive coming back as plain data.
		$cache = new WANObjectCache( [ 'cache' => new ObjectForgettingBagOStuff() ] );

		$this->newLookup( $this->newSpyLookup(), cache: $cache )->getMapping( new MappingName( 'EDM' ) );

		// A second lookup stands in for the next process: its own process-local tier is empty, so
		// only the shared tier can spare it the page read.
		$freshProcessInner = $this->newSpyLookup();
		$mapping = $this->newLookup( $freshProcessInner, cache: $cache )->getMapping( new MappingName( 'EDM' ) );

		$this->assertSame( 0, $freshProcessInner->calls );
		$this->assertSame( 'edm:Agent', $mapping?->forSchema( new SchemaName( 'Person' ) )?->subject?->class );
	}

	public function testNamesTheMappingAfterItsPageRatherThanTheRequest(): void {
		// MediaWiki capitalizes a title's first letter, so "eDM" and "EDM" are one page and must
		// yield one Mapping name.
		$mapping = $this->newLookup( $this->newSpyLookup() )->getMapping( new MappingName( 'eDM' ) );

		$this->assertSame( 'EDM', $mapping?->name->getText() );
	}

	public function testUndeserializableMappingIsNull(): void {
		$lookup = $this->newLookup( $this->newSpyLookup( json: 'not json' ) );

		$this->assertNull( $lookup->getMapping( new MappingName( 'EDM' ) ) );
	}

	public function testUnreadableMappingContentIsNull(): void {
		$lookup = $this->newLookup( $this->newSpyLookup( json: null ) );

		$this->assertNull( $lookup->getMapping( new MappingName( 'EDM' ) ) );
	}

	private function newLookup(
		MappingJsonLookup $inner,
		?WANObjectCache $cache = null,
		?PageReadAuthorizer $readAuthorizer = null
	): CachingMappingLookup {
		return new CachingMappingLookup(
			mappingJsonLookup: $inner,
			mappingDeserializer: new MappingPersistenceDeserializer(),
			cache: $cache ?? new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			titleFactory: $this->newTitleFactory(),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			cacheOptions: new ReplicaCacheOptions( $this->newConnectionProvider(), MW_VERSION )
		);
	}

	/**
	 * @return MappingJsonLookup&object{calls: int}
	 */
	private function newSpyLookup( ?string $json = self::MAPPING_JSON ): MappingJsonLookup {
		return new class( $json ) implements MappingJsonLookup {
			public int $calls = 0;

			public function __construct( private readonly ?string $json ) {
			}

			public function getMappingJson( MappingName $name ): ?string {
				$this->calls++;
				return $this->json;
			}
		};
	}

	private function newConnectionProvider(): IConnectionProvider {
		$replica = $this->createMock( IReadableDatabase::class );
		$replica->method( 'getSessionLagStatus' )->willReturn( [ 'lag' => 0, 'since' => INF ] );

		$provider = $this->createMock( IConnectionProvider::class );
		$provider->method( 'getReplicaDatabase' )->willReturn( $replica );
		return $provider;
	}

	/**
	 * Title::getArticleID() and getLatestRevID() have no native return type, so an un-stubbed mock
	 * returns null for both and makeCacheKey() builds its key out of two empty components. Stub them
	 * so the key is the shape production uses, as CachingSchemaLookupTest does.
	 */
	private function newTitleFactory(): TitleFactory {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( 'getLatestRevID' )->willReturn( 100 );
		$title->method( 'getText' )->willReturn( 'EDM' );

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturn( $title );
		return $factory;
	}

}
