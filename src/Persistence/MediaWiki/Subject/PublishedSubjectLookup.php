<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use InvalidArgumentException;
use MediaWiki\Revision\RevisionLookup;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsAtRevisionLookup;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use TypeError;

/**
 * The Subjects as the wiki publishes them: every hosting page is read at the revision its
 * RevisionPolicy publishes, which on a wiki with an approval extension is not the latest one. A page
 * that publishes nothing contributes no Subjects, so a Subject held only by a draft is not found, and
 * neither does a page whose published Subject data does not deserialize.
 */
readonly class PublishedSubjectLookup implements SubjectLookup, PageSubjectsAtRevisionLookup {

	public function __construct(
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private RevisionLookup $revisionLookup,
		private RevisionPolicy $revisionPolicy,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$subjects = new SubjectMap();
		$idsByHostingPage = new SubjectIdsByHostingPage( $this->pageIdentifiersLookup );

		foreach ( $idsByHostingPage->group( $subjectIds ) as $pageId => $idsOnPage ) {
			$pageSubjects = $this->getPublishedSubjects( $pageId )?->getAllSubjects();

			if ( $pageSubjects !== null ) {
				$subjects = $subjects->union( $pageSubjects->onlyWithIds( $idsOnPage ) );
			}
		}

		return $subjects;
	}

	public function getPageSubjects( PageId $pageId ): PageSubjects {
		return $this->getPublishedSubjects( $pageId->id ) ?? PageSubjects::newEmpty();
	}

	private function getPublishedSubjects( int $pageId ): ?PageSubjects {
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId );

		if ( $revision === null ) {
			return null;
		}

		$publishedRevision = $this->revisionPolicy->publishedRevision( $revision );
		$content = $publishedRevision === null ? null : SubjectSlotReader::read( $publishedRevision );

		try {
			return $content?->getPageSubjects();
		} catch ( InvalidArgumentException | TypeError ) {
			return null;
		}
	}

}
