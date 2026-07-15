<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Builds the wrapped SubjectLookup on demand rather than at construction. The local lookup's
 * construction eagerly requires a configured graph backend, but the SourceRegistry that holds
 * LocalSource is assembled during extension registration, which also runs on backend-less paths
 * (edits, parser-function registration). Deferring keeps that assembly backend-free.
 */
readonly class LazySubjectLookup implements SubjectLookup {

	/**
	 * @param Closure(): SubjectLookup $subjectLookupFactory
	 */
	public function __construct(
		private Closure $subjectLookupFactory
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return ( $this->subjectLookupFactory )()->getSubject( $subjectId );
	}

}
