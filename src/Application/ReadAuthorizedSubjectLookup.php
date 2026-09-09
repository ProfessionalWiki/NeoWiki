<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * Withholds Subjects hosted by pages the caller may not read, shaping a denial exactly like an
 * absent Subject so that neither a Subject's existence nor anything it holds can be read off the
 * difference (#1046).
 *
 * Ids are filtered before the fetch, so no revision is loaded and no slot deserialized for a page
 * the caller may not read.
 */
readonly class ReadAuthorizedSubjectLookup implements SubjectLookup {

	public function __construct(
		private SubjectLookup $subjectLookup,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		return $this->subjectLookup->getSubjects( $this->readableIds( $subjectIds ) );
	}

	private function readableIds( SubjectIdList $subjectIds ): SubjectIdList {
		$hostingPages = $this->pageIdentifiersLookup->getPageIdsOfSubjects( $subjectIds );

		return new SubjectIdList( array_filter(
			$subjectIds->asArray(),
			fn ( SubjectId $subjectId ): bool
				=> $this->pageIsReadableOrUnresolved( $hostingPages[$subjectId->text] ?? null )
		) );
	}

	/**
	 * Unresolved is allowed, as in GetSubjectQuery: a Subject read out of a caller-supplied revision
	 * has no current hosting page, and one no page hosts is absent from the wrapped lookup anyway.
	 */
	private function pageIsReadableOrUnresolved( ?PageIdentifiers $pageIdentifiers ): bool {
		return $pageIdentifiers === null || $this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() );
	}

}
