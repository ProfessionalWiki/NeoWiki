<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\ProjectionResolver;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction\SparqlRawParserFunction;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlUpdateEndpoint;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\SparqlStoreConfig;

/**
 * Composition root for one SPARQL graph-database backend — one instance per configured store —
 * mirroring {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Neo4jPlugin}. Owns both the write
 * path (the projection store) and the read path (the query service and its wikitext surfaces). A new
 * backend copies this shape.
 *
 * Construction is cheap and never does I/O: no HTTP, no projection resolution, no Mapping lookups. It
 * runs outside the per-plugin failure isolation, so a throw here would break every backend's edit path.
 */
readonly class SparqlPlugin {

	private GraphDatabasePlugin $projectionStore;

	public function __construct(
		private SparqlStoreConfig $store,
		ProjectionResolver $projectionResolver,
		RdfNamespaces $namespaces,
		private HttpRequestFactory $httpRequestFactory,
	) {
		$this->projectionStore = new SparqlProjectionStore(
			endpoint: new HttpSparqlUpdateEndpoint(
				httpRequestFactory: $httpRequestFactory,
				updateUrl: $store->updateUrl,
				accessToken: $store->accessToken,
			),
			projectionResolver: $projectionResolver,
			namespaces: $namespaces,
			// Prefix-less: Turtle with an empty prefix table emits full IRIs and no @prefix directives,
			// which is what is valid inside a SPARQL INSERT DATA block. The projection's own serializer
			// must NOT be reused; its prefix table would emit @prefix, which is illegal there.
			serializer: new HardfRdfSerializer( [] ),
			projectionName: $store->projection,
			endpointUrl: $store->updateUrl,
		);
	}

	public function getGraphDatabasePlugin(): GraphDatabasePlugin {
		return $this->projectionStore;
	}

	public function newQueryService(): SparqlQueryService {
		return new SparqlQueryService(
			new HttpSparqlQueryEndpoint(
				httpRequestFactory: $this->httpRequestFactory,
				queryUrl: $this->store->queryUrl,
				accessToken: $this->store->accessToken,
			)
		);
	}

	/**
	 * The Lua library function names this plugin contributes to mw.neowiki. The handler lives on
	 * ScribuntoLuaLibrary; the plugin's presence is the gate. Only the first configured store's plugin
	 * contributes these (see NeoWikiExtension::getFirstSparqlPlugin).
	 *
	 * @return string[]
	 */
	public function getLuaLibraryFunctionNames(): array {
		return [ 'sparqlQuery' ];
	}

	public function registerParserFunctions( Parser $parser ): void {
		$queryService = $this->newQueryService();

		$parser->setFunctionHook(
			'sparql_raw',
			static function ( Parser $parser, string $query ) use ( $queryService ): string {
				return ( new SparqlRawParserFunction( $queryService ) )->handle( $parser, $query );
			}
		);
	}

}
