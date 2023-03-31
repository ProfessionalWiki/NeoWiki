<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotDeserializer
 */
class SubjectSlotDeserializerTest extends TestCase {

	public function testNodeExampleSmokeTest(): void {
		$deserializer = new SubjectSlotDeserializer();
		$deserializer->deserialize( TestData::getFileContents( 'nodeExample.json' ) );

		$this->assertTrue( true );
	}

	public function testMinimalJson(): void {
		$deserializer = new SubjectSlotDeserializer();
		$subjectMap = $deserializer->deserialize( '{}' );

		$this->assertSame( [], $subjectMap->asArray() );
	}

	public function testMinimalSubject(): void {
		$deserializer = new SubjectSlotDeserializer();
		$subjectMap = $deserializer->deserialize(
			<<<JSON
{
	"subjects": {
		"f81d4fae-7dec-11d0-a765-00a0c91e6bf6": {
			"label": "ACME Inc."
		},
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "Contoso Ltd."
		}
	}
}
JSON
		);

		$this->assertEquals(
			[
				Subject::newSubject( new SubjectId( 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6' ), new SubjectLabel( 'ACME Inc.' ) ),
				Subject::newSubject( new SubjectId( '7e3e53f0-1d9d-11ec-835b-0242ac130003' ), new SubjectLabel( 'Contoso Ltd.' ) ),
			],
			$subjectMap->asArray()
		);
	}

	public function testEmptyTopLevelSubjectAttributes(): void {
		$deserializer = new SubjectSlotDeserializer();
		$subjectMap = $deserializer->deserialize(
			<<<JSON
{
	"subjects": {
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "ACME Inc.",
			"types": [
			],
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
			Subject::newSubject( new SubjectId( '7e3e53f0-1d9d-11ec-835b-0242ac130003' ), new SubjectLabel( 'ACME Inc.' ) ),
			],
			$subjectMap->asArray()
		);
	}

}
