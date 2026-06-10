<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\ValidationParams;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContentHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use StatusValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContentHandler
 * @group Database
 */
class LayoutContentHandlerValidateSaveTest extends MediaWikiIntegrationTestCase {

	private function validate( string $json, string $name = 'CompanyOverview' ): StatusValue {
		$handler = new LayoutContentHandler( LayoutContent::CONTENT_MODEL_ID );
		$title = Title::makeTitle( NeoWikiExtension::NS_LAYOUT, $name );
		$params = new ValidationParams( $title->toPageIdentity(), 0 );

		return $handler->validateSave( new LayoutContent( $json ), $params );
	}

	public function testValidLayoutPassesValidation(): void {
		$status = $this->validate( '{ "schema": "Company", "type": "infobox" }' );

		$this->assertTrue( $status->isOK() );
	}

	public function testLayoutMissingRequiredFieldsFailsValidation(): void {
		$status = $this->validate( '{ "schema": "Company" }' );

		$this->assertFalse( $status->isOK() );
	}

	public function testSavingValidLayoutViaPageUpdaterSucceeds(): void {
		$status = $this->saveLayoutPage( 'ValidSaveLayout', '{ "schema": "Company", "type": "infobox" }' );

		$this->assertTrue( $status->isOK() );
	}

	public function testSavingInvalidLayoutViaPageUpdaterIsRejected(): void {
		$status = $this->saveLayoutPage( 'InvalidSaveLayout', '{ "schema": "Company" }' );

		$this->assertFalse( $status->isOK() );
	}

	private function saveLayoutPage( string $name, string $json ): StatusValue {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::makeTitle( NeoWikiExtension::NS_LAYOUT, $name )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new LayoutContent( $json ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Test' ) );

		return $updater->getStatus() ?? StatusValue::newGood();
	}

}
