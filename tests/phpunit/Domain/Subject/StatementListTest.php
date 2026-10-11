<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\StatementList
 */
class StatementListTest extends TestCase {

	public function testGetRelationsReturnsEmptyArrayWhenThereAreNoStatements(): void {
		$statements = new StatementList( [] );

		$this->assertSame( [], $statements->getTypedRelations()->relations );
	}

	public function testGetTypedRelationsTypesEachRelationWithItsPropertyName(): void {
		$employer = TestRelation::build( targetId: 's11111111111111' );
		$firstFriend = TestRelation::build( targetId: 's11111111111112' );
		$secondFriend = TestRelation::build( targetId: 's11111111111113' );

		$statements = new StatementList( [
			TestStatement::build( property: 'Employer', value: new RelationValue( $employer ), propertyType: RelationType::NAME ),
			TestStatement::build( property: 'Nickname', value: 'Joe' ),
			TestStatement::build(
				property: 'Close friends',
				value: new RelationValue( $firstFriend, $secondFriend ),
				propertyType: RelationType::NAME
			),
		] );

		$this->assertEquals(
			[
				$employer->withPropertyName( new PropertyName( 'Employer' ) ),
				$firstFriend->withPropertyName( new PropertyName( 'Close friends' ) ),
				$secondFriend->withPropertyName( new PropertyName( 'Close friends' ) ),
			],
			$statements->getTypedRelations()->relations
		);
	}

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

	public function testWithStatementAddsStatementForNewProperty(): void {
		$list = new StatementList( [ TestStatement::build( property: 'P1' ) ] );

		$added = TestStatement::build( property: 'P2', value: 'added' );

		$this->assertEquals(
			[ 'P1', 'P2' ],
			array_keys( $list->withStatement( $added )->asArray() )
		);
	}

	public function testWithStatementReplacesStatementInPlace(): void {
		$list = new StatementList( [
			TestStatement::build( property: 'P1' ),
			TestStatement::build( property: 'P2', value: 'old' ),
			TestStatement::build( property: 'P3' ),
		] );

		$replaced = $list->withStatement( TestStatement::build( property: 'P2', value: 'new' ) );

		$this->assertSame( [ 'P1', 'P2', 'P3' ], array_keys( $replaced->asArray() ) );
		$this->assertSame(
			[ 'new' ],
			$replaced->getStatement( new PropertyName( 'P2' ) )->getValue()->toScalars()
		);
	}

	public function testWithStatementLeavesTheOriginalUntouched(): void {
		$list = new StatementList( [ TestStatement::build( property: 'P1', value: 'original' ) ] );

		$list->withStatement( TestStatement::build( property: 'P1', value: 'replacement' ) );

		$this->assertSame(
			[ 'original' ],
			$list->getStatement( new PropertyName( 'P1' ) )->getValue()->toScalars()
		);
	}

	public function testWithoutStatementRemovesOnlyTheNamedProperty(): void {
		$list = new StatementList( [
			TestStatement::build( property: 'P1' ),
			TestStatement::build( property: 'P2' ),
			TestStatement::build( property: 'P3' ),
		] );

		$this->assertSame(
			[ 'P1', 'P3' ],
			array_keys( $list->withoutStatement( new PropertyName( 'P2' ) )->asArray() )
		);
	}

	public function testWithoutStatementKeepsListUnchangedForUnknownProperty(): void {
		$list = new StatementList( [ TestStatement::build( property: 'P1' ) ] );

		$this->assertEquals(
			$list,
			$list->withoutStatement( new PropertyName( 'P2' ) )
		);
	}

	public function testWithoutStatementLeavesTheOriginalUntouched(): void {
		$list = new StatementList( [ TestStatement::build( property: 'P1' ) ] );

		$list->withoutStatement( new PropertyName( 'P1' ) );

		$this->assertNotNull( $list->getStatement( new PropertyName( 'P1' ) ) );
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
