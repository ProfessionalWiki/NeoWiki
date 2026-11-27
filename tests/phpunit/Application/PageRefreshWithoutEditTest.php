<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\MutablePagePropertyProvider;

/**
 * The refresh a Page Property Provider triggers when the state it contributes changes outside an edit
 * (#889): an approval extension marking a revision approved, say. A page holding no Subjects is the
 * case this has to cover, since approval is tracked for ordinary content pages.
 *
 * @covers \ProfessionalWiki\NeoWiki\Application\PageRebuilder
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @group Database
 */
class PageRefreshWithoutEditTest extends NeoWikiIntegrationTestCase {

	private const string PAGE_NAME = 'Page refreshed without an edit';

	private MutablePagePropertyProvider $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->provider = new MutablePagePropertyProvider( [ 'approvalState' => 'draft' ] );
		$this->registerPagePropertyProviders( $this->provider );
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The test rebuilds the singleton with a provider registered; reset it so later tests get a
		// clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testRefreshUpdatesThePagePropertiesOfAPageWithoutSubjects(): void {
		$pageId = $this->insertPage( self::PAGE_NAME, 'No subjects here.' )['id'];
		$this->assertSame( 'draft', $this->readPageNodeProperty( $pageId, 'approvalState' ), 'precondition: the page starts as a draft' );

		$this->provider->properties = [ 'approvalState' => 'approved' ];
		$this->refreshPage();

		$this->assertSame( 'approved', $this->readPageNodeProperty( $pageId, 'approvalState' ) );
	}

	/**
	 * Through NeoWikiExtension's own wiring, not a hand-built projector: the registered policy has to reach
	 * the projection a rebuild queues, or a rebuild after approval would project the draft.
	 */
	public function testRefreshProjectsTheRevisionTheRegisteredPolicyPublishes(): void {
		$approved = $this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build() );
		$this->createPageWithSubjects( self::PAGE_NAME );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		$this->refreshPage();

		$this->assertTrue(
			$this->pageHoldsSubjectInGraph( $approved->getPageId() ),
			'the approved revision holds a Subject the draft removed'
		);
	}

	public function testRefreshWithdrawsThePageWhenTheRegisteredPolicyPublishesNoRevision(): void {
		$pageId = $this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build() )->getPageId();
		$this->assertTrue( $this->graphHoldsPage( $pageId ), 'precondition: saving the page projected it' );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishingNothing() );

		$this->refreshPage();

		$this->assertFalse( $this->graphHoldsPage( $pageId ) );
	}

	public function testRefreshReportsAnExistingPageAsFound(): void {
		$this->insertPage( self::PAGE_NAME, 'No subjects here.' );

		$this->assertTrue( $this->refreshPage() );
	}

	public function testRefreshReportsAMissingPageAsNotFound(): void {
		$this->assertFalse( $this->refreshPage() );
	}

	/**
	 * @return bool Whether the page was found
	 */
	private function refreshPage(): bool {
		$found = NeoWikiExtension::getInstance()
			->newPageRebuilder()
			->rebuild( Title::newFromText( self::PAGE_NAME ) );
		DeferredUpdates::doUpdates();

		return $found;
	}

	private function pageHoldsSubjectInGraph( int $pageId ): bool {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId})-[:HasSubject]->(subject:Subject) RETURN count(subject) AS subjects',
			[ 'pageId' => $pageId ]
		);

		return ( $result->first()->toRecursiveArray()['subjects'] ?? 0 ) > 0;
	}

	private function graphHoldsPage( int $pageId ): bool {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN count(page) AS pages',
			[ 'pageId' => $pageId ]
		);

		return ( $result->first()->toRecursiveArray()['pages'] ?? 0 ) > 0;
	}

}
