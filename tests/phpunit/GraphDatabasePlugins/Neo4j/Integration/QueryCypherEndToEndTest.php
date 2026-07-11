<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Integration;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\CypherQueryApi
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Neo4jPlugin
 * @group Database
 */
class QueryCypherEndToEndTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	public function testRealCountQueryReturnsEnvelope(): void {
		$response = $this->executeHandler(
			NeoWikiExtension::getInstance()->newCypherQueryApi(),
			new RequestData( [
				'method' => 'POST',
				'bodyContents' => json_encode( [ 'cypher' => 'MATCH (n:Page) RETURN count(n) AS pages' ] ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [ 'pages' ], $body['columns'] );
		$this->assertCount( 1, $body['rows'] );
		$this->assertIsNumeric( $body['rows'][0]['pages'] );
		$this->assertGreaterThanOrEqual( 0, $body['durationMs'] );
		$this->assertFalse( $body['truncated'] );
	}

	public function testWriteQueryRejectedAtRealEngine(): void {
		$response = $this->executeHandler(
			NeoWikiExtension::getInstance()->newCypherQueryApi(),
			new RequestData( [
				'method' => 'POST',
				'bodyContents' => json_encode( [ 'cypher' => 'CREATE (x:Junk {id: "should-not-persist"}) RETURN x' ] ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'writeQueryRejected', $body['errorType'] );
	}

	public function testAdminQueryRejectedByProductionQueryService(): void {
		// STOP DATABASE passes the keyword validator but the Explain layer rejects it, so this fails
		// unless the production service composes the Explain validator, not just the keyword one.
		$service = NeoWikiExtension::getInstance()->newCypherQueryService();
		$request = new Neo4jQueryRequest( 'STOP DATABASE neo4j', [], new Neo4jQueryLimits( 30, 5000 ) );

		$this->expectException( WriteQueryRejectedException::class );

		$service->execute( $request );
	}

}
