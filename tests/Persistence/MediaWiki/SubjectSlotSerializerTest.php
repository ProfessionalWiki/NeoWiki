<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\RelationList;
use ProfessionalWiki\NeoWiki\Domain\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\SubjectProperties;
use ProfessionalWiki\NeoWiki\Domain\SubjectTypeId;
use ProfessionalWiki\NeoWiki\Domain\SubjectTypeIdList;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotSerializer;
use ProfessionalWiki\NeoWiki\Tests\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotSerializer
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotDeserializer
 */
class SubjectSlotSerializerTest extends TestCase {

	public function testSerializeEmptySubjects(): void {
		$serializer = new SubjectSlotSerializer();

		$this->assertSame(
			'{
    "subjects": {}
}',
			$serializer->serialize( new SubjectMap() )
		);
	}

	public function testSerializeMinimalSubject(): void {
		$serializer = new SubjectSlotSerializer();

		$this->assertSame(
			'{
    "subjects": {
        "f81d4fae-7dec-11d0-a765-00a0c91e6bf6": {
            "label": "Test subject",
            "types": [],
            "relations": [],
            "properties": []
        }
    }
}',
			$serializer->serialize( new SubjectMap(
				TestSubject::build( 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6' )
			) )
		);
	}

	public function testSerializeFullSubject(): void {
		$serializer = new SubjectSlotSerializer();

		$this->assertSame(
			'{
    "subjects": {
        "70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001": {
            "label": "Test subject a001",
            "types": [
                "Company",
                "Organization"
            ],
            "relations": [],
            "properties": {
                "founded": "2019-01-01",
                "founder": "John Doe"
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

	private function newFullSubjectMap(): SubjectMap {
		return new SubjectMap(
			TestSubject::build(
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001',
				label: new SubjectLabel( 'Test subject a001' ),
				types: new SubjectTypeIdList( [ new SubjectTypeId( 'Company' ), new SubjectTypeId( 'Organization' ) ] ),
				relations: new RelationList( [] ),
				properties: new SubjectProperties( [
					'founded' => '2019-01-01',
					'founder' => 'John Doe',
				] )
			),
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
	}

	public function testSerializationRoundTrip(): void {
		$this->assertEquals(
			$this->newFullSubjectMap(),
			( new SubjectSlotDeserializer() )->deserialize(
				( new SubjectSlotSerializer() )->serialize( $this->newFullSubjectMap() )
			)
		);
	}

}
