<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Content\ValidationParams;
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

}
