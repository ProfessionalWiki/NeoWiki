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
use RuntimeException;
use WikiPage;

class SubjectContentRepository {

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly Authority $authority,
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
		string $editSummary = 'Update Subject'
	): void {
		$wikiPage = $this->wikiPageFactory->newFromID( $pageId->id );

		if ( $wikiPage === null ) {
			throw new RuntimeException( 'WikiPage not found' );
		}

		$updater = $wikiPage->newPageUpdater( $this->authority );

		$updater->setContent(
			MediaWikiSubjectRepository::SLOT_NAME,
			$subjectContent
		);

		$updater->saveRevision( CommentStoreComment::newUnsavedComment( $editSummary ) );
	}

}
