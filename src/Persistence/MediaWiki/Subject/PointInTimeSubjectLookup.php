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
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
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
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$subjects = $this->getSubjectsFromRevision( $this->primaryRevision, $subjectIds );
		$idsByHostingPage = new SubjectIdsByHostingPage( $this->pageIdentifiersLookup );

		foreach ( $idsByHostingPage->group( $this->idsMissingFrom( $subjects, $subjectIds ) ) as $pageId => $idsOnPage ) {
			$revision = $this->getRevisionAtOrBefore( new PageId( $pageId ) );

			if ( $revision !== null ) {
				$subjects = $subjects->union(
					$this->getSubjectsFromRevision( $revision, new SubjectIdList( $idsOnPage ) )
				);
			}
		}

		return $subjects;
	}

	private function getSubjectsFromRevision( RevisionRecord $revision, SubjectIdList $subjectIds ): SubjectMap {
		$content = $this->getSubjectContent( $revision );

		if ( $content === null ) {
			return new SubjectMap();
		}

		return $content->getPageSubjects()->getAllSubjects()->onlyWithIds( $subjectIds );
	}

	private function idsMissingFrom( SubjectMap $subjects, SubjectIdList $subjectIds ): SubjectIdList {
		return new SubjectIdList( array_filter(
			$subjectIds->asArray(),
			static fn ( SubjectId $subjectId ): bool => !$subjects->hasSubject( $subjectId )
		) );
	}

	private function getRevisionAtOrBefore( PageId $pageId ): ?RevisionRecord {
		$revisionId = $this->findRevisionIdAtOrBefore( $pageId );

		if ( $revisionId === null ) {
			return null;
		}

		return $this->revisionLookup->getRevisionById( $revisionId );
	}

	private function findRevisionIdAtOrBefore( PageId $pageId ): ?int {
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
