<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\LinksRefreshHandler
 * @group Database
 */
class LinksRefreshGraphProjectionTest extends NeoWikiIntegrationTestCase {

	private const string TEMPLATE = 'Template:Links refresh probe';
	private const string PAGE = 'Page using the links refresh probe';
	private const string PAGE_TEXT = 'Text {{Links refresh probe}}';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	protected function tearDown(): void {
		parent::tearDown();
		// Some tests rebuild the singleton with a temporary NeoWikiRegistration hook; reset it so later tests
		// get a clean instance.
		NeoWikiExtension::resetInstance();
	}

	public function testTemplateEditUpdatesTheCategoriesOfThePagesUsingIt(): void {
		$this->savePage( self::TEMPLATE, '[[Category:Before]]' );
		$pageId = $this->savePage( self::PAGE, self::PAGE_TEXT );

		$this->savePage( self::TEMPLATE, '[[Category:After]]' );
		$this->runRefreshJobs();

		$this->assertSame( [ 'After' ], $this->readPageNodeProperty( $pageId, 'categories' ) );
	}

	public function testTemplateEditUpdatesPagePropertiesTakenFromTheParse(): void {
		$this->registerPagePropertyProviders( self::newDefaultSortProvider() );
		$this->savePage( self::TEMPLATE, '{{DEFAULTSORT:Before}}' );
		$pageId = $this->savePage( self::PAGE, self::PAGE_TEXT );

		$this->savePage( self::TEMPLATE, '{{DEFAULTSORT:After}}' );
		$this->runRefreshJobs();

		$this->assertSame( 'After', $this->readPageNodeProperty( $pageId, 'defaultSort' ) );
	}

	public function testTemplateEditThatOnlyChangesASortKeyWritesNothing(): void {
		$this->savePage( self::TEMPLATE, '[[Category:Sorted|first]]' );
		$this->savePage( self::PAGE, self::PAGE_TEXT );
		$this->savePage( self::TEMPLATE, '[[Category:Sorted|second]]' );
		$store = $this->registerSpyStore();

		$this->runRefreshJobs();

		$this->assertSame( [], self::savedPageIds( $store ) );
	}

	public function testEditThatChangesTheCategoriesProjectsThePageOnce(): void {
		$pageId = $this->savePage( self::PAGE, '[[Category:Before]]' );
		$store = $this->registerSpyStore();

		$this->savePage( self::PAGE, '[[Category:After]]' );

		$this->assertSame( [ $pageId ], self::savedPageIds( $store ) );
	}

	/**
	 * From MediaWiki 1.44 on, a move's links update does not recurse.
	 */
	public function testMovingAPageInACategoryProjectsItOnce(): void {
		$pageId = $this->savePage( 'Categorized move source', '[[Category:Moved]]' );
		$store = $this->registerSpyStore();

		$this->movePage( 'Categorized move source', 'Categorized move target' );

		$this->assertSame( [ $pageId ], self::savedPageIds( $store ) );
	}

	public function testMovingAPageOverARedirectInACategoryProjectsItOnce(): void {
		$pageId = $this->savePage( 'Moved over a redirect', 'No categories.' );
		$this->savePage( 'Redirect moved over', '#REDIRECT [[Moved over a redirect]] [[Category:Redirects]]' );
		$store = $this->registerSpyStore();

		$this->movePage( 'Moved over a redirect', 'Redirect moved over' );

		$this->assertSame( [ $pageId ], self::savedPageIds( $store ) );
	}

	/**
	 * Runs the save's deferred updates too, which record the links a later template edit refreshes.
	 *
	 * @return int The page id
	 */
	private function savePage( string $title, string $wikitext ): int {
		$pageId = $this->editPage( $title, $wikitext )->getNewRevision()->getPageId();
		DeferredUpdates::doUpdates();

		return $pageId;
	}

	/**
	 * In a request of their own, as the job queue runs them: a parse in this one can still read the template
	 * as it was before the edit.
	 */
	private function runRefreshJobs(): void {
		$this->startFreshRequest();
		$this->runJobs();
	}

	private function registerSpyStore(): SpyGraphDatabasePlugin {
		$store = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $store );

		return $store;
	}

	private static function newDefaultSortProvider(): PagePropertyProvider {
		return new class implements PagePropertyProvider {

			public function getProperties( PagePropertyProviderContext $context ): array {
				return [ 'defaultSort' => $context->parserProperties['defaultsort'] ?? '' ];
			}

		};
	}

	private function readPageNodeProperty( int $pageId, string $property ): mixed {
		return $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN page[$property] AS value',
			[ 'pageId' => $pageId, 'property' => $property ]
		)->first()->toRecursiveArray()['value'] ?? null;
	}

}
