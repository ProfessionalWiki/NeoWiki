<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\StatementList
 */
class StatementListTest extends TestCase {

	/**
	 * @dataProvider provideTestData
	 */
	public function testConstructorFiltersOutEmptyProperties( array $input, array $expected ): void {
		$statements = new StatementList( $input );

		$this->assertEquals( $expected, $statements->asMap() );
	}

	public function provideTestData(): array {
		return [
			'empty string property' => [
				[
					'Empty String' => '',
					'Non-empty String' => 'I am not empty',
				],
				[
					'Non-empty String' => 'I am not empty',
				]
			],
			'empty array property' => [
				[
					'Empty Array' => [],
					'Non-empty Array' => [ 'I am not empty' ],
				],
				[
					'Non-empty Array' => [ 'I am not empty' ],
				]
			],
			'null property' => [
				[
					'Null' => null,
				],
				[
					'Null' => null,
				]
			],
			'number property' => [
				[
					'Zero' => 0,
					'Non-zero' => 1,
				],
				[
					'Zero' => 0,
					'Non-zero' => 1,
				]
			],
			'boolean property' => [
				[
					'False' => false,
					'True' => true,
				],
				[
					'False' => false,
					'True' => true,
				]
			],
		];
	}

	public function testGetRelationsReturnsEmptyArrayWhenThereAreNoStatements(): void {
		$statements = new StatementList( [] );
		$schema = $this->newSchemaWithSomeRelations();

		$this->assertSame( [], $statements->getRelations( $schema )->asMap() );
	}

	private function newSchemaWithSomeRelations(): Schema {
		return TestSchema::build(
			properties: new PropertyDefinitions( [
				'string1' => TestProperty::buildString(),
				'relation1' => TestProperty::buildRelation( relationType: 'Type1', targetSchema: 'Schema1' ),
				'string2' => TestProperty::buildString(),
				'relation2' => TestProperty::buildRelation( relationType: 'Type2', targetSchema: 'Schema2', multiple: true ),
			] )
		);
	}

	public function testGetRelationsReturnsEmptyArrayWhenThereAreNoPropertyDefinitions(): void {
		$statements = $this->newStatementsWithSomeRelations();
		$schema = TestSchema::build();

		$this->assertSame( [], $statements->getRelations( $schema )->asMap() );
	}

	private function newStatementsWithSomeRelations(): StatementList {
		return new StatementList( [
			'string1' => 'value1',
			'relation1' => [
				[
					'id' => '12345678-0000-0000-0000-000000000044',
					'target' => '12345678-0000-0000-0000-000000000004',
				],
			],
			'string2' => 'value3',
			'relation2' => [
				[
					'id' => '12345678-0000-0000-0000-000000000055',
					'target' => '12345678-0000-0000-0000-000000000005',
				],
				[
					'id' => '12345678-0000-0000-0000-000000000066',
					'target' => '12345678-0000-0000-0000-000000000006',
				],
			],
		] );
	}

	public function testGetRelationsReturnsOnlyRelations(): void {
		$statements = $this->newStatementsWithSomeRelations();
		$schema = $this->newSchemaWithSomeRelations();

		$this->assertEquals(
			new RelationList( [
				TestRelation::build( id: '12345678-0000-0000-0000-000000000044', type: 'Type1', targetId: '12345678-0000-0000-0000-000000000004' ),
				TestRelation::build( id: '12345678-0000-0000-0000-000000000055', type: 'Type2', targetId: '12345678-0000-0000-0000-000000000005' ),
				TestRelation::build( id: '12345678-0000-0000-0000-000000000066', type: 'Type2', targetId: '12345678-0000-0000-0000-000000000006' ),
			] ),
			$statements->getRelations( $schema )
		);
	}

	public function testGetRelationsHandlesNonArrayRelationValues(): void {
		$schema = $this->newSchemaWithSomeRelations();

		$statements = new StatementList( [
			'relation1' => [
				'id' => '12345678-0000-0000-0000-000000000044',
				'target' => '12345678-0000-0000-0000-000000000004',
			],
		] );

		$this->assertEquals(
			new RelationList( [
				TestRelation::build( id: '12345678-0000-0000-0000-000000000044', type: 'Type1', targetId: '12345678-0000-0000-0000-000000000004' ),
			] ),
			$statements->getRelations( $schema )
		);
	}

	public function testGetRelationsDiscardsInvalidRelationValues(): void {
		$schema = $this->newSchemaWithSomeRelations();

		$statements = new StatementList( [
			'relation1' => [
				'not' => 'valid',
			],
			'relation2' => [
				[
					'id' => '12345678-0000-0000-0000-000000000044',
					'target' => '12345678-0000-0000-0000-000000000004',
				],
				[
					'not' => 'valid',
				],
				[
					'missing' => 'id',
					'target' => '12345678-0000-0000-0000-000000000005',
				],
				[
					'missing' => 'target',
					'id' => '12345678-0000-0000-0000-000000000044',
				],
				[
					'id' => '12345678-0000-0000-0000-000000000055',
					'target' => '12345678-0000-0000-0000-000000000005',
				],
			],
		] );

		$this->assertEquals(
			new RelationList( [
				TestRelation::build( id: '12345678-0000-0000-0000-000000000044', type: 'Type2', targetId: '12345678-0000-0000-0000-000000000004' ),
				TestRelation::build( id: '12345678-0000-0000-0000-000000000055', type: 'Type2', targetId: '12345678-0000-0000-0000-000000000005' ),
			] ),
			$statements->getRelations( $schema )
		);
	}

	public function testWithoutRelations(): void {
		$statements = $this->newStatementsWithSomeRelations();
		$schema = $this->newSchemaWithSomeRelations();

		$this->assertEquals(
			new StatementList( [
				'string1' => 'value1',
				'string2' => 'value3',
			] ),
			$statements->withoutRelations( $schema )
		);
	}

}
