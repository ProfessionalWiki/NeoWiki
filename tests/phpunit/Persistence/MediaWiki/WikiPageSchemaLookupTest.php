<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Permissions\Authority;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaContentUnavailableException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaLookup
 */
class WikiPageSchemaLookupTest extends TestCase {

	public function testReturnsTheDeserializedSchema(): void {
		$schema = new Schema( new SchemaName( 'Person' ), 'desc', new PropertyDefinitions( [] ) );

		$lookup = $this->newLookup(
			content: new SchemaContent( '{}' ),
			deserializer: $this->newDeserializerReturning( $schema )
		);

		$this->assertSame( $schema, $lookup->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testReturnsNullWhenTheContentIsNotAValidSchema(): void {
		$lookup = $this->newLookup(
			content: new SchemaContent( 'not json' ),
			deserializer: $this->newDeserializerThrowing()
		);

		$this->assertNull( $lookup->getSchema( new SchemaName( 'Broken' ) ) );
	}

	public function testThrowsWhenTheContentCouldNotBeRead(): void {
		// Distinct from content that does not deserialize: this outcome is transient, so callers
		// that cache by revision id must not store it.
		$lookup = $this->newLookup( content: null, deserializer: $this->newDeserializerThrowing() );

		$this->expectException( SchemaContentUnavailableException::class );

		$lookup->getSchema( new SchemaName( 'Person' ) );
	}

	private function newLookup( ?Content $content, SchemaPersistenceDeserializer $deserializer ): WikiPageSchemaLookup {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->method( 'getPageContent' )->willReturn( $content );

		return new WikiPageSchemaLookup(
			pageContentFetcher: $fetcher,
			authority: $this->createMock( Authority::class ),
			schemaDeserializer: $deserializer
		);
	}

	private function newDeserializerReturning( Schema $schema ): SchemaPersistenceDeserializer {
		$deserializer = $this->createMock( SchemaPersistenceDeserializer::class );
		$deserializer->method( 'deserialize' )->willReturn( $schema );
		return $deserializer;
	}

	private function newDeserializerThrowing(): SchemaPersistenceDeserializer {
		$deserializer = $this->createMock( SchemaPersistenceDeserializer::class );
		$deserializer->method( 'deserialize' )->willThrowException( new InvalidArgumentException() );
		return $deserializer;
	}

}
