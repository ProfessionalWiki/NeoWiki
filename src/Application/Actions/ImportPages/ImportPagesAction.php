<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\ImportedPageTitlesLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\PageDeleter;
use RuntimeException;

class ImportPagesAction {

	private const string DELETION_REASON = 'No longer part of the NeoWiki demo data';

	/**
	 * @var array<string, true> Prefixed DB keys of the pages this run imported, whether or not the
	 *   save succeeded. Everything the import owns that is not in this set is a page whose source
	 *   file is gone, and so gets deleted.
	 */
	private array $currentTitleKeys = [];

	public function __construct(
		private readonly ImportPresenter $presenter,
		private readonly PageContentSaver $pageContentSaver,
		private readonly ImportedPageTitlesLookup $importedPageTitlesLookup,
		private readonly PageDeleter $pageDeleter,
		private readonly SchemaContentSource $schemaContentSource,
		private readonly SubjectPageSource $subjectPageSource,
		private readonly PageContentSource $pageContentSource,
		private readonly PageContentSource $moduleContentSource,
		private readonly LayoutContentSource $layoutContentSource,
		private readonly MappingContentSource $mappingContentSource,
	) {
	}

	public function import(): void {
		$this->currentTitleKeys = [];

		foreach ( $this->schemaContentSource->getSchemas() as $schemaName => $schemaContent ) {
			$this->createPage(
				"Schema:$schemaName",
				[
					'main' => $schemaContent,
				]
			);
		}

		foreach ( $this->layoutContentSource->getLayouts() as $layoutName => $layoutContent ) {
			$this->createPage(
				"Layout:$layoutName",
				[
					'main' => $layoutContent,
				]
			);
		}

		foreach ( $this->mappingContentSource->getMappings() as $mappingName => $mappingContent ) {
			$this->createPage(
				"Mapping:$mappingName",
				[
					'main' => $mappingContent,
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
			$this->createPage(
				self::stripFileExtension( $fileName ),
				[
					'main' => $this->fileNameAndSourceToContent( $fileName, $sourceText ),
				]
			);
		}

		foreach ( $this->moduleContentSource->getPageContentStrings() as $moduleName => $moduleContent ) {
			$this->createPage(
				'Module:' . self::stripFileExtension( $moduleName ),
				[
					'main' => $this->fileNameAndSourceToContent( $moduleName, $moduleContent ),
				]
			);
		}

		$this->deleteRemovedPages();

		$this->presenter->presentDone();
	}

	private static function stripFileExtension( string $fileName ): string {
		return preg_replace( '/\.(wikitext|lua)$/', '', $fileName ) ?? $fileName;
	}

	private function fileNameAndSourceToContent( string $fileName, string $sourceText ): Content {
		if ( str_ends_with( $fileName, '.wikitext' ) ) {
			return new WikitextContent( $sourceText );
		}

		if ( str_ends_with( $fileName, '.lua' ) ) {
			return new TextContent( $sourceText, 'Scribunto' );
		}

		throw new RuntimeException( "Could not import file '$fileName'" );
	}

	/**
	 * @param array<string, Content> $contentBySlot Keys are slot names, values are Content objects
	 */
	private function createPage( string $fullTitle, array $contentBySlot ): void {
		$this->presenter->presentImportStarted( $fullTitle );

		$title = Title::newFromText( $fullTitle );

		if ( $title === null ) {
			$this->presenter->presentImportFailed( $fullTitle, 'Invalid title' );
			return;
		}

		$this->currentTitleKeys[$title->getPrefixedDBkey()] = true;

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

	private function deleteRemovedPages(): void {
		foreach ( $this->importedPageTitlesLookup->getImportedPageTitles() as $title ) {
			if ( !isset( $this->currentTitleKeys[$title->getPrefixedDBkey()] ) ) {
				$this->deletePage( $title );
			}
		}
	}

	private function deletePage( Title $title ): void {
		$prefixedTitle = $title->getPrefixedText();

		$this->presenter->presentDeletionStarted( $prefixedTitle );

		$status = $this->pageDeleter->deletePage( $title->toPageIdentity(), self::DELETION_REASON );

		if ( $status->succeeded ) {
			$this->presenter->presentDeleted( $prefixedTitle );
			return;
		}

		$this->presenter->presentDeletionFailed( $prefixedTitle, $status->errorMessage ?? '' );
	}

}
