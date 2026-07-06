<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientReadQueryEngine
 * @group Database
 */
class Neo4jClientReadQueryEngineIntegrationTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReadQueryReturnsNothingWhenDbIsEmpty(): void {
		$result = $this->readGraph( 'MATCH (n) RETURN n' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

}
