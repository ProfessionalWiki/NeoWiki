<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PublishedPageJsonLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPageContentFetcher;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * The shared tier is PublishedPageJsonLookup's, so these run with a shared cache that stores nothing,
 * as on a wiki with $wgMainCacheType = CACHE_NONE: every read past the process-local tier reads the page.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup
 */
class CachingSchemaLookupTest extends TestCase {

	private const SCHEMA_JSON = '{"description":"desc","propertyDefinitions":{}}';

	public function testReloadsWhenTheSchemaRevisionChanges(): void {
		$fetcher = $this->newFetcher();
		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'makeTitleSafe' )->willReturnOnConsecutiveCalls(
			$this->newTitle( 'Person', 100 ),
			$this->newTitle( 'Person', 101 )
		);

		$lookup = $this->newLookup( $fetcher, titleFactory: $factory );
		$lookup->getSchema( new SchemaName( 'Person' ) );
		$lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 2, $fetcher->fetchCount );
	}

	public function testResolvesTheSameRevisionOnlyOnce(): void {
		$fetcher = $this->newFetcher();

		$lookup = $this->newLookup( $fetcher );
		$first = $lookup->getSchema( new SchemaName( 'Person' ) );
		$second = $lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 1, $fetcher->fetchCount );
		$this->assertEquals( $this->expectedSchema(), $first );
		$this->assertEquals( $this->expectedSchema(), $second );
	}

	public function testResolvesUndeserializableSchemaOnlyOnce(): void {
		$fetcher = new SpyPageContentFetcher( new SchemaContent( 'not json' ) );

		$lookup = $this->newLookup( $fetcher );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );
		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );

		$this->assertSame( 1, $fetcher->fetchCount );
	}

	public function testResolvesEachSchemaSeparately(): void {
		$fetcher = $this->newFetcher();

		$lookup = $this->newLookup(
			$fetcher,
			titleFactory: $this->newTitleFactoryPerPage( [ 'Person' => 100, 'City' => 200 ] )
		);
		$lookup->getSchema( new SchemaName( 'Person' ) );
		$lookup->getSchema( new SchemaName( 'City' ) );
		$lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 2, $fetcher->fetchCount );
	}

	public function testChecksReadPermissionOnEveryCall(): void {
		$authorizer = new StubPageReadAuthorizer( allowed: true );

		$lookup = $this->newLookup( $this->newFetcher(), readAuthorizer: $authorizer );
		$this->assertNotNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );

		$authorizer->allowed = false;

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testServesTheSchemaOnceTheRevisionBecomesReadableAgain(): void {
		// Unlike content that does not deserialize, an unreadable blob is transient. Remembering it
		// would pin the Schema as missing until the page was edited.
		$fetcher = new SpyPageContentFetcher( null, new SchemaContent( self::SCHEMA_JSON ) );

		$lookup = $this->newLookup( $fetcher );

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Person' ) ) );
		$this->assertEquals( $this->expectedSchema(), $lookup->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testSchemaIsNamedAfterItsPageRatherThanAfterTheAsking(): void {
		$schema = $this->newLookup( $this->newFetcher() )->getSchema( new SchemaName( 'person' ) );

		$this->assertSame( 'Person', $schema->getName()->getText() );
	}

	/**
	 * A page may be titled as no Schema may be named, and every read of a Subject naming it reaches
	 * here. Reporting it as missing is what keeps such a page from taking a request down with it.
	 */
	public function testPageTitledAsNoSchemaMayBeNamedResolvesToNull(): void {
		$lookup = $this->newLookup(
			$this->newFetcher(),
			titleFactory: $this->newTitleFactory( 'Page', 100 )
		);

		$this->assertNull( $lookup->getSchema( new SchemaName( 'page_' ) ) );
	}

	private function newLookup(
		SpyPageContentFetcher $fetcher,
		?TitleFactory $titleFactory = null,
		?PageReadAuthorizer $readAuthorizer = null
	): CachingSchemaLookup {
		return new CachingSchemaLookup(
			jsonLookup: new PublishedPageJsonLookup(
				$fetcher,
				$this->createMock( Authority::class ),
				new WANObjectCache( [ 'cache' => new EmptyBagOStuff() ] )
			),
			schemaDeserializer: new SchemaPersistenceDeserializer( TestSources::newPropertyTypeRegistry() ),
			titleFactory: $titleFactory ?? $this->newTitleFactory( 'Person', 100 ),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
		);
	}

	private function newFetcher(): SpyPageContentFetcher {
		return new SpyPageContentFetcher( new SchemaContent( self::SCHEMA_JSON ) );
	}

	private function expectedSchema(): Schema {
		return new Schema( new SchemaName( 'Person' ), 'desc', new PropertyDefinitions( [] ), false );
	}

	private function newTitleFactory( string $pageText, int $latestRevisionId ): TitleFactory {
		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'makeTitleSafe' )->willReturn( $this->newTitle( $pageText, $latestRevisionId ) );
		return $factory;
	}

	/**
	 * @param array<string, int> $latestRevisionIdsByPageName
	 */
	private function newTitleFactoryPerPage( array $latestRevisionIdsByPageName ): TitleFactory {
		$titles = [];

		foreach ( $latestRevisionIdsByPageName as $pageName => $latestRevisionId ) {
			$titles[$pageName] = $this->newTitle( (string)$pageName, $latestRevisionId );
		}

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'makeTitleSafe' )->willReturnCallback(
			static fn ( int $namespace, string $pageName ): ?Title => $titles[$pageName] ?? null
		);
		return $factory;
	}

	private function newTitle( string $pageText, int $latestRevisionId ): Title {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getLatestRevID' )->willReturn( $latestRevisionId );
		$title->method( 'getText' )->willReturn( $pageText );
		return $title;
	}

}
