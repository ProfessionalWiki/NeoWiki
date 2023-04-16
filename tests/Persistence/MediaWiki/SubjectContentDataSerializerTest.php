<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectTypeId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectTypeIdList;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
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
            "types": [],
            "relations": [],
            "properties": []
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
            "types": [
                "Company",
                "Organization"
            ],
            "relations": [],
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
            "types": [],
            "relations": [],
            "properties": []
        },
        "9d6b4927-0c04-41b3-8daa-3b1d83f4c003": {
            "label": "Test subject c003",
            "types": [],
            "relations": {
                "70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001": {
                    "type": "HasSkill",
                    "target": "93e58a18-dc3e-41aa-8d67-79a18e98b002",
                    "properties": {
                        "level": "Expert",
                        "years": "10"
                    }
                },
                "A8EF3D2A-3477-4A84-ADEF-3EC62C0E325D": {
                    "type": "Likes",
                    "target": "9d6b4927-0c04-41b3-8daa-3b1d83f4d004",
                    "properties": []
                }
            },
            "properties": []
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
				relations: new RelationList( [
					TestRelation::build(
						id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001',
						type: 'HasSkill',
						targetId: '93e58a18-dc3e-41aa-8d67-79a18e98b002',
						properties: new RelationProperties( [
							'level' => 'Expert',
							'years' => '10'
						] )
					),
					TestRelation::build(
						id: 'A8EF3D2A-3477-4A84-ADEF-3EC62C0E325D',
						type: 'Likes',
						targetId: '9d6b4927-0c04-41b3-8daa-3b1d83f4d004',
						properties: new RelationProperties( [] )
					),
				] )
			)
		);

		return new PageSubjects(
			mainSubject: TestSubject::build(
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001',
				label: new SubjectLabel( 'Test subject a001' ),
				types: new SubjectTypeIdList( [ new SubjectTypeId( 'Company' ), new SubjectTypeId( 'Organization' ) ] ),
				relations: new RelationList( [] ),
				properties: new SubjectProperties( [
					'founded' => [ '2019-01-01' ],
					'founder' => [ 'John Doe' ],
				] )
			),
			childSubjects: $subjects
		);
	}

	public function testSerializationRoundTrip(): void {
		$this->assertEquals(
			$this->newFullSubjectMap(),
			( new SubjectContentDataDeserializer() )->deserialize(
				( new SubjectContentDataSerializer() )->serialize( $this->newFullSubjectMap() )
			)
		);
	}

}
