<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use CommentStoreComment;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;

class MediaWikiSubjectRepository implements SubjectRepository {

	public const string SLOT_NAME = 'neo';

	public function __construct(
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
		private readonly RevisionLookup $revisionLookup,
		private readonly PageContentSaver $pageContentSaver,
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

	private function getPageIdForSubject( SubjectId $subjectId ): ?PageId {
		return $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();
	}

	private function getContentByPageId( PageId $pageId ): ?SubjectContent {
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId->id );

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

	private function saveContent( SubjectContent $content, PageId $pageId ): void {
		$this->pageContentSaver->saveContent(
			$pageId,
			[
				self::SLOT_NAME => $content,
			],
			CommentStoreComment::newUnsavedComment( 'TODO' ) // TODO
		);

		// TODO: expose failure information
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
		return $this->getContentByPageId( $pageId )?->getPageSubjects()->getMainSubject();
	}

	public function getSubjectsByPageId( PageId $pageId ): PageSubjects {
		return $this->getContentByPageId( $pageId )?->getPageSubjects() ?? PageSubjects::newEmpty();
	}

	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId ): void {
		$content = $this->getContentByPageId( $pageId ) ?? SubjectContent::newEmpty();

		$content->setPageSubjects( $pageSubjects );

		$this->saveContent( $content, $pageId );
	}
}
