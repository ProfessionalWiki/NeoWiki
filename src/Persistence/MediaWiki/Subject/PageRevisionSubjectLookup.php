<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use InvalidArgumentException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsAtRevisionLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use TypeError;

/**
 * Reads every hosting page at the revision a subclass chooses. A page with no revision to read, and
 * one whose Subject data at that revision does not deserialize, contributes no Subjects.
 */
abstract readonly class PageRevisionSubjectLookup implements SubjectLookup, PageSubjectsAtRevisionLookup {

	public function __construct(
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private RevisionLookup $revisionLookup,
	) {
	}

	/**
	 * The revision to read the page at, given its latest one, or null to read nothing.
	 */
	abstract protected function revisionToRead( RevisionRecord $latestRevision ): ?RevisionRecord;

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$subjects = new SubjectMap();
		$idsByHostingPage = new SubjectIdsByHostingPage( $this->pageIdentifiersLookup );

		foreach ( $idsByHostingPage->group( $subjectIds ) as $pageId => $idsOnPage ) {
			$subjects = $subjects->union(
				$this->getPageSubjects( new PageId( $pageId ) )->getAllSubjects()->onlyWithIds( $idsOnPage )
			);
		}

		return $subjects;
	}

	public function getPageSubjects( PageId $pageId ): PageSubjects {
		$latestRevision = $this->revisionLookup->getRevisionByPageId( $pageId->id );
		$revision = $latestRevision === null ? null : $this->revisionToRead( $latestRevision );
		$content = $revision === null ? null : SubjectSlotReader::read( $revision );

		try {
			return $content?->getPageSubjects() ?? PageSubjects::newEmpty();
		} catch ( InvalidArgumentException | TypeError ) {
			return PageSubjects::newEmpty();
		}
	}

}
