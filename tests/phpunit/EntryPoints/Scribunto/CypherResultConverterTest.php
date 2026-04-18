<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\UnboundRelationship;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\CypherResultConverter;
use RuntimeException;
use stdClass;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\CypherResultConverter
 */
class CypherResultConverterTest extends TestCase {

	private function newConverter(): CypherResultConverter {
		return new CypherResultConverter();
	}

	public function testEmptyResultReturnsEmptyArray(): void {
		$this->assertSame(
			[],
			$this->newConverter()->convertRows( new CypherList( [] ) )
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
			$this->newConverter()->convertRows( $result )
		);
	}

	public function testNullsArePreserved(): void {
		$result = new CypherList( [ new CypherMap( [ 'name' => null ] ) ] );

		$this->assertSame(
			[ 1 => [ 'name' => null ] ],
			$this->newConverter()->convertRows( $result )
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
			$this->newConverter()->convertRows( $result )
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
			$this->newConverter()->convertRows( $result )
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
			$this->newConverter()->convertRows(
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
			$this->newConverter()->convertRows(
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
			$this->newConverter()->convertRows(
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

		$converted = $this->newConverter()->convertRows(
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

	public function testUnknownObjectTypeThrows(): void {
		$result = new CypherList( [ new CypherMap( [ 'mystery' => new stdClass() ] ) ] );

		$this->expectException( RuntimeException::class );
		$this->newConverter()->convertRows( $result );
	}

}
