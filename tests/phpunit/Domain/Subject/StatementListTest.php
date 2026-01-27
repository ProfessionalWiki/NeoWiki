<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\StatementList
 */
class StatementListTest extends TestCase {

	public function testGetRelationsReturnsEmptyArrayWhenThereAreNoStatements(): void {
		$statements = new StatementList( [] );
		$schema = $this->newSchemaWithSomeRelations();

		$this->assertSame( [], $statements->getTypedRelations( $schema )->relations );
	}

	private function newSchemaWithSomeRelations(): Schema {
		return TestSchema::build(
			properties: new PropertyDefinitions( [
				'string1' => TestProperty::buildText(),
				'relation1' => TestProperty::buildRelation( relationType: 'Type1', targetSchema: 'Schema1' ),
				'string2' => TestProperty::buildText(),
				'relation2' => TestProperty::buildRelation( relationType: 'Type2', targetSchema: 'Schema2', multiple: true ),
			] )
		);
	}

	// TODO: more getTypedRelations tests

	public function testGetReferencedSubjectsReturnsEmptyArrayForEmptyList(): void {
		$statements = new StatementList( [] );

		$this->assertSame( [], $statements->getReferencedSubjects()->asStringArray() );
	}

	public function testGetReferencedSubjectsReturnsAllAndOnlyReferencedSubjects(): void {
		$statements = new StatementList( [
			TestStatement::build(
				property: 'P0',
			),
			TestStatement::build(
				property: 'P1',
				value: new RelationValue( TestRelation::build( targetId: 's11111111111111' ) ),
				propertyType: RelationType::NAME
			),
			TestStatement::build(
				property: 'P1 and a half',
			),
			TestStatement::build(
				property: 'P2',
				value: new RelationValue(
					TestRelation::build( targetId: 's11111111111112' ),
					TestRelation::build( targetId: 's11111111111113' )
				),
				propertyType: RelationType::NAME
			),
			TestStatement::build(
				property: 'P3',
				value: new RelationValue(
					TestRelation::build( targetId: 's11111111111113' ), // Duplicate
					TestRelation::build( targetId: 's11111111111114' )
				),
				propertyType: RelationType::NAME
			),
		] );

		$this->assertSame(
			[
				's11111111111111',
				's11111111111112',
				's11111111111113',
				's11111111111114',
			],
			$statements->getReferencedSubjects()->asStringArray()
		);
	}

	public function testGetStatementReturnsKnownStatement(): void {
		$statement = TestStatement::build( property: 'P2' );

		$list = new StatementList( [
			TestStatement::build( property: 'P1' ),
			$statement,
			TestStatement::build( property: 'P3' )
		] );

		$this->assertEquals(
			$statement,
			$list->getStatement( new PropertyName( 'P2' ) )
		);
	}

	public function testGetStatementReturnsNullForUnknownStatement(): void {
		$list = new StatementList( [
			TestStatement::build( property: 'P1' ),
			TestStatement::build( property: 'P3' )
		] );

		$this->assertNull(
			$list->getStatement( new PropertyName( 'P2' ) )
		);
	}

	public function testConstructorThrowsOnNonStatement(): void {
		$this->expectException( \InvalidArgumentException::class );

		new StatementList( [
			TestStatement::build( property: 'P1' ),
			[ 'P2' => 'old style statement' ],
			TestStatement::build( property: 'P3' )
		] );
	}

}
