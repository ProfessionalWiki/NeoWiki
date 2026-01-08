<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use CommentStoreComment;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use RuntimeException;
use WikiPage;

class SubjectContentRepository {

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly Authority $authority,
		private readonly PageContentSaver $pageContentSaver,
	) {
	}

	public function getSubjectContentByPageId( PageId $pageId ): ?SubjectContent {
		return $this->getSubjectContentFromWikiPage( $this->wikiPageFactory->newFromID( $pageId->id ) );
	}

	public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent {
		return $this->getSubjectContentFromWikiPage( $this->wikiPageFactory->newFromTitle( $pageIdentity ) );
	}

	private function getSubjectContentFromWikiPage( ?WikiPage $wikiPage ): ?SubjectContent {
		if ( $wikiPage === null ) {
			return null;
		}

		$revision = $wikiPage->getRevisionRecord();

		if ( $revision === null ) {
			return null;
		}

		try {
			$slot = $revision->getSlot(
				MediaWikiSubjectRepository::SLOT_NAME,
				RevisionRecord::FOR_THIS_USER,
				$this->authority
			);
		}
		catch ( RevisionAccessException ) {
			return null;
		}

		$content = $slot->getContent();

		if ( !( $content instanceof SubjectContent ) ) {
			throw new RuntimeException( 'Expected SubjectContent' );
		}

		return $content;
	}

	public function editSubjectContent(
		SubjectContent $subjectContent,
		PageId $pageId,
		string $editSummary
	): void {
		$savingResult = $this->pageContentSaver->saveContent(
			$pageId,
			[
				MediaWikiSubjectRepository::SLOT_NAME => $subjectContent,
			],
			CommentStoreComment::newUnsavedComment( $editSummary )
		);

		if ( $savingResult->errorMessage !== null ) {
			throw new RuntimeException( $savingResult->errorMessage );
		}
	}

}
