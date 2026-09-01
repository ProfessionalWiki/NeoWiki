<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Content\Content;
use MediaWiki\Content\FallbackContent;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSlots;
use MediaWiki\User\UserIdentityValue;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use ProfessionalWiki\NeoWiki\FailureIsolatingPagePropertiesSource;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectPageIndex;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingSubjectPageIndex;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectHeader;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler
 * @group Database
 */
class OnRevisionCreatedHandlerTest extends NeoWikiIntegrationTestCase {

	private const int DELETED_PAGE_ID = 42;

	private SpyGraphDatabasePlugin $graphStore;
	private SpySubjectPageIndex $subjectPageIndex;
	private TestLogger $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->graphStore = new SpyGraphDatabasePlugin();
		$this->subjectPageIndex = new SpySubjectPageIndex();
		$this->logger = new TestLogger();
	}

	public function testSavesPageWithSubjects(): void {
		$revision = $this->createPageWithSubjects( 'Page with subject', TestSubject::build() );

		$outcome = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertCount( 1, $this->graphStore->savedPages );
	}

	public function testIndexesTheSubjectsThePageHolds(): void {
		$revision = $this->createPageWithSubjects(
			'Page with indexed subject',
			TestSubject::build( label: 'Indexed subject' )
		);

		$this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertEquals(
			[
				$revision->getPageId() => [
					new SubjectHeader(
						id: TestSubject::ZERO_GUID,
						schemaName: TestSubject::DEFAULT_SCHEMA_ID,
						label: 'Indexed subject',
						isMainSubject: true
					),
				],
			],
			$this->subjectPageIndex->indexedSubjectsByPageId
		);
	}

	public function testSavesPageWithoutSubjects(): void {
		$revision = $this->newPlainPageRevision( 'Plain page' );

		$outcome = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertCount( 1, $this->graphStore->savedPages );
		$this->assertFalse( $this->graphStore->savedPages[0]->getSubjects()->hasSubjects() );
	}

	public function testSavesPageWhoseSubjectsWereAllDeleted(): void {
		$revision = $this->createPageWithSubjects( 'Page that lost its subjects' );

		$outcome = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertCount( 1, $this->graphStore->savedPages );
		$this->assertSame( [], $this->graphStore->deletedPageIds );
	}

	public function testWritesNothingWhenThePagePropertiesCannotBeBuilt(): void {
		// Building the properties parses the page and runs every provider, which throws for a page
		// MediaWiki can no longer handle or a provider that fails. On the hook path that must not abort
		// the triggering edit, which is what the isolating source production wires there is for.
		$revision = $this->newPlainPageRevision( 'Page with unbuildable properties' );

		$outcome = $this->newHandlerWith(
			$this->graphStore,
			$this->newFailingProviderRegistry(),
			isolatePageProperties: true
		)->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertSame( PageRefreshOutcome::SkippedUnreadablePageProperties, $outcome );
		$this->assertSame( [], $this->graphStore->savedPages );
		$this->assertTrue( $this->logger->hasErrorRecords(), 'the skipped page should be logged' );
	}

	/**
	 * The rebuild path is given the undecorated source, so a page whose properties cannot be built
	 * surfaces to RebuildGraphDatabases, which reports it against the page with its cause, rather than
	 * as a skip the operator cannot act on.
	 */
	public function testPagePropertyFailurePropagatesWithoutTheIsolatingSource(): void {
		$revision = $this->newPlainPageRevision( 'Page with unbuildable properties' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'unknown content model' );

		$this->newHandlerWith( $this->graphStore, $this->newFailingProviderRegistry() )
			->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );
	}

	public function testWritesNothingWhenTheSubjectSlotDoesNotHoldSubjectContent(): void {
		// An unregistered content model hands back FallbackContent rather than SubjectContent. Projecting
		// the page as holding no Subjects would wipe the ones it does hold, so nothing is written.
		$revision = $this->newRevisionWithSlotContent( new FallbackContent( '{"subjects":{}}', 'unregistered-model' ) );

		$outcome = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertSame( PageRefreshOutcome::SkippedUnreadableSubjects, $outcome );
		$this->assertSame( [], $this->graphStore->savedPages );
		$this->assertSame( [], $this->graphStore->deletedPageIds );
		$this->assertSame( [], $this->subjectPageIndex->indexedSubjectsByPageId, 'the index should be left alone' );
		$this->assertTrue( $this->logger->hasWarningRecords(), 'the skipped page should be logged' );
	}

	public function testPropagatesReadFailureWhenSubjectSlotIsPresentButUnreadable(): void {
		$slots = $this->createStub( RevisionSlots::class );
		$slots->method( 'getContent' )->willThrowException( new RevisionAccessException( 'blob read failed' ) );

		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getPageId' )->willReturn( 42 );
		$revision->method( 'hasSlot' )->willReturn( true );
		$revision->method( 'getSlots' )->willReturn( $slots );

		$this->expectException( RevisionAccessException::class );

		$this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );
	}

	public function testUnreachableBackendDoesNotHardFailRevisionHandling(): void {
		$revision = $this->createPageWithSubjects( 'Page with a failing backend', TestSubject::build() );

		// The isolating decorator is what production wires around each backend on the hook path; here it
		// wraps a backend that is down.
		$handler = $this->newHandlerWith(
			new FailureIsolatingGraphDatabasePlugin( new ThrowingGraphDatabasePlugin(), new NullLogger() )
		);

		$outcome = $handler->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertSame(
			PageRefreshOutcome::Refreshed,
			$outcome,
			'a projection write to an unreachable backend must not propagate out of the handler'
		);
	}

	public function testDeletingAPageRemovesItFromTheGraph(): void {
		$this->newHandler()->onPageDelete( self::DELETED_PAGE_ID );

		$this->assertEquals( [ new PageId( self::DELETED_PAGE_ID ) ], $this->graphStore->deletedPageIds );
	}

	/**
	 * Only the index removal can propagate — the projection write is failure-isolated — so the graph has
	 * to be deleted first. The other order lets a database fault in the index skip the graph deletion,
	 * leaving the deleted page's Subject values queryable through the Cypher surfaces.
	 */
	public function testGraphDeletionHappensEvenWhenTheIndexRemovalFails(): void {
		try {
			$this->newHandlerWithFailingIndex()->onPageDelete( self::DELETED_PAGE_ID );
		} catch ( RuntimeException ) {
			// Propagating is the index's contract; what matters is what already ran.
		}

		$this->assertEquals( [ new PageId( self::DELETED_PAGE_ID ) ], $this->graphStore->deletedPageIds );
	}

	private function newFailingProviderRegistry(): PagePropertyProviderRegistry {
		$registry = new PagePropertyProviderRegistry();
		$registry->addProvider( new class implements PagePropertyProvider {
			public function getProperties( PagePropertyProviderContext $context ): array {
				throw new RuntimeException( 'unknown content model' );
			}
		} );

		return $registry;
	}

	private function newRevisionWithSlotContent( Content $content ): RevisionRecord {
		$slots = $this->createStub( RevisionSlots::class );
		$slots->method( 'getContent' )->willReturn( $content );

		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getPageId' )->willReturn( 42 );
		$revision->method( 'hasSlot' )->willReturn( true );
		$revision->method( 'getSlots' )->willReturn( $slots );

		return $revision;
	}

	private function newPlainPageRevision( string $pageName ): RevisionRecord {
		$pageId = $this->insertPage( $pageName, 'Just wikitext, no subjects.' )['id'];
		$revision = $this->getServiceContainer()->getRevisionStore()->getRevisionByPageId( $pageId );
		$this->assertInstanceOf( RevisionRecord::class, $revision );

		return $revision;
	}

	private function newHandler(): OnRevisionCreatedHandler {
		return $this->newHandlerWith( $this->graphStore );
	}

	private function newHandlerWith(
		GraphDatabasePlugin $graphStore,
		?PagePropertyProviderRegistry $providerRegistry = null,
		bool $isolatePageProperties = false
	): OnRevisionCreatedHandler {
		$pageProperties = $this->newPagePropertiesBuilder( $providerRegistry ?? new PagePropertyProviderRegistry() );

		return new OnRevisionCreatedHandler(
			$graphStore,
			$this->subjectPageIndex,
			$isolatePageProperties
				? new FailureIsolatingPagePropertiesSource( $pageProperties, $this->logger )
				: $pageProperties,
			$this->logger
		);
	}

	private function newHandlerWithFailingIndex(): OnRevisionCreatedHandler {
		return new OnRevisionCreatedHandler(
			$this->graphStore,
			new ThrowingSubjectPageIndex(),
			$this->newPagePropertiesBuilder( new PagePropertyProviderRegistry() ),
			$this->logger
		);
	}

	private function newPagePropertiesBuilder( PagePropertyProviderRegistry $providerRegistry ): PagePropertiesBuilder {
		$services = $this->getServiceContainer();

		return new PagePropertiesBuilder(
			revisionStore: $services->getRevisionStore(),
			contentHandlerFactory: $services->getContentHandlerFactory(),
			titleFormatter: $services->getTitleFormatter(),
			providerRegistry: $providerRegistry,
		);
	}

}
