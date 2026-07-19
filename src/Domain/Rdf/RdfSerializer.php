<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * Serializes {@see Quad}s into an RDF document. This is NeoWiki's own port; concrete implementations
 * wrap a third-party RDF library so its term representation never leaks into the rest of the codebase.
 */
interface RdfSerializer {

	public function serialize( QuadList $quads, RdfFormat $format ): string;

	/**
	 * Opens a streaming writer so large exports (e.g. a full dump) can be emitted incrementally
	 * without materializing every quad at once.
	 */
	public function newWriter( RdfFormat $format ): RdfStreamWriter;

}
