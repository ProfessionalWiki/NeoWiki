<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

readonly class SubjectSummaries {

	/**
	 * @param list<SubjectSummary> $summaries
	 * @param ?SubjectSummaryCursor $nextCursor Where the next page starts; null once the listing is exhausted or
	 * the lookup's scan bound ends it.
	 */
	public function __construct(
		public array $summaries,
		public ?SubjectSummaryCursor $nextCursor,
	) {
	}

}
