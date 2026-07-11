<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSlots;
use MediaWiki\User\UserIdentityValue;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler
 * @group Database
 */
class OnRevisionCreatedHandlerTest extends NeoWikiIntegrationTestCase {

	private SpyGraphDatabasePlugin $graphStore;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
		$this->graphStore = new SpyGraphDatabasePlugin();
	}

	public function testReturnsTrueAndSavesPageWhenSubjectSlotPresent(): void {
		$revision = $this->createPageWithSubjects( 'Page with subject', TestSubject::build() );

		$wrote = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertTrue( $wrote );
		$this->assertCount( 1, $this->graphStore->savedPages );
	}

	public function testReturnsFalseAndDoesNotSaveWhenSubjectSlotMissing(): void {
		$pageId = $this->insertPage( 'Plain page', 'Just wikitext, no subjects.' )['id'];
		$revision = $this->getServiceContainer()->getRevisionStore()->getRevisionByPageId( $pageId );
		$this->assertInstanceOf( RevisionRecord::class, $revision );

		$wrote = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertFalse( $wrote );
		$this->assertSame( [], $this->graphStore->savedPages );
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

	public function testOnPageUndeleteReprojectsRestoredPage(): void {
		$revision = $this->createPageWithSubjects( 'Restored page', TestSubject::build() );

		$this->newHandler()->onPageUndelete( $revision );

		$this->assertCount( 1, $this->graphStore->savedPages );
	}

	public function testUnreachableBackendDoesNotHardFailRevisionHandling(): void {
		$revision = $this->createPageWithSubjects( 'Page with a failing backend', TestSubject::build() );

		// The isolating decorator is what production wires around each backend on the hook path; here it
		// wraps a backend that is down.
		$handler = $this->newHandlerWith(
			new FailureIsolatingGraphDatabasePlugin( new ThrowingGraphDatabasePlugin(), new NullLogger() )
		);

		$wrote = $handler->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertTrue( $wrote, 'a projection write to an unreachable backend must not propagate out of the handler' );
	}

	private function newHandler(): OnRevisionCreatedHandler {
		return $this->newHandlerWith( $this->graphStore );
	}

	private function newHandlerWith( GraphDatabasePlugin $graphStore ): OnRevisionCreatedHandler {
		$services = $this->getServiceContainer();

		return new OnRevisionCreatedHandler(
			$graphStore,
			new PagePropertiesBuilder(
				revisionStore: $services->getRevisionStore(),
				contentHandlerFactory: $services->getContentHandlerFactory(),
				titleFormatter: $services->getTitleFormatter(),
				providerRegistry: new PagePropertyProviderRegistry(),
			)
		);
	}

}
