<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentDataDeserializer
 */
class SubjectContentDataDeserializerTest extends TestCase {

	public function testNodeExampleSmokeTest(): void {
		$deserializer = new SubjectContentDataDeserializer( $this->newSchemaRepoWithCompanyAndProduct() );
		$subjects = $deserializer->deserialize( TestData::getFileContents( 'nodeExample.json' ) );

		$this->assertSame(
			'Professional Wiki GmbH',
			$subjects->getMainSubject()->getLabel()->text
		);
	}

	private function newSchemaRepoWithCompanyAndProduct(): SchemaRepository {
		return new InMemorySchemaRepository(
			( new SchemaDeserializer() )->deserialize(
				new SchemaId( 'Company' ),
				TestData::getFileContents( 'companySchema.json' )
			),
			( new SchemaDeserializer() )->deserialize(
				new SchemaId( 'Product' ),
				TestData::getFileContents( 'productSchema.json' )
			)
		);
	}

	public function testMinimalJson(): void {
		$deserializer = new SubjectContentDataDeserializer( new InMemorySchemaRepository() );
		$data = $deserializer->deserialize( '{}' );

		$this->assertSame( [], $data->getAllSubjects()->asArray() );
		$this->assertNull( $data->getMainSubject() );
	}

	public function testMinimalSubjects(): void {
		$deserializer = new SubjectContentDataDeserializer( $this->newSchemaRepoWithCompanyAndProduct() );
		$data = $deserializer->deserialize(
			<<<JSON
{
	"subjects": {
		"f81d4fae-7dec-11d0-a765-00a0c91e6bf6": {
			"label": "ACME Inc.",
			"schema": "Company"
		},
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
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
					new SubjectId( 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6' ),
					new SubjectLabel( 'ACME Inc.' ),
					new SchemaId( "Company" )
				),
				Subject::newSubject(
					new SubjectId( '7e3e53f0-1d9d-11ec-835b-0242ac130003' ),
					new SubjectLabel( 'Contoso Ltd.' ),
					new SchemaId( "Company" )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

	public function testEmptyTopLevelSubjectAttributes(): void {
		$deserializer = new SubjectContentDataDeserializer( $this->newSchemaRepoWithCompanyAndProduct() );
		$data = $deserializer->deserialize(
			<<<JSON
{
	"subjects": {
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "ACME Inc.",
			"schema": "Company",
			"properties": {
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
					new SchemaId( 'Company' )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

	public function testWhenSchemaIsNotFoundAnEmptyFallbackIsUsed(): void {
		$deserializer = new SubjectContentDataDeserializer( $this->newSchemaRepoWithCompanyAndProduct() );
		$subjects = $deserializer->deserialize(
			<<<JSON
{
	"mainSubject": "7e3e53f0-1d9d-11ec-835b-0242ac130003",
	"subjects": {
		"7e3e53f0-1d9d-11ec-835b-0242ac130003": {
			"label": "ACME Inc.",
			"schema": "UnknownSchema",
			"properties": {
				"foo": "bar",
				"baz": 42
			}
		}
	}
}
JSON
		);

		$this->assertSame(
			[
				'foo' => 'bar',
				'baz' => 42,
			],
			$subjects->getMainSubject()->getProperties()->asMap()
		);

		$this->assertSame(
			'UnknownSchema',
			$subjects->getMainSubject()->getSchemaId()->getText()
		);
	}

	public function testArrayOfRelationProperty(): void {
		$deserializer = new SubjectContentDataDeserializer( $this->newSchemaRepoWithCompanyAndProduct() );
		$subjects = $deserializer->deserialize(
			<<<JSON
{
	"mainSubject": "12345678-0000-0000-0000-000000000001",
	"subjects": {
		"12345678-0000-0000-0000-000000000001": {
			"label": "Professional Wiki GmbH",
			"schema": "Company",
			"properties": {
				"Has product": [
					{
						"target": "12345678-0000-0000-0000-000000000004"
					},
					{
						"target": "12345678-0000-0000-0000-000000000005"
					}
				]
			}
		}
	}
}
JSON
		);

		$this->assertSame( [], $subjects->getMainSubject()->getProperties()->asMap() );

		$this->assertSame(
			[
				'12345678-0000-0000-0000-000000000004',
				'12345678-0000-0000-0000-000000000005',
			],
			$subjects->getMainSubject()->getRelationsAsIdStringArray()
		);
	}

	public function testRelationProperty(): void {
		$deserializer = new SubjectContentDataDeserializer( $this->newSchemaRepoWithCompanyAndProduct() );
		$subjects = $deserializer->deserialize(
			<<<JSON
{
	"mainSubject": "12345678-0000-0000-0000-000000000001",
	"subjects": {
		"12345678-0000-0000-0000-000000000001": {
			"label": "Professional Wiki GmbH",
			"schema": "Company",
			"properties": {
				"Has main product": {
					"target": "12345678-0000-0000-0000-000000000004"
				}
			}
		}
	}
}
JSON
		);

		$this->assertSame( [], $subjects->getMainSubject()->getProperties()->asMap() );

		$this->assertSame(
			[
				'12345678-0000-0000-0000-000000000004',
			],
			$subjects->getMainSubject()->getRelationsAsIdStringArray()
		);
	}

}
