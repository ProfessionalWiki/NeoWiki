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
	 * Oxigraph has no authentication at all (oxigraph#88), so there is no token to send.
	 */
	protected function storeAccessToken(): ?string {
		return null;
	}

	/**
	 * Oxigraph splits queries and updates over two fixed paths, so one base URL configures both.
	 */
	private function storeBaseUrl(): string {
		return rtrim( $this->requireEnv( 'OXIGRAPH_TEST_URL', 'Oxigraph' ), '/' );
	}

}
