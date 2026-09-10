<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

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
 * the caller may not read. A Subject whose hosting page does not resolve is withheld rather than
 * served ungated, as in GetPageSubjectsQuery: the wrapped lookup reaches Subjects through that same
 * index, so today the two agree, and a lookup that later bypasses the index cannot escape the gate.
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
		$pageIsReadable = [];
		$readableIds = [];

		foreach ( $subjectIds->asArray() as $idText => $subjectId ) {
			$pageId = ( $hostingPages[$idText] ?? null )?->getId();

			if ( $pageId === null ) {
				continue;
			}

			// Several Subjects share a hosting page often enough to matter here: each check loads
			// the page row and runs the full permission hook, and a Subject being saved is
			// validated twice.
			$pageIsReadable[$pageId->id] ??= $this->readAuthorizer->authorizeReadByPageId( $pageId );

			if ( $pageIsReadable[$pageId->id] ) {
				$readableIds[] = $subjectId;
			}
		}

		return new SubjectIdList( $readableIds );
	}

}
