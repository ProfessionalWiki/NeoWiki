<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Mappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Mappings
 */
class MappingsTest extends TestCase {

	private function mapping( string $name, string $schema, string $target ): Mapping {
		return new Mapping(
			name: new MappingName( $name ),
			schema: new SchemaName( $schema ),
			target: $target,
			prefixes: [],
			subjectClass: 'http://example.org/Class',
			properties: new PropertyMappings( [] ),
		);
	}

	/**
	 * @param Mapping[] $mappings
	 * @return string[]
	 */
	private function schemaNames( array $mappings ): array {
		return array_map( static fn ( Mapping $mapping ): string => $mapping->schema->getText(), $mappings );
	}

	public function testForTargetReturnsOnlyMappingsOfThatTarget(): void {
		// A matching Mapping is placed before and after a non-matching one, so returning the first or
		// last element would not pass.
		$mappings = new Mappings( [
			$this->mapping( 'A', 'Person', 'edm' ),
			$this->mapping( 'B', 'City', 'cidoc' ),
			$this->mapping( 'C', 'Artwork', 'edm' ),
		] );

		$this->assertSame( [ 'Person', 'Artwork' ], $this->schemaNames( $mappings->forTarget( 'edm' ) ) );
	}

	public function testForTargetIsEmptyForAnUnknownTarget(): void {
		$mappings = new Mappings( [ $this->mapping( 'A', 'Person', 'edm' ) ] );

		$this->assertSame( [], $mappings->forTarget( 'bibframe' ) );
	}

	public function testTargetsAreDistinctAndSorted(): void {
		$mappings = new Mappings( [
			$this->mapping( 'A', 'Person', 'edm' ),
			$this->mapping( 'B', 'City', 'cidoc' ),
			$this->mapping( 'C', 'Artwork', 'edm' ),
		] );

		$this->assertSame( [ 'cidoc', 'edm' ], $mappings->targets() );
	}

	public function testConflictForFindsAnotherPageClaimingTheSamePair(): void {
		$conflict = $this->mapping( 'Existing', 'Person', 'edm' );
		$mappings = new Mappings( [
			$this->mapping( 'Other', 'City', 'edm' ),
			$conflict,
		] );

		$this->assertSame(
			$conflict,
			$mappings->conflictFor( new SchemaName( 'Person' ), 'edm', new MappingName( 'New page' ) )
		);
	}

	public function testConflictForIgnoresTheMappingBeingSaved(): void {
		// The page being saved keeps its own (Schema, target) pair; it must not conflict with itself.
		$mappings = new Mappings( [ $this->mapping( 'Person to EDM', 'Person', 'edm' ) ] );

		$this->assertNull(
			$mappings->conflictFor( new SchemaName( 'Person' ), 'edm', new MappingName( 'Person to EDM' ) )
		);
	}

	public function testConflictForReturnsNullWhenTheTargetDiffers(): void {
		$mappings = new Mappings( [ $this->mapping( 'Person to CIDOC', 'Person', 'cidoc' ) ] );

		$this->assertNull(
			$mappings->conflictFor( new SchemaName( 'Person' ), 'edm', new MappingName( 'New page' ) )
		);
	}

}
