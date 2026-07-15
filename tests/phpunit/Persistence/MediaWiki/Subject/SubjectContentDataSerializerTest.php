<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer
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

	private const string FULL_PAGE_JSON = '{
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
                    "type": "text",
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
}';

	public function testSerializeFullSubject(): void {
		$serializer = new SubjectContentDataSerializer();

		$this->assertSame(
			self::FULL_PAGE_JSON,
			$serializer->serialize( $this->newFullSubjectMap() )
		);
	}

	private const string FOREIGN_SCHEMA_PAGE_JSON = '{
    "mainSubject": null,
    "subjects": {
        "sTestSCDST11116": {
            "label": "Foreign schema subject",
            "schema": {
                "source": "otherwiki",
                "name": "Person"
            },
            "statements": {}
        }
    }
}';

	public function testForeignSchemaReferenceSerializesAsObject(): void {
		$serializer = new SubjectContentDataSerializer();

		$this->assertSame(
			self::FOREIGN_SCHEMA_PAGE_JSON,
			$serializer->serialize( new PageSubjects(
				null,
				new SubjectMap(
					new Subject(
						id: new SubjectId( 'sTestSCDST11116' ),
						label: new SubjectLabel( 'Foreign schema subject' ),
						schemaReference: new SchemaReference( 'otherwiki', new SchemaName( 'Person' ) ),
						statements: new StatementList( [] ),
					)
				)
			) )
		);
	}

	public function testForeignSchemaReferenceRoundTripsByteIdentically(): void {
		$this->assertSame(
			self::FOREIGN_SCHEMA_PAGE_JSON,
			$this->roundTrip( self::FOREIGN_SCHEMA_PAGE_JSON )
		);
	}

	public function testSchemaObjectNamingTheLocalSourceCanonicalizesToBareName(): void {
		$objectForm = '{
    "mainSubject": null,
    "subjects": {
        "sTestSCDST11117": {
            "label": "Local schema subject",
            "schema": {
                "source": "testwiki",
                "name": "Person"
            },
            "statements": {}
        }
    }
}';
		$bareForm = '{
    "mainSubject": null,
    "subjects": {
        "sTestSCDST11117": {
            "label": "Local schema subject",
            "schema": "Person",
            "statements": {}
        }
    }
}';

		$this->assertSame( $bareForm, $this->roundTrip( $objectForm ) );
	}

	public function testLocalOnlyPageSlotJsonRoundTripsByteIdentically(): void {
		$deserializer = NeoWikiExtension::getInstance()->newSubjectContentDataDeserializer();
		$serializer = new SubjectContentDataSerializer();

		$this->assertSame(
			self::FULL_PAGE_JSON,
			$serializer->serialize( $deserializer->deserialize( self::FULL_PAGE_JSON ) )
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
						propertyType: 'relation',
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
						propertyType: 'relation',
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
				schemaName: new SchemaName( 'Employee' ),
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'founded' ),
						propertyType: 'text',
						value: new StringValue( '2019-01-01' )
					),
					new Statement(
						property: new PropertyName( 'founder' ),
						propertyType: 'text',
						value: new StringValue( 'John Doe' )
					),
				] )
			),
			childSubjects: $subjects
		);
	}

	public function testStatementOfUnregisteredTypeRoundTripsByteIdentically(): void {
		$contentJson = <<<'JSON'
			{
			    "mainSubject": "sTestSCDS111111",
			    "subjects": {
			        "sTestSCDS111111": {
			            "label": "Test Subject",
			            "schema": "TestSchema",
			            "statements": {
			                "Swatch": {
			                    "type": "color",
			                    "value": [
			                        "#ff5733"
			                    ]
			                },
			                "Name": {
			                    "type": "text",
			                    "value": [
			                        "John Doe"
			                    ]
			                }
			            }
			        }
			    }
			}
			JSON;

		$this->assertJsonStringEqualsJsonString( $contentJson, $this->roundTrip( $contentJson ) );
	}

	/**
	 * Core types only: no extension is loaded, so "color" is an unregistered type.
	 */
	private function roundTrip( string $contentJson ): string {
		$deserializer = new SubjectContentDataDeserializer(
			new StatementDeserializer( PropertyTypeRegistry::withCoreTypes(), TestData::newSubjectIdParser() ),
			TestData::newSubjectIdParser(),
			TestData::newSchemaReferenceParser()
		);

		return ( new SubjectContentDataSerializer() )->serialize( $deserializer->deserialize( $contentJson ) );
	}

	/**
	 * @dataProvider exampleSubjectProvider
	 */
	public function testSerializationRoundTrip( string $contentJson ): void {
		$this->assertJsonStringEqualsJsonString( $contentJson, $this->roundTrip( $contentJson ) );
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
