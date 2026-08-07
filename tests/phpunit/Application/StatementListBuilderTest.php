<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\StatementListBuilder
 */
class StatementListBuilderTest extends TestCase {

	private function newBuilder(): StatementListBuilder {
		return new StatementListBuilder(
			propertyTypeLookup: PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
			idGenerator: new StubIdGenerator( '11111111111111' ),
			subjectIdParser: TestSubjectIds::newParser()
		);
	}

	public function testEmptyArrayProducesEmptyList(): void {
		$list = $this->newBuilder()->build( [] );

		$this->assertSame( [], $list->asArray() );
	}

	public function testSingleStatementIsBuilt(): void {
		$list = $this->newBuilder()->build( [
			'Founded at' => [ 'propertyType' => 'number', 'value' => 2019 ],
		] );

		$statement = $list->getStatement( new PropertyName( 'Founded at' ) );

		$this->assertNotNull( $statement );
		$this->assertSame( 'number', $statement->getPropertyType() );
	}

	public function testMultipleStatementsAreBuilt(): void {
		$list = $this->newBuilder()->build( [
			'A' => [ 'propertyType' => 'text', 'value' => 'one' ],
			'B' => [ 'propertyType' => 'number', 'value' => 2 ],
		] );

		$this->assertNotNull( $list->getStatement( new PropertyName( 'A' ) ) );
		$this->assertNotNull( $list->getStatement( new PropertyName( 'B' ) ) );
	}

	public function testExplicitlyLocalRelationTargetIsStoredBare(): void {
		$list = $this->newBuilder()->build( [
			'Owner' => [
				'propertyType' => 'relation',
				'value' => [ [ 'target' => TestSubjectIds::LOCAL_SOURCE_KEY . ':s11111111111111' ] ],
			],
		] );

		$this->assertSame( 's11111111111111', $this->firstRelationTarget( $list ) );
	}

	public function testRelationTargetFromAnotherSourceKeepsItsQualifiedForm(): void {
		$list = $this->newBuilder()->build( [
			'Owner' => [
				'propertyType' => 'relation',
				'value' => [ [ 'target' => 'otherwiki:Q42' ] ],
			],
		] );

		$this->assertSame( 'otherwiki:Q42', $this->firstRelationTarget( $list ) );
	}

	private function firstRelationTarget( StatementList $list ): string {
		$value = $list->getStatement( new PropertyName( 'Owner' ) )?->getValue();

		$this->assertInstanceOf( RelationValue::class, $value );

		return $value->relations[0]->targetId->text;
	}

	public function testUnregisteredTypePreservesRawValue(): void {
		$list = $this->newBuilder()->build( [
			'Swatch' => [ 'propertyType' => 'color', 'value' => [ '#ff5733' ] ],
		] );

		$statement = $list->getStatement( new PropertyName( 'Swatch' ) );

		$this->assertNotNull( $statement );
		$this->assertSame( 'color', $statement->getPropertyType() );
		$this->assertEquals( new UnregisteredTypeValue( 'color', [ '#ff5733' ] ), $statement->getValue() );
	}

	public function testEmptyValueIsDropped(): void {
		$list = $this->newBuilder()->build( [
			'Kept' => [ 'propertyType' => 'text', 'value' => [ 'yes' ] ],
			'Dropped' => [ 'propertyType' => 'text', 'value' => [] ],
		] );

		$this->assertNotNull( $list->getStatement( new PropertyName( 'Kept' ) ) );
		$this->assertNull( $list->getStatement( new PropertyName( 'Dropped' ) ) );
	}

	public function testUnregisteredTypeStatementIsNotDroppedAsEmpty(): void {
		$list = $this->newBuilder()->build( [
			'Swatch' => [ 'propertyType' => 'color', 'value' => [] ],
		] );

		$this->assertNotNull( $list->getStatement( new PropertyName( 'Swatch' ) ) );
	}

	public function testNullValueIsDropped(): void {
		$list = $this->newBuilder()->build( [
			'Wanted' => [ 'propertyType' => 'text', 'value' => 'yes' ],
			'Unwanted' => null,
		] );

		$this->assertNotNull( $list->getStatement( new PropertyName( 'Wanted' ) ) );
		$this->assertNull( $list->getStatement( new PropertyName( 'Unwanted' ) ) );
	}

	/**
	 * The legacy `type` key is tolerated when reading stored revisions, never on API input:
	 * accepting both here would restore the ambiguity the rename removed.
	 */
	public function testLegacyTypeKeyIsNotAcceptedAsPropertyType(): void {
		$list = $this->newBuilder()->build( [
			'Wanted' => [ 'propertyType' => 'text', 'value' => 'yes' ],
			'Unwanted' => [ 'type' => 'text', 'value' => 'yes' ],
		] );

		$this->assertNotNull( $list->getStatement( new PropertyName( 'Wanted' ) ) );
		$this->assertNull( $list->getStatement( new PropertyName( 'Unwanted' ) ) );
	}

}
