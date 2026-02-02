<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\ExplainCypherQueryValidator;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\ExplainCypherQueryValidator
 * @group Database
 */
class ExplainCypherQueryValidatorTest extends NeoWikiIntegrationTestCase {

	private ExplainCypherQueryValidator $validator;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->validator = new ExplainCypherQueryValidator(
			NeoWikiExtension::getInstance()->getReadOnlyNeo4jClient()
		);
	}

	public function testMatchReturnIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'MATCH (n) RETURN n' ) );
	}

	public function testMatchWithWhereIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'MATCH (n) WHERE n.name = "test" RETURN n' ) );
	}

	public function testMatchWithOrderByAndLimitIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'MATCH (n) RETURN n ORDER BY n.name LIMIT 10' ) );
	}

	public function testRelationshipTraversalIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'MATCH (a)-[r]->(b) RETURN a, r, b' ) );
	}

	public function testVariableLengthPathIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'MATCH (a)-[*1..3]->(b) RETURN a, b' ) );
	}

	public function testOptionalMatchIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed(
			'MATCH (a) OPTIONAL MATCH (a)-[r]->(b) RETURN a, r, b'
		) );
	}

	public function testAggregationIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'MATCH (n) RETURN count(n)' ) );
	}

	public function testDistinctIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'MATCH (n) RETURN DISTINCT n.name' ) );
	}

	public function testUnionIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed(
			'MATCH (n) RETURN n.name AS name UNION MATCH (m) RETURN m.name AS name'
		) );
	}

	public function testUnwindIsAllowed(): void {
		$this->assertTrue( $this->validator->queryIsAllowed( 'UNWIND [1, 2, 3] AS x RETURN x' ) );
	}

	public function testCreateIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed( 'CREATE (n:Test) RETURN n' ) );
	}

	public function testSetPropertyIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed( 'MATCH (n) SET n.x = 1 RETURN n' ) );
	}

	public function testDeleteIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed( 'MATCH (n:Test) DELETE n' ) );
	}

	public function testDetachDeleteIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed( 'MATCH (n) DETACH DELETE n' ) );
	}

	public function testMergeIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed( 'MERGE (n:Test {name: "x"}) RETURN n' ) );
	}

	public function testRemovePropertyIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed( 'MATCH (n:Test) REMOVE n.x RETURN n' ) );
	}

	public function testCreateIndexIsNotAllowed(): void {
		$this->assertFalse(
			$this->validator->queryIsAllowed( 'CREATE INDEX test_index IF NOT EXISTS FOR (n:Test) ON (n.name)' )
		);
	}

	public function testCallSubqueryWithCreateIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed( 'CALL { CREATE (n:Test) RETURN n } RETURN n' ) );
	}

	public function testCreateConstraintIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed(
			'CREATE CONSTRAINT test_constraint IF NOT EXISTS FOR (n:Test) REQUIRE n.name IS UNIQUE'
		) );
	}

	public function testCreateRelationshipIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed(
			'MATCH (a:Test), (b:Test) CREATE (a)-[:KNOWS]->(b)'
		) );
	}

	public function testForeachWithSetIsNotAllowed(): void {
		$this->assertFalse( $this->validator->queryIsAllowed(
			'MATCH (n) FOREACH (x IN [1, 2, 3] | SET n.prop = x)'
		) );
	}

}
