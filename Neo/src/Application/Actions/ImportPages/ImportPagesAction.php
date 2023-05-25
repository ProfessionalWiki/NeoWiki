<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use CommentStoreComment;
use Content;
use FileFetcher\FileFetcher;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use WikitextContent;

class ImportPagesAction {

	public function __construct(
		private readonly ImportPresenter $presenter,
		private readonly Authority $performer,
		private readonly FileFetcher $fileFetcher,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly PageContentFetcher $pageContentFetcher,
	) {
	}

	public function import(): void {
		foreach ( [ 'Company', 'Employee', 'Product' ] as $schemaName ) {
			$this->createPage(
				"Schema:$schemaName",
				[
					'main' => new SchemaContent(
						$this->getFileContent( "/DemoData/Schema/$schemaName.json" )
					),
				]
			);
		}

		foreach ( [ 'NeoWiki', 'ProWiki', 'Professional_Wiki' ] as $pageName ) {
			$this->createPage(
				$pageName,
				[
					'main' => $this->getOrDefaultMainWikitextContent( $pageName, '{{#infobox:}}' ),
					MediaWikiSubjectRepository::SLOT_NAME => new SubjectContent(
						$this->getFileContent( "/DemoData/Subject/$pageName.json" )
					),
				]
			);
		}

		$this->presenter->presentDone();
	}

	private function getOrDefaultMainWikitextContent( string $pageName, string $default ): Content {
		return $this->pageContentFetcher->getPageContent( $pageName, $this->performer ) ?? new WikitextContent( $default );
	}

	private function getFileContent( string $fileName ): string {
		return $this->fileFetcher->fetchFile(
			NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . $fileName
		);
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
