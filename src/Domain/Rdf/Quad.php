<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * A single RDF statement scoped to a named graph. Subject, predicate and graph are always IRIs;
 * the object is an IRI or a {@see Literal}. Dropping the graph yields the triple used for Turtle.
 */
readonly class Quad {

	public function __construct(
		public Iri $subject,
		public Iri $predicate,
		public RdfTerm $object,
		public Iri $graph,
	) {
	}

	public function equals( self $other ): bool {
		return $this->subject->equals( $other->subject )
			&& $this->predicate->equals( $other->predicate )
			&& $this->object->equals( $other->object )
			&& $this->graph->equals( $other->graph );
	}

}
