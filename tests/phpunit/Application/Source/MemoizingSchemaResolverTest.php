<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Source;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Source\MemoizingSchemaResolver;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceResolver;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Source\MemoizingSchemaResolver
 */
class MemoizingSchemaResolverTest extends TestCase {

	public function testResolvesAReferenceOnce(): void {
		$counting = $this->countingResolver( TestSchema::build( name: 'Person' ) );
		$resolver = new MemoizingSchemaResolver( $counting );

		$resolver->getSchema( $this->local( 'Person' ) );
		$schema = $resolver->getSchema( $this->local( 'Person' ) );

		$this->assertSame( 'Person', $schema?->getName()->getText() );
		$this->assertSame( 1, $counting->calls );
	}

	public function testResolvesAReferenceThatResolvesToNoSchemaOnce(): void {
		$counting = $this->countingResolver( null );
		$resolver = new MemoizingSchemaResolver( $counting );

		$resolver->getSchema( $this->local( 'Deleted' ) );

		$this->assertNull( $resolver->getSchema( $this->local( 'Deleted' ) ) );
		$this->assertSame( 1, $counting->calls );
	}

	public function testResolvesASchemaOfAnotherSourceApartFromItsLocalNamesake(): void {
		$counting = $this->countingResolver( TestSchema::build( name: 'Person' ) );
		$resolver = new MemoizingSchemaResolver( $counting );

		$resolver->getSchema( $this->local( 'Person' ) );
		$resolver->getSchema( SchemaReference::sourced( 'otherwiki', new SchemaName( 'Person' ) ) );

		$this->assertSame( 2, $counting->calls );
	}

	private function local( string $name ): SchemaReference {
		return SchemaReference::local( new SchemaName( $name ) );
	}

	private function countingResolver( ?Schema $schema ): SchemaReferenceResolver {
		return new class( $schema ) implements SchemaReferenceResolver {

			public int $calls = 0;

			public function __construct(
				private readonly ?Schema $schema,
			) {
			}

			public function getSchema( SchemaReference $reference ): ?Schema {
				$this->calls++;

				return $this->schema;
			}

		};
	}

}
