<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\PageIdLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;
use WikiPage;

class MediaWikiSubjectRepository implements SubjectRepository {

	public function __construct(
		private PageIdLookup $pageIdLookup,
		private RevisionLookup $revisionLookup,
		private UserIdentity $user
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getContentBySubjectId( $subjectId )
			?->getSubjects()
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

		$content = $revision->getContent( SlotRecord::MAIN ); // TODO: slot name

		if ( $content instanceof SubjectContent ) {
			return $content;
		}

		return null;
	}

	public function saveSubject( Subject $subject ): void {
		$pageId = $this->getPageIdForSubject( $subject->id );

		if ( $pageId === null ) {
			return;
		}

		$content = $this->getContentByPageId( $pageId );

		if ( $content instanceof SubjectContent ) {
			$this->updateSubject( $content, $subject );
			$this->saveContent( $content, $pageId );
		}
	}

	private function updateSubject( SubjectContent $content, Subject $subject ): void {
		$subjects = $content->getSubjects();
		$subjects->updateSubject( $subject );
		$content->setSubjects( $subjects );
	}

	private function saveContent( SubjectContent $content, int $pageId ): void {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $pageId );

		if ( $wikiPage instanceof WikiPage ) {
			$updater = $wikiPage->newPageUpdater( $this->user );
			$updater->setContent( SlotRecord::MAIN, $content ); // TODO: slot name
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'TODO' ) );
		}
	}

}
