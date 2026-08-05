<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\ProjectionChangeTimeLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPageChangeTimeLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPageChangeTimeLookup
 * @group Database
 */
class MappingPageChangeTimeLookupTest extends NeoWikiIntegrationTestCase {

	public function testAProjectionChangedWhenItsMappingPageWasLastEdited(): void {
		$this->createMapping( 'EDM', '{"version":1,"schemas":{}}' );
		$latestRevision = $this->createMapping( 'EDM', '{"version":1,"prefixes":{},"schemas":{}}' );

		$this->assertSame(
			$latestRevision?->getTimestamp(),
			$this->newLookup()->getLastChangeTime( 'EDM' )
		);
	}

	public function testAProjectionNoMappingPageEverDefinedHasNeverChanged(): void {
		$this->assertNull( $this->newLookup()->getLastChangeTime( 'EDM' ) );
	}

	public function testAProjectionNameNoPageCouldBeCalledHasNeverChanged(): void {
		$this->assertNull( $this->newLookup()->getLastChangeTime( '<not a title>' ) );
	}

	/**
	 * Every page projected under a deleted Mapping still carries the vocabulary it described, so the
	 * stores holding that projection are exactly as far from the wiki as an edit would have left them.
	 */
	public function testAProjectionChangedWhenItsMappingPageWasDeleted(): void {
		$this->createMapping( 'EDM', '{"version":1,"schemas":{}}' );
		$this->deleteMapping( 'EDM' );

		$this->assertMatchesRegularExpression(
			'/^\d{14}$/',
			(string)$this->newLookup()->getLastChangeTime( 'EDM' )
		);
	}

	/**
	 * A restored revision keeps its original timestamp, so reading the page alone puts the projection's
	 * last change before the deletion. A store rebuilt while the page was gone was built without the
	 * projection and is exactly as far from the wiki as one rebuilt before an edit, so the restoration
	 * is what it has to be measured against.
	 */
	public function testAProjectionWhoseMappingPageWasRestoredChangedWhenItWasPutBack(): void {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$this->createMapping( 'EDM', '{"version":1,"schemas":{}}' );
		$this->deleteMapping( 'EDM' );

		ConvertibleTimestamp::setFakeTime( '20260202000000' );
		$this->undeleteMapping( 'EDM' );

		$this->assertSame( '20260202000000', $this->newLookup()->getLastChangeTime( 'EDM' ) );
	}

	/**
	 * Protecting a page inserts a revision carrying the content of the one before it. Read as a change,
	 * every store holding the projection reports stale over an action that changed nothing.
	 */
	public function testProtectingAMappingPageIsNotAChangeToItsProjection(): void {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$this->createMapping( 'EDM', '{"version":1,"schemas":{}}' );

		ConvertibleTimestamp::setFakeTime( '20260202000000' );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( 'EDM', NeoWikiExtension::NS_MAPPING )
		);
		$this->assertStatusGood( $page->doUpdateRestrictions(
			[ 'edit' => 'sysop' ],
			[],
			$cascade,
			'protection is not a definition change',
			$this->getTestSysop()->getUser()
		) );

		$this->assertSame( '20260101000000', $this->newLookup()->getLastChangeTime( 'EDM' ) );
	}

	private function deleteMapping( string $name ): void {
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->mappingTitle( $name ) ),
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'test deletion' ) );
	}

	private function undeleteMapping( string $name ): void {
		$this->assertStatusGood(
			MediaWikiServices::getInstance()->getUndeletePageFactory()
				->newUndeletePage(
					MediaWikiServices::getInstance()->getWikiPageFactory()
						->newFromTitle( $this->mappingTitle( $name ) ),
					$this->getTestSysop()->getAuthority()
				)
				->undeleteUnsafe( 'test undeletion' )
		);
	}

	private function mappingTitle( string $name ): Title {
		$title = MediaWikiServices::getInstance()->getTitleFactory()
			->newFromText( $name, NeoWikiExtension::NS_MAPPING );
		$this->assertNotNull( $title );

		return $title;
	}

	private function newLookup(): ProjectionChangeTimeLookup {
		return new MappingPageChangeTimeLookup(
			MediaWikiServices::getInstance()->getTitleFactory(),
			MediaWikiServices::getInstance()->getRevisionLookup(),
			MediaWikiServices::getInstance()->getConnectionProvider(),
		);
	}

}
