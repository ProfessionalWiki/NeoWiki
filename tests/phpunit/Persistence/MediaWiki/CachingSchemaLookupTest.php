<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup
 */
class CachingSchemaLookupTest extends TestCase {

	public function testCachesSchemaSoTheInnerLookupRunsOnce(): void {
		$inner = $this->newSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $this->newTitleFactory( 1, 100, 100 ) );
		$first = $lookup->getSchema( new SchemaName( 'Person' ) );
		$second = $lookup->getSchema( new SchemaName( 'Person' ) );

		$this->assertSame( 1, $inner->calls );
		$this->assertEquals( $inner->schema, $first );
		$this->assertEquals( $inner->schema, $second );
	}

	public function testReloadsWhenTheSchemaRevisionChanges(): void {
		$inner = $this->newSpyLookup();

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $this->newTitleFactory( 1, 100, 101 ) );
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

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $factory );

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Missing' ) ) );
		$this->assertSame( 0, $inner->calls );
	}

	public function testCachesANullResultForTheSameRevision(): void {
		// An existing page whose content is not a valid schema yields null; that
		// result is cached too, so it is not re-loaded on every call for the rev.
		$inner = new class() implements SchemaLookup {
			public int $calls = 0;

			public function getSchema( SchemaName $schemaName ): ?Schema {
				$this->calls++;
				return null;
			}
		};

		$lookup = new CachingSchemaLookup( $inner, $this->newCache(), $this->newTitleFactory( 1, 100, 100 ) );
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

}
