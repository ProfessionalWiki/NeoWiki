<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Integration;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\QueryCypherApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Cypher\QueryService
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
			NeoWikiExtension::getInstance()->newQueryCypherApi(),
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
			NeoWikiExtension::getInstance()->newQueryCypherApi(),
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

}
