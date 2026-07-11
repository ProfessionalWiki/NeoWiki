<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry
 */
class RdfValueMapperRegistryTest extends TestCase {

	private function xsd( string $local ): Iri {
		return new Iri( 'http://www.w3.org/2001/XMLSchema#' . $local );
	}

	public function testTextMapsEachPartToAnXsdString(): void {
		$this->assertEquals(
			[
				new Literal( 'foo', $this->xsd( 'string' ) ),
				new Literal( 'bar', $this->xsd( 'string' ) ),
			],
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'text', new StringValue( 'foo', 'bar' ) )
		);
	}

	public function testUrlMapsToXsdAnyUri(): void {
		$this->assertEquals(
			[ new Literal( 'https://pro.wiki', $this->xsd( 'anyURI' ) ) ],
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'url', new StringValue( 'https://pro.wiki' ) )
		);
	}

	public function testIntegerNumberMapsToXsdInteger(): void {
		$this->assertEquals(
			[ new Literal( '2019', $this->xsd( 'integer' ) ) ],
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'number', new NumberValue( 2019 ) )
		);
	}

	public function testFractionlessFloatMapsToXsdInteger(): void {
		$this->assertEquals(
			[ new Literal( '2019', $this->xsd( 'integer' ) ) ],
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'number', new NumberValue( 2019.0 ) )
		);
	}

	public function testDecimalNumberMapsToXsdDecimalWithoutScientificNotation(): void {
		$this->assertEquals(
			[ new Literal( '3.14159', $this->xsd( 'decimal' ) ) ],
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'number', new NumberValue( 3.14159 ) )
		);
	}

	public function testBooleanMapsToXsdBoolean(): void {
		$this->assertEquals(
			[ new Literal( 'true', $this->xsd( 'boolean' ) ) ],
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'boolean', new BooleanValue( true ) )
		);
	}

	public function testDateMapsValidPartsAndDropsInvalidOnes(): void {
		$this->assertEquals(
			[
				new Literal( '1685-03-21', $this->xsd( 'date' ) ),
				new Literal( '2020-12-31', $this->xsd( 'date' ) ),
			],
			RdfValueMapperRegistry::withCoreMappers()->mapValue(
				'date',
				new StringValue( '1685-03-21', 'not a date', '2020-13-01', '2020-12-31' )
			)
		);
	}

	public function testDateTimeMapsValidPartsAndDropsInvalidOnes(): void {
		$this->assertEquals(
			[
				new Literal( '2024-01-01T12:00:00Z', $this->xsd( 'dateTime' ) ),
				new Literal( '2025-06-15T08:30:00+02:00', $this->xsd( 'dateTime' ) ),
			],
			RdfValueMapperRegistry::withCoreMappers()->mapValue(
				'dateTime',
				new StringValue(
					'2024-01-01T12:00:00Z',
					'2024-01-01T12:00:00',
					'2025-06-15T08:30:00+02:00'
				)
			)
		);
	}

	public function testUnregisteredTypeReturnsNull(): void {
		$this->assertNull(
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'relation', new StringValue( 'x' ) )
		);
		$this->assertNull(
			RdfValueMapperRegistry::withCoreMappers()->mapValue( 'nonexistent', new StringValue( 'x' ) )
		);
	}

	public function testHasMapperReflectsRegisteredCoreTypes(): void {
		$registry = RdfValueMapperRegistry::withCoreMappers();

		$this->assertTrue( $registry->hasMapper( 'text' ) );
		$this->assertTrue( $registry->hasMapper( 'date' ) );
		$this->assertFalse( $registry->hasMapper( 'relation' ) );
	}

	public function testCustomMapperCanBeRegistered(): void {
		$registry = new RdfValueMapperRegistry();
		$registry->registerMapper(
			'color',
			static fn( $value ): array => [ new Literal( '#' . $value->toScalars()[0], new Iri( 'http://example.org/hex' ) ) ]
		);

		$this->assertEquals(
			[ new Literal( '#00ff00', new Iri( 'http://example.org/hex' ) ) ],
			$registry->mapValue( 'color', new StringValue( '00ff00' ) )
		);
	}

}
