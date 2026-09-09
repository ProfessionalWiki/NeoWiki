<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
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
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler
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
		$this->assertSame( 'draft', $this->readApprovalState( $pageId ), 'precondition: the page starts as a draft' );

		$this->provider->properties = [ 'approvalState' => 'approved' ];
		$outcome = $this->refreshPage();

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		$this->assertSame( 'approved', $this->readApprovalState( $pageId ) );
	}

	/**
	 * Through NeoWikiExtension's own wiring, not a hand-built handler: the registered policy has to reach
	 * both PageRebuilder and the handler behind it, or a rebuild after approval would project the draft.
	 */
	public function testRefreshProjectsTheRevisionTheRegisteredPolicyPublishes(): void {
		$approved = $this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build() );
		$afterFirstSave = $this->readGraph( 'MATCH (s:Subject) RETURN count(s) AS n', [] )->first()->toRecursiveArray()['n'];
		$this->createPageWithSubjects( self::PAGE_NAME );
		$afterDraft = $this->readGraph( 'MATCH (s:Subject) RETURN count(s) AS n', [] )->first()->toRecursiveArray()['n'];
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );
		$contentHasSubjects = $approved->getSlots()->getContent( 'neo' )->getPageSubjects()->hasSubjects();
		fwrite( STDERR, "\nDEBUG2 afterFirstSave=$afterFirstSave afterDraft=$afterDraft approvedContentHasSubjects=" . var_export( $contentHasSubjects, true ) . "\n" );

		$outcome = $this->refreshPage();

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
		// DEBUG
		$dump = $this->readGraph(
			'MATCH (page:Page {id: $pageId}) OPTIONAL MATCH (page)-[r]->(n) RETURN page.id AS id, page.wiki_id AS wiki, collect(type(r)) AS rels, collect(labels(n)) AS targets',
			[ 'pageId' => $approved->getPageId() ]
		);
		$all = $this->readGraph( 'MATCH (s:Subject) RETURN s.id AS id, labels(s) AS labels', [] );
		fwrite( STDERR, "\nDEBUG approvedRev=" . $approved->getId() . " page=" . $approved->getPageId()
			. " hasSlot=" . var_export( $approved->hasSlot( 'neo' ), true )
			. " dump=" . json_encode( $dump->first()?->toRecursiveArray() )
			. " subjects=" . json_encode( array_map( static fn ( $r ) => $r->toRecursiveArray(), iterator_to_array( $all ) ) ) . "\n" );
		$this->assertTrue(
			$this->pageHoldsSubjectInGraph( $approved->getPageId() ),
			'the approved revision holds a Subject the draft removed'
		);
	}

	public function testRefreshWritesNothingWhenTheRegisteredPolicyPublishesNoRevision(): void {
		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build() );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishingNothing() );

		$this->assertSame( PageRefreshOutcome::SkippedUnpublishableRevision, $this->refreshPage() );
	}

	public function testRefreshOfAMissingPageWritesNothing(): void {
		$outcome = $this->refreshPage();

		$this->assertSame( PageRefreshOutcome::SkippedMissingRevision, $outcome );
	}

	private function refreshPage(): PageRefreshOutcome {
		return NeoWikiExtension::getInstance()
			->newPageRebuilder()
			->rebuild( Title::newFromText( self::PAGE_NAME ) );
	}

	private function pageHoldsSubjectInGraph( int $pageId ): bool {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId})-[:HasSubject]->(subject:Subject) RETURN count(subject) AS subjects',
			[ 'pageId' => $pageId ]
		);

		return ( $result->first()->toRecursiveArray()['subjects'] ?? 0 ) > 0;
	}

	private function readApprovalState( int $pageId ): ?string {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN page.approvalState AS approvalState',
			[ 'pageId' => $pageId ]
		);

		return $result->first()->toRecursiveArray()['approvalState'] ?? null;
	}

}
