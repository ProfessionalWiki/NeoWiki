<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Integration;

/**
 * {@see QuerySparqlEndToEndTestCase} against Apache Jena Fuseki — a conformance target, not an engine
 * NeoWiki ships or recommends. Jena is the most complete SPARQL 1.1 implementation available to point
 * the same loop at, so what passing here establishes is about the SPARQL NeoWiki emits rather than
 * about one store's tolerance of it.
 *
 * The only one of these suites that skips rather than fails when its store is unset, because it is the
 * only one no stack ships: nothing sets `FUSEKI_TEST_BASE_URL` except the CI workflow that starts the
 * server, so `make phpunit` skips it and CI runs it. Its siblings guard stores the Docker stacks bring
 * up, where an unreachable store is a broken stack rather than an absent extra.
 *
 * To run it here, build Fuseki's container image — Apache publishes none, only a build kit
 * (https://jena.apache.org/documentation/fuseki2/fuseki-docker.html) — and put it on the dev stack's
 * network (`<stack project>_default`, so `neowiki-neowiki_default` for the main checkout):
 *
 *     docker run --rm --name test_fuseki --network neowiki-neowiki_default <your-image> --mem /ds
 *
 * then, from a shell in the mediawiki container (`make bash`):
 *
 *     FUSEKI_TEST_BASE_URL=http://test_fuseki:3030/ds make phpunit filter=QueryFusekiEndToEndTest
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\SparqlPlugin
 * @group Database
 */
class QueryFusekiEndToEndTest extends QuerySparqlEndToEndTestCase {

	private const string BASE_URL_VARIABLE = 'FUSEKI_TEST_BASE_URL';

	protected function storeUpdateUrl(): string {
		return $this->storeBaseUrl() . '/update';
	}

	protected function storeQueryUrl(): string {
		return $this->storeBaseUrl() . '/query';
	}

	/**
	 * The CI server runs without authentication, and Fuseki ignores a Bearer token where none is
	 * configured, so sending one would prove nothing the QLever suite does not already.
	 */
	protected function storeAccessToken(): ?string {
		return null;
	}

	/**
	 * Fuseki keeps its named graphs out of the default graph, as SPARQL 1.1 specifies. A dataset
	 * assembled with `tdb2:unionDefaultGraph` would not, which is why the server is started from the
	 * command line with a plain in-memory dataset.
	 */
	protected function storeUnionsNamedGraphsIntoTheDefaultGraph(): bool {
		return false;
	}

	/**
	 * The base URL of the Fuseki dataset, both endpoints hang off it. Skips instead of failing through
	 * {@see requireEnv}: see the class docblock for why this suite alone is opt-in.
	 */
	private function storeBaseUrl(): string {
		$baseUrl = trim( (string)getenv( self::BASE_URL_VARIABLE ) );

		if ( $baseUrl === '' ) {
			$this->markTestSkipped(
				self::BASE_URL_VARIABLE . ' is not set, so there is no Fuseki to run against. CI sets it; '
				. 'this class\'s docblock has the command to run one here.'
			);
		}

		return rtrim( $baseUrl, '/' );
	}

}
