<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jValueBuilderRegistry;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jValueBuilderRegistry
 */
class Neo4jValueBuilderRegistryTest extends TestCase {

	public function testBuildNeo4jValueForOneString(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertEquals(
			[ 'foo' ],
			$registry->buildNeo4jValue( 'url', new StringValue( 'foo' ) )
		);
	}

	public function testBuildNeo4jValueForMultipleStrings(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertEquals(
			[ 'foo', 'bar' ],
			$registry->buildNeo4jValue( 'url', new StringValue( 'foo', 'bar' ) )
		);
	}

	public function testUnregisteredTypeReturnsNull(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertNull(
			$registry->buildNeo4jValue( 'relation', new RelationValue( TestRelation::build() ) )
		);
	}

	public function testHasBuilderForRegisteredType(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertTrue( $registry->hasBuilder( 'text' ) );
		$this->assertTrue( $registry->hasBuilder( 'url' ) );
		$this->assertTrue( $registry->hasBuilder( 'number' ) );
	}

	public function testHasBuilderReturnsFalseForUnregisteredType(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertFalse( $registry->hasBuilder( 'relation' ) );
		$this->assertFalse( $registry->hasBuilder( 'nonexistent' ) );
	}

	public function testCustomBuilderCanBeRegistered(): void {
		$registry = new Neo4jValueBuilderRegistry();
		$registry->registerBuilder( 'custom', static fn( $value ) => 'custom-' . $value->toScalars()[0] );

		$this->assertSame(
			'custom-hello',
			$registry->buildNeo4jValue( 'custom', new StringValue( 'hello' ) )
		);
	}

	public function testTextBuilderConvertsToScalars(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertEquals(
			[ 'hello' ],
			$registry->buildNeo4jValue( 'text', new StringValue( 'hello' ) )
		);
	}

}
