<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\PageIdLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use WikiPage;

class MediaWikiSubjectRepository implements SubjectRepository {

	public const SLOT_NAME = 'neo';

	public function __construct(
		private PageIdLookup $pageIdLookup,
		private RevisionLookup $revisionLookup,
		private UserIdentity $user
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getContentBySubjectId( $subjectId )
			?->getPageSubjects()->getAllSubjects()
			->getSubject( $subjectId );
	}

	private function getContentBySubjectId( SubjectId $subjectId ): ?SubjectContent {
		$pageId = $this->getPageIdForSubject( $subjectId );

		if ( $pageId === null ) {
			return null;
		}

		return $this->getContentByPageId( $pageId );
	}

	private function getPageIdForSubject( SubjectId $subjectId ): ?int {
		return $this->pageIdLookup->getPageIdOfSubject( $subjectId );
	}

	private function getContentByPageId( int $pageId ): ?SubjectContent {
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId );

		if ( $revision === null ) {
			return null;
		}

		try {
			$content = $revision->getContent( self::SLOT_NAME );
		}
		catch ( RevisionAccessException ) {
			return null;
		}

		if ( $content instanceof SubjectContent ) {
			return $content;
		}

		throw new \RuntimeException( 'Content is not a SubjectContent' );
	}

	public function updateSubject( Subject $subject ): void {
		$pageId = $this->getPageIdForSubject( $subject->id );

		if ( $pageId === null ) {
			return;
		}

		$content = $this->getContentByPageId( $pageId );

		if ( $content !== null ) {
			$this->updateSubjectContent( $content, $subject );
			$this->saveContent( $content, $pageId );
		}
	}

	private function updateSubjectContent( SubjectContent $content, Subject $subject ): void {
		$contentData = $content->getPageSubjects();
		$contentData->updateSubject( $subject );
		$content->setPageSubjects( $contentData );
	}

	private function saveContent( SubjectContent $content, int $pageId ): void {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $pageId );

		if ( $wikiPage instanceof WikiPage ) {
			$updater = $wikiPage->newPageUpdater( $this->user );
			$updater->setContent( self::SLOT_NAME, $content );
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'TODO' ) );
		}
	}

	public function deleteSubject( SubjectId $id ): void {
		$pageId = $this->getPageIdForSubject( $id );

		if ( $pageId === null ) {
			return;
		}

		$content = $this->getContentByPageId( $pageId );

		if ( $content === null ) {
			return;
		}

		$content->mutatePageSubjects( function( PageSubjects $pageSubjects ) use ( $id ): void {
			$pageSubjects->removeSubject( $id );
		} );

		$this->saveContent( $content, $pageId );
	}

	public function getMainSubject( PageId $pageId ): ?Subject {
		return $this->getContentByPageId( $pageId->id )?->getPageSubjects()->getMainSubject();
	}

	public function getSubjectsByPageId( PageId $pageId ): PageSubjects {
		return $this->getContentByPageId( $pageId->id )?->getPageSubjects() ?? PageSubjects::newEmpty();
	}

	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId ): void {
		$content = $this->getContentByPageId( $pageId->id ) ?? SubjectContent::newEmpty();

		$content->setPageSubjects( $pageSubjects );

		$this->saveContent( $content, $pageId->id );
	}
}
