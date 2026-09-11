<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Exports one Subject's outbound bounded description as a self-contained RDF document, placed in the
 * hosting page's per-projection named graph (the same graph the Subject's triples occupy in the page
 * export and the store sync). Returns null — which the REST handler maps to one indistinguishable
 * 404 — when SubjectHostingPageResolver answers no readable page, when that page cannot be loaded,
 * or when its current revision no longer holds the Subject (an index lagging the slot).
 */
readonly class RdfSubjectExporter {

	public function __construct(
		private SubjectHostingPageResolver $hostingPageResolver,
		private RdfPageLoader $loader,
		private PageProjector $projector,
		private RdfSerializer $serializer,
	) {
	}

	public function exportBySubjectId( SubjectId $subjectId, RdfFormat $format ): ?string {
		$pageIdentifiers = $this->hostingPageResolver->resolveReadableHostingPage( $subjectId );

		if ( $pageIdentifiers === null ) {
			return null;
		}

		$page = $this->loader->loadByPageId( $pageIdentifiers->getId() );

		if ( $page === null || $page->getSubjects()->getAllSubjects()->getSubject( $subjectId ) === null ) {
			return null;
		}

		return $this->serializer->serialize( $this->projector->projectSubject( $page, $subjectId ), $format );
	}

}
