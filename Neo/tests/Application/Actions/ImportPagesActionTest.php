<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SchemaContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SubjectPageData;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SubjectPageSource;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use WikitextContent;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction
 * @group Database
 */
class ImportPagesActionTest extends \MediaWikiIntegrationTestCase {

	private ImportPresenter $presenter;
	private Authority $performer;
	private WikiPageFactory $wikiPageFactory;
	private SchemaContentSource $schemaContentSource;
	private SubjectPageSource $subjectPageSource;
	private ImportPagesAction $importPagesAction;

	protected function setUp(): void {
		$this->presenter = $this->newPresenterSpy();
		$this->performer = $this->getTestUser()->getUser();
		$this->wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$this->schemaContentSource = $this->createMock( SchemaContentSource::class );
		$this->subjectPageSource = $this->createMock( SubjectPageSource::class );

		$this->importPagesAction = new ImportPagesAction(
			$this->presenter,
			$this->performer,
			$this->wikiPageFactory,
			$this->schemaContentSource,
			$this->subjectPageSource,
		);
	}

	public function testImportAction(): void {
		$mockedSchemas = [
			'Schema1' => new WikitextContent( 'schema1Content' ),
			'Schema2' => new WikitextContent( 'schema2Content' )
		];

		$mockedSubjectPages = [
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
		];

		$this->schemaContentSource->method( 'getSchemas' )->willReturn( $mockedSchemas );
		$this->subjectPageSource->method( 'getSubjectPages' )->willReturn( $mockedSubjectPages );

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
