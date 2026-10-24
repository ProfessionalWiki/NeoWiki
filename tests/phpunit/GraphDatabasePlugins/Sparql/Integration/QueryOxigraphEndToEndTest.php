<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Integration;

/**
 * {@see QuerySparqlEndToEndTestCase} against Oxigraph — the `test_oxigraph` dev-stack service, a
 * second engine and a second endpoint shape.
 *
 * It runs without `--union-default-graph`, so it is strict where QLever is lenient: an unscoped query
 * sees only the default graph, never the named graphs NeoWiki projects pages into. That is what SPARQL
 * 1.1 specifies, so a query passing here passes on any conforming store.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\SparqlPlugin
 * @group Database
 */
class QueryOxigraphEndToEndTest extends QuerySparqlEndToEndTestCase {

	protected function storeUpdateUrl(): string {
		return $this->storeBaseUrl() . '/update';
	}

	protected function storeQueryUrl(): string {
		return $this->storeBaseUrl() . '/query';
	}

	/**
	 * Oxigraph has no authentication (oxigraph#88), so there is no token to send.
	 */
	protected function storeAccessToken(): ?string {
		return null;
	}

	/**
	 * The service is started without `--union-default-graph`. Unlike QLever's counterpart this is not
	 * an engine property but Oxigraph's spec-conforming default, which the `:latest` image tracks: an
	 * upstream change to it would fail this suite with nothing here having changed.
	 */
	protected function storeUnionsNamedGraphsIntoTheDefaultGraph(): bool {
		return false;
	}

	private function storeBaseUrl(): string {
		return rtrim( $this->requireEnv( 'OXIGRAPH_TEST_BASE_URL', 'Oxigraph (the `test_oxigraph` service)' ), '/' );
	}

}
