<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Projects a {@see Page} to RDF quads in some target vocabulary. The native projection
 * ({@see RdfPageProjector}) and each ontology mapping ({@see OntologyMappingProjector}) are sibling
 * implementations selected per store (OntologyMapping.md); the SPARQL store plugin (#586) consumes the
 * projector for its configured projection through this seam.
 */
interface PageProjector {

	public function projectPage( Page $page ): QuadList;

	/**
	 * Projects the single Subject $subjectId hosted on $page: exactly the quads {@see projectPage()}
	 * emits for that Subject (its outbound bounded description, including native relation reification),
	 * with none of the page-metadata quads. The page is passed for context — the graph IRI derives from
	 * its id and the Schema/Mapping is resolved as in a full-page projection. Returns an empty list when
	 * the Subject is absent from the page or its projection yields no quads (native: the Schema is
	 * unavailable; ontology: no Mapping targets it).
	 */
	public function projectSubject( Page $page, SubjectId $subjectId ): QuadList;

}
