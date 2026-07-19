<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * Incremental RDF writer. {@see write()} returns the serialized text produced for the given quads;
 * {@see finish()} returns any remaining text (closing the last graph block). Callers concatenate or
 * stream the returned chunks in order.
 */
interface RdfStreamWriter {

	public function write( QuadList $quads ): string;

	public function finish(): string;

}
