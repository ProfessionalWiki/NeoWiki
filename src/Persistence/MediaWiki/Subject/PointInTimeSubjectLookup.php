<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use Wikimedia\Rdbms\IConnectionProvider;

class PointInTimeSubjectLookup implements SubjectLookup {

	public function __construct(
		private readonly RevisionLookup $revisionLookup,
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
		private readonly IConnectionProvider $connectionProvider,
		private readonly RevisionRecord $primaryRevision,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjectFromPrimaryRevision( $subjectId )
			?? $this->getSubjectFromOtherPage( $subjectId );
	}

	private function getSubjectFromPrimaryRevision( SubjectId $subjectId ): ?Subject {
		$content = $this->getSubjectContent( $this->primaryRevision );

		if ( $content === null ) {
			return null;
		}

		return $content->getPageSubjects()->getAllSubjects()->getSubject( $subjectId );
	}

	private function getSubjectFromOtherPage( SubjectId $subjectId ): ?Subject {
		$pageId = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();

		if ( $pageId === null ) {
			return null;
		}

		$revisionId = $this->findRevisionAtOrBefore( $pageId );

		if ( $revisionId === null ) {
			return null;
		}

		$revision = $this->revisionLookup->getRevisionById( $revisionId );

		if ( $revision === null ) {
			return null;
		}

		$content = $this->getSubjectContent( $revision );

		if ( $content === null ) {
			return null;
		}

		return $content->getPageSubjects()->getAllSubjects()->getSubject( $subjectId );
	}

	private function findRevisionAtOrBefore( PageId $pageId ): ?int {
		$dbr = $this->connectionProvider->getReplicaDatabase();

		$row = $dbr->newSelectQueryBuilder()
			->select( 'rev_id' )
			->from( 'revision' )
			->where( [
				'rev_page' => $pageId->id,
				$dbr->expr( 'rev_timestamp', '<=', $this->primaryRevision->getTimestamp() ),
			] )
			->orderBy( 'rev_timestamp', 'DESC' )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchRow();

		if ( $row === false ) {
			return null;
		}

		return (int)$row->rev_id;
	}

	private function getSubjectContent( RevisionRecord $revision ): ?SubjectContent {
		try {
			$content = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME );
		} catch ( RevisionAccessException ) {
			return null;
		}

		if ( $content instanceof SubjectContent ) {
			return $content;
		}

		return null;
	}

}
