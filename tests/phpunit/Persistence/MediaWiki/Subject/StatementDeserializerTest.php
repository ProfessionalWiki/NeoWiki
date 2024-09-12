<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\StatementDeserializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\StatementDeserializer
 * @covers \ProfessionalWiki\NeoWiki\Application\ValueDeserializer
 */
class StatementDeserializerTest extends TestCase {

	public function testDeserializesNumber(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyNumber' ),
				format: 'number',
				value: new NumberValue( 42 )
			),
			$this->newDeserializer()->deserialize(
				'MyNumber',
				[
					'format' => 'number',
					'value' => 42,
				]
			)
		);
	}

	private function newDeserializer(): StatementDeserializer {
		return new StatementDeserializer( NeoWikiExtension::getInstance()->getFormatTypeLookup() );
	}

	public function testDeserializesText(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyText' ),
				format: 'text',
				value: new StringValue( 'Foo', 'Bar', 'Baz' )
			),
			$this->newDeserializer()->deserialize(
				'MyText',
				[
					'format' => 'text',
					'value' => [ 'Foo', 'Bar', 'Baz' ],
				]
			)
		);
	}

	public function testDeserializesRelation(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyRelation' ),
				format: 'relation',
				value: new RelationValue(
					new Relation(
						id: new RelationId( '00000000-1111-2222-1100-000000000001' ),
						targetId: new SubjectId( '12345678-0000-0000-0000-000000000011' ),
						properties: new RelationProperties( [] ),
					),
					new Relation(
						id: new RelationId( '00000000-1111-2222-1100-000000000002' ),
						targetId: new SubjectId( '12345678-0000-0000-0000-000000000012' ),
						properties: new RelationProperties( [ 'Foo' => 'Bar' ] ),
					),
				)
			),
			$this->newDeserializer()->deserialize(
				'MyRelation',
				[
					'format' => 'relation',
					'value' => [
						[
							'id' => '00000000-1111-2222-1100-000000000001',
							'target' => '12345678-0000-0000-0000-000000000011',
						],
						[
							'id' => '00000000-1111-2222-1100-000000000002',
							'target' => '12345678-0000-0000-0000-000000000012',
							'properties' => [
								'Foo' => 'Bar',
							],
						],
					],
				]
			)
		);
	}

}
