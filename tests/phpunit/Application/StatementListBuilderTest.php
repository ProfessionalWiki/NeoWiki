<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\StatementListBuilder
 */
class StatementListBuilderTest extends TestCase {

	private function newBuilder(): StatementListBuilder {
		return new StatementListBuilder(
			new StatementListPatcher(
				propertyTypeToValueType: new PropertyTypeToValueType( PropertyTypeRegistry::withCoreTypes() ),
				idGenerator: new StubIdGenerator( '11111111111111' )
			)
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

	public function testNullValueIsDropped(): void {
		$list = $this->newBuilder()->build( [
			'Wanted' => [ 'propertyType' => 'text', 'value' => 'yes' ],
			'Unwanted' => null,
		] );

		$this->assertNotNull( $list->getStatement( new PropertyName( 'Wanted' ) ) );
		$this->assertNull( $list->getStatement( new PropertyName( 'Unwanted' ) ) );
	}

}
