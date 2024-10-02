<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\Application\Actions;

use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\ImportPagesAction;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\ImportPresenter;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\PageContentSource;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\SchemaContentSource;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\SubjectPageData;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\SubjectPageSource;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\PageContentSaver;
use WikitextContent;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\ImportPagesAction
 * @group Database
 */
class ImportPagesActionTest extends \MediaWikiIntegrationTestCase {

	private ImportPresenter $presenter;
	private SchemaContentSource $schemaContentSource;
	private SubjectPageSource $subjectPageSource;
	private PageContentSource $pageContentSource;
	private PageContentSource $moduleContentSource;
	private ImportPagesAction $importPagesAction;

	protected function setUp(): void {
		$this->presenter = $this->newPresenterSpy();
		$this->schemaContentSource = $this->createMock( SchemaContentSource::class );
		$this->subjectPageSource = $this->createMock( SubjectPageSource::class );
		$this->pageContentSource = $this->createMock( PageContentSource::class );
		$this->moduleContentSource = $this->createMock( PageContentSource::class );

		$this->importPagesAction = new ImportPagesAction(
			$this->presenter,
			new PageContentSaver(
				MediaWikiServices::getInstance()->getWikiPageFactory(),
				$this->getTestUser()->getUser(),
			),
			$this->schemaContentSource,
			$this->subjectPageSource,
			$this->pageContentSource,
			$this->moduleContentSource
		);
	}

	public function testImportAction(): void {
		$this->schemaContentSource->method( 'getSchemas' )->willReturn(
			[
				'Schema1' => new WikitextContent( 'schema1Content' ),
				'Schema2' => new WikitextContent( 'schema2Content' )
			]
		);
		$this->subjectPageSource->method( 'getSubjectPages' )->willReturn(
			[
				new SubjectPageData(
					'Page1',
					'Page1Content',
					'[]'
				),
				new SubjectPageData(
					'Page2',
					'Page2Content',
					'[]'
				)
			]
		);

		$this->pageContentSource->method( 'getPageContentStrings' )->willReturn(
			[
				'PageOne.wikitext' => 'Whatever wikitext',
			]
		);

		$this->moduleContentSource->method( 'getPageContentStrings' )->willReturn(
			[
				'ModuleOne.lua' => 'return {}'
			]
		);

		$this->markTestSkipped( 'TODO: Fix test isolation issue' );
		$this->importPagesAction->import();

		$this->assertSame(
			[
				'Importing Schema:Schema1...',
				'Created revision for Schema:Schema1',
				'Importing Schema:Schema2...',
				'Created revision for Schema:Schema2',
				'Importing Page1...',
				'Created revision for Page1',
				'Importing Page2...',
				'Created revision for Page2',
				'Importing PageOne...',
				'Created revision for PageOne',
				'Importing Module:ModuleOne.lua...',
				'Created revision for Module:ModuleOne.lua',
				'Done'
			],
			$this->presenter->getMessages()
		);
	}

	private function newPresenterSpy(): object {
		return new class () implements ImportPresenter {

			private array $messages = [];

			public function presentDone(): void {
				$this->messages[] = 'Done';
			}

			public function presentImportStarted( string $pageTitle ): void {
				$this->messages[] = "Importing $pageTitle...";
			}

			public function presentCreatedRevision( string $pageTitle ): void {
				$this->messages[] = "Created revision for $pageTitle";
			}

			public function presentNoChanges( string $pageTitle ): void {
				$this->messages[] = "No changes for $pageTitle";
			}

			public function presentImportFailed( string $pageTitle, string $errorMessage ): void {
				$this->messages[] = "Import failed for $pageTitle: $errorMessage";
			}

			/**
			 * @return string[]
			 */
			public function getMessages(): array {
				return $this->messages;
			}

		};
	}

}
