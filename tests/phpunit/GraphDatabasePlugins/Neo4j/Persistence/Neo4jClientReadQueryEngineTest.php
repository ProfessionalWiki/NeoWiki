<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Types\CypherList;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientReadQueryEngine;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientReadQueryEngine
 */
class Neo4jClientReadQueryEngineTest extends TestCase {

	public function testTimeoutIsForwardedAsTransactionConfiguration(): void {
		$capturedConfig = null;
		$readOnlyClient = $this->newClientCapturingConfig( $capturedConfig );

		$engine = $this->newReadQueryEngine( $readOnlyClient );
		$engine->runReadQuery( 'MATCH (n) RETURN n', [], 30 );

		$this->assertNotNull( $capturedConfig );
		$this->assertSame( 30.0, $capturedConfig->getTimeout() );
	}

	public function testNoTimeoutMeansNoTransactionConfiguration(): void {
		$capturedConfig = 'sentinel-not-overwritten';
		$readOnlyClient = $this->newClientCapturingConfig( $capturedConfig );

		$engine = $this->newReadQueryEngine( $readOnlyClient );
		$engine->runReadQuery( 'MATCH (n) RETURN n', [] );

		$this->assertNull( $capturedConfig );
	}

	private function newClientCapturingConfig( mixed &$capturedConfig ): ClientInterface {
		$client = $this->createMock( ClientInterface::class );
		$client
			->method( 'readTransaction' )
			->willReturnCallback(
				function ( callable $cb, ?string $alias = null, ?TransactionConfiguration $config = null ) use ( &$capturedConfig ): SummarizedResult {
					$capturedConfig = $config;
					$summary = null;
					return new SummarizedResult( $summary, new CypherList( [] ) );
				}
			);
		return $client;
	}

	private function newReadQueryEngine( ClientInterface $readOnlyClient ): Neo4jClientReadQueryEngine {
		return new Neo4jClientReadQueryEngine( $readOnlyClient );
	}

}
