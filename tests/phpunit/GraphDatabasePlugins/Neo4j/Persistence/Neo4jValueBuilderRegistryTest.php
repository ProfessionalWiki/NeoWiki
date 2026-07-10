<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use DateTimeImmutable;
use Laudis\Neo4j\Types\Date;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry
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
		$this->assertTrue( $registry->hasBuilder( 'dateTime' ) );
		$this->assertTrue( $registry->hasBuilder( 'date' ) );
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

	public function testDateTimeBuilderConvertsStringsToDateTimeObjects(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertEquals(
			[
				new DateTimeImmutable( '2024-01-01T12:00:00Z' ),
				new DateTimeImmutable( '2025-06-15T08:30:00+02:00' ),
			],
			$registry->buildNeo4jValue(
				'dateTime',
				new StringValue( '2024-01-01T12:00:00Z', '2025-06-15T08:30:00+02:00' )
			)
		);
	}

	public function testDateBuilderConvertsStringsToDateObjects(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertEquals(
			[
				new Date( 19723 ), // 2024-01-01
				new Date( 20254 ), // 2025-06-15
				new Date( -165 ), // 1969-07-20
			],
			$registry->buildNeo4jValue(
				'date',
				new StringValue( '2024-01-01', '2025-06-15', '1969-07-20' )
			)
		);
	}

	public function testDateBuilderDropsValuesThatAreNotStrictIsoDates(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertEquals(
			[
				new Date( 19723 ), // 2024-01-01
				new Date( 20578 ), // 2026-05-05
			],
			$registry->buildNeo4jValue(
				'date',
				new StringValue(
					'2024-01-01',
					'Ignored bad value',
					'2024-01-01T12:00:00Z', // Time and timezone not allowed
					'2024-02-30', // Calendar overflow
					'2026-05-05',
				)
			)
		);
	}

	public function testDateTimeBuilderDropsValuesThatAreNotStrictIsoDateTimes(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$this->assertEquals(
			[
				new DateTimeImmutable( '2024-01-01T12:00:00Z' ),
				new DateTimeImmutable( '2026-05-05T05:05:05+02:00' ),
			],
			$registry->buildNeo4jValue(
				'dateTime',
				new StringValue(
					'2024-01-01T12:00:00Z',
					'Ignored bad value',
					'2024-01-01T12:00:00',
					'2024-02-30T00:00:00Z',
					'2026-05-05T05:05:05+02:00',
				)
			)
		);
	}

}
