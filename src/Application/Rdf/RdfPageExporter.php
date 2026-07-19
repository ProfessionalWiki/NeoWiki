<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;

/**
 * Exports a single page as a self-contained RDF document. Returns null when the page has no NeoWiki
 * data to export (missing page or no Subject slot), so callers can map that to a 404.
 */
readonly class RdfPageExporter {

	public function __construct(
		private RdfPageLoader $loader,
		private PageProjector $projector,
		private RdfSerializer $serializer,
	) {
	}

	public function exportByPageId( PageId $pageId, RdfFormat $format ): ?string {
		$page = $this->loader->loadByPageId( $pageId );

		if ( $page === null ) {
			return null;
		}

		return $this->serializer->serialize( $this->projector->projectPage( $page ), $format );
	}

}
