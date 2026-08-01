<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
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

	public function testRefreshOfAMissingPageWritesNothing(): void {
		$outcome = $this->refreshPage();

		$this->assertSame( PageRefreshOutcome::SkippedMissingRevision, $outcome );
	}

	private function refreshPage(): PageRefreshOutcome {
		return NeoWikiExtension::getInstance()
			->newPageRebuilder()
			->rebuild( Title::newFromText( self::PAGE_NAME ) );
	}

	private function readApprovalState( int $pageId ): ?string {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN page.approvalState AS approvalState',
			[ 'pageId' => $pageId ]
		);

		return $result->first()->toRecursiveArray()['approvalState'] ?? null;
	}

}
