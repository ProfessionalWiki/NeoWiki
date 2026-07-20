<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\LayoutContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\MappingContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\PageContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SchemaContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SubjectPageSource;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\ImportedPageTitlesLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Persistence\PageDeleter;
use ProfessionalWiki\NeoWiki\Persistence\PageDeletionStatus;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ImportPresenterSpy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\PageDeleterSpy;

/**
 * Covers how the import prunes pages it previously created once their source files are gone. The
 * managed-set discovery itself lives in FirstRevisionAuthorPageTitlesLookupTest; here that lookup is
 * a fake so the deletion decision can be tested against controlled managed and current sets.
 *
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction
 * @group Database
 */
class ImportPagesDeletionTest extends MediaWikiIntegrationTestCase {

	public function testPageNoLongerInTheSourceIsDeleted(): void {
		$deleter = $this->newPageDeleterSpy();

		$this->runImport(
			importedTitles: [ Title::newFromText( 'Kept' ), Title::newFromText( 'Removed' ) ],
			currentPageFiles: [ 'Kept.wikitext' => 'still here' ],
			deleter: $deleter,
		);

		$this->assertSame( [ 'Removed' ], $deleter->deletedKeys );
	}

	public function testDeletionIsReportedToThePresenter(): void {
		$presenter = $this->newPresenterSpy();

		$this->runImport(
			importedTitles: [ Title::newFromText( 'Removed' ) ],
			currentPageFiles: [],
			presenter: $presenter,
		);

		$this->assertSame( [ 'Removed' ], $presenter->deletionsStarted );
		$this->assertSame( [ 'Removed' ], $presenter->deleted );
	}

	public function testUnchangedSourceSetDeletesNothing(): void {
		$deleter = $this->newPageDeleterSpy();

		$this->runImport(
			importedTitles: [ Title::newFromText( 'First' ), Title::newFromText( 'Second' ) ],
			currentPageFiles: [ 'First.wikitext' => 'a', 'Second.wikitext' => 'b' ],
			deleter: $deleter,
		);

		$this->assertSame( [], $deleter->deletedKeys );
	}

	public function testPageWhoseImportFailedThisRunIsNotDeleted(): void {
		$deleter = $this->newPageDeleterSpy();
		$presenter = $this->newPresenterSpy();

		$this->runImport(
			importedTitles: [ Title::newFromText( 'Flaky' ) ],
			currentPageFiles: [ 'Flaky.wikitext' => 'content that fails to save' ],
			deleter: $deleter,
			presenter: $presenter,
			titlesThatFailToSave: [ 'Flaky' ],
		);

		$this->assertSame( [ 'Flaky' ], $presenter->importFailures, 'the save should have failed' );
		$this->assertSame( [], $deleter->deletedKeys, 'a failed save must not cascade into a deletion' );
	}

	public function testDeletionFailureIsReported(): void {
		$presenter = $this->newPresenterSpy();

		$this->runImport(
			importedTitles: [ Title::newFromText( 'Removed' ) ],
			currentPageFiles: [],
			deleter: $this->newFailingPageDeleter( 'the page could not be deleted' ),
			presenter: $presenter,
		);

		$this->assertSame( [ 'Removed' ], $presenter->deletionFailures );
		$this->assertSame( [], $presenter->deleted );
	}

	/**
	 * @param Title[] $importedTitles The managed set the lookup reports.
	 * @param array<string, string> $currentPageFiles Page source files (name => content) for this run.
	 * @param string[] $titlesThatFailToSave DB keys whose save the saver should reject.
	 */
	private function runImport(
		array $importedTitles,
		array $currentPageFiles,
		?PageDeleter $deleter = null,
		?ImportPresenter $presenter = null,
		array $titlesThatFailToSave = [],
	): void {
		$pageContentSource = $this->createMock( PageContentSource::class );
		$pageContentSource->method( 'getPageContentStrings' )->willReturn( $currentPageFiles );

		( new ImportPagesAction(
			presenter: $presenter ?? $this->newPresenterSpy(),
			pageContentSaver: $this->newPageContentSaver( ...$titlesThatFailToSave ),
			importedPageTitlesLookup: $this->newImportedPageTitlesLookup( ...$importedTitles ),
			pageDeleter: $deleter ?? $this->newPageDeleterSpy(),
			schemaContentSource: $this->createMock( SchemaContentSource::class ),
			subjectPageSource: $this->createMock( SubjectPageSource::class ),
			pageContentSource: $pageContentSource,
			moduleContentSource: $this->createMock( PageContentSource::class ),
			layoutContentSource: $this->createMock( LayoutContentSource::class ),
			mappingContentSource: $this->createMock( MappingContentSource::class ),
		) )->import();
	}

	private function newImportedPageTitlesLookup( Title ...$titles ): ImportedPageTitlesLookup {
		return new class( $titles ) implements ImportedPageTitlesLookup {

			/**
			 * @param Title[] $titles
			 */
			public function __construct(
				private readonly array $titles
			) {
			}

			public function getImportedPageTitles(): array {
				return $this->titles;
			}

		};
	}

	/**
	 * A saver that persists nothing and simply reports the outcome the test asked for, so the action's
	 * deletion decision can be exercised without touching the database.
	 */
	private function newPageContentSaver( string ...$failingKeys ): PageContentSaver {
		return new class(
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( Authority::class ),
			$failingKeys
		) extends PageContentSaver {

			/**
			 * @param string[] $failingKeys
			 */
			public function __construct(
				WikiPageFactory $wikiPageFactory,
				Authority $performer,
				private readonly array $failingKeys
			) {
				parent::__construct( $wikiPageFactory, $performer );
			}

			public function saveContent( PageIdentity|PageId $page, array $contentBySlot, CommentStoreComment $comment ): PageContentSavingStatus {
				if ( $page instanceof PageIdentity && in_array( $page->getDBkey(), $this->failingKeys, true ) ) {
					return new PageContentSavingStatus( PageContentSavingStatus::ERROR, 'forced failure' );
				}

				return new PageContentSavingStatus( PageContentSavingStatus::REVISION_CREATED );
			}

		};
	}

	private function newPageDeleterSpy(): PageDeleterSpy {
		return new PageDeleterSpy();
	}

	private function newFailingPageDeleter( string $errorMessage ): PageDeleter {
		return new class( $errorMessage ) implements PageDeleter {

			public function __construct(
				private readonly string $errorMessage
			) {
			}

			public function deletePage( ProperPageIdentity $page, string $reason ): PageDeletionStatus {
				return new PageDeletionStatus( false, $this->errorMessage );
			}

		};
	}

	private function newPresenterSpy(): ImportPresenterSpy {
		return new ImportPresenterSpy();
	}

}
