<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

interface SubjectLabelLookup {

	/**
	 * @return SubjectLabelLookupResult[]
	 */
	public function getSubjectLabelsMatching( string $search, int $limit, array $schemaNames = [] ): array;

}
