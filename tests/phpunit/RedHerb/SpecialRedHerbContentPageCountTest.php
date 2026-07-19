<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\RedHerb\Specials\SpecialRedHerbContentPageCount;

/**
 * @covers \ProfessionalWiki\RedHerb\Specials\SpecialRedHerbContentPageCount
 * @group Database
 */
class SpecialRedHerbContentPageCountTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testCountsOnlyContentNamespacePagesTrackedInTheGraph(): void {
		$this->createPageWithSubjects( 'RedHerb content page one', TestSubject::build( id: 'sRedHerbCP11111' ) );
		$this->createPageWithSubjects( 'RedHerb content page two', TestSubject::build( id: 'sRedHerbCP22222' ) );
		$this->createPageWithSubjects( 'Help:RedHerb help page', TestSubject::build( id: 'sRedHerbHP33333' ) );

		$this->assertStringContainsString(
			'Content namespace pages tracked in the graph: 2',
			$this->executeContentPageCount()
		);
	}

	public function testErrorOutputIncludesUnderlyingCauseWhenGraphBackendMissing(): void {
		$html = $this->runWithoutGraphBackend(
			fn() => $this->executeContentPageCount()
		);

		$this->assertStringContainsString( 'A configured Neo4j backend is required', $html );
	}

	private function executeContentPageCount(): string {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'RedHerbContentPageCount' ) );
		$output = new OutputPage( $context );
		$context->setOutput( $output );

		$page = new SpecialRedHerbContentPageCount();
		$page->setContext( $context );
		$page->execute( null );

		return $output->getHTML();
	}

}
