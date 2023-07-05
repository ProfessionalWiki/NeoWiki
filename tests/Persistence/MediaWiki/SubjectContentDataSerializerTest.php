<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataSerializer
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer
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
            "properties": {}
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
            "properties": {
                "founded": [
                    "2019-01-01"
                ],
                "founder": [
                    "John Doe"
                ]
            }
        },
        "93e58a18-dc3e-41aa-8d67-79a18e98b002": {
            "label": "Test subject b002",
            "schema": "TestSubjectSchemaId",
            "properties": {}
        },
        "9d6b4927-0c04-41b3-8daa-3b1d83f4c003": {
            "label": "Test subject c003",
            "schema": "TestSubjectSchemaId",
            "properties": {
                "Has skill": [
                    {
                        "target": "93e58a18-dc3e-41aa-8d67-79a18e98b002",
                        "properties": {
                            "level": "Expert",
                            "years": "10"
                        }
                    }
                ],
                "Likes": [
                    {
                        "target": "9d6b4927-0c04-41b3-8daa-3b1d83f4d004"
                    }
                ]
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
				properties: new StatementList( [
					'Has skill' => [
						[
							'target' => '93e58a18-dc3e-41aa-8d67-79a18e98b002',
							'properties' => [
								'level' => 'Expert',
								'years' => '10'
							]
						]
					],
					'Likes' => [
						[
							'target' => '9d6b4927-0c04-41b3-8daa-3b1d83f4d004'
						]
					]
				] ),
			)
		);

		return new PageSubjects(
			mainSubject: TestSubject::build(
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001',
				label: new SubjectLabel( 'Test subject a001' ),
				schemaId: new SchemaId( 'Employee' ),
				properties: new StatementList( [
					'founded' => [ '2019-01-01' ],
					'founder' => [ 'John Doe' ],
				] )
			),
			childSubjects: $subjects
		);
	}

	/**
	 * @dataProvider exampleSubjectProvider
	 */
	public function testSerializationRoundTrip( string $contentJson ): void {
		$deserializer = new SubjectContentDataDeserializer();
		$serializer = new SubjectContentDataSerializer();

		$newJson = $serializer->serialize( $deserializer->deserialize( $contentJson ) );

		$this->assertJsonStringEqualsJsonString( $contentJson, $newJson );
	}

	public function exampleSubjectProvider(): iterable {
		$dir = new \DirectoryIterator( __DIR__ . '/../../../DemoData/Subject' );

		foreach ( $dir as $fileinfo ) {
			if ( !$fileinfo->isDot() && $fileinfo->getExtension() === 'json' ) {
				yield [ TestData::getFileContents( 'Subject/' . $fileinfo->getFilename() ) ];
			}
		}
	}

}
