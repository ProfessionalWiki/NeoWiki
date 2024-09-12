<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki;

use CommentStoreComment;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;
use Title;
use WikitextContent;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\PageContentFetcher
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\PageContentSaver
 * @group Database
 */
class PageContentFetcherIntegrationTest extends NeoWikiIntegrationTestCase {

	private const TITLE = 'PageContentFetcherIntegrationTest';

	public function setUp(): void {
		$savingStatus = NeoWikiExtension::getInstance()->getPageContentSaver()->saveContent(
			Title::newFromText( self::TITLE ),
			[
				'main' => new WikitextContent( 'foo' ),
				//'blocks' => BlocksContent::newEmpty(),
			],
			CommentStoreComment::newUnsavedComment( 'whatever' )
		);

		$this->assertNull( $savingStatus->errorMessage );
	}

	public function testCanGetMainContent(): void {
		$content = NeoWikiExtension::getInstance()->getPageContentFetcher()->getPageContent(
			self::TITLE,
			$this->getTestSysop()->getAuthority()
		);

		$this->assertSame( 'foo', $content->getText() );
	}

	public function testReturnsNullForNonExistingPage(): void {
		$this->assertNull(
				NeoWikiExtension::getInstance()->getPageContentFetcher()->getPageContent(
				self::TITLE . '404',
				$this->getTestSysop()->getAuthority()
			)
		);
	}

	public function testReturnsNullForNonExistingSlot(): void {
		$this->assertNull(
			NeoWikiExtension::getInstance()->getPageContentFetcher()->getPageContent(
				self::TITLE,
				$this->getTestSysop()->getAuthority(),
				slotName: 'neo'
			)
		);
	}

	//public function testCanGetSpecialSlotContent(): void {
	//	$content = NeoWikiExtension::getInstance()->getPageContentFetcher()->getPageContent(
	//		self::TITLE,
	//		$this->getTestSysop()->getAuthority(),
	//		slotName: 'blocks'
	//	);
	//
	//	$this->assertJsonStringEqualsJsonString(
	//		BlocksContent::newEmpty()->getText(),
	//		$content->getText()
	//	);
	//}

	public function testCanUseTitleObject(): void {
		$content = NeoWikiExtension::getInstance()->getPageContentFetcher()->getPageContent(
			Title::newFromText( self::TITLE ),
			$this->getTestSysop()->getAuthority()
		);

		$this->assertSame( 'foo', $content->getText() );
	}

}
