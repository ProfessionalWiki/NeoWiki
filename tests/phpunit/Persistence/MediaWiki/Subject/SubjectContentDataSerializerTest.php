<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\Data\TestData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer
 */
class SubjectContentDataSerializerTest extends TestCase {

	public function testSerializeEmptySubjects(): void {
		$serializer = new SubjectContentDataSerializer();

		$this->assertSame(
			'{
    "mainSubject": null,
    "subjects": {}
}',
			$serializer->serialize( PageSubjects::newEmpty() )
		);
	}

	public function testSerializeMinimalSubject(): void {
		$serializer = new SubjectContentDataSerializer();

		$this->assertSame(
			'{
    "mainSubject": null,
    "subjects": {
        "sTestSCDST11111": {
            "label": "Test subject",
            "schema": "TestSubjectSchemaId",
            "statements": {}
        }
    }
}',
			$serializer->serialize( new PageSubjects(
				null,
				new SubjectMap(
					TestSubject::build( 'sTestSCDST11111' )
				)
			) )
		);
	}

	public function testSerializeFullSubject(): void {
		$serializer = new SubjectContentDataSerializer();

		$this->assertSame(
			'{
    "mainSubject": "sTestSCDST11112",
    "subjects": {
        "sTestSCDST11112": {
            "label": "Test subject 112",
            "schema": "Employee",
            "statements": {
                "founded": {
                    "type": "text",
                    "value": [
                        "2019-01-01"
                    ]
                },
                "founder": {
                    "type": "string",
                    "value": [
                        "John Doe"
                    ]
                }
            }
        },
        "sTestSCDST11113": {
            "label": "Test subject sTestSCDST11113",
            "schema": "TestSubjectSchemaId",
            "statements": {}
        },
        "sTestSCDST11114": {
            "label": "Test subject sTestSCDST11114",
            "schema": "TestSubjectSchemaId",
            "statements": {
                "Has skill": {
                    "type": "relation",
                    "value": [
                        {
                            "id": "rTestSCDST11rr2",
                            "target": "sTestSCDST11113",
                            "properties": {
                                "level": "Expert",
                                "years": "10"
                            }
                        }
                    ]
                },
                "Likes": {
                    "type": "relation",
                    "value": [
                        {
                            "id": "rTestSCDST11rr5",
                            "target": "sTestSCDST11115"
                        }
                    ]
                }
            }
        }
    }
}',
			$serializer->serialize( $this->newFullSubjectMap() )
		);
	}

	private function newFullSubjectMap(): PageSubjects {
		$subjects = new SubjectMap(
			TestSubject::build(
				id: 'sTestSCDST11113',
				label: new SubjectLabel( 'Test subject sTestSCDST11113' ),
			),
			TestSubject::build(
				id: 'sTestSCDST11114',
				label: new SubjectLabel( 'Test subject sTestSCDST11114' ),
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'Has skill' ),
						format: 'relation',
						value: new RelationValue(
							new Relation(
								id: new RelationId( 'rTestSCDST11rr2' ),
								targetId: new SubjectId( 'sTestSCDST11113' ),
								properties: new RelationProperties( [
									'level' => 'Expert',
									'years' => '10'
								] )
							)
						)
					),
					new Statement(
						property: new PropertyName( 'Likes' ),
						format: 'relation',
						value: new RelationValue(
							new Relation(
								id: new RelationId( 'rTestSCDST11rr5' ),
								targetId: new SubjectId( 'sTestSCDST11115' ),
								properties: new RelationProperties( [] )
							)
						)
					),
				] ),
			)
		);

		return new PageSubjects(
			mainSubject: TestSubject::build(
				id: 'sTestSCDST11112',
				label: new SubjectLabel( 'Test subject 112' ),
				schemaId: new SchemaName( 'Employee' ),
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'founded' ),
						format: 'text',
						value: new StringValue( '2019-01-01' )
					),
					new Statement(
						property: new PropertyName( 'founder' ),
						format: 'string',
						value: new StringValue( 'John Doe' )
					),
				] )
			),
			childSubjects: $subjects
		);
	}

	/**
	 * @dataProvider exampleSubjectProvider
	 */
	public function testSerializationRoundTrip( string $contentJson ): void {
		$deserializer = new SubjectContentDataDeserializer( new StatementDeserializer( NeoWikiExtension::getInstance()->getFormatTypeLookup() ) );
		$serializer = new SubjectContentDataSerializer();

		$newJson = $serializer->serialize( $deserializer->deserialize( $contentJson ) );

		$this->assertJsonStringEqualsJsonString( $contentJson, $newJson );
	}

	public function exampleSubjectProvider(): iterable {
		$dir = new \DirectoryIterator( __DIR__ . '/../../../../../DemoData/Subject' );

		foreach ( $dir as $fileinfo ) {
			if ( !$fileinfo->isDot() && $fileinfo->getExtension() === 'json' ) {
				yield [ TestData::getFileContents( 'Subject/' . $fileinfo->getFilename() ) ];
			}
		}
	}

}
