<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;

/**
 * Projects a {@see Page} to RDF quads in some target vocabulary. The native projection
 * ({@see RdfPageProjector}) and each ontology mapping ({@see OntologyMappingProjector}) are sibling
 * implementations selected per store (OntologyMapping.md); the SPARQL store plugin (#586) consumes the
 * projector for its configured projection through this seam.
 */
interface PageProjector {

	public function projectPage( Page $page ): QuadList;

}
