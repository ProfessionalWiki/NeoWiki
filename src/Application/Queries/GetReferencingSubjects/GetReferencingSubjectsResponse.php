<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects;

readonly class GetReferencingSubjectsResponse {

	public function __construct(
		/**
		 * @var ReferencingSubject[] An ordered list: a Subject appears once however many of its
		 *   properties point at the one asked about.
		 */
		public array $referencingSubjects,
		/**
		 * Reports that referencing Subjects were left out. False does not promise there are none,
		 * and no total is given: counting rows the caller may not read would leak them (#1062).
		 */
		public bool $truncated,
	) {
	}

}
