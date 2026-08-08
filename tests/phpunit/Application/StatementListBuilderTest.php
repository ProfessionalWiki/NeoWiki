<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\StatementListBuilder
 */
class StatementListBuilderTest extends TestCase {

	private function newBuilder(): StatementListBuilder {
		return new StatementListBuilder(
			propertyTypeLookup: PropertyTypeRegistry::withCoreTypes(),
			idGenerator: new StubIdGenerator( '11111111111111' )
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
	 * @dataProvider valueNotFittingItsTypeProvider
	 */
	public function testValueNotFittingItsPropertyTypeIsRejected( string $propertyType, mixed $value ): void {
		$builder = $this->newBuilder();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Mismatched' );

		$builder->build( [ 'Mismatched' => [ 'propertyType' => $propertyType, 'value' => $value ] ] );
	}

	public static function valueNotFittingItsTypeProvider(): iterable {
		yield 'text given a number' => [ 'text', 2019 ];
		yield 'number given a string' => [ 'number', 'not a number' ];
		yield 'number with no value' => [ 'number', null ];
		yield 'boolean given a string' => [ 'boolean', 'yes' ];
		yield 'relation given a scalar' => [ 'relation', 'sTargetIdWanted' ];
		yield 'relation target missing' => [ 'relation', [ [ 'properties' => [] ] ] ];
		yield 'relation given a list of bare target ids' => [ 'relation', [ 'sTargetIdWanted' ] ];
	}

	/**
	 * @dataProvider nonStringPropertyTypeProvider
	 */
	public function testNonStringPropertyTypeIsRejected( mixed $propertyType ): void {
		$builder = $this->newBuilder();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Mistyped' );

		$builder->build( [ 'Mistyped' => [ 'propertyType' => $propertyType, 'value' => 'yes' ] ] );
	}

	public static function nonStringPropertyTypeProvider(): iterable {
		yield 'number' => [ 2019 ];
		yield 'boolean' => [ true ];
		yield 'array' => [ [ 'text' ] ];
		yield 'object' => [ [ 'name' => 'text' ] ];
	}

	/**
	 * PHP turns a decimal-integer array key into an int, so a property named like a year
	 * reaches the value objects as one.
	 */
	public function testPropertyNameThatLooksLikeAnIntegerIsBuilt(): void {
		$list = $this->newBuilder()->build( [ '2024' => [ 'propertyType' => 'text', 'value' => 'yes' ] ] );

		$this->assertSame(
			[ 'yes' ],
			$list->getStatement( new PropertyName( '2024' ) )?->getValue()->toScalars()
		);
	}

	/**
	 * @dataProvider objectWhereAListIsExpectedProvider
	 */
	public function testObjectValueWhereAListIsExpectedIsRejected( string $propertyType, mixed $value ): void {
		$builder = $this->newBuilder();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Objected' );

		$builder->build( [ 'Objected' => [ 'propertyType' => $propertyType, 'value' => $value ] ] );
	}

	public static function objectWhereAListIsExpectedProvider(): iterable {
		yield 'text given an object' => [ 'text', [ 'a' => 'yes' ] ];
		yield 'url given an object' => [ 'url', [ 'a' => 'https://pro.wiki' ] ];
		yield 'relation given one relation rather than a list' => [
			'relation',
			[ 'target' => 'sTargetIdWanted' ],
		];
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
