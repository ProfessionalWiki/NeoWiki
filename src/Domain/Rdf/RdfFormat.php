<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * An RDF serialization format the native projection can be written as. TriG keeps the per-page
 * named graph; Turtle emits the same triples without the graph wrapper.
 */
enum RdfFormat {

	case Turtle;
	case TriG;

}
