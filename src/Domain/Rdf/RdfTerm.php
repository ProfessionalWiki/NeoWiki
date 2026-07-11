<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * An RDF term that can occupy the object position of a {@see Quad}: an {@see Iri} or a {@see Literal}.
 * Blank nodes are intentionally not modelled; the native projection mints IRIs for every node.
 */
interface RdfTerm {

	public function equals( self $other ): bool;

}
