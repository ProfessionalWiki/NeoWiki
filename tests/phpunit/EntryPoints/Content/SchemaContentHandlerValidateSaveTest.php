<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Content\ValidationParams;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use StatusValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler
 * @group Database
 */
class SchemaContentHandlerValidateSaveTest extends MediaWikiIntegrationTestCase {

	private function validate( string $json, string $name = 'Person' ): StatusValue {
		$handler = new SchemaContentHandler( SchemaContent::CONTENT_MODEL_ID );
		$title = Title::makeTitle( NeoWikiExtension::NS_SCHEMA, $name );
		$params = new ValidationParams( $title->toPageIdentity(), 0 );

		return $handler->validateSave( new SchemaContent( $json ), $params );
	}

	public function testValidSchemaPassesValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "Age": { "type": "number" } } }' );

		$this->assertTrue( $status->isOK() );
	}

	public function testSchemaMissingPropertyDefinitionsFailsValidation(): void {
		$status = $this->validate( '{ "notPropertyDefinitions": {} }' );

		$this->assertFalse( $status->isOK() );
	}

	public function testStructurallyInvalidPropertyDefinitionFailsValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "Age": { "type": "" } } }' );

		$this->assertFalse( $status->isOK() );
	}

	public function testReservedSchemaNameFailsValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": {} }', 'Page' );

		$this->assertFalse( $status->isOK() );
	}

	/**
	 * The REST envelope carries only the first message, so a caller that never sees the rest
	 * is told nothing unless that one names every error, and how many there were.
	 */
	public function testFirstMessageNamesEveryErrorAndCountsThem(): void {
		$status = $this->validate( '{ "propertyDefinitions": {
			"Owner": { "type": "relation", "relation": "Owner" },
			"Maker": { "type": "relation", "relation": " ", "targetSchema": "Company" }
		} }' );

		$text = wfMessage( $status->getMessages()[0] )->text();

		$this->assertStringContainsString( '2 errors', $text );
		$this->assertStringContainsString( '/propertyDefinitions/Owner', $text );
		$this->assertStringContainsString( '/propertyDefinitions/Maker/relation', $text );
	}

	public function testLabelTemplateNamingPropertiesOfTheSchemaPassesValidation(): void {
		$status = $this->validate( '{ "labelTemplate": "{Title}", "propertyDefinitions": { "Title": { "type": "text" } } }' );

		$this->assertTrue( $status->isOK() );
	}

	public function testLabelTemplateNamingAMissingPropertyFailsValidationAndSaysWhich(): void {
		$status = $this->validate( '{ "labelTemplate": "{Name}", "propertyDefinitions": { "Title": { "type": "text" } } }' );

		$this->assertFalse( $status->isOK() );
		$this->assertStringContainsString( '"Name"', wfMessage( $status->getMessages()[0] )->text() );
	}

}
