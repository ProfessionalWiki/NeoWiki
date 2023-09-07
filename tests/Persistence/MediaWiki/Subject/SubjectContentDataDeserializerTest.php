<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer
 */
class SubjectContentDataDeserializerTest extends TestCase {

	public function testNodeExampleSmokeTest(): void {
		$subjects = $this->newDeserializer()->deserialize( TestData::getFileContents( 'Subject/Professional_Wiki.json' ) );

		$this->assertSame(
			'Professional Wiki GmbH',
			$subjects->getMainSubject()->getLabel()->text
		);
	}

	private function newDeserializer(): SubjectContentDataDeserializer {
		return new SubjectContentDataDeserializer(
			new StatementDeserializer( NeoWikiExtension::getInstance()->getFormatTypeLookup() )
		);
	}

	public function testMinimalJson(): void {
		$data = $this->newDeserializer()->deserialize( '{}' );

		$this->assertSame( [], $data->getAllSubjects()->asArray() );
		$this->assertNull( $data->getMainSubject() );
	}

	public function testMinimalSubjects(): void {
		$data = $this->newDeserializer()->deserialize(
			<<<JSON
{
	"subjects": {
		"f81d4fae-7dec-11d0-a765-00a0c91e6bf6": {
			"label": "ACME Inc.",
			"schema": "Company"
		},
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "Contoso Ltd.",
			"schema": "Company"
		}
	}
}
JSON
		);

		$this->assertEquals(
			[
				Subject::newSubject(
					new SubjectId( 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6' ),
					new SubjectLabel( 'ACME Inc.' ),
					new SchemaName( "Company" )
				),
				Subject::newSubject(
					new SubjectId( '7e3e53f0-1d9d-11ec-835b-0242ac130003' ),
					new SubjectLabel( 'Contoso Ltd.' ),
					new SchemaName( "Company" )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

	public function testEmptyTopLevelSubjectAttributes(): void {
		$data = $this->newDeserializer()->deserialize(
			<<<JSON
{
	"subjects": {
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "ACME Inc.",
			"schema": "Company",
			"statements": {
			}
		}
	}
}
JSON
		);

		$this->assertEquals(
			[
				Subject::newSubject(
					new SubjectId( '7e3e53f0-1d9d-11ec-835b-0242ac130003' ),
					new SubjectLabel( 'ACME Inc.' ),
					new SchemaName( 'Company' )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

	public function testRelationPropertyWithMultipleValues(): void {
		$subjects = $this->newDeserializer()->deserialize(
			<<<JSON
{
	"mainSubject": "12345678-0000-0000-0000-000000000001",
	"subjects": {
		"12345678-0000-0000-0000-000000000001": {
			"label": "Professional Wiki GmbH",
			"schema": "Company",
			"statements": {
				"Products": {
					"format": "relation",
					"value": [
						{
							"id": "12345678-0000-0000-0000-900000000004",
							"target": "12345678-0000-0000-0000-000000000004"
						},
						{
							"id": "12345678-0000-0000-0000-900000000005",
							"target": "12345678-0000-0000-0000-000000000005"
						}
					]
				}
			}
		}
	}
}
JSON
		);

		$this->assertEquals(
			[
				'Products' => new Statement(
					property: new PropertyName( 'Products' ),
					format: 'relation',
					value: new RelationValue(
						new Relation(
							id: new RelationId( '12345678-0000-0000-0000-900000000004' ),
							targetId: new SubjectId( '12345678-0000-0000-0000-000000000004' ),
							properties: new RelationProperties( [] )
						),
						new Relation(
							id: new RelationId( '12345678-0000-0000-0000-900000000005' ),
							targetId: new SubjectId( '12345678-0000-0000-0000-000000000005' ),
							properties: new RelationProperties( [] )
						)
					)
				),
			],
			$subjects->getMainSubject()->getStatements()->asArray()
		);
	}

}
