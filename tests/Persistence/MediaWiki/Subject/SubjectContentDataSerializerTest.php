<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

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
        "f81d4fae-7dec-11d0-a765-00a0c91e6bf6": {
            "label": "Test subject",
            "schema": "TestSubjectSchemaId",
            "statements": {}
        }
    }
}',
			$serializer->serialize( new PageSubjects(
				null,
				new SubjectMap(
					TestSubject::build( 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6' )
				)
			) )
		);
	}

	public function testSerializeFullSubject(): void {
		$serializer = new SubjectContentDataSerializer();

		$this->assertSame(
			'{
    "mainSubject": "70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001",
    "subjects": {
        "70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001": {
            "label": "Test subject a001",
            "schema": "Employee",
            "statements": {
                "founded": {
                    "format": "date",
                    "value": [
                        "2019-01-01"
                    ]
                },
                "founder": {
                    "format": "string",
                    "value": [
                        "John Doe"
                    ]
                }
            }
        },
        "93e58a18-dc3e-41aa-8d67-79a18e98b002": {
            "label": "Test subject b002",
            "schema": "TestSubjectSchemaId",
            "statements": {}
        },
        "9d6b4927-0c04-41b3-8daa-3b1d83f4c003": {
            "label": "Test subject c003",
            "schema": "TestSubjectSchemaId",
            "statements": {
                "Has skill": {
                    "format": "relation",
                    "value": [
                        {
                            "id": "93e58a18-dc3e-41aa-8d67-79a18e98b022",
                            "target": "93e58a18-dc3e-41aa-8d67-79a18e98b002",
                            "properties": {
                                "level": "Expert",
                                "years": "10"
                            }
                        }
                    ]
                },
                "Likes": {
                    "format": "relation",
                    "value": [
                        {
                            "id": "9d6b4927-0c04-41b3-8daa-3b1d83f4d044",
                            "target": "9d6b4927-0c04-41b3-8daa-3b1d83f4d004"
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
				id: '93e58a18-dc3e-41aa-8d67-79a18e98b002',
				label: new SubjectLabel( 'Test subject b002' ),
			),
			TestSubject::build(
				id: '9d6b4927-0c04-41b3-8daa-3b1d83f4c003',
				label: new SubjectLabel( 'Test subject c003' ),
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'Has skill' ),
						format: 'relation',
						value: new RelationValue(
							new Relation(
								id: new RelationId( '93e58a18-dc3e-41aa-8d67-79a18e98b022' ),
								targetId: new SubjectId( '93e58a18-dc3e-41aa-8d67-79a18e98b002' ),
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
								id: new RelationId( '9d6b4927-0c04-41b3-8daa-3b1d83f4d044' ),
								targetId: new SubjectId( '9d6b4927-0c04-41b3-8daa-3b1d83f4d004' ),
								properties: new RelationProperties( [] )
							)
						)
					),
				] ),
			)
		);

		return new PageSubjects(
			mainSubject: TestSubject::build(
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001',
				label: new SubjectLabel( 'Test subject a001' ),
				schemaId: new SchemaName( 'Employee' ),
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'founded' ),
						format: 'date',
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
		$dir = new \DirectoryIterator( __DIR__ . '/../../../../DemoData/Subject' );

		foreach ( $dir as $fileinfo ) {
			if ( !$fileinfo->isDot() && $fileinfo->getExtension() === 'json' ) {
				yield [ TestData::getFileContents( 'Subject/' . $fileinfo->getFilename() ) ];
			}
		}
	}

}
