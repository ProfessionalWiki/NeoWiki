<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer
 */
class SubjectContentDataDeserializerTest extends TestCase {

	public function testNodeExampleSmokeTest(): void {
		$deserializer = new SubjectContentDataDeserializer();
		$deserializer->deserialize( TestData::getFileContents( 'nodeExample.json' ) );

		$this->assertTrue( true );
	}

	public function testMinimalJson(): void {
		$deserializer = new SubjectContentDataDeserializer();
		$data = $deserializer->deserialize( '{}' );

		$this->assertSame( [], $data->getAllSubjects()->asArray() );
		$this->assertNull( $data->getMainSubject() );
	}

	public function testMinimalSubject(): void {
		$deserializer = new SubjectContentDataDeserializer();
		$data = $deserializer->deserialize(
			<<<JSON
{
	"subjects": {
		"f81d4fae-7dec-11d0-a765-00a0c91e6bf6": {
			"label": "ACME Inc.",
			"schema": "ABC"
		},
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "Contoso Ltd.",
			"schema": "XYZ"
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
					new SchemaId( "ABC" )
				),
				Subject::newSubject(
					new SubjectId( '7e3e53f0-1d9d-11ec-835b-0242ac130003' ),
					new SubjectLabel( 'Contoso Ltd.' ),
					new SchemaId( "XYZ" )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

	public function testEmptyTopLevelSubjectAttributes(): void {
		$deserializer = new SubjectContentDataDeserializer();
		$data = $deserializer->deserialize(
			<<<JSON
{
	"subjects": {
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "ACME Inc.",
			"schema": "SubjectContentDataDeserializerTest",
			"properties": {
			},
			"relations": {
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
					new SchemaId( "SubjectContentDataDeserializerTest" )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

}
