<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\LayoutContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\MappingContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\PageContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SchemaContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SubjectPageSource;
use ProfessionalWiki\NeoWiki\Persistence\ImportedPageTitlesLookup;
use ProfessionalWiki\NeoWiki\Persistence\PageDeleter;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ImportPresenterSpy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\PageContentSaverStub;

/**
 * Covers the title and content model each demo data source directory imports into. The saver is a
 * stub, so what the action derives from the file names can be checked without touching the database.
 *
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction
 */
class ImportPagesNamespaceTest extends MediaWikiIntegrationTestCase {

	private PageContentSaverStub $saver;

	public function testPageFilesImportIntoTheMainNamespace(): void {
		$presenter = $this->runImport( pageFiles: [ 'Museum collection.wikitext' => 'hubs' ] );

		$this->assertSame( [ 'Museum collection' ], $presenter->created );
	}

	public function testModuleFilesImportIntoTheModuleNamespace(): void {
		$presenter = $this->runImport( moduleFiles: [ 'SubjectRow.lua' => 'return {}' ] );

		$this->assertSame( [ 'Module:SubjectRow' ], $presenter->created );
	}

	public function testMediaWikiFilesImportIntoTheMediaWikiNamespace(): void {
		$presenter = $this->runImport( mediaWikiFiles: [ 'Sidebar.wikitext' => '* Demo tour' ] );

		$this->assertSame( [ 'MediaWiki:Sidebar' ], $presenter->created );
	}

	public function testCssFilesKeepTheirExtensionInTheTitle(): void {
		$presenter = $this->runImport( mediaWikiFiles: [ 'Vector-2022.css' => '.foo { display: none; }' ] );

		$this->assertSame( [ 'MediaWiki:Vector-2022.css' ], $presenter->created );
	}

	public function testCssFilesImportAsSiteCss(): void {
		$this->runImport( mediaWikiFiles: [ 'Vector-2022.css' => '.foo { display: none; }' ] );

		$this->assertSame(
			CONTENT_MODEL_CSS,
			$this->saver->savedContent['Vector-2022.css']['main']->getModel()
		);
	}

	/**
	 * @param array<string, string> $pageFiles Source files (name => content) per source directory.
	 * @param array<string, string> $moduleFiles
	 * @param array<string, string> $mediaWikiFiles
	 */
	private function runImport(
		array $pageFiles = [],
		array $moduleFiles = [],
		array $mediaWikiFiles = [],
	): ImportPresenterSpy {
		$presenter = new ImportPresenterSpy();
		$this->saver = new PageContentSaverStub(
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( Authority::class )
		);

		( new ImportPagesAction(
			presenter: $presenter,
			pageContentSaver: $this->saver,
			importedPageTitlesLookup: $this->createMock( ImportedPageTitlesLookup::class ),
			pageDeleter: $this->createMock( PageDeleter::class ),
			schemaContentSource: $this->createMock( SchemaContentSource::class ),
			subjectPageSource: $this->createMock( SubjectPageSource::class ),
			pageContentSource: $this->newPageContentSource( $pageFiles ),
			moduleContentSource: $this->newPageContentSource( $moduleFiles ),
			mediaWikiContentSource: $this->newPageContentSource( $mediaWikiFiles ),
			layoutContentSource: $this->createMock( LayoutContentSource::class ),
			mappingContentSource: $this->createMock( MappingContentSource::class ),
		) )->import();

		return $presenter;
	}

	/**
	 * @param array<string, string> $files
	 */
	private function newPageContentSource( array $files ): PageContentSource {
		$source = $this->createMock( PageContentSource::class );
		$source->method( 'getPageContentStrings' )->willReturn( $files );

		return $source;
	}

}
