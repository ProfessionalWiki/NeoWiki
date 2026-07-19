<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfStreamWriter;

readonly class HardfRdfSerializer implements RdfSerializer {

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI, used for abbreviation.
	 */
	public function __construct(
		private array $prefixes,
	) {
	}

	public function serialize( QuadList $quads, RdfFormat $format ): string {
		$writer = $this->newWriter( $format );

		return $writer->write( $quads ) . $writer->finish();
	}

	public function newWriter( RdfFormat $format ): RdfStreamWriter {
		return new HardfRdfStreamWriter( $this->prefixes, $format );
	}

}
