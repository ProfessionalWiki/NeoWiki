<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use CommentStoreComment;
use Content;
use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use WikitextContent;

class ImportPagesAction {

	public function __construct(
		private readonly ImportPresenter $presenter,
		private readonly Authority $performer,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly SchemaContentSource $schemaContentSource,
		private readonly SubjectPageSource $subjectPageSource,
		private readonly PageContentSource $pageContentSource,
	) {
	}

	public function import(): void {
		foreach ( $this->schemaContentSource->getSchemas() as $schemaName => $schemaContent ) {
			$this->createPage(
				"Schema:$schemaName",
				[
					'main' => $schemaContent,
				]
			);
		}

		foreach ( $this->subjectPageSource->getSubjectPages() as $subjectPageData ) {
			$this->createPage(
				$subjectPageData->pageName,
				[
					'main' => new WikitextContent( $subjectPageData->wikitext ),
					MediaWikiSubjectRepository::SLOT_NAME => new SubjectContent( $subjectPageData->subjectsJson ),
				]
			);
		}

		foreach ( $this->pageContentSource->getPageContentStrings() as $pageName => $sourceText ) {
			$content = str_starts_with( $pageName, 'Module:' ) ? new ScribuntoContent( $sourceText ) : new WikitextContent( $sourceText );

			$this->createPage(
				$pageName,
				[
					'main' => $content,
				]
			);
		}

		$this->presenter->presentDone();
	}

	/**
	 * @param array<string, Content> $contentBySlot Keys are slot names, values are Content objects
	 */
	private function createPage( string $fullTitle, array $contentBySlot ): void {
		$this->presenter->presentImportStarted( $fullTitle );

		$title = \Title::newFromText( $fullTitle );

		if ( $title === null ) {
			$this->presenter->presentImportFailed( $fullTitle, 'Invalid title' );
			return;
		}

		$updater = $this->wikiPageFactory->newFromTitle( $title )->newPageUpdater( $this->performer );

		foreach ( $contentBySlot as $slotName => $content ) {
			$updater->setContent( $slotName, $content );
		}

		$updater->saveRevision( CommentStoreComment::newUnsavedComment(
			'Importing NeoWiki demo data'
		) );

		if ( $updater->wasSuccessful() ) {
			if ( $updater->wasRevisionCreated() ) {
				$this->presenter->presentCreatedRevision( $fullTitle );
			}
			else {
				$this->presenter->presentNoChanges( $fullTitle );
			}
		}
		else {
			$this->presenter->presentImportFailed(
				pageTitle: $fullTitle,
				errorMessage: $updater->getStatus()?->getWikiText() ?? 'Unknown error'
			);
		}
	}

}
