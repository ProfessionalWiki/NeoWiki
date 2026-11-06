<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Content\FallbackContent;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\NullRevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphStoreProjector;
use ProfessionalWiki\NeoWiki\FailureIsolatingPagePropertiesSource;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Persistence\CorePagePropertyProvider;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\GraphStoreProjector
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectsToPublish
 * @group Database
 */
class GraphStoreProjectorTest extends NeoWikiIntegrationTestCase {

	private const int DELETED_PAGE_ID = 42;
	private const int STUB_PAGE_ID = 43;
	private const string APPROVED_PAGE = 'Page with an approved revision';

	private SpyGraphDatabasePlugin $graphStore;
	private TestLogger $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->graphStore = new SpyGraphDatabasePlugin();
		$this->logger = new TestLogger();
	}

	public function testSavesPageWithSubjects(): void {
		$revision = $this->createPageWithSubjects( 'Page with subject', TestSubject::build() );

		$outcome = $this->newProjector()->projectRevision( $revision, null );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertCount( 1, $this->graphStore->savedPages );
	}

	public function testSavesPageWithoutSubjects(): void {
		$revision = $this->newPlainPageRevision( 'Plain page' );

		$outcome = $this->newProjector()->projectRevision( $revision, null );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertCount( 1, $this->graphStore->savedPages );
		$this->assertFalse( $this->graphStore->savedPages[0]->getSubjects()->hasSubjects() );
	}

	public function testSavesPageWhoseSubjectsWereAllDeleted(): void {
		$revision = $this->createPageWithSubjects( 'Page that lost its subjects' );

		$outcome = $this->newProjector()->projectRevision( $revision, null );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertCount( 1, $this->graphStore->savedPages );
		$this->assertSame( [], $this->graphStore->deletedPageIds );
	}

	public function testSkipsThePageWhenItsPagePropertiesCannotBeBuilt(): void {
		// Building the properties parses the page and runs every provider, which throws for a page
		// MediaWiki can no longer handle or a provider that fails. On the hook path that must not fail the
		// projection, which is what the isolating source production wires there is for.
		$revision = $this->newPlainPageRevision( 'Page with unbuildable properties' );

		$outcome = $this->newProjectorWith(
			$this->graphStore,
			$this->newFailingProviderRegistry(),
			isolatePageProperties: true
		)->projectRevision( $revision, null );

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

		$this->newProjectorWith( $this->graphStore, $this->newFailingProviderRegistry() )
			->projectRevision( $revision, null );
	}

	public function testWritesNothingWhenTheSubjectSlotDoesNotHoldSubjectContent(): void {
		// An unregistered content model hands back FallbackContent rather than SubjectContent. Projecting
		// the page as holding no Subjects would wipe the ones it does hold, so nothing is written.
		$revision = $this->newRevisionWithSlotContent(
			self::STUB_PAGE_ID,
			new FallbackContent( '{"subjects":{}}', 'unregistered-model' )
		);

		$outcome = $this->newProjector()->projectRevision( $revision, null );

		$this->assertSame( PageRefreshOutcome::SkippedUnreadableSubjects, $outcome );
		$this->assertSame( [], $this->graphStore->savedPages );
		$this->assertSame( [], $this->graphStore->deletedPageIds );
		$this->assertTrue( $this->logger->hasWarningRecords(), 'the skipped page should be logged' );
	}

	public function testUnreachableBackendDoesNotFailTheProjection(): void {
		$revision = $this->createPageWithSubjects( 'Page with a failing backend', TestSubject::build() );

		// The isolating decorator is what production wires around each backend on the hook path; here it
		// wraps a backend that is down.
		$projector = $this->newProjectorWith(
			new FailureIsolatingGraphDatabasePlugin( new ThrowingGraphDatabasePlugin(), new NullLogger() )
		);

		$outcome = $projector->projectRevision( $revision, null );

		$this->assertSame(
			PageRefreshOutcome::Refreshed,
			$outcome,
			'a projection write to an unreachable backend must not propagate out of the projector'
		);
	}

	public function testWithdrawsAPage(): void {
		$this->newProjector()->withdrawPage( new PageId( self::DELETED_PAGE_ID ) );

		$this->assertEquals( [ new PageId( self::DELETED_PAGE_ID ) ], $this->graphStore->deletedPageIds );
	}

	public function testProjectsTheSubjectsOfTheRevisionThePolicyPublishes(): void {
		$approved = $this->createPageWithSubjects( self::APPROVED_PAGE, TestSubject::build() );
		$draft = $this->createPageWithSubjects( self::APPROVED_PAGE );

		$outcome = $this->newProjectorWithPolicy( FixedRevisionPolicy::publishing( $approved ) )
			->projectRevision( $draft, null );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertTrue(
			$this->graphStore->savedPages[0]->getSubjects()->hasSubjects(),
			'the approved revision holds a Subject the draft removed'
		);
	}

	public function testProjectsThePagePropertiesOfTheRevisionThePolicyPublishes(): void {
		$approved = $this->createPageWithSubjects( self::APPROVED_PAGE, TestSubject::build() );
		$draft = $this->editPage(
			self::APPROVED_PAGE,
			'A draft nobody approved',
			'',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)->getNewRevision();

		$this->newProjectorWith(
			$this->graphStore,
			$this->newCorePropertyRegistry(),
			revisionPolicy: FixedRevisionPolicy::publishing( $approved )
		)->projectRevision( $draft, null );

		$this->assertSame(
			$this->getTestSysop()->getUser()->getName(),
			$this->graphStore->savedPages[0]->getProperties()->get( 'lastEditor' ),
			'the properties describe the approved revision, whose author is not the drafter'
		);
	}

	public function testBuildsThePagePropertiesFromMediaWikisRenderingOfThePublishedRevision(): void {
		$revision = $this->editPage( 'Page rendered by MediaWiki', '[[Category:From the content]]' )->getNewRevision();

		$this->newProjectorWith( $this->graphStore, $this->newCorePropertyRegistry() )
			->projectRevision( $revision, $this->newRenderingOf( $revision, 'From_the_rendering' ) );

		$this->assertSame(
			[ 'From_the_rendering' ],
			$this->graphStore->savedPages[0]->getProperties()->get( 'categories' ),
			'the rendering stands for a parse the projection does not repeat'
		);
	}

	public function testParsesThePublishedRevisionWhenMediaWikiRenderedAnotherOne(): void {
		$approved = $this->editPage( self::APPROVED_PAGE, '[[Category:Approved]]' )->getNewRevision();
		$draft = $this->editPage( self::APPROVED_PAGE, '[[Category:Draft]]' )->getNewRevision();

		$this->newProjectorWith(
			$this->graphStore,
			$this->newCorePropertyRegistry(),
			revisionPolicy: FixedRevisionPolicy::publishing( $approved )
		)->projectRevision( $draft, $this->newRenderingOf( $draft, 'Draft' ) );

		$this->assertSame( [ 'Approved' ], $this->graphStore->savedPages[0]->getProperties()->get( 'categories' ) );
	}

	public function testWithdrawsThePageWhenThePolicyPublishesNothing(): void {
		$revision = $this->createPageWithSubjects( 'Page whose approval was revoked', TestSubject::build() );

		$outcome = $this->newProjectorWithPolicy( FixedRevisionPolicy::publishingNothing() )
			->projectRevision( $revision, null );

		$this->assertSame( PageRefreshOutcome::Unpublished, $outcome );
		$this->assertEquals( [ new PageId( $revision->getPageId() ) ], $this->graphStore->deletedPageIds );
		$this->assertSame( [], $this->graphStore->savedPages );
	}

	/**
	 * @dataProvider subjectJsonThatDoesNotDeserializeProvider
	 */
	public function testLeavesThePageAsItWasWhenItsPublishedSubjectsDoNotDeserialize( string $subjectJson ): void {
		$revision = $this->newRevisionWithSlotContent( self::STUB_PAGE_ID, new SubjectContent( $subjectJson ) );

		$outcome = $this->newProjector()->projectRevision( $revision, null );

		$this->assertSame( PageRefreshOutcome::SkippedUnreadableSubjects, $outcome );
		$this->assertSame( [], $this->graphStore->savedPages );
		$this->assertSame( [], $this->graphStore->deletedPageIds );
		$this->assertTrue( $this->logger->hasWarningRecords(), 'the skipped page should be logged' );
	}

	public static function subjectJsonThatDoesNotDeserializeProvider(): iterable {
		yield 'a Subject that fails validation' => [ TestSubject::jsonThatDoesNotDeserialize( TestSubject::ZERO_GUID ) ];
		yield 'a Subject that is not an object' => [
			'{"mainSubject":"' . TestSubject::ZERO_GUID . '","subjects":{"' . TestSubject::ZERO_GUID . '":1}}',
		];
	}

	private function newCorePropertyRegistry(): PagePropertyProviderRegistry {
		$registry = new PagePropertyProviderRegistry();
		$registry->addProvider( new CorePagePropertyProvider() );

		return $registry;
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

	private function newRenderingOf( RevisionRecord $revision, string $category ): RenderedRevision {
		$parserOutput = new ParserOutput();
		$parserOutput->addCategory( $category );

		$rendering = $this->createStub( RenderedRevision::class );
		$rendering->method( 'getRevision' )->willReturn( $revision );
		$rendering->method( 'getRevisionParserOutput' )->willReturn( $parserOutput );

		return $rendering;
	}

	private function newPlainPageRevision( string $pageName ): RevisionRecord {
		$pageId = $this->insertPage( $pageName, 'Just wikitext, no subjects.' )['id'];
		$revision = $this->getServiceContainer()->getRevisionStore()->getRevisionByPageId( $pageId );
		$this->assertInstanceOf( RevisionRecord::class, $revision );

		return $revision;
	}

	private function newProjector(): GraphStoreProjector {
		return $this->newProjectorWith( $this->graphStore );
	}

	private function newProjectorWithPolicy( RevisionPolicy $policy ): GraphStoreProjector {
		return $this->newProjectorWith( $this->graphStore, revisionPolicy: $policy );
	}

	private function newProjectorWith(
		GraphDatabasePlugin $graphStore,
		?PagePropertyProviderRegistry $providerRegistry = null,
		bool $isolatePageProperties = false,
		?RevisionPolicy $revisionPolicy = null
	): GraphStoreProjector {
		$pageProperties = $this->newPagePropertiesBuilder( $providerRegistry ?? new PagePropertyProviderRegistry() );

		return new GraphStoreProjector(
			$graphStore,
			$isolatePageProperties
				? new FailureIsolatingPagePropertiesSource( $pageProperties, $this->logger )
				: $pageProperties,
			$revisionPolicy ?? new NullRevisionPolicy(),
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
