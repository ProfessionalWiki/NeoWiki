<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Exports one Subject's outbound bounded description as a self-contained RDF document, placed in the
 * hosting page's per-projection named graph (the same graph the Subject's triples occupy in the page
 * export and the store sync). Returns null — which the REST handler maps to one indistinguishable
 * 404 — for every reason the Subject cannot be served: it is not in the graph, its hosting page is
 * gone or carries no Subject slot, the current revision no longer holds it (a graph lagging the slot),
 * or the hosting page is not readable.
 *
 * The read gate lives here, not in the handler as for {@see RdfPageExporter} (whose page id is a path
 * param known up front): a Subject's hosting page is only known after the graph resolves it. Folding
 * the gate in keeps every not-found reason byte identical (cf. #1046) and mirrors GetSubjectQuery,
 * which authorizes the resolved page the same way.
 */
readonly class RdfSubjectExporter {

	public function __construct(
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private RdfPageLoader $loader,
		private PageProjector $projector,
		private RdfSerializer $serializer,
		private PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function exportBySubjectId( SubjectId $subjectId, RdfFormat $format ): ?string {
		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId );

		if ( $pageIdentifiers === null ) {
			return null;
		}

		if ( !$this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() ) ) {
			return null;
		}

		$page = $this->loader->loadByPageId( $pageIdentifiers->getId() );

		if ( $page === null || $page->getSubjects()->getAllSubjects()->getSubject( $subjectId ) === null ) {
			return null;
		}

		return $this->serializer->serialize( $this->projector->projectSubject( $page, $subjectId ), $format );
	}

}
