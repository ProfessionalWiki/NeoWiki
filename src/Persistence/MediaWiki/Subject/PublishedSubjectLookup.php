<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use MediaWiki\Revision\RevisionLookup;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;

/**
 * The Subjects as the wiki publishes them: every hosting page is read at the revision its
 * RevisionPolicy publishes, which on a wiki with an approval extension is not the latest one. A page
 * that publishes nothing contributes no Subjects, so a Subject held only by a draft is not found.
 */
readonly class PublishedSubjectLookup implements SubjectLookup {

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
			$pageSubjects = $this->getPublishedContent( $pageId )?->getPageSubjects()->getAllSubjects();

			if ( $pageSubjects !== null ) {
				$subjects = $subjects->union( $pageSubjects->onlyWithIds( $idsOnPage ) );
			}
		}

		return $subjects;
	}

	private function getPublishedContent( int $pageId ): ?SubjectContent {
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId );

		if ( $revision === null ) {
			return null;
		}

		$publishedRevision = $this->revisionPolicy->publishedRevision( $revision );

		return $publishedRevision === null ? null : SubjectSlotReader::read( $publishedRevision );
	}

}
