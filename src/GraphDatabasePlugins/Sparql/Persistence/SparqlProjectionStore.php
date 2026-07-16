<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence;

use ProfessionalWiki\NeoWiki\Application\Rdf\PageProjector;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\UnknownProjectionException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\ProjectionResolver;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlUpdateEndpoint;

/**
 * Keeps a SPARQL 1.1 graph store in sync with wiki page changes, one instance per configured store
 * (NativeRdfProjection.md § Sync Mechanism). Each page is a named graph identified by this store's
 * projection and the page (`{$base}/graph/{projection}/page/{id}`, #1053): a save replaces that graph
 * atomically (DROP + INSERT DATA), a delete drops it. Qualifying the graph by projection is what lets
 * two stores with different projections point at one endpoint without each save wiping the other's
 * triples for that page.
 *
 * The configured projection is resolved lazily on each save through the {@see ProjectionResolver}
 * seam — never at construction — so the store always uses the current Mapping definitions and its
 * construction stays I/O-free (it runs outside the per-plugin failure isolation, where a throw would
 * break every backend's edit path).
 */
readonly class SparqlProjectionStore implements GraphDatabasePlugin {

	public function __construct(
		private SparqlUpdateEndpoint $endpoint,
		private ProjectionResolver $projectionResolver,
		private RdfNamespaces $namespaces,
		// A prefix-less serializer on purpose: Turtle with an empty prefix table emits full IRIs and no
		// @prefix directives, which is what is valid inside a SPARQL INSERT DATA block. The projection's
		// own serializer must NOT be reused — its prefix table would emit @prefix, which is illegal there.
		private RdfSerializer $serializer,
		private string $projectionName,
		private string $endpointUrl,
	) {
	}

	public function savePage( Page $page ): void {
		$projector = $this->resolveProjector();
		$graph = $this->namespaces->graph( $this->projectionName, $page->getId() );

		$this->endpoint->postUpdate(
			$this->buildSaveUpdate( $graph, $projector->projectPage( $page ) )
		);
	}

	private function resolveProjector(): PageProjector {
		$projection = $this->projectionResolver->newRdfProjection( $this->projectionName );

		if ( $projection === null ) {
			throw new UnknownProjectionException(
				$this->endpointUrl,
				$this->projectionName,
				$this->projectionResolver->getRdfProjectionNames()
			);
		}

		return $projection->projector;
	}

	private function buildSaveUpdate( Iri $graph, QuadList $quads ): string {
		$drop = $this->dropGraph( $graph );

		// An empty projection means the page has no triples, so replace the graph with nothing: a bare
		// DROP, no INSERT DATA (INSERT DATA with an empty group is not what we want and some stores reject
		// it).
		if ( $quads->isEmpty() ) {
			return $drop;
		}

		return $drop . " ;\n"
			. 'INSERT DATA { GRAPH <' . $graph->value . "> {\n"
			. $this->serializer->serialize( $quads, RdfFormat::Turtle )
			. "} }";
	}

	public function deletePage( PageId $pageId ): void {
		// No projection resolution here on purpose: a delete only needs the graph IRI, which this store
		// can name from its own projection, so it keeps working even when the Mapping is broken or
		// missing.
		$this->endpoint->postUpdate(
			$this->dropGraph( $this->namespaces->graph( $this->projectionName, $pageId ) )
		);
	}

	private function dropGraph( Iri $graph ): string {
		// DROP SILENT so the first save of a new page does not error on a not-yet-existing graph. The
		// graph IRI is built from trusted config (the base URI), an integer page id, and the projection
		// name — the one untrusted part, since a Mapping target is author-controlled. RdfNamespaces::graph()
		// percent-encodes it, `>` included, so it cannot close the <...> early and forge an update clause.
		return 'DROP SILENT GRAPH <' . $graph->value . '>';
	}

}
