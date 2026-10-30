<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Content\Content;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * Every parse of the page records a category of its own, so the categories a projection holds tell which parse
 * it read them from. Through a hook that both the legacy parser and Parsoid, the default from MediaWiki 1.47,
 * go through.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ProjectPageUpdate
 * @group Database
 */
class ProjectionParseReuseTest extends NeoWikiIntegrationTestCase {

	private const string PAGE = 'Page parsed once per save';

	protected function tearDown(): void {
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	public function testASaveIsProjectedFromTheParseMediaWikiRecordedItsLinksFrom(): void {
		$this->giveEveryParseOfThePageACategoryOfItsOwn();
		$store = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $store );

		$pageId = $this->editPage( self::PAGE, 'Some text' )->getNewRevision()->getPageId();
		DeferredUpdates::doUpdates();

		$this->assertSame(
			$this->recordedCategoriesOf( $pageId ),
			end( $store->savedPages )->getProperties()->get( 'categories' )
		);
	}

	private function giveEveryParseOfThePageACategoryOfItsOwn(): void {
		$parses = 0;

		$this->setTemporaryHook(
			'ContentAlterParserOutput',
			static function ( Content $content, Title $title, ParserOutput $output ) use ( &$parses ): void {
				if ( $title->getPrefixedText() === self::PAGE ) {
					$output->addCategory( 'Parse_' . ++$parses );
				}
			}
		);
	}

	/**
	 * @return string[]
	 */
	private function recordedCategoriesOf( int $pageId ): array {
		$categories = [];

		foreach ( $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId )->getCategories() as $category ) {
			$categories[] = $category->getDBkey();
		}

		return $categories;
	}

}
