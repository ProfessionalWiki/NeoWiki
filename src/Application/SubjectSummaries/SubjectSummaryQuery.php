<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

readonly class SubjectSummaryQuery {

	public function __construct(
		/** Only Subjects of this Schema; null for every Schema. */
		public ?SchemaName $schemaName,
		/** Empty for no search. */
		public string $search,
		public SubjectSummarySort $sort,
		/** Ignored for SubjectSummarySort::Newest. */
		public SortDirection $direction,
		/** The last row of the previous page; null for the first page. */
		public ?SubjectSummaryCursor $after,
		public int $limit,
	) {
	}

}
