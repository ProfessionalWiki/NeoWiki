<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphReads;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\GraphReadingPageRenderings;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\GraphReadingPageRenderings
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\GraphReads
 * @group Database
 */
class GraphReadingPageRenderingsTest extends MediaWikiIntegrationTestCase {

	/**
	 * Ahead of the clock, so a purge that touches the page at the current time does not move it.
	 */
	private const string SAVE_TIME = '20991231120000';

	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( self::SAVE_TIME );
	}

	public function testRenderingsOfAPageWhoseRenderingReadTheGraphExpire(): void {
		$pageId = $this->insertPage( 'Page reading the graph' )['id'];

		$this->expireAfterProjection( $pageId, self::newRenderingReadingTheGraph() );

		$this->assertGreaterThan( self::SAVE_TIME, $this->touchedTimeOf( $pageId ) );
	}

	public function testRenderingsOfAPageWhoseRenderingDidNotReadTheGraphStayCached(): void {
		$pageId = $this->insertPage( 'Page not reading the graph' )['id'];

		$this->expireAfterProjection( $pageId, new ParserOutput() );

		$this->assertSame( self::SAVE_TIME, $this->touchedTimeOf( $pageId ) );
	}

	public function testRenderingsExpireWhenWhatMediaWikiRenderedBeforeTheProjectionIsUnknown(): void {
		$pageId = $this->insertPage( 'Page rendered unseen' )['id'];

		$this->expireAfterProjection( $pageId, null );

		$this->assertGreaterThan( self::SAVE_TIME, $this->touchedTimeOf( $pageId ) );
	}

	public function testAPageWhoseRenderingReadTheGraphIsPurgedFromTheCdn(): void {
		$pageId = $this->insertPage( 'Page cached by the CDN' )['id'];
		$purgedPages = [];
		$htmlCacheUpdater = $this->createStub( HTMLCacheUpdater::class );
		$htmlCacheUpdater->method( 'purgeTitleUrls' )->willReturnCallback(
			static function ( Title $title ) use ( &$purgedPages ): void {
				$purgedPages[] = $title->getPrefixedText();
			}
		);

		$this->newRenderings( $htmlCacheUpdater )
			->expireAfterProjection( new PageId( $pageId ), self::newRenderingReadingTheGraph() );

		$this->assertSame( [ 'Page cached by the CDN' ], $purgedPages );
	}

	private static function newRenderingReadingTheGraph(): ParserOutput {
		$rendering = new ParserOutput();
		GraphReads::record( $rendering );

		return $rendering;
	}

	private function expireAfterProjection( int $pageId, ?ParserOutput $rendering ): void {
		$this->newRenderings( $this->getServiceContainer()->getHtmlCacheUpdater() )
			->expireAfterProjection( new PageId( $pageId ), $rendering );
		DeferredUpdates::doUpdates();
	}

	private function newRenderings( HTMLCacheUpdater $htmlCacheUpdater ): GraphReadingPageRenderings {
		return new GraphReadingPageRenderings(
			$this->getServiceContainer()->getTitleFactory(),
			$htmlCacheUpdater
		);
	}

	private function touchedTimeOf( int $pageId ): string {
		return (string)Title::newFromID( $pageId, IDBAccessObject::READ_LATEST )?->getTouched( IDBAccessObject::READ_LATEST );
	}

}
