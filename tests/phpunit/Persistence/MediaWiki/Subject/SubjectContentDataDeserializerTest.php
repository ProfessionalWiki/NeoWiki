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
		"sTestSCDD111115": {
			"label": "ACME Inc.",
			"schema": "Company"
		},
		"sTestSCDD111114": {
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
					new SubjectId( 'sTestSCDD111115' ),
					new SubjectLabel( 'ACME Inc.' ),
					new SchemaName( "Company" )
				),
				Subject::newSubject(
					new SubjectId( 'sTestSCDD111114' ),
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
		"sTestSCDD111114": {
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
					new SubjectId( 'sTestSCDD111114' ),
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
	"mainSubject": "sTestSCDD111111",
	"subjects": {
		"sTestSCDD111111": {
			"label": "Professional Wiki GmbH",
			"schema": "Company",
			"statements": {
				"Products": {
					"type": "relation",
					"value": [
						{
							"id": "rTestSCDDrrrrr1",
							"target": "sTestSCDD111112"
						},
						{
							"id": "rTestSCDDrrrrr2",
							"target": "sTestSCDD111113"
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
							id: new RelationId( 'rTestSCDDrrrrr1' ),
							targetId: new SubjectId( 'sTestSCDD111112' ),
							properties: new RelationProperties( [] )
						),
						new Relation(
							id: new RelationId( 'rTestSCDDrrrrr2' ),
							targetId: new SubjectId( 'sTestSCDD111113' ),
							properties: new RelationProperties( [] )
						)
					)
				),
			],
			$subjects->getMainSubject()->getStatements()->asArray()
		);
	}

}
