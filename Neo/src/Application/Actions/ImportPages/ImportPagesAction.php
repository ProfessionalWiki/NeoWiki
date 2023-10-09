<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use CommentStoreComment;
use Content;
use MediaWiki\Extension\Scribunto\ScribuntoContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\BlocksContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use RuntimeException;
use WikitextContent;

class ImportPagesAction {

	public function __construct(
		private readonly ImportPresenter $presenter,
		private readonly PageContentSaver $pageContentSaver,
		private readonly SchemaContentSource $schemaContentSource,
		private readonly SubjectPageSource $subjectPageSource,
		private readonly PageContentSource $pageContentSource,
		private readonly PageContentSource $moduleContentSource
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

		foreach ( $this->pageContentSource->getPageContentStrings() as $fileName => $sourceText ) {
			$slotName = $this->fileNameToSlotName( $fileName );

			$this->createPage(
				explode( '.', $fileName )[0],
				[
					$slotName => $this->fileNameAndSourceToContent( $fileName, $sourceText ),
				]
			);
		}

		foreach ( $this->moduleContentSource->getPageContentStrings() as $moduleName => $moduleContent ) {
			$this->createPage(
				"Module:$moduleName",
				[
					'main' => $this->fileNameAndSourceToContent( $moduleName, $moduleContent ),
				]
			);
		}

		$this->presenter->presentDone();
	}

	private function fileNameToSlotName( string $fileName ): string {
		return str_ends_with( $fileName, '.blocks.json' ) ? BlocksContent::SLOT_NAME : 'main';
	}

	private function fileNameAndSourceToContent( string $fileName, string $sourceText ): Content {
		if ( str_ends_with( $fileName, '.wikitext' ) ) {
			return new WikitextContent( $sourceText );
		}

		if ( str_ends_with( $fileName, '.blocks.json' ) ) {
			return new BlocksContent( $sourceText );
		}

		if ( str_ends_with( $fileName, '.lua' ) ) {
			return new ScribuntoContent( $sourceText );
		}

		throw new RuntimeException( "Could not import file '$fileName'" );
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

		$savingResult = $this->pageContentSaver->saveContent(
			page: $title,
			contentBySlot: $contentBySlot,
			comment: CommentStoreComment::newUnsavedComment(
				'Importing NeoWiki demo data'
			)
		);

		if ( $savingResult->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentImportFailed(
				pageTitle: $fullTitle,
				errorMessage: $savingResult->errorMessage ?? ''
			);
			return;
		}

		if ( $savingResult->status === PageContentSavingStatus::REVISION_CREATED ) {
			$this->presenter->presentCreatedRevision( $fullTitle );
			return;
		}

		if ( $savingResult->status === PageContentSavingStatus::NO_CHANGES ) {
			$this->presenter->presentNoChanges( $fullTitle );
			return;
		}

		throw new RuntimeException();
	}

}
