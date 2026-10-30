<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Closure;
use File;
use ImportStringSource;
use MediaWiki\Content\Content;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphReads;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\ScopedCallback;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Each operation runs in a transaction round, as in a web request, where the hooks it fires run before the
 * round commits.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ProjectPageUpdate
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Jobs\ProjectPageJob
 * @group Database
 */
class ProjectionAfterCommitTest extends NeoWikiIntegrationTestCase {

	/**
	 * Ahead of the clock, so a purge that touches the page at the current time does not move it.
	 */
	private const string SAVE_TIME = '20991231120000';

	private bool $operationCommitted = false;

	/**
	 * @var bool[] Whether the operation had committed, as each projection found it
	 */
	private array $commitsSeen = [];

	protected function tearDown(): void {
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	public function testGraphStoresAreWrittenAfterTheEditCommits(): void {
		$this->registerGraphDatabasePlugins( $this->transactionRecordingStore() );

		$this->inTransactionRound( fn () => $this->editPage( 'Stores written after commit', 'Some text' ) );

		$this->assertSame( [ true ], $this->commitsSeen );
	}

	public function testDeletionWithdrawsThePageAfterItCommits(): void {
		$this->editPage( 'Withdrawn after commit', 'Some text' );
		DeferredUpdates::doUpdates();
		$this->registerGraphDatabasePlugins( $this->transactionRecordingStore() );

		$this->inTransactionRound( fn () => $this->deletePageByName( 'Withdrawn after commit' ) );

		$this->assertSame( [ true ], $this->commitsSeen );
	}

	public function testUndeletionProjectsThePageAfterItCommits(): void {
		$this->editPage( 'Projected after undeletion', 'Some text' );
		$this->deletePageByName( 'Projected after undeletion' );
		DeferredUpdates::doUpdates();
		$this->registerGraphDatabasePlugins( $this->transactionRecordingStore() );

		$this->inTransactionRound( fn () => $this->undeleteArchivedRevisions( 'Projected after undeletion' ) );

		$this->assertSame( [ true ], $this->commitsSeen );
	}

	public function testImportProjectsThePageAfterItCommits(): void {
		$this->editPage( 'Exported for import', 'Some text' );
		$xml = str_replace( 'Exported for import', 'Projected after import', $this->exportPageToXml( 'Exported for import' ) );
		$this->registerGraphDatabasePlugins( $this->transactionRecordingStore() );

		$this->inTransactionRound( fn () => $this->getServiceContainer()->getWikiImporterFactory()
			->getWikiImporter( new ImportStringSource( $xml ), $this->getTestSysop()->getAuthority() )
			->doImport() );

		$this->assertSame( [ true ], $this->commitsSeen );
	}

	/**
	 * A protection writes a revision without MediaWiki's secondary data updates, the path every other new
	 * revision is projected through.
	 */
	public function testProtectionProjectsThePageAfterItCommits(): void {
		$this->editPage( 'Projected after protection', 'Some text' );
		DeferredUpdates::doUpdates();
		$this->registerGraphDatabasePlugins( $this->transactionRecordingStore() );

		$this->inTransactionRound( fn () => $this->protectPageByName( 'Projected after protection' ) );

		$this->assertSame( [ true ], array_unique( $this->commitsSeen ) );
	}

	public function testAProjectionThatFailsAfterTheEditIsRetriedFromTheJobQueue(): void {
		$store = $this->storeFailingItsFirstSave();
		$this->registerGraphDatabasePlugins( $store );

		try {
			$this->editPage( 'Retried from the job queue', 'Some text' );
			DeferredUpdates::doUpdates();
		} catch ( DBError ) {
			// Under PHPUnit, MediaWiki rethrows a failed deferred update once it has queued it as a job.
		}
		$this->runJobs();

		$this->assertNotSame( [], $store->savedPages );
	}

	/**
	 * MediaWiki's own retry of a links refresh skips a page whose links it already refreshed, which the failed
	 * refresh did before the projection ran.
	 */
	public function testAProjectionThatFailsDuringALinksRefreshIsRetriedFromTheJobQueue(): void {
		$this->editPage( 'Template:Refreshed through a failure', '[[Category:Before]]' );
		$pageId = $this->editPage( 'Page refreshed through a failure', '{{Refreshed through a failure}}' )
			->getNewRevision()->getPageId();
		$this->editPage( 'Template:Refreshed through a failure', '[[Category:After]]' );
		DeferredUpdates::doUpdates();
		$store = $this->storeFailingItsFirstSave();
		$this->registerGraphDatabasePlugins( $store );

		$this->runJobs( [ 'ignoreErrorsMatchingFormat' => '%Athe database went away mid-projection%A' ] );

		$this->assertSame( [ $pageId ], self::savedPageIds( $store ) );
	}

	/**
	 * MediaWiki writes the new version's revision of the description page without secondary data updates.
	 */
	public function testUploadingANewVersionOfAFileProjectsItsDescriptionPage(): void {
		$pageId = $this->editPage( 'File:Uploaded again.png', 'Description' )->getNewRevision()->getPageId();
		DeferredUpdates::doUpdates();
		$store = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $store );

		NeoWikiHooks::onFileUpload( $this->newFileOn( 'File:Uploaded again.png' ), reupload: true, hasDescription: true );
		DeferredUpdates::doUpdates();

		$this->assertSame( [ $pageId ], self::savedPageIds( $store ) );
	}

	public function testUploadingAFirstVersionLeavesTheProjectionToTheDescriptionPageSave(): void {
		$this->editPage( 'File:Uploaded once.png', 'Description' );
		DeferredUpdates::doUpdates();
		$store = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $store );

		NeoWikiHooks::onFileUpload( $this->newFileOn( 'File:Uploaded once.png' ), reupload: false, hasDescription: true );
		DeferredUpdates::doUpdates();

		$this->assertSame( [], $store->savedPages );
	}

	public function testPagePropertiesAreBuiltAfterTheEditCommits(): void {
		$this->registerPagePropertyProviders( $this->transactionRecordingProvider() );

		$this->inTransactionRound( fn () => $this->editPage( 'Properties built after commit', 'Some text' ) );

		$this->assertSame( [ true ], $this->commitsSeen );
	}

	public function testCachedRenderingsOfAPageReadingTheGraphExpireOnceItIsProjected(): void {
		ConvertibleTimestamp::setFakeTime( self::SAVE_TIME );
		$this->markEveryParseAsReadingTheGraph();
		$touchedWhileProjecting = [];
		$this->registerGraphDatabasePlugins( new SpyGraphDatabasePlugin(
			whileSavingEachPage: function () use ( &$touchedWhileProjecting ): void {
				$touchedWhileProjecting[] = $this->pageTouched( 'Rendered before its projection' );
			}
		) );

		$this->inTransactionRound( fn () => $this->editPage( 'Rendered before its projection', 'Some text' ) );

		$this->assertSame( [ self::SAVE_TIME ], $touchedWhileProjecting );
		$this->assertGreaterThan( self::SAVE_TIME, $this->pageTouched( 'Rendered before its projection' ) );
	}

	/**
	 * Expiring them would only make the next view parse the page again, and for a cascade-protected page,
	 * queue another refresh.
	 */
	public function testALinksRefreshThatChangesNothingLeavesTheRenderingsOfAPageReadingTheGraphCached(): void {
		ConvertibleTimestamp::setFakeTime( self::SAVE_TIME );
		$this->editPage( 'Refreshed without a change', 'Some text' );
		DeferredUpdates::doUpdates();
		$this->markEveryParseAsReadingTheGraph();

		$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( Title::newFromText( 'Refreshed without a change' ) )
			->doSecondaryDataUpdates( [ 'recursive' => false ] );
		DeferredUpdates::doUpdates();

		$this->assertSame( self::SAVE_TIME, $this->pageTouched( 'Refreshed without a change' ) );
	}

	/**
	 * Without MediaWiki's rendering, nothing tells whether a rendering made before the projection read the graph.
	 */
	public function testRenderingsExpireOnceAProjectionWithoutMediaWikisRenderingRuns(): void {
		ConvertibleTimestamp::setFakeTime( self::SAVE_TIME );
		$this->editPage( 'Rebuilt after its rendering', 'Some text' );
		DeferredUpdates::doUpdates();

		NeoWikiExtension::getInstance()->newPageRebuilder()->rebuild( Title::newFromText( 'Rebuilt after its rendering' ) );
		DeferredUpdates::doUpdates();

		$this->assertGreaterThan( self::SAVE_TIME, $this->pageTouched( 'Rebuilt after its rendering' ) );
	}

	public function testDeletionUnindexesThePageWithoutWaitingForItsProjection(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$revision = $this->createPageWithSubjects( 'Unindexed within its deletion', TestSubject::build() );
		DeferredUpdates::doUpdates();

		$this->withoutDeferredUpdates( fn () => $this->deletePageByName( 'Unindexed within its deletion' ) );

		$this->assertSame( [], $this->subjectIdsIndexedFor( $revision->getPageId() ) );
	}

	public function testEditIndexesItsSubjectsWithoutWaitingForItsProjection(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$revision = $this->withoutDeferredUpdates(
			fn () => $this->createPageWithSubjects( 'Indexed within its edit', TestSubject::build() )
		);

		$this->assertSame( [ TestSubject::ZERO_GUID ], $this->subjectIdsIndexedFor( $revision->getPageId() ) );
	}

	/**
	 * Stands in for the graph queries, which mark the parse as they run. Through a hook that both the legacy
	 * parser and Parsoid, the default from MediaWiki 1.47, go through.
	 */
	private function markEveryParseAsReadingTheGraph(): void {
		$this->setTemporaryHook(
			'ContentAlterParserOutput',
			static function ( Content $content, Title $title, ParserOutput $output ): void {
				GraphReads::record( $output );
			}
		);
	}

	private function newFileOn( string $pageName ): File {
		$file = $this->createStub( File::class );
		$file->method( 'getTitle' )->willReturn( Title::newFromText( $pageName ) );

		return $file;
	}

	private function protectPageByName( string $pageName ): void {
		$cascade = false;

		$this->assertStatusGood(
			$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) )
				->doUpdateRestrictions(
					[ 'edit' => 'sysop' ],
					[],
					$cascade,
					'',
					$this->getTestSysop()->getUser()
				)
		);
	}

	private function pageTouched( string $pageName ): string {
		return (string)Title::newFromText( $pageName )->getTouched( IDBAccessObject::READ_LATEST );
	}

	/**
	 * @return string[]
	 */
	private function subjectIdsIndexedFor( int $pageId ): array {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'nwsp_subject_id' )
			->from( DatabaseSubjectPageIndex::TABLE )
			->where( [ 'nwsp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Runs the operation and drops the updates it deferred, as when the request ends before they run.
	 */
	private function withoutDeferredUpdates( Closure $operation ): mixed {
		$noUpdates = DeferredUpdates::preventOpportunisticUpdates();

		try {
			return $operation();
		} finally {
			DeferredUpdates::clearPendingUpdates();
			ScopedCallback::consume( $noUpdates );
		}
	}

	private function inTransactionRound( Closure $operation ): void {
		$lbFactory = $this->getServiceContainer()->getDBLoadBalancerFactory();
		$lbFactory->beginPrimaryChanges( __METHOD__ );

		try {
			$operation();

			$this->getDb()->onTransactionCommitOrIdle(
				function (): void {
					$this->operationCommitted = true;
				},
				__METHOD__
			);
			$lbFactory->commitPrimaryChanges( __METHOD__ );
		} finally {
			// An open round would fail every later test of the run.
			if ( $lbFactory->hasTransactionRound() ) {
				$lbFactory->rollbackPrimaryChanges( __METHOD__ );
			}
		}

		DeferredUpdates::doUpdates();
	}

	private function transactionRecordingStore(): GraphDatabasePlugin {
		return new SpyGraphDatabasePlugin(
			whileSavingEachPage: $this->recordTransaction( ... ),
			whileDeletingEachPage: $this->recordTransaction( ... )
		);
	}

	private function storeFailingItsFirstSave(): SpyGraphDatabasePlugin {
		$failures = 0;

		return new SpyGraphDatabasePlugin( whileSavingEachPage: static function () use ( &$failures ): void {
			if ( $failures++ === 0 ) {
				throw new DBError( null, 'the database went away mid-projection' );
			}
		} );
	}

	private function recordTransaction(): void {
		$this->commitsSeen[] = $this->operationCommitted;
	}

	private function transactionRecordingProvider(): PagePropertyProvider {
		return new class( $this->recordTransaction( ... ) ) implements PagePropertyProvider {

			public function __construct(
				private readonly Closure $record,
			) {
			}

			public function getProperties( PagePropertyProviderContext $context ): array {
				( $this->record )();
				return [];
			}

		};
	}

}
