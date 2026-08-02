<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Integration;

/**
 * {@see QuerySparqlEndToEndTestCase} against QLever — the `test_qlever` dev-stack service, and the
 * engine the development stack points the wiki itself at.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\SparqlPlugin
 * @group Database
 */
class QueryQLeverEndToEndTest extends QuerySparqlEndToEndTestCase {

	protected function storeUpdateUrl(): string {
		return $this->requireEnv( 'QLEVER_TEST_URL', 'QLever (the `test_qlever` service)' );
	}

	/**
	 * QLever serves queries and updates on one path, so this is the update endpoint's URL.
	 */
	protected function storeQueryUrl(): string {
		return $this->storeUpdateUrl();
	}

	protected function storeAccessToken(): ?string {
		return getenv( 'QLEVER_TEST_ACCESS_TOKEN' ) ?: null;
	}

	/**
	 * QLever unions unconditionally, with no way to turn it off.
	 */
	protected function storeUnionsNamedGraphsIntoTheDefaultGraph(): bool {
		return true;
	}

}
