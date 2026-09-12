<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

class MediaWikiSubjectRepository implements SubjectRepository {

	public const string SLOT_NAME = 'neo';

	public function __construct(
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
		private readonly RevisionLookup $revisionLookup,
		private readonly PageContentSaver $pageContentSaver,
		private readonly TitleFactory $titleFactory,
		private readonly IContentHandlerFactory $contentHandlerFactory,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$subjects = new SubjectMap();
		$idsByHostingPage = new SubjectIdsByHostingPage( $this->pageIdentifiersLookup );

		foreach ( $idsByHostingPage->group( $subjectIds ) as $pageId => $idsOnPage ) {
			$pageSubjects = $this->getContentByPageId( new PageId( $pageId ) )?->getPageSubjects()->getAllSubjects();

			if ( $pageSubjects !== null ) {
				$subjects = $subjects->union( $pageSubjects->onlyWithIds( $idsOnPage ) );
			}
		}

		return $subjects;
	}

	private function getPageIdForSubject( SubjectId $subjectId ): ?PageId {
		return $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();
	}

	/**
	 * Strict where the read-only lookups are lenient: this content is what the write methods mutate
	 * and save back, so a slot holding another content model has to stop the write rather than read
	 * as no Subjects and be saved over.
	 */
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

	public function updateSubject( Subject $subject, ?string $comment = null ): void {
		$pageId = $this->getPageIdForSubject( $subject->id );

		if ( $pageId === null ) {
			return;
		}

		$content = $this->getContentByPageId( $pageId );

		if ( $content !== null ) {
			$this->updateSubjectContent( $content, $subject );
			$this->saveContent( $content, $pageId, $comment );
		}
	}

	private function updateSubjectContent( SubjectContent $content, Subject $subject ): void {
		$contentData = $content->getPageSubjects();
		$contentData->updateSubject( $subject );
		$content->setPageSubjects( $contentData );
	}

	private function saveContent( SubjectContent $content, PageId $pageId, ?string $comment = null ): PageContentSavingStatus {
		return $this->pageContentSaver->saveContent(
			$pageId,
			[
				self::SLOT_NAME => $content,
			],
			CommentStoreComment::newUnsavedComment( $comment ?? 'Update NeoWiki subject' )
		);
	}

	public function deleteSubject( SubjectId $id, ?string $comment ): PageContentSavingStatus {
		$pageId = $this->getPageIdForSubject( $id );

		if ( $pageId === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}

		$content = $this->getContentByPageId( $pageId );

		if ( $content === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}

		// Asked of the slot rather than inferred from the save: mutatePageSubjects re-serializes the
		// slot whatever the mutation did, so a slot written by anything other than the serializer -
		// an import, or Special:NeoJson - yields new bytes and a real revision even when the removal
		// removed nothing.
		if ( $content->getPageSubjects()->getAllSubjects()->getSubject( $id ) === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}

		$content->mutatePageSubjects( function( PageSubjects $pageSubjects ) use ( $id ): void {
			$pageSubjects->removeSubject( $id );
		} );

		return $this->saveContent( $content, $pageId, $comment );
	}

	public function getMainSubject( PageId $pageId ): ?Subject {
		return $this->getContentByPageId( $pageId )?->getPageSubjects()->getMainSubject();
	}

	public function getSubjectsByPageId( PageId $pageId ): PageSubjects {
		return $this->getContentByPageId( $pageId )?->getPageSubjects() ?? PageSubjects::newEmpty();
	}

	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId, ?string $comment = null ): PageContentSavingStatus {
		$content = $this->getContentByPageId( $pageId ) ?? SubjectContent::newEmpty();

		$content->setPageSubjects( $pageSubjects );

		return $this->saveContent( $content, $pageId, $comment );
	}

	public function createPageWithSubjects( string $pageTitle, PageSubjects $pageSubjects, ?string $comment = null ): PageContentSavingStatus {
		$title = $this->titleFactory->newFromTextThrow( $pageTitle );

		return $this->pageContentSaver->createPage(
			$title,
			[
				// Empty rather than absent, because a page needs a main slot.
				SlotRecord::MAIN => $this->emptyContent( $title ),
				self::SLOT_NAME => SubjectContent::newFromData( $pageSubjects ),
			],
			CommentStoreComment::newUnsavedComment( $comment ?? 'Create a page for a new NeoWiki subject' )
		);
	}

	private function emptyContent( Title $title ): Content {
		return $this->contentHandlerFactory
			->getContentHandler( $title->getContentModel() )
			->makeEmptyContent();
	}
}
