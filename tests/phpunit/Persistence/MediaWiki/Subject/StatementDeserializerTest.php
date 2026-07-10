<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer
 * @covers \ProfessionalWiki\NeoWiki\Application\ValueDeserializer
 */
class StatementDeserializerTest extends TestCase {

	public function testDeserializesNumber(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyNumber' ),
				propertyType: 'number',
				value: new NumberValue( 42 )
			),
			$this->newDeserializer()->deserialize(
				'MyNumber',
				[
					'type' => 'number',
					'value' => 42,
				]
			)
		);
	}

	/**
	 * Core types only: no extension is loaded, so "color" is an unregistered type.
	 */
	private function newDeserializer(): StatementDeserializer {
		return new StatementDeserializer( PropertyTypeRegistry::withCoreTypes() );
	}

	public function testDeserializesText(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyText' ),
				propertyType: 'text',
				value: new StringValue( 'Foo', 'Bar', 'Baz' )
			),
			$this->newDeserializer()->deserialize(
				'MyText',
				[
					'type' => 'text',
					'value' => [ 'Foo', 'Bar', 'Baz' ],
				]
			)
		);
	}

	public function testDeserializesRelation(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyRelation' ),
				propertyType: 'relation',
				value: new RelationValue(
					new Relation(
						id: new RelationId( 'rTestSDT1111rr1' ),
						targetId: new SubjectId( 'sTestSDT1111111' ),
						properties: new RelationProperties( [] ),
					),
					new Relation(
						id: new RelationId( 'rTestSDT1111rr2' ),
						targetId: new SubjectId( 'sTestSDT1111112' ),
						properties: new RelationProperties( [ 'Foo' => 'Bar' ] ),
					),
				)
			),
			$this->newDeserializer()->deserialize(
				'MyRelation',
				[
					'type' => 'relation',
					'value' => [
						[
							'id' => 'rTestSDT1111rr1',
							'target' => 'sTestSDT1111111',
						],
						[
							'id' => 'rTestSDT1111rr2',
							'target' => 'sTestSDT1111112',
							'properties' => [
								'Foo' => 'Bar',
							],
						],
					],
				]
			)
		);
	}

	public function testDeserializesUnregisteredTypeWithoutThrowing(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'Swatch' ),
				propertyType: 'color',
				value: new UnregisteredTypeValue( 'color', [ '#ff5733' ] )
			),
			$this->newDeserializer()->deserialize(
				'Swatch',
				[
					'type' => 'color',
					'value' => [ '#ff5733' ],
				]
			)
		);
	}

	public function testUnregisteredTypeValueReserializesToTheOriginalJson(): void {
		$value = [ 'nested' => [ 'a', 1, true ], 'other' => null ];

		$statement = $this->newDeserializer()->deserialize( 'Swatch', [ 'type' => 'color', 'value' => $value ] );

		$this->assertSame( $value, $statement->getValue()->toScalars() );
	}

}
