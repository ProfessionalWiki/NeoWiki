<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

class NullSubjectLabelLookup implements SubjectLabelLookup {

	public function getSubjectLabelsMatching( string $search, int $limit, ?array $schemaNames ): array {
		return [];
	}

}
