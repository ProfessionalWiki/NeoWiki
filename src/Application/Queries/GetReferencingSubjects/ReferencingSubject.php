<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

readonly class ReferencingSubject {

	public function __construct(
		public GetSubjectResponseItem $subject,
		/**
		 * The properties on this Subject that point at the one asked about, in the order it holds
		 * them. Never empty: a Subject that points nowhere near it is not a referencing Subject.
		 *
		 * @var string[]
		 */
		public array $propertyNames,
	) {
	}

}
