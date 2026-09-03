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
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectHeader;
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
		$subjects = $this->newDeserializer()->deserialize( TestData::getFileContents( 'Subject/ACME_Inc.json' ) );

		$this->assertSame(
			'ACME Inc',
			$subjects->getMainSubject()->getLabel()->text
		);
	}

	private function newDeserializer(): SubjectContentDataDeserializer {
		return new SubjectContentDataDeserializer(
			new StatementDeserializer( NeoWikiExtension::getInstance()->getPropertyTypeLookup() )
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

	/**
	 * @dataProvider labelJsonThatMeansNoLabelProvider
	 */
	public function testLabelJsonThatMeansNoLabelDeserializesToNull( string $labelJson ): void {
		$data = $this->newDeserializer()->deserialize(
			'{"subjects":{"sTestSCDD111115":{' . $labelJson . '"schema":"Company","statements":{}}}}'
		);

		$this->assertNull( $data->getAllSubjects()->getSubject( new SubjectId( 'sTestSCDD111115' ) )?->getLabel() );
	}

	public static function labelJsonThatMeansNoLabelProvider(): iterable {
		yield 'key omitted' => [ '' ];
		yield 'empty string' => [ '"label":"",' ];
		yield 'whitespace only' => [ '"label":"  \\t ",' ];
		yield 'null' => [ '"label":null,' ];
	}

	public function testStoredLabelIsKeptVerbatim(): void {
		$data = $this->newDeserializer()->deserialize(
			'{"subjects":{"sTestSCDD111115":{"label":"  ACME Inc.  ","schema":"Company","statements":{}}}}'
		);

		$this->assertSame(
			'  ACME Inc.  ',
			$data->getAllSubjects()->getSubject( new SubjectId( 'sTestSCDD111115' ) )?->getLabel()?->text
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
					"propertyType": "relation",
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
					propertyType: 'relation',
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

	public function testHeadersCarryIdSchemaLabelAndMainFlag(): void {
		$headers = SubjectContentDataDeserializer::deserializeSubjectHeaders( '{
			"mainSubject": "s1aaaaaaaaaaaa1",
			"subjects": {
				"s1aaaaaaaaaaaa1": { "label": "ACME Inc", "schema": "Company" },
				"s1bbbbbbbbbbbb2": { "label": "Berlin", "schema": "City" }
			}
		}' );

		$this->assertEquals(
			[
				new SubjectHeader( 's1aaaaaaaaaaaa1', 'Company', 'ACME Inc', true ),
				new SubjectHeader( 's1bbbbbbbbbbbb2', 'City', 'Berlin', false ),
			],
			$headers
		);
	}

	public function testSubjectWithoutLabelHasNullLabel(): void {
		$headers = SubjectContentDataDeserializer::deserializeSubjectHeaders(
			'{ "subjects": { "s1aaaaaaaaaaaa1": { "schema": "Company" } } }'
		);

		$this->assertNull( $headers[0]->label );
	}

	public function testSubjectTooBrokenToDeserializeStillHasAHeader(): void {
		$headers = SubjectContentDataDeserializer::deserializeSubjectHeaders(
			'{ "subjects": { "s1aaaaaaaaaaaa1": { "statements": "not an object" } } }'
		);

		$this->assertEquals( [ new SubjectHeader( 's1aaaaaaaaaaaa1', null, null, false ) ], $headers );
	}

	public function testMainSubjectIdNamingNoSubjectMarksNoneAsMain(): void {
		$headers = SubjectContentDataDeserializer::deserializeSubjectHeaders( '{
			"mainSubject": "s1zzzzzzzzzzzz9",
			"subjects": { "s1aaaaaaaaaaaa1": { "schema": "Company" } }
		}' );

		$this->assertFalse( $headers[0]->isMainSubject );
	}

	public function testHeadersSkipIdsThatAreNotSubjectIds(): void {
		$headers = SubjectContentDataDeserializer::deserializeSubjectHeaders( '{
			"subjects": {
				"not-a-subject-id": { "schema": "Company" },
				"s1bbbbbbbbbbbb2": { "schema": "City" }
			}
		}' );

		$this->assertSame( [ 's1bbbbbbbbbbbb2' ], array_map( static fn ( $h ) => $h->id, $headers ) );
	}

	public function testHeadersOfContentWithoutSubjectsAreEmpty(): void {
		$this->assertSame( [], SubjectContentDataDeserializer::deserializeSubjectHeaders( '{}' ) );
	}

	public function testWhitespaceOnlyLabelIsReadAsNoLabel(): void {
		$headers = SubjectContentDataDeserializer::deserializeSubjectHeaders(
			'{ "subjects": { "s1aaaaaaaaaaaa1": { "schema": "Company", "label": "   " } } }'
		);

		$this->assertNull( $headers[0]->label );
	}

	public function testEmptySchemaNameIsReadAsNoSchema(): void {
		$headers = SubjectContentDataDeserializer::deserializeSubjectHeaders(
			'{ "subjects": { "s1aaaaaaaaaaaa1": { "schema": "" } } }'
		);

		$this->assertNull( $headers[0]->schemaName );
	}

}
