<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;

/**
 * A selected projection: the {@see PageProjector} that produces its quads and the {@see RdfSerializer}
 * configured with the prefix table that renders them readably (native prefixes plus, for an ontology
 * projection, the Mapping-declared ontology prefixes). Returned by the projection factory so the RDF
 * export surfaces — and the SPARQL store plugin (#586), which needs only the projector — resolve a
 * projection name to everything they need.
 */
readonly class RdfProjection {

	public function __construct(
		public PageProjector $projector,
		public RdfSerializer $serializer,
	) {
	}

}
