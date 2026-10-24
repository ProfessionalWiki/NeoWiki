<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Integration;

/**
 * {@see QuerySparqlEndToEndTestCase} against QLever — the `test_qlever` dev-stack service, and the
 * engine both Docker stacks point the wiki itself at.
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

	/**
	 * QLever rejects unauthorized updates, so an unset token is as fatal as an unset URL and fails
	 * the same way rather than surfacing later as a 403 from the store.
	 */
	protected function storeAccessToken(): ?string {
		return $this->requireEnv( 'QLEVER_TEST_ACCESS_TOKEN', 'QLever (the `test_qlever` service)' );
	}

	/**
	 * QLever unions unconditionally, with no way to turn it off.
	 */
	protected function storeUnionsNamedGraphsIntoTheDefaultGraph(): bool {
		return true;
	}

}
