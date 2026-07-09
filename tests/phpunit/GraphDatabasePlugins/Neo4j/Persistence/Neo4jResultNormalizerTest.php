<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\DateTimeZoneId;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Time;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\UnboundRelationship;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use RuntimeException;
use stdClass;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer
 */
class Neo4jResultNormalizerTest extends TestCase {

	private function newNormalizer(): Neo4jResultNormalizer {
		return new Neo4jResultNormalizer();
	}

	public function testEmptyResultReturnsEmptyArray(): void {
		$this->assertSame(
			[],
			$this->newNormalizer()->convertRows( new CypherList( [] ) )
		);
	}

	public function testScalarRowsAreReturnedOneIndexed(): void {
		$result = new CypherList( [
			new CypherMap( [ 'name' => 'Ada', 'age' => 36, 'active' => true ] ),
			new CypherMap( [ 'name' => 'Grace', 'age' => 85, 'active' => false ] ),
		] );

		$this->assertSame(
			[
				1 => [ 'name' => 'Ada', 'age' => 36, 'active' => true ],
				2 => [ 'name' => 'Grace', 'age' => 85, 'active' => false ],
			],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testNullsArePreserved(): void {
		$result = new CypherList( [ new CypherMap( [ 'name' => null ] ) ] );

		$this->assertSame(
			[ 1 => [ 'name' => null ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testNestedCypherListBecomesOneIndexedArray(): void {
		$result = new CypherList( [
			new CypherMap( [
				'tags' => new CypherList( [ 'alpha', 'beta', 'gamma' ] ),
			] ),
		] );

		$this->assertSame(
			[ 1 => [ 'tags' => [ 1 => 'alpha', 2 => 'beta', 3 => 'gamma' ] ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testNestedCypherMapBecomesStringKeyedArray(): void {
		$result = new CypherList( [
			new CypherMap( [
				'props' => new CypherMap( [ 'city' => 'Berlin', 'founded' => 2019 ] ),
			] ),
		] );

		$this->assertSame(
			[ 1 => [ 'props' => [ 'city' => 'Berlin', 'founded' => 2019 ] ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testNodeIsConvertedWithIdLabelsAndProperties(): void {
		$node = new Node(
			42,
			new CypherList( [ 'Person', 'Employee' ] ),
			new CypherMap( [ 'name' => 'Ada', 'age' => 36 ] ),
			null
		);

		$this->assertSame(
			[ 1 => [ 'node' => [
				'id' => 42,
				'labels' => [ 1 => 'Person', 2 => 'Employee' ],
				'properties' => [ 'name' => 'Ada', 'age' => 36 ],
			] ] ],
			$this->newNormalizer()->convertRows(
				new CypherList( [ new CypherMap( [ 'node' => $node ] ) ] )
			)
		);
	}

	public function testRelationshipIsConvertedWithEndpointsAndType(): void {
		$rel = new Relationship(
			7,
			1,
			2,
			'KNOWS',
			new CypherMap( [ 'since' => 2020 ] ),
			null
		);

		$this->assertSame(
			[ 1 => [ 'r' => [
				'id' => 7,
				'type' => 'KNOWS',
				'startNodeId' => 1,
				'endNodeId' => 2,
				'properties' => [ 'since' => 2020 ],
			] ] ],
			$this->newNormalizer()->convertRows(
				new CypherList( [ new CypherMap( [ 'r' => $rel ] ) ] )
			)
		);
	}

	public function testUnboundRelationshipHasNoEndpoints(): void {
		$rel = new UnboundRelationship(
			3,
			'TAGGED',
			new CypherMap( [ 'weight' => 0.5 ] ),
			null
		);

		$this->assertSame(
			[ 1 => [ 'r' => [
				'id' => 3,
				'type' => 'TAGGED',
				'properties' => [ 'weight' => 0.5 ],
			] ] ],
			$this->newNormalizer()->convertRows(
				new CypherList( [ new CypherMap( [ 'r' => $rel ] ) ] )
			)
		);
	}

	public function testPathBecomesNodesAndRelationships(): void {
		$nodeA = new Node( 1, new CypherList( [ 'A' ] ), new CypherMap( [] ), null );
		$nodeB = new Node( 2, new CypherList( [ 'B' ] ), new CypherMap( [] ), null );
		$rel = new UnboundRelationship( 9, 'R', new CypherMap( [] ), null );

		$path = new Path(
			new CypherList( [ $nodeA, $nodeB ] ),
			new CypherList( [ $rel ] ),
			new CypherList( [] ),
		);

		$converted = $this->newNormalizer()->convertRows(
			new CypherList( [ new CypherMap( [ 'p' => $path ] ) ] )
		);

		$this->assertSame(
			[
				1 => [ 'id' => 1, 'labels' => [ 1 => 'A' ], 'properties' => [] ],
				2 => [ 'id' => 2, 'labels' => [ 1 => 'B' ], 'properties' => [] ],
			],
			$converted[1]['p']['nodes']
		);
		$this->assertSame( 9, $converted[1]['p']['relationships'][1]['id'] );
	}

	public function testZonedDatetimeBecomesIsoStringWithOffset(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new DateTime( 1694614943, 123000000, 7200, false ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '2023-09-13T16:22:23.123+02:00' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testSubMinuteOffsetDatetimeKeepsWallClockAccurate(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new DateTime( 1623758400, 0, 1172, false ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '2021-06-15T12:19:32+00:19' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testZonedDatetimeWithNanosecondPrecisionKeepsInstant(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new DateTime( 1704067200, 999999999, 0, false ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '2024-01-01T00:00:00.999999999+00:00' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testZoneIdDatetimeBecomesIsoStringWithOffset(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new DateTimeZoneId( 1694614943, 0, 'UTC' ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '2023-09-13T14:22:23+00:00' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testZoneIdDatetimeWithNanosecondPrecisionKeepsInstant(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new DateTimeZoneId( 1704067200, 999999, 'UTC' ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '2024-01-01T00:00:00.000999999+00:00' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testLocalDatetimeWithNanosecondPrecisionKeepsWallClock(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new LocalDateTime( 1704067200, 555555555 ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '2024-01-01T00:00:00.555555555' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testLocalDatetimeBecomesIsoStringWithoutOffset(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new LocalDateTime( 1694614943, 500000000 ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '2023-09-13T14:22:23.5' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testDateBecomesIsoDateString(): void {
		$result = new CypherList( [ new CypherMap( [ 'on' => new Date( 19631 ) ] ) ] );

		$this->assertSame(
			[ 1 => [ 'on' => '2023-10-01' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testTimeBecomesIsoTimeWithOffset(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new Time( 51743500000000, 7200 ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '14:22:23.5+02:00' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testLocalTimeBecomesIsoTimeWithoutOffset(): void {
		$result = new CypherList( [ new CypherMap( [
			'at' => new LocalTime( 34200000000000 ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'at' => '09:30:00' ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testOtherPropertyObjectsBecomeTheirArrayRepresentation(): void {
		$result = new CypherList( [ new CypherMap( [
			'for' => new Duration( 1, 2, 3, 4 ),
		] ) ] );

		$this->assertSame(
			[ 1 => [ 'for' => [ 'months' => 1, 'days' => 2, 'seconds' => 3, 'nanoseconds' => 4 ] ] ],
			$this->newNormalizer()->convertRows( $result )
		);
	}

	public function testUnknownObjectTypeThrows(): void {
		$result = new CypherList( [ new CypherMap( [ 'mystery' => new stdClass() ] ) ] );

		$this->expectException( RuntimeException::class );
		$this->newNormalizer()->convertRows( $result );
	}

}
