<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

interface SubjectLabelLookup {

	/**
	 * @param int $limit Maximum results to return. Callers must cap this to a small value: an
	 *   implementation may over-fetch and run a per-result permission check, so an unbounded limit
	 *   is unbounded work. The REST entry point caps it at 50.
	 * @return SubjectLabelLookupResult[]
	 */
	public function getSubjectLabelsMatching( string $search, int $limit, string $schemaName ): array;

}
