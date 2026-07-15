<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\LazySchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\LazySchemaLookup
 */
class LazySchemaLookupTest extends TestCase {

	public function testGetSchemaReturnsTheSchemaFromTheWrappedLookup(): void {
		$schema = TestSchema::build( name: 'Person' );

		$lookup = new LazySchemaLookup(
			static fn (): SchemaLookup => new InMemorySchemaLookup( $schema )
		);

		$this->assertSame( $schema, $lookup->getSchema( new SchemaName( 'Person' ) ) );
	}

	public function testTheWrappedLookupIsNotBuiltAtConstructionTime(): void {
		$lookup = new LazySchemaLookup(
			static fn (): SchemaLookup => throw new RuntimeException( 'The factory ran at construction time.' )
		);

		$this->assertInstanceOf( LazySchemaLookup::class, $lookup );
	}

}
