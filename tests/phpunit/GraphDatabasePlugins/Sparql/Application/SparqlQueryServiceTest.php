<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\EmptySparqlQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\InternalSparqlQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlStoreUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlSyntaxException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryResult;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FakeSparqlQueryEndpoint;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService
 */
class SparqlQueryServiceTest extends TestCase {

	private const string RESULTS = '{"head":{"vars":["label"]},"results":{"bindings":[{"label":{"type":"literal","value":"Bach"}}]}}';

	public function testReturnsDecodedResultsDocument(): void {
		$result = $this->execute(
			FakeSparqlQueryEndpoint::returning( self::RESULTS ),
			'SELECT ?label WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#label> ?label }'
		);

		$this->assertSame( [ 'label' ], $result->document['head']['vars'] );
		$this->assertSame( 'Bach', $result->document['results']['bindings'][0]['label']['value'] );
	}

	public function testEmptyQueryThrowsEmptySparqlQueryException(): void {
		$this->expectException( EmptySparqlQueryException::class );

		$this->execute( FakeSparqlQueryEndpoint::returning( self::RESULTS ), '   ' );
	}

	public function testClientErrorFromStoreBecomesSyntaxExceptionCarryingStoreDetail(): void {
		try {
			$this->execute(
				FakeSparqlQueryEndpoint::failingWith( new SparqlQueryFailedException( 'https://s.example', 400, 'parse error near WHERE' ) ),
				'INVALID'
			);
			$this->fail( 'Expected SparqlSyntaxException' );
		} catch ( SparqlSyntaxException $exception ) {
			$this->assertSame( 'sparqlSyntaxError', $exception->errorType() );
			$this->assertStringContainsString( 'parse error near WHERE', $exception->getMessage() );
		}
	}

	public function testServerErrorFromStoreBecomesStoreUnavailableException(): void {
		$this->expectException( SparqlStoreUnavailableException::class );

		$this->execute(
			FakeSparqlQueryEndpoint::failingWith( new SparqlQueryFailedException( 'https://s.example', 503, 'overloaded' ) ),
			'SELECT * WHERE { ?s ?p ?o }'
		);
	}

	public function testTransportFailureBecomesStoreUnavailableException(): void {
		$this->expectException( SparqlStoreUnavailableException::class );

		$this->execute(
			FakeSparqlQueryEndpoint::failingWith( new SparqlQueryFailedException( 'https://s.example', 0, '' ) ),
			'SELECT * WHERE { ?s ?p ?o }'
		);
	}

	public function testNonJsonResponseBecomesInternalSparqlQueryException(): void {
		$this->expectException( InternalSparqlQueryException::class );

		$this->execute( FakeSparqlQueryEndpoint::returning( '<html>Gateway Timeout</html>' ), 'SELECT * WHERE { ?s ?p ?o }' );
	}

	public function testAppliesTierTimeoutToTheEndpoint(): void {
		$endpoint = FakeSparqlQueryEndpoint::returning( self::RESULTS );

		( new SparqlQueryService( $endpoint ) )->execute(
			new SparqlQueryRequest( 'SELECT * WHERE { ?s ?p ?o }', new SparqlQueryLimits( 17 ) )
		);

		$this->assertSame( 17, $endpoint->lastTimeoutSeconds );
	}

	public function testTrimsQueryBeforeSending(): void {
		$endpoint = FakeSparqlQueryEndpoint::returning( self::RESULTS );

		$this->execute( $endpoint, "  SELECT * WHERE { ?s ?p ?o }  \n" );

		$this->assertSame( 'SELECT * WHERE { ?s ?p ?o }', $endpoint->lastQuery );
	}

	private function execute( FakeSparqlQueryEndpoint $endpoint, string $sparql ): SparqlQueryResult {
		return ( new SparqlQueryService( $endpoint ) )->execute(
			new SparqlQueryRequest( $sparql, new SparqlQueryLimits( 30 ) )
		);
	}

}
