<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * The Subjects the given revision holds, and for the rest whatever their own pages answer. The
 * caller has one page's revision in hand and has decided its reader may see it, so Subjects on that
 * page come from it; the other pages the caller said nothing about, and are left to a lookup that
 * answers for a page rather than a revision.
 */
readonly class LatestRevisionSubjectLookup implements SubjectLookup {

	public function __construct(
		private RevisionRecord $revision,
		private SubjectLookup $otherPages,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		// Reading the slot deserializes all of it, and a Subject with no relations asks for nothing.
		if ( $subjectIds->asStringArray() === [] ) {
			return new SubjectMap();
		}

		$subjects = $this->subjectsInRevision( $subjectIds );
		$missingIds = $this->idsMissingFrom( $subjects, $subjectIds );

		if ( $missingIds->asStringArray() === [] ) {
			return $subjects;
		}

		return $subjects->union( $this->otherPages->getSubjects( $missingIds ) );
	}

	private function subjectsInRevision( SubjectIdList $subjectIds ): SubjectMap {
		$content = SubjectSlotReader::read( $this->revision );

		return $content === null ? new SubjectMap() : $content->getPageSubjects()->getAllSubjects()->onlyWithIds( $subjectIds );
	}

	private function idsMissingFrom( SubjectMap $subjects, SubjectIdList $subjectIds ): SubjectIdList {
		return new SubjectIdList( array_filter(
			$subjectIds->asArray(),
			static fn ( SubjectId $subjectId ): bool => !$subjects->hasSubject( $subjectId )
		) );
	}

}
