<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientWriteQueryEngine
 * @group Database
 */
class Neo4jClientWriteQueryEngineTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testRunWriteQuerySavesToDb(): void {
		$this->writeGraph( 'CREATE (:TestNode {name: "Test"} )' );

		$result = $this->readGraph( 'MATCH (node:TestNode {name: "Test"}) RETURN node.name' );

		$this->assertSame(
			[
				[ 'node.name' => 'Test' ]
			],
			$result->toRecursiveArray()
		);
	}

}
