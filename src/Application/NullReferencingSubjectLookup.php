<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class NullReferencingSubjectLookup implements ReferencingSubjectLookup {

	public function getIdsOfSubjectsReferencing( SubjectId $target, int $limit ): array {
		return [];
	}

}
