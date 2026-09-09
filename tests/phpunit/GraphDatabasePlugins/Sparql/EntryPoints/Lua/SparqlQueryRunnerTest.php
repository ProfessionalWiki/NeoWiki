<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\EntryPoints\Lua;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\EmptySparqlQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlStoreUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\Lua\SparqlQueryRunner;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FakeSparqlQueryEndpoint;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubRawQueryAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\Lua\SparqlQueryRunner
 */
class SparqlQueryRunnerTest extends TestCase {

	public function testReindexesListsToOneBasedWhilePreservingObjectKeys(): void {
		$results = '{"head":{"vars":["s","label"]},"results":{"bindings":['
			. '{"s":{"type":"uri","value":"urn:1"},"label":{"type":"literal","value":"One"}},'
			. '{"s":{"type":"uri","value":"urn:2"},"label":{"type":"literal","value":"Two"}}]}}';

		$table = $this->runViaRunner( $results );

		// head.vars is a JSON array -> 1-indexed Lua sequence.
		$this->assertSame( [ 1 => 's', 2 => 'label' ], $table['head']['vars'] );

		// results.bindings is a JSON array -> 1-indexed; each binding is an object keeping string keys.
		$bindings = $table['results']['bindings'];
		$this->assertSame( [ 1, 2 ], array_keys( $bindings ) );
		$this->assertSame( 'urn:1', $bindings[1]['s']['value'] );
		$this->assertSame( 'Two', $bindings[2]['label']['value'] );
	}

	public function testPreservesAskBooleanResult(): void {
		$table = $this->runViaRunner( '{"head":{},"boolean":true}' );

		$this->assertTrue( $table['boolean'] );
	}

	public function testPreservesEmptyBindingsWhileReindexingVars(): void {
		$table = $this->runViaRunner( '{"head":{"vars":["s"]},"results":{"bindings":[]}}' );

		$this->assertSame( [ 1 => 's' ], $table['head']['vars'] );
		$this->assertSame( [], $table['results']['bindings'] );
	}

	public function testEmptyQueryPropagatesEmptySparqlQueryException(): void {
		$this->expectException( EmptySparqlQueryException::class );

		$this->runViaRunner( '{"head":{},"results":{"bindings":[]}}', '   ' );
	}

	public function testStoreFailurePropagatesAsStoreUnavailableException(): void {
		$runner = $this->newRunner(
			FakeSparqlQueryEndpoint::failingWith( new SparqlQueryFailedException( 'https://s.example', 500, 'boom' ) )
		);

		$this->expectException( SparqlStoreUnavailableException::class );
		$runner->run( 'SELECT * WHERE { ?s ?p ?o }' );
	}

	/**
	 * @return array<int|string, mixed>
	 */
	private function runViaRunner( string $results, string $query = 'SELECT * WHERE { ?s ?p ?o }' ): array {
		return $this->newRunner( FakeSparqlQueryEndpoint::returning( $results ) )->run( $query );
	}

	private function newRunner( FakeSparqlQueryEndpoint $endpoint, ?SparqlQueryLimits $limits = null ): SparqlQueryRunner {
		return new SparqlQueryRunner(
			new SparqlQueryService( $endpoint, new StubRawQueryAuthorizer( true ) ),
			$limits ?? new SparqlQueryLimits( 30 ),
		);
	}

	public function testRunsTheQueryWithTheInjectedLimits(): void {
		$endpoint = FakeSparqlQueryEndpoint::returning( '{"head":{},"results":{"bindings":[]}}' );

		$this->newRunner( $endpoint, new SparqlQueryLimits( 17 ) )->run( 'SELECT * WHERE { ?s ?p ?o }' );

		$this->assertSame( 17, $endpoint->lastTimeoutSeconds );
	}

}
